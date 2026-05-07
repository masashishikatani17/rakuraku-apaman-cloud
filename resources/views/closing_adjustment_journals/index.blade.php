@extends('layouts.app')

@section('title', '決算整理仕訳')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">決算整理仕訳</h2>
            <p class="page-description">期末に必要な調整仕訳を、通常仕訳とは区別して登録・確認します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('tax-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                決算・申告メニューへ戻る
            </a>
            <a
                href="{{ route('accounting-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                会計管理メニューへ戻る
            </a>
            @if ($selectedBookId)
                <a
                    href="{{ route('closing-adjustment-journals.create', ['book_id' => $selectedBookId]) }}"
                    class="button"
                >
                    決算整理仕訳を登録
                </a>
                <a
                    href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    損益計算書へ
                </a>
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
                <a
                    href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    仕訳一覧へ
                </a>
                <a
                    href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    減価償却へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、借方1行・貸方1行の決算整理仕訳を登録します。
        登録した仕訳は <strong>entry_type = closing</strong> として保存され、損益計算書・貸借対照表・残高試算表・総勘定元帳へ通常の登録済仕訳と同じように反映されます。
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('closing-adjustment-journals.index') }}">
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
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('closing-adjustment-journals.index', ['book_id' => $selectedBookId]) : route('closing-adjustment-journals.index') }}"
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
                        {{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>決算整理仕訳件数</label>
                    <div>{{ $summary['entries_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>合計金額</label>
                    <div>{{ number_format((float) $summary['total_amount'], 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>日付</th>
                    <th>伝票番号</th>
                    <th>摘要文</th>
                    <th>借方</th>
                    <th>貸方</th>
                    <th>金額</th>
                    <th>備考</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($closingAdjustmentJournals as $journalEntry)
                    @php
                        $debitLine = $journalEntry->debitLines->first();
                        $creditLine = $journalEntry->creditLines->first();
                    @endphp
                    <tr>
                        <td>{{ $journalEntry->id }}</td>
                        <td>{{ $journalEntry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $journalEntry->voucher_no ?: '—' }}</td>
                        <td>{{ $journalEntry->description_text }}</td>
                        <td>
                            @if ($debitLine)
                                {{ $debitLine->accountTitle?->account_code ?? '' }}
                                {{ $debitLine->accountTitle?->name ?? '—' }}
                                @if ($debitLine->subAccountTitle)
                                    <div class="muted">
                                        補助: {{ $debitLine->subAccountTitle->sub_account_code }} {{ $debitLine->subAccountTitle->name }}
                                    </div>
                                @endif
                                @if ($debitLine->department)
                                    <div class="muted">
                                        部門: {{ $debitLine->department->department_code }} {{ $debitLine->department->name }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($creditLine)
                                {{ $creditLine->accountTitle?->account_code ?? '' }}
                                {{ $creditLine->accountTitle?->name ?? '—' }}
                                @if ($creditLine->subAccountTitle)
                                    <div class="muted">
                                        補助: {{ $creditLine->subAccountTitle->sub_account_code }} {{ $creditLine->subAccountTitle->name }}
                                    </div>
                                @endif
                                @if ($creditLine->department)
                                    <div class="muted">
                                        部門: {{ $creditLine->department->department_code }} {{ $creditLine->department->name }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ number_format((float) $journalEntry->total_amount, 2) }}</td>
                        <td>{{ $journalEntry->note ?: '—' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('closing-adjustment-journals.edit', $journalEntry) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('closing-adjustment-journals.destroy', $journalEntry) }}"
                                    onsubmit="return confirm('この決算整理仕訳を削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="button"
                                        style="background: #dc2626;"
                                    >
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">指定条件に一致する決算整理仕訳はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection