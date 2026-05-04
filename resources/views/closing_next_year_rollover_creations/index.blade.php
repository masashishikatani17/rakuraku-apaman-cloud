@extends('layouts.app')

@section('title', '翌期帳簿作成')

@section('content')
    @php
        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">翌期帳簿作成</h2>
            <p class="page-description">年度繰越プレビューをもとに、翌期帳簿と開始残高仕訳を作成します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('closing.next-year-rollovers.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">年度繰越プレビューへ</a>
                <a href="{{ route('closing.next-year-rental-carryovers.index', ['source_book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸データ引継ぎへ</a>
                <a href="{{ route('opening-balances.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">開始残高へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この初版では、翌期帳簿、帳簿設定、勘定科目、補助科目、摘要、部門、翌期開始残高仕訳を作成します。
        物件・契約・入金予定などの賃貸管理データ引継ぎは次段階で追加します。
    </div>

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
        <form method="GET" action="{{ route('closing.next-year-rollover-creations.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">元帳簿<span class="required">必須</span></label>
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
                            <option value="{{ $accountTitle->id }}" {{ (string) $selectedBalancingAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}>
                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">再計算する</button>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">作成内容</h3>

            <form
                method="POST"
                action="{{ route('closing.next-year-rollover-creations.store') }}"
                onsubmit="return confirm('翌期帳簿と開始残高仕訳を作成しますか？同じ帳簿コードが既にある場合は作成できません。');"
            >
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <input type="hidden" name="balancing_account_title_id" value="{{ $selectedBalancingAccountTitleId }}">

                <div class="form-grid">
                    <div class="field">
                        <label>元帳簿</label>
                        <div>{{ $selectedBook->book_code }} / {{ $selectedBook->name }}</div>
                    </div>

                    <div class="field">
                        <label>元期間</label>
                        <div>{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</div>
                    </div>

                    <div class="field">
                        <label for="next_book_code">翌期帳簿CODE<span class="required">必須</span></label>
                        <input id="next_book_code" type="text" name="next_book_code" value="{{ old('next_book_code', $nextPeriod['book_code']) }}" maxlength="20" required>
                    </div>

                    <div class="field">
                        <label for="next_book_name">翌期帳簿名<span class="required">必須</span></label>
                        <input id="next_book_name" type="text" name="next_book_name" value="{{ old('next_book_name', $nextPeriod['name']) }}" maxlength="120" required>
                    </div>

                    <div class="field">
                        <label for="next_period_start_date">翌期開始日<span class="required">必須</span></label>
                        <input id="next_period_start_date" type="date" name="next_period_start_date" value="{{ old('next_period_start_date', $nextPeriod['period_start_date']) }}" required>
                    </div>

                    <div class="field">
                        <label for="next_period_end_date">翌期終了日<span class="required">必須</span></label>
                        <input id="next_period_end_date" type="date" name="next_period_end_date" value="{{ old('next_period_end_date', $nextPeriod['period_end_date']) }}" required>
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

                <div class="form-grid" style="margin-top: 16px;">
                    <div class="field">
                        <label>当期収入</label>
                        <div>{{ number_format((float) $summary['revenue_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>当期経費</label>
                        <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>当期所得</label>
                        <div style="{{ (float) $summary['income_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $summary['income_total'], 2) }}
                        </div>
                    </div>

                    <div class="field">
                        <label>開始残高候補 借方</label>
                        <div>{{ number_format((float) $summary['rollover_debit_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>開始残高候補 貸方</label>
                        <div>{{ number_format((float) $summary['rollover_credit_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>貸借差額</label>
                        <div style="{{ abs((float) $summary['rollover_difference']) < 0.005 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $summary['rollover_difference'], 2) }}
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button" {{ $selectedBalancingAccountTitleId === null ? 'disabled' : '' }}>
                        翌期帳簿と開始残高を作成
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0;">翌期開始残高仕訳候補</h3>

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
        </table>
    </div>
@endsection