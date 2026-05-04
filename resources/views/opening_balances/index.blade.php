@extends('layouts.app')

@section('title', '開始残高')

@section('content')
    @php
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
            <h2 class="page-title">開始残高</h2>
            <p class="page-description">帳簿開始時点の資産・負債・純資産残高を登録します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $openingDate, 'date_to' => $openingDate]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $openingDate, 'date_to' => $openingDate]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $openingDate, 'date_to' => $openingDate]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
                <a
                    href="{{ route('closing.next-year-rollovers.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    年度繰越プレビューへ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、入力した開始残高から「開始残高」仕訳を1本作成します。
        登録済みの開始残高仕訳がある場合は、再登録時に作り直します。
        貸借差額は、指定した差額調整科目へ自動で入ります。
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-error">
            <strong>入力内容を確認してください。</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('opening-balances.index') }}">
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
                    <label for="opening_date">開始残高日<span class="required">必須</span></label>
                    <input
                        id="opening_date"
                        type="date"
                        name="opening_date"
                        value="{{ $openingDate }}"
                        required
                    >
                </div>

                <div class="field field-full">
                    <label for="balancing_account_title_id">差額調整科目<span class="required">必須</span></label>
                    <select id="balancing_account_title_id" name="balancing_account_title_id" required>
                        <option value="">選択してください</option>
                        @foreach ($accountTitles as $accountTitle)
                            <option
                                value="{{ $accountTitle->id }}"
                                {{ (string) $selectedBalancingAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                            >
                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                （{{ $categoryLabels[$accountTitle->category] ?? $accountTitle->category }} / {{ $sideLabels[$accountTitle->normal_balance] ?? $accountTitle->normal_balance }}）
                            </option>
                        @endforeach
                    </select>
                    <div class="muted" style="margin-top: 4px;">
                        資産・負債の入力差額を受ける科目です。通常は「元入金」などの純資産科目を選びます。
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">この条件で表示する</button>
            </div>
        </form>
    </div>

    @if ($selectedBook === null)
        <div class="alert alert-error">開始残高を登録する帳簿を選択してください。</div>
    @elseif ($accountTitles->isEmpty())
        <div class="alert alert-error">
            この帳簿には貸借対照表科目がありません。先に資産・負債・純資産の勘定科目を登録してください。
        </div>
    @elseif ($selectedBalancingAccountTitle === null)
        <div class="alert alert-error">差額調整科目を選択してください。</div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>選択中の帳簿</label>
                    <div class="muted">
                        {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                    </div>
                </div>

                <div class="field">
                    <label>現在の開始残高仕訳</label>
                    @if ($summary['exists'])
                        <div>
                            {{ $summary['entry_date'] }} / {{ $summary['voucher_no'] ?: '伝票番号なし' }}
                        </div>
                    @else
                        <div class="muted">未登録</div>
                    @endif
                </div>

                <div class="field">
                    <label>借方合計</label>
                    <div>{{ number_format((float) $summary['debit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>貸方合計</label>
                    <div>{{ number_format((float) $summary['credit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差額調整科目</label>
                    <div>
                        {{ $selectedBalancingAccountTitle->account_code }} / {{ $selectedBalancingAccountTitle->name }}
                    </div>
                    <div class="muted">
                        現在の調整額: {{ number_format((float) $summary['balancing_amount'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('opening-balances.store') }}">
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="opening_date" value="{{ $openingDate }}">
                <input type="hidden" name="balancing_account_title_id" value="{{ $selectedBalancingAccountTitleId }}">

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>科目コード</th>
                                <th>科目名</th>
                                <th>区分</th>
                                <th>通常残高</th>
                                <th>残高方向</th>
                                <th>開始残高</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($inputAccountTitles as $accountTitle)
                                @php
                                    $existingLine = $existingLineMap->get($accountTitle->id, [
                                        'side' => $accountTitle->normal_balance,
                                        'amount' => 0,
                                    ]);
                                    $oldPrefix = 'balances.' . $accountTitle->id . '.';
                                @endphp
                                <tr>
                                    <td>{{ $accountTitle->account_code }}</td>
                                    <td>{{ $accountTitle->name }}</td>
                                    <td>{{ $categoryLabels[$accountTitle->category] ?? $accountTitle->category }}</td>
                                    <td>{{ $sideLabels[$accountTitle->normal_balance] ?? $accountTitle->normal_balance }}</td>
                                    <td>
                                        <input
                                            type="hidden"
                                            name="balances[{{ $accountTitle->id }}][account_title_id]"
                                            value="{{ $accountTitle->id }}"
                                        >
                                        <select name="balances[{{ $accountTitle->id }}][side]">
                                            @foreach ($sideLabels as $value => $label)
                                                <option
                                                    value="{{ $value }}"
                                                    {{ old($oldPrefix . 'side', $existingLine['side']) === $value ? 'selected' : '' }}
                                                >
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            name="balances[{{ $accountTitle->id }}][amount]"
                                            value="{{ old($oldPrefix . 'amount', (float) $existingLine['amount'] > 0 ? number_format((float) $existingLine['amount'], 2, '.', '') : '') }}"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">入力対象の科目がありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">開始残高を登録する</button>
                    <a
                        href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $openingDate, 'date_to' => $openingDate]) }}"
                        class="button button-secondary"
                    >
                        作成済み仕訳を確認する
                    </a>
                </div>
            </form>
        </div>
    @endif
@endsection