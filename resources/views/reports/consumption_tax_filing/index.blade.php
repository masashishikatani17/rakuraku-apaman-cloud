@extends('layouts.app')

@section('title', '消費税申告用集計')

@section('content')
    @php
        $amountModeLabels = [
            'tax_included' => '税込入力として計算',
            'tax_excluded' => '税抜入力として計算',
        ];

        $taxMethodLabels = [
            'general' => '原則課税の概算',
            'simplified' => '簡易課税の概算',
            'exempt' => '免税・申告対象外',
        ];

        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">消費税申告用集計</h2>
            <p class="page-description">消費税区分・税率ごとに、課税売上・課税仕入・対象外候補を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.consumption-tax.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'tax_rate' => $defaultTaxRate, 'amount_mode' => $amountMode]) }}" class="button button-secondary">消費税集計へ</a>
                <a href="{{ route('consumption-tax-settlement-journals.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'tax_rate' => $defaultTaxRate, 'amount_mode' => $amountMode]) }}" class="button button-secondary">消費税精算仕訳へ</a>
                <a href="{{ route('account-titles.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">勘定科目設定へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #fff7ed; color: #9a3412; border-color: #fed7aa;">
        この画面は申告前の確認用です。
        勘定科目の消費税区分が「自動判定」の場合は、科目名から概算判定します。
        申告前に、勘定科目マスタの消費税区分と税率を確認してください。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.consumption-tax-filing.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿<span class="required">必須</span></label>
                    <select id="book_id" name="book_id" required>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">開始日</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="field">
                    <label for="default_tax_rate">既定税率（%）</label>
                    <input id="default_tax_rate" type="number" name="default_tax_rate" value="{{ $defaultTaxRate }}" step="0.1" min="0" max="100">
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
                    <label for="tax_method">計算方式</label>
                    <select id="tax_method" name="tax_method">
                        @foreach ($taxMethodLabels as $value => $label)
                            <option value="{{ $value }}" {{ $taxMethod === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="deemed_purchase_rate">みなし仕入率（%）</label>
                    <input id="deemed_purchase_rate" type="number" name="deemed_purchase_rate" value="{{ $deemedPurchaseRate }}" step="0.1" min="0" max="100">
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
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">申告用サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>課税売上税抜相当額</label>
                    <div>{{ number_format((float) $summary['taxable_sales_base_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>課税売上消費税</label>
                    <div>{{ number_format((float) $summary['taxable_sales_tax_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>課税仕入税抜相当額</label>
                    <div>{{ number_format((float) $summary['taxable_purchase_base_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>課税仕入消費税</label>
                    <div>{{ number_format((float) $summary['taxable_purchase_tax_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>原則課税概算</label>
                    <div style="{{ (float) $summary['general_payable'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['general_payable'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>簡易課税概算</label>
                    <div style="{{ (float) $summary['simplified_payable'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['simplified_payable'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>選択方式の概算税額</label>
                    <div style="{{ (float) $summary['estimated_payable'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_payable'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>自動判定科目</label>
                    <div style="{{ (int) $summary['auto_judged_count'] > 0 ? 'color: #f97316;' : 'color: #166534;' }}">
                        {{ $summary['auto_judged_count'] }} 科目
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">税率別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>税率</th>
                    <th>科目数</th>
                    <th>税抜相当額</th>
                    <th>消費税相当額</th>
                    <th>税込相当額</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($taxRateRows as $row)
                    <tr>
                        <td>{{ $row->tax_group_label }}</td>
                        <td>{{ number_format((float) $row->tax_rate, 2) }}%</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_base_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_included_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">税率別集計対象がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">消費税区分別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>消費税区分</th>
                    <th>科目数</th>
                    <th>会計金額</th>
                    <th>税抜相当額</th>
                    <th>消費税相当額</th>
                    <th>自動判定</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categoryRows as $row)
                    <tr>
                        <td>{{ $row->tax_group_label }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_base_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_total, 2) }}</td>
                        <td style="{{ (int) $row->auto_count > 0 ? 'color: #f97316;' : 'color: #166534;' }}">
                            {{ $row->auto_count }} 科目
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">消費税区分別集計対象がありません。</td>
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
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>マスタ区分</th>
                    <th>判定区分</th>
                    <th>税率</th>
                    <th>会計金額</th>
                    <th>税抜相当額</th>
                    <th>消費税相当額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>
                            {{ $row->consumption_tax_category_label }}
                            @if ($row->judgement_source === 'auto')
                                <div class="muted" style="color: #f97316;">自動判定</div>
                            @endif
                        </td>
                        <td>{{ $row->tax_group_label }}</td>
                        <td>{{ number_format((float) $row->tax_rate, 2) }}%</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->tax_base_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->consumption_tax_amount, 2) }}</td>
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
                        <td colspan="9">科目別明細がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection