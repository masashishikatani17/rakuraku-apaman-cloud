@extends('layouts.app')

@section('title', '年度繰越プレビュー')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
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
            <h2 class="page-title">年度繰越プレビュー</h2>
            <p class="page-description">当期末残高と当期所得から、翌期開始残高の候補を確認します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('data-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                データメニューへ戻る
            </a>
            <a
                href="{{ route('utility-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                ユーティリティメニューへ戻る
            </a>
            @if ($selectedBookId)
                <a href="{{ route('reports.blue-return-statement-previews.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">青色申告決算書プレビューへ</a>
                <a href="{{ route('reports.real-estate-closing-details.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">決算書内訳確認へ</a>
                <a href="{{ route('opening-balances.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">開始残高へ</a>
                <a href="{{ route('closing.next-year-rollover-creations.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'balancing_account_title_id' => $selectedBalancingAccountTitleId]) }}" class="button">翌期帳簿作成へ</a>
                <a href="{{ route('closing.next-year-rental-carryovers.index', ['source_book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸データ引継ぎへ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、翌期帳簿や開始残高仕訳はまだ作成しません。
        まず、資産・負債・元入金の期末残高と当期所得を確認し、翌期へ繰り越すべき開始残高候補を表示します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('closing.next-year-rollovers.index') }}">
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
                    <label for="date_from">当期開始日</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">当期終了日</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="field">
                    <label for="balancing_account_title_id">当期所得の繰入先科目</label>
                    <select id="balancing_account_title_id" name="balancing_account_title_id">
                        <option value="">自動選択</option>
                        @foreach ($accountTitles as $accountTitle)
                            <option
                                value="{{ $accountTitle->id }}"
                                {{ (string) $selectedBalancingAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                            >
                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="muted">通常は「元入金」または事業主勘定を選びます。</div>
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
                <a href="{{ $selectedBookId ? route('closing.next-year-rollovers.index', ['book_id' => $selectedBookId]) : route('closing.next-year-rollovers.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">翌期帳簿候補</h3>

            <div class="form-grid">
                <div class="field">
                    <label>現在の帳簿</label>
                    <div>{{ $selectedBook->book_code }} / {{ $selectedBook->name }}</div>
                </div>

                <div class="field">
                    <label>現在の期間</label>
                    <div>{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</div>
                </div>

                <div class="field">
                    <label>翌期帳簿CODE候補</label>
                    <div>{{ $nextPeriod['book_code'] }}</div>
                </div>

                <div class="field">
                    <label>翌期帳簿名候補</label>
                    <div>{{ $nextPeriod['name'] }}</div>
                </div>

                <div class="field">
                    <label>翌期期間候補</label>
                    <div>{{ $nextPeriod['period_start_date'] }} 〜 {{ $nextPeriod['period_end_date'] }}</div>
                </div>

                <div class="field">
                    <label>当期所得繰入先</label>
                    <div>
                        @if ($selectedBalancingAccountTitle)
                            {{ $selectedBalancingAccountTitle->account_code }} / {{ $selectedBalancingAccountTitle->name }}
                        @else
                            <span style="color: #dc2626;">未選択</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">繰越サマリー</h3>

            <div class="form-grid">
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
                    <label>当期収入</label>
                    <div>{{ number_format((float) $profitLossSummary['revenue_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>当期経費</label>
                    <div>{{ number_format((float) $profitLossSummary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>当期所得</label>
                    <div style="{{ (float) $summary['income_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['income_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>BS差額</label>
                    <div style="{{ abs((float) $summary['balance_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['balance_difference'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>開始残高候補の貸借差額</label>
                    <div style="{{ abs((float) $summary['rollover_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['rollover_difference'], 2) }}
                    </div>
                </div>
            </div>

            @if (abs((float) $summary['rollover_difference']) >= 0.005)
                <div class="alert alert-error" style="margin-top: 16px;">
                    翌期開始残高候補の借方・貸方が一致していません。
                    開始残高、元入金、事業主貸・事業主借、当期所得の繰入先科目を確認してください。
                </div>
            @endif
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">期末残高</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>期末残高</th>
                    <th>翌期開始側</th>
                    <th>翌期開始額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balanceRows as $row)
                    <tr>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->balance_amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->balance_amount, 2) }}
                        </td>
                        <td>{{ $sideLabels[$row->opening_side] ?? $row->opening_side }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->opening_amount, 2) }}</td>
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
                        <td colspan="10">期末残高の対象科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">翌期開始残高候補</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>元データ</th>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>借貸</th>
                    <th>金額</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rolloverRows as $row)
                    <tr>
                        <td>{{ $row->source_type === 'current_income' ? '当期所得' : '期末残高' }}</td>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $sideLabels[$row->opening_side] ?? $row->opening_side }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->opening_amount, 2) }}</td>
                        <td>{{ $row->line_note }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">翌期開始残高候補はありません。</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3">合計</th>
                    <th>借方</th>
                    <th style="text-align: right;">{{ number_format((float) $summary['rollover_debit_total'], 2) }}</th>
                    <th></th>
                </tr>
                <tr>
                    <th colspan="3">合計</th>
                    <th>貸方</th>
                    <th style="text-align: right;">{{ number_format((float) $summary['rollover_credit_total'], 2) }}</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

        <div class="alert alert-success" style="margin-top: 16px; background: #f8fafc; color: #334155; border-color: #cbd5e1;">
            次の段階で、この候補をもとに翌期帳簿作成・開始残高仕訳作成・マスタ引継ぎを実装します。
        </div>
    </div>
@endsection