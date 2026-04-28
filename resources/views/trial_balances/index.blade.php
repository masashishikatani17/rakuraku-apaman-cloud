@extends('layouts.app')

@section('title', '残高試算表')

@section('content')
    @php
        $categoryLabels = [
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '純資産',
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
            <h2 class="page-title">残高試算表</h2>
            <p class="page-description">登録済の仕訳を勘定科目ごとに集計する初版です。</p>
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
                    href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    総勘定元帳へ
                </a>
                <a
                    href="{{ route('department-trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    部門別試算表へ
                </a>
                <a
                    href="{{ route('reports.monthly-trends.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    月次推移表へ
                </a>
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
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        登録済みの仕訳を勘定科目ごとに集計します。開始残高入力で作成した開始残高仕訳も集計対象に含まれます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('trial-balances.index') }}">
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
                    <label for="date_from">集計開始日</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">集計終了日</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">集計する</button>
                <a
                    href="{{ $selectedBookId ? route('trial-balances.index', ['book_id' => $selectedBookId]) : route('trial-balances.index') }}"
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
                    <label>集計期間</label>
                    <div class="muted">
                        {{ $dateFrom ?: '開始未指定' }}
                        〜
                        {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>対象科目数</label>
                    <div class="muted">{{ $summary['accounts_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>借方合計</label>
                    <div>{{ number_format((float) $summary['total_debit'], 2) }}</div>
                </div>

                <div class="field">
                    <label>貸方合計</label>
                    <div>{{ number_format((float) $summary['total_credit'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差額</label>
                    <div style="{{ abs((float) $summary['difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['difference'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>判定</label>
                    <div style="{{ abs((float) $summary['difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ abs((float) $summary['difference']) < 0.005 ? '一致' : '不一致' }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>期末残高</th>
                    <th>状態</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($trialBalanceRows as $row)
                    <tr>
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                        <td>{{ number_format((float) $row->debit_total, 2) }}</td>
                        <td>{{ number_format((float) $row->credit_total, 2) }}</td>
                        <td>
                            {{ number_format((float) $row->ending_balance, 2) }}
                            @if ($row->ending_balance_side)
                                <div class="muted">{{ $sideLabels[$row->ending_balance_side] ?? $row->ending_balance_side }}</div>
                            @endif
                        </td>
                        <td>{{ $row->is_active ? '有効' : '停止' }}</td>
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
                        <td colspan="9">表示できる勘定科目がありません。勘定科目と仕訳を登録してから再度確認してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection