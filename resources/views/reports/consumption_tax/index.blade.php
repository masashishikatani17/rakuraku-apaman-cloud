@extends('layouts.app')

@section('title', '消費税集計')

@section('content')
    @php
        $amountModeLabels = [
            'tax_included' => '税込入力として計算',
            'tax_excluded' => '税抜入力として計算',
        ];

        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
            'revenue' => '売上・収入',
            'expense' => '仕入・経費',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">消費税集計</h2>
            <p class="page-description">登録済み仕訳の収益・費用科目から、消費税の概算額を確認します。</p>
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
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
                <a
                    href="{{ route('consumption-tax-settlement-journals.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'tax_rate' => $taxRate, 'amount_mode' => $amountMode]) }}"
                    class="button"
                >
                    消費税精算仕訳へ
                </a>
                <a
                    href="{{ route('reports.consumption-tax-filing.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'default_tax_rate' => $taxRate, 'amount_mode' => $amountMode]) }}"
                    class="button"
                >
                    消費税申告用集計へ
                </a>
                <a
                    href="{{ route('consumption-tax-category-reviews.index', ['book_id' => $selectedBookId, 'default_tax_rate' => $taxRate]) }}"
                    class="button button-secondary"
                >
                    消費税区分レビューへ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #fff7ed; color: #9a3412; border-color: #fed7aa;">
        初版では、勘定科目に消費税区分を保存せず、科目名から「課税候補」「対象外候補」を自動判定します。
        住宅家賃、非課税売上、給与、支払利息、保険料などは実際の税務判断と異なる可能性があります。
        申告用の確定値ではなく、確認用の概算集計として使用してください。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.consumption-tax.index') }}">
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
                    <label for="tax_rate">消費税率（%）</label>
                    <input
                        id="tax_rate"
                        type="number"
                        name="tax_rate"
                        value="{{ old('tax_rate', $taxRate) }}"
                        step="0.1"
                        min="0"
                        max="100"
                    >
                </div>

                <div class="field">
                    <label for="amount_mode">入力金額の扱い</label>
                    <select id="amount_mode" name="amount_mode">
                        @foreach ($amountModeLabels as $value => $label)
                            <option value="{{ $value }}" {{ $amountMode === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
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
                    href="{{ $selectedBookId ? route('reports.consumption-tax.index', ['book_id' => $selectedBookId]) : route('reports.consumption-tax.index') }}"
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
                    <div class="muted">{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</div>
                </div>

                <div class="field">
                    <label>税率・金額の扱い</label>
                    <div>{{ number_format((float) $taxRate, 1) }}% / {{ $amountModeLabels[$amountMode] ?? $amountMode }}</div>
                </div>

                <div class="field">
                    <label>表示科目数</label>
                    <div>{{ $summary['rows_count'] }} 科目</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">消費税の概算集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>税抜相当額</th>
                    <th>消費税相当額</th>
                    <th>税込相当額</th>
                    <th>対象外候補</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>課税売上候補</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_sales_base_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_sales_tax_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_sales_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['excluded_sales_total'], 2) }}</td>
                </tr>
                <tr>
                    <td>課税仕入候補</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_purchase_base_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_purchase_tax_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['taxable_purchase_total'], 2) }}</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['excluded_purchase_total'], 2) }}</td>
                </tr>
                <tr>
                    <td><strong>概算納付税額</strong></td>
                    <td colspan="2" style="text-align: right; {{ (float) $summary['estimated_consumption_tax_payable'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        <strong>{{ number_format((float) $summary['estimated_consumption_tax_payable'], 2) }}</strong>
                    </td>
                    <td colspan="2">
                        課税売上候補の消費税 - 課税仕入候補の消費税
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">売上・収入側</h3>
        @include('reports.consumption_tax.partials.account_rows', [
            'rows' => $salesRows,
            'categoryLabels' => $categoryLabels,
            'sideLabels' => $sideLabels,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'emptyMessage' => '表示できる売上・収入科目がありません。',
        ])
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">仕入・経費側</h3>
        @include('reports.consumption_tax.partials.account_rows', [
            'rows' => $purchaseRows,
            'categoryLabels' => $categoryLabels,
            'sideLabels' => $sideLabels,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'emptyMessage' => '表示できる仕入・経費科目がありません。',
        ])
    </div>
@endsection