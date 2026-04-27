@php
    $statusLabels = [
        'confirmed' => '確定',
        'cancelled' => '取消',
    ];
@endphp

<div class="form-grid">
    <div class="field field-full">
        <label for="payment_schedule_id">入金予定<span class="required">必須</span></label>
        <select id="payment_schedule_id" name="payment_schedule_id" required>
            <option value="">選択してください</option>
            @foreach ($paymentSchedules as $paymentSchedule)
                <option
                    value="{{ $paymentSchedule->id }}"
                    {{ (string) old('payment_schedule_id', $paymentReceipt?->payment_schedule_id) === (string) $paymentSchedule->id ? 'selected' : '' }}
                >
                    {{ $paymentSchedule->due_on?->format('Y-m-d') }}
                    /
                    {{ $paymentSchedule->contractTenant?->tenant_code }}
                    {{ $paymentSchedule->contractTenant?->name }}
                    /
                    {{ $paymentSchedule->paymentItem?->name }}
                    /
                    予定 {{ number_format((float) $paymentSchedule->expected_amount, 2) }}
                    /
                    入金済 {{ number_format((float) $paymentSchedule->received_amount, 2) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="payment_account_id">入金口座</label>
        <select id="payment_account_id" name="payment_account_id">
            <option value="">入金予定の口座を使う / 未設定</option>
            @foreach ($paymentAccounts as $paymentAccount)
                <option
                    value="{{ $paymentAccount->id }}"
                    {{ (string) old('payment_account_id', $paymentReceipt?->payment_account_id) === (string) $paymentAccount->id ? 'selected' : '' }}
                >
                    {{ $paymentAccount->account_code }} / {{ $paymentAccount->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="received_on">入金日<span class="required">必須</span></label>
        <input
            id="received_on"
            type="date"
            name="received_on"
            value="{{ old('received_on', $paymentReceipt?->received_on?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
            required
        >
    </div>

    <div class="field">
        <label for="amount">入金額<span class="required">必須</span></label>
        <input
            id="amount"
            type="number"
            step="0.01"
            min="0.01"
            name="amount"
            value="{{ old('amount', $paymentReceipt?->amount) }}"
            required
        >
    </div>

    <div class="field">
        <label for="status">状態<span class="required">必須</span></label>
        <select id="status" name="status" required>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" {{ old('status', $paymentReceipt?->status ?? 'confirmed') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field field-full">
        <label for="payer_name">入金者名 / 振込人名</label>
        <input
            id="payer_name"
            type="text"
            name="payer_name"
            value="{{ old('payer_name', $paymentReceipt?->payer_name) }}"
            maxlength="120"
        >
    </div>

    <div class="field field-full">
        <label for="note">備考</label>
        <textarea id="note" name="note">{{ old('note', $paymentReceipt?->note) }}</textarea>
    </div>
</div>