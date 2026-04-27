@extends('layouts.app')

@section('title', '仕訳日記帳')

@section('content')
    @php
        $entryTypeLabels = [
            'manual' => '手入力',
            'rental_payment' => '賃貸入金',
            'system' => 'システム',
            'closing' => '決算',
        ];

        $statusLabels = [
            'all' => 'すべて',
            'draft' => '下書き',
            'posted' => '登録済',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳日記帳</h2>
            <p class="page-description">登録済みの仕訳を日付順に確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('journal-entries.create', ['book_id' => $selectedBookId]) }}"
                    class="button"
                >
                    仕訳を新規登録
                </a>
                <a
                    href="{{ $selectedBookId ? route('journal-diaries.index', ['book_id' => $selectedBookId]) : route('journal-diaries.index') }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    総勘定元帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は仕訳日記帳の初版です。手入力仕訳と賃貸入金から自動作成した仕訳をまとめて確認できます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('journal-diaries.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿<span class="required">必須</span></label>
                    <select id="book_id" name="book_id" required>
                        @foreach ($books as $book)
                            <option
                                value="{{ $book->id }}"
                                {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}
                            >
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">開始日</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>

                <div class="field">
                    <label for="status">状態</label>
                    <select id="status" name="status">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('journal-diaries.index', ['book_id' => $selectedBookId]) : route('journal-diaries.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>選択中の帳簿</label>
                    <div class="muted">
                        {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                    </div>
                </div>

                <div class="field">
                    <label>表示期間</label>
                    <div class="muted">
                        {{ $dateFrom ?: '開始未指定' }}
                        〜
                        {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>仕訳件数</label>
                    <div>{{ $summary['entries_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>表示状態</label>
                    <div>{{ $statusLabels[$status] ?? $status }}</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>借方合計</label>
                    <div>{{ number_format((float) $summary['debit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>貸方合計</label>
                    <div>{{ number_format((float) $summary['credit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差額</label>
                    <div style="{{ abs((float) $summary['difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['difference'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>判定</label>
                    <div style="{{ abs((float) $summary['difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ abs((float) $summary['difference']) < 0.005 ? '一致' : '不一致' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>伝票番号</th>
                    <th>種別</th>
                    <th>状態</th>
                    <th>摘要</th>
                    <th>借方</th>
                    <th>貸方</th>
                    <th>金額</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($journalEntries as $journalEntry)
                    @php
                        $debitLines = $journalEntry->lines->where('side', 'debit');
                        $creditLines = $journalEntry->lines->where('side', 'credit');
                        $debitTotal = $debitLines->sum(fn ($line) => (float) $line->amount);
                        $creditTotal = $creditLines->sum(fn ($line) => (float) $line->amount);
                    @endphp

                    <tr>
                        <td>{{ $journalEntry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $journalEntry->voucher_no ?: '—' }}</td>
                        <td>{{ $entryTypeLabels[$journalEntry->entry_type] ?? $journalEntry->entry_type }}</td>
                        <td>{{ $statusLabels[$journalEntry->status] ?? $journalEntry->status }}</td>
                        <td>
                            {{ $journalEntry->description_text }}
                            @if ($journalEntry->note)
                                <div class="muted">備考: {{ $journalEntry->note }}</div>
                            @endif
                        </td>
                        <td>
                            @forelse ($debitLines as $line)
                                <div style="margin-bottom: 8px;">
                                    {{ $line->accountTitle?->account_code ?? '' }}
                                    {{ $line->accountTitle?->name ?? '—' }}
                                    <div>{{ number_format((float) $line->amount, 2) }}</div>

                                    @if ($line->subAccountTitle)
                                        <div class="muted">
                                            補助:
                                            {{ $line->subAccountTitle->sub_account_code }}
                                            {{ $line->subAccountTitle->name }}
                                        </div>
                                    @endif

                                    @if ($line->department)
                                        <div class="muted">
                                            部門:
                                            {{ $line->department->department_code }}
                                            {{ $line->department->name }}
                                        </div>
                                    @endif

                                    @if ($line->line_note)
                                        <div class="muted">行備考: {{ $line->line_note }}</div>
                                    @endif
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            @forelse ($creditLines as $line)
                                <div style="margin-bottom: 8px;">
                                    {{ $line->accountTitle?->account_code ?? '' }}
                                    {{ $line->accountTitle?->name ?? '—' }}
                                    <div>{{ number_format((float) $line->amount, 2) }}</div>

                                    @if ($line->subAccountTitle)
                                        <div class="muted">
                                            補助:
                                            {{ $line->subAccountTitle->sub_account_code }}
                                            {{ $line->subAccountTitle->name }}
                                        </div>
                                    @endif

                                    @if ($line->department)
                                        <div class="muted">
                                            部門:
                                            {{ $line->department->department_code }}
                                            {{ $line->department->name }}
                                        </div>
                                    @endif

                                    @if ($line->line_note)
                                        <div class="muted">行備考: {{ $line->line_note }}</div>
                                    @endif
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>
                            借方: {{ number_format((float) $debitTotal, 2) }}
                            <br>
                            貸方: {{ number_format((float) $creditTotal, 2) }}
                            @if (abs($debitTotal - $creditTotal) >= 0.005)
                                <div style="color: #dc2626;">不一致</div>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('journal-entries.edit', $journalEntry) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">指定条件に一致する仕訳がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection