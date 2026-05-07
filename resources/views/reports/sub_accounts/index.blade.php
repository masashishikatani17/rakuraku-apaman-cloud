@extends('layouts.app')

@section('title', '補助科目一覧')

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
            <h2 class="page-title">補助科目一覧</h2>
            <p class="page-description">補助科目ごとの借方・貸方・残高を確認します。</p>
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
                    href="{{ route('sub-account-titles.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    補助科目マスタへ
                </a>
                <a
                    href="{{ route('sub-account-ledgers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    補助科目別元帳へ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、補助科目マスタと仕訳明細をもとに、補助科目別の残高を表示します。
        銀行口座別や取引先別に補助科目を使っている場合、ここで内訳を確認できます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.sub-accounts.index') }}">
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
                    <label for="account_title_id">勘定科目</label>
                    <select id="account_title_id" name="account_title_id">
                        <option value="">すべて表示</option>
                        @foreach ($accountTitles as $accountTitle)
                            <option
                                value="{{ $accountTitle->id }}"
                                {{ (string) $selectedAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                            >
                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
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
                    href="{{ $selectedBookId ? route('reports.sub-accounts.index', ['book_id' => $selectedBookId]) : route('reports.sub-accounts.index') }}"
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
                    <label>対象勘定科目</label>
                    <div>
                        @if ($selectedAccountTitle)
                            {{ $selectedAccountTitle->account_code }} / {{ $selectedAccountTitle->name }}
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
                    <label>補助科目数</label>
                    <div>{{ $summary['sub_accounts_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>借方合計</label>
                    <div>{{ number_format((float) $summary['debit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>貸方合計</label>
                    <div>{{ number_format((float) $summary['credit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差額</label>
                    <div style="{{ abs((float) $summary['difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['difference'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>対象勘定科目数</label>
                    <div>{{ $summary['accounts_count'] }} 件</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>勘定科目</th>
                    <th>補助科目コード</th>
                    <th>補助科目名</th>
                    <th>区分</th>
                    <th>通常残高</th>
                    <th>借方合計</th>
                    <th>貸方合計</th>
                    <th>残高</th>
                    <th>状態</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subAccountRows as $row)
                    <tr>
                        <td>
                            {{ $row->account_code }}
                            /
                            {{ $row->account_name }}
                        </td>
                        <td>{{ $row->sub_account_code }}</td>
                        <td>{{ $row->sub_account_name }}</td>
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
                        <td>{{ $row->sub_account_is_active ? '有効' : '停止' }}</td>
                        <td>
                            <a
                                href="{{ route('sub-account-ledgers.index', [
                                    'book_id' => $selectedBookId,
                                    'account_title_id' => $row->account_title_id,
                                    'sub_account_title_id' => $row->sub_account_title_id,
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
                        <td colspan="10">表示できる補助科目がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection