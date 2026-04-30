@extends('layouts.app')

@section('title', '退去精算仕訳作成')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">退去精算仕訳作成</h2>
            <p class="page-description">退去精算の返還額・追加請求額をもとに、会計仕訳を作成します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $settlement->book_id]) }}" class="button button-secondary">退去精算一覧へ戻る</a>
            <a href="{{ route('journal-entries.index', ['book_id' => $settlement->book_id]) }}" class="button button-secondary">仕訳一覧へ</a>
        </div>
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

    @if ($settlement->journal_entry_id)
        <div class="alert alert-error">
            この退去精算はすでに仕訳作成済みです。再作成する場合は、先に仕訳を取り消してください。
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">精算内容</h3>

        <div class="form-grid">
            <div class="field">
                <label>契約者</label>
                <div>{{ $settlement->rentalContract?->contractTenant?->tenant_code ?? '—' }} / {{ $settlement->rentalContract?->contractTenant?->name ?? '—' }}</div>
            </div>

            <div class="field">
                <label>物件・部屋</label>
                <div>
                    {{ $settlement->rentalContract?->property?->property_code ?? '—' }}
                    /
                    {{ $settlement->rentalContract?->property?->name ?? '—' }}
                    @if ($settlement->rentalContract?->propertyUnit?->unit_no)
                        {{ $settlement->rentalContract->propertyUnit->unit_no }}
                    @endif
                </div>
            </div>

            <div class="field">
                <label>精算日</label>
                <div>{{ $settlement->settlement_on?->format('Y-m-d') }}</div>
            </div>

            <div class="field">
                <label>状態</label>
                <div>{{ $settlement->statusLabel() }}</div>
            </div>

            <div class="field">
                <label>預り金等合計</label>
                <div>{{ number_format($settlement->totalDepositAmount(), 2) }}</div>
            </div>

            <div class="field">
                <label>請求控除合計</label>
                <div>{{ number_format($settlement->totalChargeAmount(), 2) }}</div>
            </div>

            <div class="field">
                <label>返還額</label>
                <div style="color: #166534;">{{ number_format((float) $settlement->refund_amount, 2) }}</div>
            </div>

            <div class="field">
                <label>追加請求額</label>
                <div style="color: #dc2626;">{{ number_format((float) $settlement->additional_billing_amount, 2) }}</div>
            </div>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        仕訳は、預り金等を借方、請求控除額を貸方に計上します。
        差額が返還額の場合は貸方に支払口座、追加請求額の場合は借方に未収金等を追加します。
    </div>

    <div class="card">
        <form method="POST" action="{{ route('rental-move-out-settlements.journal.store', $settlement) }}">
            @csrf

            <div class="form-grid">
                <div class="field">
                    <label for="entry_date">仕訳日<span class="required">必須</span></label>
                    <input
                        id="entry_date"
                        type="date"
                        name="entry_date"
                        value="{{ old('entry_date', $settlement->settlement_on?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                        required
                    >
                </div>

                <div class="field">
                    <label for="voucher_no">伝票番号</label>
                    <input
                        id="voucher_no"
                        type="text"
                        name="voucher_no"
                        value="{{ old('voucher_no') }}"
                        maxlength="20"
                    >
                </div>

                <div class="field field-full">
                    <label for="description_text">摘要文<span class="required">必須</span></label>
                    <input
                        id="description_text"
                        type="text"
                        name="description_text"
                        value="{{ old('description_text', $descriptionText) }}"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $settlement->note) }}</textarea>
                </div>
            </div>

            <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                <h3 style="margin-top: 0;">使用する勘定科目</h3>

                <div class="form-grid">
                    <div class="field">
                        <label for="deposit_liability_account_title_id">敷金・保証金等の預り金科目<span class="required">必須</span></label>
                        <select id="deposit_liability_account_title_id" name="deposit_liability_account_title_id" required>
                            <option value="">選択してください</option>
                            @foreach ($liabilityAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('deposit_liability_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="muted">敷金・保証金・前受家賃等を借方に計上します。</div>
                    </div>

                    <div class="field">
                        <label for="settlement_revenue_account_title_id">原状回復費等の請求収益科目<span class="required">必須</span></label>
                        <select id="settlement_revenue_account_title_id" name="settlement_revenue_account_title_id" required>
                            <option value="">選択してください</option>
                            @foreach ($revenueAccountTitles as $accountTitle)
                                <option value="{{ $accountTitle->id }}" {{ (string) old('settlement_revenue_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}>
                                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                        <div class="muted">未収家賃・原状回復費・その他請求控除を貸方に計上します。</div>
                    </div>

                    @if ((float) $settlement->refund_amount > 0)
                        <div class="field">
                            <label for="refund_payment_account_title_id">返還元口座科目<span class="required">必須</span></label>
                            <select id="refund_payment_account_title_id" name="refund_payment_account_title_id" required>
                                <option value="">選択してください</option>
                                @foreach ($assetAccountTitles as $accountTitle)
                                    <option value="{{ $accountTitle->id }}" {{ (string) old('refund_payment_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}>
                                        {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="muted">返還額を貸方に計上します。</div>
                        </div>
                    @endif

                    @if ((float) $settlement->additional_billing_amount > 0)
                        <div class="field">
                            <label for="additional_receivable_account_title_id">追加請求の未収金科目<span class="required">必須</span></label>
                            <select id="additional_receivable_account_title_id" name="additional_receivable_account_title_id" required>
                                <option value="">選択してください</option>
                                @foreach ($assetAccountTitles as $accountTitle)
                                    <option value="{{ $accountTitle->id }}" {{ (string) old('additional_receivable_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}>
                                        {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="muted">追加請求額を借方に計上します。</div>
                        </div>
                    @endif
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button" {{ $settlement->journal_entry_id ? 'disabled' : '' }}>退去精算仕訳を作成する</button>
                <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $settlement->book_id]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection