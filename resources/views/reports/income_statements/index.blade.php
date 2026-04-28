@extends('layouts.app')

@section('title', '損益計算書')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">損益計算書</h2>
            <p class="page-description">登録済み仕訳から、収益・費用・差引損益を集計します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.monthly-trends.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    月次推移表へ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('department-trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    部門別試算表へ
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
        初版では、登録済み仕訳のPL科目、つまり収益・費用だけを対象にします。
        開始残高・決算整理仕訳・税務申告用の細分類は、後続の決算機能で拡張します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.income-statements.index') }}">
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
                    href="{{ $selectedBookId ? route('reports.income-statements.index', ['book_id' => $selectedBookId]) : route('reports.income-statements.index') }}"
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
                    <label>表示科目数</label>
                    <div>{{ $summary['rows_count'] }} 科目</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
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

                <div class="field">
                    <label>判定</label>
                    <div style="{{ (float) $summary['profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ (float) $summary['profit_loss_total'] >= 0 ? '利益' : '損失' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">損益計算書</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>金額</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>収益合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['revenue_total'], 2) }}</td>
                </tr>
                <tr>
                    <td>費用合計</td>
                    <td style="text-align: right;">{{ number_format((float) $summary['expense_total'], 2) }}</td>
                </tr>
                <tr>
                    <td><strong>差引損益</strong></td>
                    <td style="text-align: right; {{ (float) $summary['profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        <strong>{{ number_format((float) $summary['profit_loss_total'], 2) }}</strong>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">収益</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>損益計算書金額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($revenueRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>
                            {{ $row->account_name }}
                            @unless ($row->is_active)
                                <div class="muted">停止中</div>
                            @endunless
                        </td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->amount, 2) }}
                        </td>
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
                        <td colspan="7">表示できる収益科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">費用</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>損益計算書金額</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenseRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>
                            {{ $row->account_name }}
                            @unless ($row->is_active)
                                <div class="muted">停止中</div>
                            @endunless
                        </td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->amount, 2) }}
                        </td>
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
                        <td colspan="7">表示できる費用科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
