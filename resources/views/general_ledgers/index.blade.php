@extends('layouts.app')

@section('title', '総勘定元帳')

@section('content')
    @php
        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">総勘定元帳</h2>
            <p class="page-description">試算表の数字の根拠になった仕訳明細を、勘定科目ごとに時系列で確認する初版です。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>

        <div class="actions">
            <a href="{{ route('books.create') }}" class="button">帳簿を登録する</a>
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('general-ledgers.index') }}">
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
                        <label for="account_title_id">勘定科目<span class="required">必須</span></label>
                        <select id="account_title_id" name="account_title_id" {{ $accountTitles->isEmpty() ? 'disabled' : '' }}>
                            @if ($accountTitles->isEmpty())
                                <option value="">勘定科目がありません</option>
                            @else
                                @foreach ($accountTitles as $accountTitle)
                                    <option
                                        value="{{ $accountTitle->id }}"
                                        {{ (string) $selectedAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                                    >
                                        {{ $accountTitle->account_code . ' / ' . $accountTitle->name }}
                                    </option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div class="field">
                        <label for="date_from">集計開始日</label>
                        <input
                            id="date_from"
                            type="date"
                            name="date_from"
                            value="{{ $dateFrom }}"
                        >
                    </div>

                    <div class="field">
                        <label for="date_to">集計終了日</label>
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
                        href="{{ $selectedBookId ? route('general-ledgers.index', ['book_id' => $selectedBookId]) : route('general-ledgers.index') }}"
                        class="button button-secondary"
                    >
                        条件を初期化
                    </a>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">
                対象の帳簿が見つかりません。
            </div>
        @elseif ($accountTitles->isEmpty())
            <div class="alert alert-error">
                この帳簿には勘定科目がまだ登録されていません。先に勘定科目を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('account-titles.create', ['book_id' => $selectedBookId]) }}" class="button">勘定科目を登録する</a>
            </div>
        @else
            <div class="card" style="margin-bottom: 16px;">
                <div class="form-grid">
                    <div class="field">
                        <label>選択中の帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    <div class="field">
                        <label>勘定科目</label>
                        <div class="muted">
                            {{ $selectedAccountTitle?->account_code ?? '—' }}
                            /
                            {{ $selectedAccountTitle?->name ?? '—' }}
                        </div>
                    </div>

                    <div class="field">
                        <label>期間前残高</label>
                        <div>
                            {{ number_format((float) $summary['opening_balance'], 2) }}
                            @if ($summary['opening_balance_side'])
                                <span class="muted">({{ $sideLabels[$summary['opening_balance_side']] ?? $summary['opening_balance_side'] }})</span>
                            @endif
                        </div>
                    </div>

                    <div class="field">
                        <label>件数</label>
                        <div>{{ $summary['entries_count'] }} 件</div>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 16px;">
                    <div class="field">
                        <label>期間借方合計</label>
                        <div>{{ number_format((float) $summary['period_debit_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>期間貸方合計</label>
                        <div>{{ number_format((float) $summary['period_credit_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>期末残高</label>
                        <div>
                            {{ number_format((float) $summary['ending_balance'], 2) }}
                            @if ($summary['ending_balance_side'])
                                <span class="muted">({{ $sideLabels[$summary['ending_balance_side']] ?? $summary['ending_balance_side'] }})</span>
                            @endif
                        </div>
                    </div>

                    <div class="field">
                        <label>集計期間</label>
                        <div class="muted">
                            {{ $dateFrom ?: '開始未指定' }}
                            〜
                            {{ $dateTo ?: '終了未指定' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>日付</th>
                            <th>伝票番号</th>
                            <th>摘要文</th>
                            <th>相手科目</th>
                            <th>補助科目</th>
                            <th>部門</th>
                            <th>借方</th>
                            <th>貸方</th>
                            <th>残高</th>
                            <th>行備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $dateFrom ?: ($selectedBook?->period_start_date?->format('Y-m-d') ?? '—') }}</td>
                            <td>—</td>
                            <td>期間前残高</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td>—</td>
                            <td>
                                {{ number_format((float) $summary['opening_balance'], 2) }}
                                @if ($summary['opening_balance_side'])
                                    <div class="muted">{{ $sideLabels[$summary['opening_balance_side']] ?? $summary['opening_balance_side'] }}</div>
                                @endif
                            </td>
                            <td>—</td>
                        </tr>

                        @forelse ($ledgerRows as $row)
                            <tr>
                                <td>{{ $row->entry_date ?? '—' }}</td>
                                <td>{{ $row->voucher_no ?: '—' }}</td>
                                <td>{{ $row->description_text }}</td>
                                <td>
                                    @if ($row->counterpart_account_code || $row->counterpart_account_name)
                                        {{ trim(($row->counterpart_account_code ?: '') . ' ' . ($row->counterpart_account_name ?: '')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($row->sub_account_code || $row->sub_account_name)
                                        {{ trim(($row->sub_account_code ?: '') . ' ' . ($row->sub_account_name ?: '')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($row->department_code || $row->department_name)
                                        {{ trim(($row->department_code ?: '') . ' ' . ($row->department_name ?: '')) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ number_format((float) $row->debit_amount, 2) }}</td>
                                <td>{{ number_format((float) $row->credit_amount, 2) }}</td>
                                <td>
                                    {{ number_format((float) $row->running_balance, 2) }}
                                    @if ($row->running_balance_side)
                                        <div class="muted">{{ $sideLabels[$row->running_balance_side] ?? $row->running_balance_side }}</div>
                                    @endif
                                </td>
                                <td>{{ $row->line_note ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10">指定条件に一致する仕訳がありません。仕訳を登録するか、集計期間を見直してください。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection