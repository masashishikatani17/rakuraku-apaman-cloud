@extends('layouts.app')

@section('title', '消費税精算仕訳')

@section('content')
    @php
        $amountModeLabels = [
            'tax_included' => '税込入力として計算',
            'tax_excluded' => '税抜入力として計算',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">消費税精算仕訳</h2>
            <p class="page-description">消費税集計の概算税額をもとに、仮受・仮払消費税を未払/未収消費税へ振り替えます。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.consumption-tax.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'tax_rate' => $taxRate, 'amount_mode' => $amountMode]) }}" class="button button-secondary">消費税集計へ</a>
                <a href="{{ route('reports.consumption-tax-filing.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'default_tax_rate' => $taxRate, 'amount_mode' => $amountMode]) }}" class="button">消費税申告用集計へ</a>
                <a href="{{ route('consumption-tax-category-reviews.index', ['book_id' => $selectedBookId, 'default_tax_rate' => $taxRate]) }}" class="button button-secondary">消費税区分レビューへ</a>
                <a href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">仕訳一覧へ</a>
                <a href="{{ route('closing.book-locks.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">年度締めへ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #fff7ed; color: #9a3412; border-color: #fed7aa;">
        消費税集計は概算です。実際の申告では、課税区分・軽減税率・課税方式・按分計算などを確認してください。
        この画面は、Cloud版の決算整理用として仮受消費税・仮払消費税を振り替える初版です。
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
        <form method="GET" action="{{ route('consumption-tax-settlement-journals.index') }}">
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
                    <label for="tax_rate">消費税率（%）</label>
                    <input id="tax_rate" type="number" name="tax_rate" value="{{ $taxRate }}" step="0.1" min="0" max="100">
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
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">再計算する</button>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">消費税精算サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>課税売上税抜相当額</label>
                    <div>{{ number_format((float) $summary['taxable_sales_base_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>仮受消費税相当額</label>
                    <div>{{ number_format((float) $summary['taxable_sales_tax_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>課税仕入税抜相当額</label>
                    <div>{{ number_format((float) $summary['taxable_purchase_base_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>仮払消費税相当額</label>
                    <div>{{ number_format((float) $summary['taxable_purchase_tax_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>概算納付/還付</label>
                    <div style="{{ (float) $summary['estimated_consumption_tax_payable'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_consumption_tax_payable'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>集計科目数</label>
                    <div>{{ $summary['rows_count'] }} 科目</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">精算仕訳作成</h3>

            <form method="POST" action="{{ route('consumption-tax-settlement-journals.store') }}" onsubmit="return confirm('消費税精算仕訳を作成しますか？');">
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
                <input type="hidden" name="tax_rate" value="{{ $taxRate }}">
                <input type="hidden" name="amount_mode" value="{{ $amountMode }}">

                <div class="form-grid">
                    <div class="field">
                        <label for="entry_date">仕訳日<span class="required">必須</span></label>
                        <input id="entry_date" type="date" name="entry_date" value="{{ old('entry_date', $dateTo ?: now()->format('Y-m-d')) }}" required>
                    </div>

                    <div class="field">
                        <label for="sales_tax_account_title_id">仮受消費税科目<span class="required">必須</span></label>
                        <select id="sales_tax_account_title_id" name="sales_tax_account_title_id" required>
                            <option value="">選択してください</option>
                            @foreach ($liabilityAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('sales_tax_account_title_id', $selectedSalesTaxAccountTitleId) === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="purchase_tax_account_title_id">仮払消費税科目<span class="required">必須</span></label>
                        <select id="purchase_tax_account_title_id" name="purchase_tax_account_title_id" required>
                            <option value="">選択してください</option>
                            @foreach ($assetAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('purchase_tax_account_title_id', $selectedPurchaseTaxAccountTitleId) === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="payable_tax_account_title_id">未払消費税科目</label>
                        <select id="payable_tax_account_title_id" name="payable_tax_account_title_id">
                            <option value="">選択してください</option>
                            @foreach ($liabilityAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('payable_tax_account_title_id', $selectedPayableTaxAccountTitleId) === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="receivable_tax_account_title_id">未収消費税科目</label>
                        <select id="receivable_tax_account_title_id" name="receivable_tax_account_title_id">
                            <option value="">選択してください</option>
                            @foreach ($assetAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('receivable_tax_account_title_id', $selectedReceivableTaxAccountTitleId) === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="voucher_no">伝票番号</label>
                        <input id="voucher_no" type="text" name="voucher_no" value="{{ old('voucher_no') }}" maxlength="20" placeholder="空欄なら自動採番">
                    </div>

                    <div class="field">
                        <label for="note">備考</label>
                        <input id="note" type="text" name="note" value="{{ old('note', '消費税精算仕訳') }}">
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button" {{ abs((float) $summary['estimated_consumption_tax_payable']) < 0.005 && abs((float) $summary['taxable_sales_tax_total']) < 0.005 && abs((float) $summary['taxable_purchase_tax_total']) < 0.005 ? 'disabled' : '' }}>
                        消費税精算仕訳を作成
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0;">作成済み消費税精算仕訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>仕訳日</th>
                    <th>伝票番号</th>
                    <th>摘要</th>
                    <th>金額</th>
                    <th>明細</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($settlementJournals as $journalEntry)
                    <tr>
                        <td>{{ $journalEntry->entry_date?->format('Y-m-d') }}</td>
                        <td>{{ $journalEntry->voucher_no ?: '—' }}</td>
                        <td>{{ $journalEntry->description_text }}</td>
                        <td style="text-align: right;">{{ number_format((float) $journalEntry->total_amount, 2) }}</td>
                        <td>
                            @foreach ($journalEntry->lines as $line)
                                <div>
                                    {{ $line->side === 'debit' ? '借方' : '貸方' }}
                                    {{ $line->accountTitle?->name ?? '科目不明' }}
                                    {{ number_format((float) $line->amount, 2) }}
                                </div>
                            @endforeach
                        </td>
                        <td>
                            <form
                                method="POST"
                                action="{{ route('consumption-tax-settlement-journals.destroy', $journalEntry) }}"
                                onsubmit="return confirm('この消費税精算仕訳を削除しますか？');"
                                style="display: inline-block; margin: 0;"
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="button" style="background: #dc2626;">削除</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">消費税精算仕訳はまだありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection