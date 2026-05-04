@extends('layouts.app')

@section('title', '白色収支内訳書プレビュー')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
            'revenue' => '収入',
            'expense' => '必要経費',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">白色収支内訳書プレビュー</h2>
            <p class="page-description">白色申告の収支内訳書へ転記する前の収入・必要経費を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.real-estate-closing-details.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    決算書内訳確認へ
                </a>
                <a
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
                <a
                    href="{{ route('reports.blue-return-statement-previews.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    青色申告決算書プレビューへ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は白色申告の収支内訳書へ転記する前の確認画面です。
        不動産所得決算書の補正額がある場合は、補正後の申告用金額を反映します。
        固定様式PDFは後続で対応します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.white-return-statement-previews.index') }}">
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
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
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
                    href="{{ $selectedBookId ? route('reports.white-return-statement-previews.index', ['book_id' => $selectedBookId]) : route('reports.white-return-statement-previews.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">収支内訳書サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>収入金額</label>
                    <div>{{ number_format((float) $summary['income_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>必要経費</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>所得金額</label>
                    <div style="{{ (float) $summary['profit_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['profit_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>補正額合計</label>
                    <div>{{ number_format((float) $summary['adjustment_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>確認必要科目</label>
                    <div style="{{ (int) $summary['review_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['review_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>科目数</label>
                    <div>{{ $summary['account_count'] }} 科目</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">収入金額の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>収支内訳書区分</th>
                    <th>科目数</th>
                    <th>会計集計額</th>
                    <th>補正額</th>
                    <th>申告用金額</th>
                    <th>内訳科目</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incomeRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->adjustment_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->filing_amount, 2) }}</td>
                        <td>
                            @foreach ($row->accounts as $account)
                                <div>
                                    {{ $account->account_code }}
                                    {{ $account->account_name }}
                                    <span class="muted">
                                        {{ number_format((float) $account->filing_amount, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">収入金額の対象がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">必要経費の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>収支内訳書区分</th>
                    <th>科目数</th>
                    <th>会計集計額</th>
                    <th>補正額</th>
                    <th>申告用金額</th>
                    <th>内訳科目</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->adjustment_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->filing_amount, 2) }}</td>
                        <td>
                            @foreach ($row->accounts as $account)
                                <div>
                                    {{ $account->account_code }}
                                    {{ $account->account_name }}
                                    <span class="muted">
                                        {{ number_format((float) $account->filing_amount, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">必要経費の対象がありません。</td>
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
                    <th>区分</th>
                    <th>収支内訳書区分</th>
                    <th>会計集計額</th>
                    <th>補正額</th>
                    <th>申告用金額</th>
                    <th>補正理由</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->statement_category_label }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->adjustment_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->filing_amount, 2) }}</td>
                        <td>{{ $row->adjustment_reason ?: '—' }}</td>
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