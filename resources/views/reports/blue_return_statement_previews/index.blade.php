@extends('layouts.app')

@section('title', '青色申告決算書プレビュー')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $bsCategoryLabels = [
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '元入金・事業主勘定',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">青色申告決算書プレビュー</h2>
            <p class="page-description">青色申告決算書のPL/BSに載せる前段データを確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">不動産所得集計へ</a>
                <a href="{{ route('reports.real-estate-closing-details.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button">決算書内訳確認へ</a>
                <a href="{{ route('reports.white-return-statement-previews.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">白色収支内訳書プレビューへ</a>
                <a href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">損益計算書へ</a>
                <a href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_to' => $dateTo]) }}" class="button button-secondary">貸借対照表へ</a>
                <a href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">減価償却へ</a>
                <a href="{{ route('closing.next-year-rollovers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button">年度繰越プレビューへ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は、税務署提出用PDFではなく、青色申告決算書へ転記する前の確認画面です。
        固定様式PDFやxy座標出力は、集計内容が固まってから後続で対応します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.blue-return-statement-previews.index') }}">
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
                    href="{{ $selectedBookId ? route('reports.blue-return-statement-previews.index', ['book_id' => $selectedBookId]) : route('reports.blue-return-statement-previews.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">決算書サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>収入金額</label>
                    <div>{{ number_format((float) $summary['revenue_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>必要経費</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>不動産所得</label>
                    <div style="{{ (float) $summary['income_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['income_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>資産合計</label>
                    <div>{{ number_format((float) $summary['asset_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>負債合計</label>
                    <div>{{ number_format((float) $summary['liability_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>元入金等</label>
                    <div>{{ number_format((float) $summary['equity_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>負債・元入金・所得</label>
                    <div>{{ number_format((float) $summary['liability_equity_income_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>BS差額</label>
                    <div style="{{ abs((float) $summary['balance_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['balance_difference'], 2) }}
                    </div>
                </div>
            </div>

            <div class="alert alert-success" style="margin-top: 16px; background: #f8fafc; color: #334155; border-color: #cbd5e1;">
                BS差額は「資産合計 - (負債合計 + 元入金等 + 当期所得)」です。
                期中仕訳や元入金・事業主貸借の設計によって差額が出る場合があるため、まず確認指標として表示しています。
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">青色PL相当: 不動産所得決算書区分別</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>決算書区分</th>
                    <th>区分</th>
                    <th>科目数</th>
                    <th>金額</th>
                    <th>内訳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profitLossCategoryRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $row->category === 'revenue' ? '収入' : '必要経費' }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                        <td>
                            @foreach ($row->accounts as $account)
                                <div>
                                    {{ $account->account_code }} {{ $account->account_name }}
                                    <span class="muted">{{ number_format((float) $account->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">青色PL相当の集計対象がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">青色BS相当: 資産・負債・元入金</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>残高</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balanceSheetRows as $row)
                    <tr>
                        <td>{{ $bsCategoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->amount, 2) }}
                        </td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'account_title_id' => $row->account_title_id, 'date_to' => $dateTo]) }}"
                                class="button button-secondary"
                            >
                                元帳
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">青色BS相当の集計対象がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">PL科目別明細</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>決算書区分</th>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>補正額</th>
                    <th>金額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($profitLossAccountRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $row->category === 'revenue' ? '収入' : '必要経費' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) ($row->adjustment_amount ?? 0), 2) }}</td>
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
                        <td colspan="9">PL科目別明細がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection