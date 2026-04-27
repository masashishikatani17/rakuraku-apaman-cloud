@extends('layouts.app')

@section('title', $pageTitle)

@section('content')
    @php
        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];

        $entryTypeLabels = [
            'manual' => '手入力',
            'rental_payment' => '賃貸入金',
            'system' => 'システム',
            'closing' => '決算',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">{{ $pageTitle }}</h2>
            <p class="page-description">
                {{ $ledgerType === 'cash' ? '現金科目' : '預金科目' }}の入出金と残高を確認します。
            </p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
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
                    href="{{ route('general-ledgers.index', [
                        'book_id' => $selectedBookId,
                        'account_title_id' => $selectedAccountTitleId,
                        'date_from' => $dateFrom,
                        'date_to' => $dateTo,
                    ]) }}"
                    class="button button-secondary"
                >
                    総勘定元帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、選択した勘定科目の仕訳明細を出納帳形式で表示します。
        預金口座を補助科目で分けている場合は、補助科目でも絞り込めます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route($routeName) }}">
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
                    <select id="account_title_id" name="account_title_id" required>
                        @foreach ($accountTitles as $accountTitle)
                            <option
                                value="{{ $accountTitle->id }}"
                                {{ (string) $selectedAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                            >
                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="sub_account_title_id">補助科目</label>
                    <select id="sub_account_title_id" name="sub_account_title_id">
                        <option value="">すべて表示</option>
                        @foreach ($subAccountTitles as $subAccountTitle)
                            <option
                                value="{{ $subAccountTitle->id }}"
                                {{ (string) $selectedSubAccountTitleId === (string) $subAccountTitle->id ? 'selected' : '' }}
                            >
                                {{ $subAccountTitle->sub_account_code }} / {{ $subAccountTitle->name }}
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
                    href="{{ $selectedBookId ? route($routeName, ['book_id' => $selectedBookId]) : route($routeName) }}"
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
                    <label>対象科目</label>
                    <div>
                        @if ($selectedAccountTitle)
                            {{ $selectedAccountTitle->account_code }} / {{ $selectedAccountTitle->name }}
                        @else
                            —
                        @endif
                        @if ($selectedSubAccountTitle)
                            <div class="muted">
                                補助: {{ $selectedSubAccountTitle->sub_account_code }} / {{ $selectedSubAccountTitle->name }}
                            </div>
                        @endif
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
                    <label>明細件数</label>
                    <div>{{ $summary['entries_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
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
                    <label>期間{{ $debitLabel }}合計</label>
                    <div>{{ number_format((float) $summary['period_debit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>期間{{ $creditLabel }}合計</label>
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
                    <th>摘要</th>
                    <th>相手科目</th>
                    <th>{{ $debitLabel }}</th>
                    <th>{{ $creditLabel }}</th>
                    <th>残高</th>
                    <th>補助 / 部門 / 備考</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $dateFrom ?: ($selectedBook?->period_start_date?->format('Y-m-d') ?? '—') }}</td>
                    <td>—</td>
                    <td>—</td>
                    <td>期間前残高</td>
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
                    <td>—</td>
                </tr>

                @forelse ($ledgerRows as $row)
                    <tr>
                        <td>{{ $row->entry_date ?? '—' }}</td>
                        <td>{{ $row->voucher_no ?: '—' }}</td>
                        <td>{{ $entryTypeLabels[$row->entry_type] ?? ($row->entry_type ?: '—') }}</td>
                        <td>{{ $row->description_text }}</td>
                        <td>
                            @forelse ($row->counterpart_labels as $counterpartLabel)
                                <div>{{ $counterpartLabel }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ number_format((float) $row->debit_amount, 2) }}</td>
                        <td>{{ number_format((float) $row->credit_amount, 2) }}</td>
                        <td>
                            {{ number_format((float) $row->running_balance, 2) }}
                            @if ($row->running_balance_side)
                                <div class="muted">{{ $sideLabels[$row->running_balance_side] ?? $row->running_balance_side }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($row->sub_account_code || $row->sub_account_name)
                                <div>
                                    補助:
                                    {{ trim(($row->sub_account_code ?: '') . ' ' . ($row->sub_account_name ?: '')) }}
                                </div>
                            @endif

                            @if ($row->department_code || $row->department_name)
                                <div>
                                    部門:
                                    {{ trim(($row->department_code ?: '') . ' ' . ($row->department_name ?: '')) }}
                                </div>
                            @endif

                            @if ($row->line_note)
                                <div class="muted">行備考: {{ $row->line_note }}</div>
                            @endif

                            @if (
                                !$row->sub_account_code
                                && !$row->sub_account_name
                                && !$row->department_code
                                && !$row->department_name
                                && !$row->line_note
                            )
                                —
                            @endif
                        </td>
                        <td>
                            <a
                                href="{{ route('journal-entries.edit', $row->journal_entry_id) }}"
                                class="button button-secondary"
                            >
                                仕訳を見る
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">指定条件に一致する明細がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection