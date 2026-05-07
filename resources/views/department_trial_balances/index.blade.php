@extends('layouts.app')

@section('title', '部門別試算表')

@section('content')
    @php
        $categoryLabels = [
            'revenue' => '収益',
            'expense' => '費用',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">部門別試算表</h2>
            <p class="page-description">部門ごとの収益・費用・差引損益を確認します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('accounting-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                会計管理メニューへ戻る
            </a>
            <a
                href="{{ route('output-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                帳票・出力メニューへ戻る
            </a>
            @if ($selectedBookId)
                <a
                    href="{{ route('departments.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    部門マスタへ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('reports.monthly-trends.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    月次推移表へ
                </a>
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
                <a
                    href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    損益計算書へ
                </a>
                <a
                    href="{{ route('expense-ledgers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    経費帳へ
                </a>
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、収益科目と費用科目だけを対象にした部門別PL試算表として表示します。
        部門が未設定の仕訳は「部門未設定」として集計します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('department-trial-balances.index') }}">
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
                    href="{{ $selectedBookId ? route('department-trial-balances.index', ['book_id' => $selectedBookId]) : route('department-trial-balances.index') }}"
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
                    <label>対象部門</label>
                    <div>
                        @if ($selectedDepartment)
                            {{ $selectedDepartment->department_code }} / {{ $selectedDepartment->name }}
                        @else
                            すべて
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
                    <label>明細行数</label>
                    <div>{{ $summary['rows_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>部門数</label>
                    <div>{{ $summary['departments_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>対象科目数</label>
                    <div>{{ $summary['accounts_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>収益合計</label>
                    <div>{{ number_format((float) $summary['revenue_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>費用合計</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差引損益</label>
                    <div style="{{ (float) $summary['profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['profit_loss_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">部門別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>部門コード</th>
                    <th>部門名</th>
                    <th>科目数</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>収益合計</th>
                    <th>費用合計</th>
                    <th>差引損益</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departmentSummaries as $departmentSummary)
                    <tr>
                        <td>{{ $departmentSummary->department_code ?: '—' }}</td>
                        <td>{{ $departmentSummary->department_name }}</td>
                        <td>{{ $departmentSummary->accounts_count }} 件</td>
                        <td>{{ number_format((float) $departmentSummary->debit_total, 2) }}</td>
                        <td>{{ number_format((float) $departmentSummary->credit_total, 2) }}</td>
                        <td>{{ number_format((float) $departmentSummary->revenue_total, 2) }}</td>
                        <td>{{ number_format((float) $departmentSummary->expense_total, 2) }}</td>
                        <td style="{{ (float) $departmentSummary->profit_loss_total >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $departmentSummary->profit_loss_total, 2) }}
                        </td>
                        <td>
                            @if ($departmentSummary->department_id === null)
                                —
                            @else
                                {{ $departmentSummary->department_is_active ? '有効' : '停止' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">表示できる部門別集計がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">科目別明細</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>部門</th>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>残高</th>
                    <th>収益</th>
                    <th>費用</th>
                    <th>差引損益</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departmentTrialBalanceRows as $row)
                    <tr>
                        <td>
                            @if ($row->department_id === null)
                                部門未設定
                            @else
                                {{ $row->department_code }} / {{ $row->department_name }}
                            @endif
                        </td>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td>{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td>{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td>
                            {{ number_format((float) $row->ending_balance, 2) }}
                            @if ($row->ending_balance_side)
                                <div class="muted">
                                    {{ $sideLabels[$row->ending_balance_side] ?? $row->ending_balance_side }}
                                </div>
                            @endif
                        </td>
                        <td>{{ number_format((float) $row->revenue_amount, 2) }}</td>
                        <td>{{ number_format((float) $row->expense_amount, 2) }}</td>
                        <td style="{{ (float) $row->profit_loss_amount >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->profit_loss_amount, 2) }}
                        </td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', [
                                    'book_id' => $selectedBookId,
                                    'account_title_id' => $row->account_title_id,
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
                        <td colspan="12">指定条件に一致する部門別試算表データがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection