@extends('layouts.app')

@section('title', '貸借対照表')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '純資産',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">貸借対照表</h2>
            <p class="page-description">登録済み仕訳から、資産・負債・純資産を集計します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('opening-balances.index', ['book_id' => $selectedBookId, 'opening_date' => $dateFrom]) }}"
                    class="button button-secondary"
                >
                    開始残高へ
                </a>
                <a
                    href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    損益計算書へ
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
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
                <a
                    href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    減価償却へ
                </a>
                <a
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
                <a
                    href="{{ route('borrowing-loans.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    借入金台帳へ
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
        初版では、貸借対照表科目、つまり資産・負債・純資産を対象にします。
        開始残高入力で作成した開始残高仕訳も、通常の登録済み仕訳として基準日までの集計に含まれます。
        当期損益は指定期間の収益・費用から計算して純資産側に加算します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.balance-sheets.index') }}">
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
                    <label for="date_from">当期損益の開始日</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">基準日</label>
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
                    href="{{ $selectedBookId ? route('reports.balance-sheets.index', ['book_id' => $selectedBookId]) : route('reports.balance-sheets.index') }}"
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
                    <label>会計期間</label>
                    <div class="muted">
                        {{ $selectedBook->period_start_date?->format('Y-m-d') ?? '—' }}
                        〜
                        {{ $selectedBook->period_end_date?->format('Y-m-d') ?? '—' }}
                    </div>
                </div>

                <div class="field">
                    <label>基準日</label>
                    <div>{{ $dateTo ?: '未指定' }}</div>
                </div>

                <div class="field">
                    <label>表示科目数</label>
                    <div>{{ $summary['rows_count'] }} 科目</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>資産合計</label>
                    <div>{{ number_format((float) $summary['asset_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>負債合計</label>
                    <div>{{ number_format((float) $summary['liability_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>純資産合計</label>
                    <div>{{ number_format((float) $summary['net_assets_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>貸借差額</label>
                    <div style="{{ abs((float) $summary['balance_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['balance_difference'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">貸借対照表</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>金額</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>資産合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['asset_total'], 2) }}</td>
                </tr>
                <tr>
                    <td>負債合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['liability_total'], 2) }}</td>
                </tr>
                <tr>
                    <td>純資産科目合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['equity_total'], 2) }}</td>
                </tr>
                <tr>
                    <td>
                        当期損益
                        <div class="muted">
                            {{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}
                        </div>
                    </td>
                    <td style="text-align: right; {{ (float) $summary['current_profit_loss'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['current_profit_loss'], 2) }}
                    </td>
                </tr>
                <tr>
                    <td>負債・純資産合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['liability_equity_total'], 2) }}</td>
                </tr>
                <tr>
                    <td><strong>貸借差額</strong></td>
                    <td style="text-align: right; {{ abs((float) $summary['balance_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        <strong>{{ number_format((float) $summary['balance_difference'], 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">資産</h3>
        @include('reports.balance_sheets.partials.account_rows', [
            'rows' => $assetRows,
            'emptyMessage' => '表示できる資産科目がありません。',
            'sideLabels' => $sideLabels,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => null,
            'dateTo' => $dateTo,
        ])
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">負債</h3>
        @include('reports.balance_sheets.partials.account_rows', [
            'rows' => $liabilityRows,
            'emptyMessage' => '表示できる負債科目がありません。',
            'sideLabels' => $sideLabels,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => null,
            'dateTo' => $dateTo,
        ])
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">純資産</h3>
        @include('reports.balance_sheets.partials.account_rows', [
            'rows' => $equityRows,
            'emptyMessage' => '表示できる純資産科目がありません。',
            'sideLabels' => $sideLabels,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => null,
            'dateTo' => $dateTo,
        ])

        <table class="data-table" style="margin-top: 16px;">
            <tbody>
                <tr>
                    <td>当期損益</td>
                    <td style="text-align: right; {{ (float) $summary['current_profit_loss'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['current_profit_loss'], 2) }}
                    </td>
                    <td>
                        <a
                            href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                            class="button button-secondary"
                        >
                            損益計算書
                        </a>
                    </td>
                </tr>
                <tr>
                    <td><strong>純資産合計</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format((float) $summary['net_assets_total'], 2) }}</strong></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection