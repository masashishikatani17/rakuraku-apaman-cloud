@extends('layouts.app')

@section('title', '経費帳')

@section('content')
    @php
        $entryTypeLabels = [
            'manual' => '手入力',
            'rental_payment' => '賃貸入金',
            'system' => 'システム',
            'closing' => '決算',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">経費帳</h2>
            <p class="page-description">費用科目の仕訳明細を、経費帳形式で確認します。</p>
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
            <a
                href="{{ (isset($selectedBookId) && $selectedBookId) ? route('accounting-menu.index', ['book_id' => $selectedBookId]) : route('accounting-menu.index') }}"
                class="button button-secondary"
            >
                会計管理メニューへ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、勘定科目マスタの区分が「expense」の科目を経費科目として表示します。
        修繕費・管理費・水道光熱費などは、勘定科目登録時に区分を「費用」にしてください。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('expense-ledgers.index') }}">
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
                    <label for="account_title_id">経費科目</label>
                    <select id="account_title_id" name="account_title_id">
                        <option value="">すべての経費科目</option>
                        @foreach ($expenseAccountTitles as $accountTitle)
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
                    <label for="department_id">部門</label>
                    <select id="department_id" name="department_id">
                        <option value="">すべて表示</option>
                        @foreach ($departments as $department)
                            <option
                                value="{{ $department->id }}"
                                {{ (string) $selectedDepartmentId === (string) $department->id ? 'selected' : '' }}
                            >
                                {{ $department->department_code }} / {{ $department->name }}
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
                    href="{{ $selectedBookId ? route('expense-ledgers.index', ['book_id' => $selectedBookId]) : route('expense-ledgers.index') }}"
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
                    <label>経費科目</label>
                    <div>
                        @if ($selectedAccountTitle)
                            {{ $selectedAccountTitle->account_code }} / {{ $selectedAccountTitle->name }}
                        @else
                            すべての経費科目
                        @endif
                        @if ($selectedSubAccountTitle)
                            <div class="muted">
                                補助: {{ $selectedSubAccountTitle->sub_account_code }} / {{ $selectedSubAccountTitle->name }}
                            </div>
                        @endif
                    </div>
                </div>

                <div class="field">
                    <label>部門</label>
                    <div>
                        @if ($selectedDepartment)
                            {{ $selectedDepartment->department_code }} / {{ $selectedDepartment->name }}
                        @else
                            すべて
                        @endif
                    </div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>明細件数</label>
                    <div>{{ $summary['rows_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>科目数</label>
                    <div>{{ $summary['accounts_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>経費発生合計</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>戻入/取消合計</label>
                    <div>{{ number_format((float) $summary['reversal_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差引経費合計</label>
                    <div style="{{ (float) $summary['net_expense_total'] >= 0 ? 'color: #111827;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['net_expense_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">経費科目別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>明細件数</th>
                    <th>経費発生</th>
                    <th>戻入/取消</th>
                    <th>差引経費</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountSummaries as $accountSummary)
                    <tr>
                        <td>{{ $accountSummary->account_code }}</td>
                        <td>{{ $accountSummary->account_name }}</td>
                        <td>{{ $accountSummary->rows_count }} 件</td>
                        <td>{{ number_format((float) $accountSummary->expense_total, 2) }}</td>
                        <td>{{ number_format((float) $accountSummary->reversal_total, 2) }}</td>
                        <td>{{ number_format((float) $accountSummary->net_expense_total, 2) }}</td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', [
                                    'book_id' => $selectedBookId,
                                    'account_title_id' => $accountSummary->account_title_id,
                                    'date_from' => $dateFrom,
                                    'date_to' => $dateTo,
                                ]) }}"
                                class="button button-secondary"
                            >
                                元帳
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">表示できる経費科目別集計がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">明細</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>日付</th>
                    <th>伝票番号</th>
                    <th>種別</th>
                    <th>経費科目</th>
                    <th>補助 / 部門</th>
                    <th>摘要</th>
                    <th>相手科目</th>
                    <th>経費発生</th>
                    <th>戻入/取消</th>
                    <th>差引</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ledgerRows as $row)
                    <tr>
                        <td>{{ $row->entry_date ?? '—' }}</td>
                        <td>{{ $row->voucher_no ?: '—' }}</td>
                        <td>{{ $entryTypeLabels[$row->entry_type] ?? ($row->entry_type ?: '—') }}</td>
                        <td>
                            {{ $row->account_code ?? '—' }}
                            /
                            {{ $row->account_name ?? '—' }}
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

                            @if (!$row->sub_account_code && !$row->sub_account_name && !$row->department_code && !$row->department_name)
                                —
                            @endif
                        </td>
                        <td>
                            {{ $row->description_text }}
                            @if ($row->line_note)
                                <div class="muted">行備考: {{ $row->line_note }}</div>
                            @endif
                        </td>
                        <td>
                            @forelse ($row->counterpart_labels as $counterpartLabel)
                                <div>{{ $counterpartLabel }}</div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ number_format((float) $row->expense_amount, 2) }}</td>
                        <td>{{ number_format((float) $row->reversal_amount, 2) }}</td>
                        <td>{{ number_format((float) $row->net_amount, 2) }}</td>
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
                        <td colspan="11">指定条件に一致する経費明細がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection