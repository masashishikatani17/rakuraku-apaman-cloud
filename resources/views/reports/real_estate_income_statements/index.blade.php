@extends('layouts.app')

@section('title', '不動産所得決算書集計')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
            'revenue' => '収入金額',
            'expense' => '必要経費',
        ];

        $realEstateStatementCategoryLabels = [
            'auto' => '自動判定',
            'none' => '決算書対象外',
            'revenue_rent' => '収入: 賃貸料',
            'revenue_common_service' => '収入: 共益費',
            'revenue_parking' => '収入: 駐車料',
            'revenue_key_money' => '収入: 礼金・権利金',
            'revenue_other' => '収入: その他',
            'expense_tax_dues' => '経費: 租税公課',
            'expense_insurance' => '経費: 損害保険料',
            'expense_repair' => '経費: 修繕費',
            'expense_depreciation' => '経費: 減価償却費',
            'expense_interest' => '経費: 借入金利子',
            'expense_management_fee' => '経費: 管理費',
            'expense_commission' => '経費: 支払手数料',
            'expense_salary' => '経費: 給料賃金',
            'expense_utilities' => '経費: 水道光熱費',
            'expense_other' => '経費: その他',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];

        $paymentTypeLabels = [
            'rent' => '家賃',
            'common_service' => '共益費',
            'parking' => '駐車場',
            'deposit' => '敷金',
            'key_money' => '礼金',
            'other' => 'その他',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">不動産所得決算書集計</h2>
            <p class="page-description">会計仕訳、入金予定、固定資産から、不動産所得の決算書に近い集計を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
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
                    href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    減価償却へ
                </a>
                <a
                    href="{{ route('reports.property-annual-incomes.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別年間収入台帳へ
                </a>
                <a
                    href="{{ route('reports.property-owner-profit-losses.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件・所有者別損益へ
                </a>
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
                <a
                    href="{{ route('reports.consumption-tax.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    消費税集計へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、税務申告書そのものを作るのではなく、決算書作成前の確認用として、
        仕訳ベースの収入・必要経費、入金予定ベースの賃貸収入、固定資産ベースの減価償却を並べて表示します。
        税務上の細かい区分、青色申告決算書の様式、PDF出力は後続で拡張します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.real-estate-income-statements.index') }}">
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
                    <label for="display">表示方法</label>
                    <select id="display" name="display">
                        @foreach ($displayLabels as $value => $label)
                            <option value="{{ $value }}" {{ $display === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId]) : route('reports.real-estate-income-statements.index') }}"
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
                    <label>表示方法</label>
                    <div>{{ $displayLabels[$display] ?? $display }}</div>
                </div>

                <div class="field">
                    <label>PL科目数</label>
                    <div>{{ $summary['accounting_rows_count'] }} 科目</div>
                </div>

                <div class="field">
                    <label>決算書区分対象</label>
                    <div>{{ $summary['statement_category_rows_count'] }} 科目</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">不動産所得の集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>金額</th>
                    <th>確認元</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>収入金額</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['revenue_total'], 2) }}</td>
                    <td>登録済み仕訳の収益科目</td>
                </tr>
                <tr>
                    <td>必要経費</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['expense_total'], 2) }}</td>
                    <td>登録済み仕訳の費用科目</td>
                </tr>
                <tr>
                    <td><strong>不動産所得</strong></td>
                    <td style="text-align: right; {{ (float) $summary['real_estate_income_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        <strong>{{ number_format((float) $summary['real_estate_income_total'], 2) }}</strong>
                    </td>
                    <td>収入金額 - 必要経費</td>
                </tr>
            </tbody>
        </table>
    </div>
 
    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">決算書区分別集計</h3>

        <div class="alert alert-success" style="background: #f8fafc; color: #334155; border-color: #cbd5e1;">
            勘定科目マスタの「不動産所得決算書区分」を優先して集計します。
            区分が「自動判定」の科目は、科目名から賃貸料・駐車料・修繕費・借入金利子などを仮分類します。
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>決算書区分</th>
                    <th>大区分</th>
                    <th>科目数</th>
                    <th>金額</th>
                    <th>内訳科目</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statementCategoryRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->amount, 2) }}
                        </td>
                        <td>
                            @foreach ($row->rows as $accountRow)
                                <div>
                                    {{ $accountRow->account_code }}
                                    {{ $accountRow->account_name }}
                                    <span class="muted">
                                        {{ number_format((float) $accountRow->amount, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">決算書区分別に集計できる科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">参考: 賃貸収入の入金予定・入金実績</h3>

        <div class="form-grid" style="margin-bottom: 16px;">
            <div class="field">
                <label>予定額合計</label>
                <div>{{ number_format((float) $summary['rental_expected_total'], 2) }}</div>
            </div>
            <div class="field">
                <label>入金済合計</label>
                <div>{{ number_format((float) $summary['rental_received_total'], 2) }}</div>
            </div>
            <div class="field">
                <label>未入金合計</label>
                <div style="{{ (float) $summary['rental_remaining_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                    {{ number_format((float) $summary['rental_remaining_total'], 2) }}
                </div>
            </div>
            <div class="field">
                <label>対象物件数</label>
                <div>{{ $summary['property_rows_count'] }} 件</div>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>入金項目CODE</th>
                        <th>入金項目名</th>
                        <th>種別</th>
                        <th>対応科目</th>
                        <th>件数</th>
                        <th>予定額</th>
                        <th>入金済</th>
                        <th>未入金</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($paymentItemRows as $row)
                        <tr>
                            <td>{{ $row->item_code }}</td>
                            <td>{{ $row->payment_item_name }}</td>
                            <td>{{ $paymentTypeLabels[$row->item_type] ?? $row->item_type }}</td>
                            <td>
                                @if ($row->account_code || $row->account_name)
                                    {{ trim(($row->account_code ?? '') . ' ' . ($row->account_name ?? '')) }}
                                @else
                                    <span class="muted">未設定</span>
                                @endif
                            </td>
                            <td>{{ $row->schedules_count }} 件</td>
                            <td style="text-align: right;">{{ number_format((float) $row->expected_total, 2) }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->received_total, 2) }}</td>
                            <td style="text-align: right; {{ (float) $row->remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                                {{ number_format((float) $row->remaining_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">指定期間の入金予定データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">参考: 物件別収入</h3>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>物件CODE</th>
                        <th>物件名</th>
                        <th>契約数</th>
                        <th>入金予定件数</th>
                        <th>予定額</th>
                        <th>入金済</th>
                        <th>未入金</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyIncomeRows as $row)
                        <tr>
                            <td>{{ $row->property_code ?? '—' }}</td>
                            <td>{{ $row->property_name ?? '物件未設定' }}</td>
                            <td>{{ $row->contracts_count }} 件</td>
                            <td>{{ $row->schedules_count }} 件</td>
                            <td style="text-align: right;">{{ number_format((float) $row->expected_total, 2) }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->received_total, 2) }}</td>
                            <td style="text-align: right; {{ (float) $row->remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                                {{ number_format((float) $row->remaining_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">指定期間の物件別収入データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">参考: 減価償却</h3>

        <div class="form-grid" style="margin-bottom: 16px;">
            <div class="field">
                <label>固定資産数</label>
                <div>{{ $summary['depreciable_assets_count'] }} 件</div>
            </div>
            <div class="field">
                <label>当期償却費合計</label>
                <div>{{ number_format((float) $summary['depreciation_total'], 2) }}</div>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>資産CODE</th>
                        <th>資産名</th>
                        <th>物件</th>
                        <th>取得価額</th>
                        <th>耐用年数</th>
                        <th>事業割合</th>
                        <th>当期月数</th>
                        <th>当期償却費</th>
                        <th>期末帳簿価額</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($depreciableAssetRows as $row)
                        <tr>
                            <td>{{ $row->asset->asset_code }}</td>
                            <td>{{ $row->asset->name }}</td>
                            <td>{{ $row->asset->property?->name ?? '—' }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->asset->acquisition_cost, 2) }}</td>
                            <td>{{ $row->asset->useful_life_years }} 年</td>
                            <td>{{ number_format((float) $row->asset->business_use_ratio, 2) }}%</td>
                            <td>{{ $row->depreciation['period_months'] }} か月</td>
                            <td style="text-align: right;">{{ number_format((float) $row->depreciation['period_depreciation_amount'], 2) }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->depreciation['book_value_after_period'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">固定資産データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">収入金額の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>決算書区分</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>金額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($revenueRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->real_estate_statement_category_label ?? ($realEstateStatementCategoryLabels[$row->real_estate_statement_category ?? 'auto'] ?? ($row->real_estate_statement_category ?? '自動判定')) }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'account_title_id' => $row->account_title_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                class="button button-secondary"
                            >
                                元帳
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">収入金額の対象科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">必要経費の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>決算書区分</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>金額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->real_estate_statement_category_label ?? ($realEstateStatementCategoryLabels[$row->real_estate_statement_category ?? 'auto'] ?? ($row->real_estate_statement_category ?? '自動判定')) }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'account_title_id' => $row->account_title_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                class="button button-secondary"
                            >
                                元帳
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">必要経費の対象科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection