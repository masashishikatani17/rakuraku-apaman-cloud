@php
    $accountTypeLabels = [
        'ordinary' => '普通',
        'current' => '当座',
        'savings' => '貯蓄',
        'other' => 'その他',
    ];
@endphp

<div class="form-grid">
    <div class="field">
        <label for="account_code">入金口座CODE<span class="required">必須</span></label>
        <input
            id="account_code"
            type="text"
            name="account_code"
            value="{{ old('account_code', $paymentAccount?->account_code) }}"
            maxlength="20"
            required
        >
    </div>

    <div class="field">
        <label for="name">入金口座名<span class="required">必須</span></label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $paymentAccount?->name) }}"
            maxlength="120"
            required
        >
    </div>

    <div class="field">
        <label for="bank_name">金融機関名</label>
        <input id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', $paymentAccount?->bank_name) }}" maxlength="120">
    </div>

    <div class="field">
        <label for="branch_name">支店名</label>
        <input id="branch_name" type="text" name="branch_name" value="{{ old('branch_name', $paymentAccount?->branch_name) }}" maxlength="120">
    </div>

    <div class="field">
        <label for="account_type">口座種別</label>
        <select id="account_type" name="account_type">
            <option value="">選択しない</option>
            @foreach ($accountTypeLabels as $value => $label)
                <option
                    value="{{ $value }}"
                    {{ old('account_type', $paymentAccount?->account_type) === $value ? 'selected' : '' }}
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="account_number">口座番号</label>
        <input id="account_number" type="text" name="account_number" value="{{ old('account_number', $paymentAccount?->account_number) }}" maxlength="50">
    </div>

    <div class="field field-full">
        <label for="account_holder">口座名義</label>
        <input id="account_holder" type="text" name="account_holder" value="{{ old('account_holder', $paymentAccount?->account_holder) }}" maxlength="120">
    </div>

    <div class="field">
        <label for="account_title_id">会計科目</label>
        <select id="account_title_id" name="account_title_id">
            <option value="">選択しない</option>
            @foreach ($accountTitles as $accountTitle)
                <option
                    value="{{ $accountTitle->id }}"
                    {{ (string) old('account_title_id', $paymentAccount?->account_title_id) === (string) $accountTitle->id ? 'selected' : '' }}
                >
                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="sub_account_title_id">補助科目</label>
        <select id="sub_account_title_id" name="sub_account_title_id">
            <option value="">選択しない</option>
            @foreach ($subAccountTitles as $subAccountTitle)
                <option
                    value="{{ $subAccountTitle->id }}"
                    {{ (string) old('sub_account_title_id', $paymentAccount?->sub_account_title_id) === (string) $subAccountTitle->id ? 'selected' : '' }}
                >
                    {{ $subAccountTitle->accountTitle?->account_code }} / {{ $subAccountTitle->sub_account_code }} / {{ $subAccountTitle->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="sort_order">並び順</label>
        <input
            id="sort_order"
            type="number"
            name="sort_order"
            value="{{ old('sort_order', $paymentAccount?->sort_order ?? 0) }}"
            min="0"
            max="999999"
        >
    </div>

    <div class="field field-full">
        <label for="note">備考</label>
        <textarea id="note" name="note">{{ old('note', $paymentAccount?->note) }}</textarea>
    </div>

    <div class="field field-full">
        <label>状態</label>
        <div class="checkbox-wrap">
            <input type="hidden" name="is_active" value="0">
            <input
                id="is_active"
                type="checkbox"
                name="is_active"
                value="1"
                {{ (string) old('is_active', ($paymentAccount?->is_active ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}
            >
            <label for="is_active">有効</label>
        </div>
    </div>
</div>