@php
    $statusLabels = [
        'unpaid' => '未入金',
        'partial' => '一部入金',
        'paid' => '入金済',
        'cancelled' => '取消',
    ];
@endphp

<div class="form-grid">
    <div class="field field-full">
        <label for="rental_contract_id">契約<span class="required">必須</span></label>
        <select id="rental_contract_id" name="rental_contract_id" required>
            <option value="">選択してください</option>
            @foreach ($rentalContracts as $rentalContract)
                <option
                    value="{{ $rentalContract->id }}"
                    {{ (string) old('rental_contract_id', $paymentSchedule?->rental_contract_id) === (string) $rentalContract->id ? 'selected' : '' }}
                >
                    {{ ($rentalContract->contractTenant?->tenant_code ?? '') . ' / ' . ($rentalContract->contractTenant?->name ?? '') }}
                    -
                    {{ ($rentalContract->property?->property_code ?? '') . ' / ' . ($rentalContract->property?->name ?? '') }}
                    @if ($rentalContract->propertyUnit)
                        - {{ $rentalContract->propertyUnit->unit_no }}
                    @endif
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="payment_item_id">入金項目<span class="required">必須</span></label>
        <select id="payment_item_id" name="payment_item_id" required>
            <option value="">選択してください</option>
            @foreach ($paymentItems as $paymentItem)
                <option
                    value="{{ $paymentItem->id }}"
                    {{ (string) old('payment_item_id', $paymentSchedule?->payment_item_id) === (string) $paymentItem->id ? 'selected' : '' }}
                >
                    {{ $paymentItem->item_code }} / {{ $paymentItem->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="payment_account_id">入金口座</label>
        <select id="payment_account_id" name="payment_account_id">
            <option value="">選択しない</option>
            @foreach ($paymentAccounts as $paymentAccount)
                <option
                    value="{{ $paymentAccount->id }}"
                    {{ (string) old('payment_account_id', $paymentSchedule?->payment_account_id) === (string) $paymentAccount->id ? 'selected' : '' }}
                >
                    {{ $paymentAccount->account_code }} / {{ $paymentAccount->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="target_year_month">対象年月<span class="required">必須</span></label>
        <input
            id="target_year_month"
            type="month"
            name="target_year_month"
            value="{{ old('target_year_month', $paymentSchedule?->target_year_month) }}"
            required
        >
    </div>

    <div class="field">
        <label for="due_on">入金予定日<span class="required">必須</span></label>
        <input
            id="due_on"
            type="date"
            name="due_on"
            value="{{ old('due_on', $paymentSchedule?->due_on?->format('Y-m-d')) }}"
            required
        >
    </div>

    <div class="field">
        <label for="expected_amount">予定金額<span class="required">必須</span></label>
        <input
            id="expected_amount"
            type="number"
            step="0.01"
            min="0"
            name="expected_amount"
            value="{{ old('expected_amount', $paymentSchedule?->expected_amount ?? 0) }}"
            required
        >
    </div>

    <div class="field">
        <label for="received_amount">入金済金額</label>
        <input
            id="received_amount"
            type="number"
            step="0.01"
            min="0"
            name="received_amount"
            value="{{ old('received_amount', $paymentSchedule?->received_amount ?? 0) }}"
        >
    </div>

    <div class="field">
        <label for="status">状態<span class="required">必須</span></label>
        <select id="status" name="status" required>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" {{ old('status', $paymentSchedule?->status ?? 'unpaid') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field field-full">
        <label for="note">備考</label>
        <textarea id="note" name="note">{{ old('note', $paymentSchedule?->note) }}</textarea>
    </div>
</div>