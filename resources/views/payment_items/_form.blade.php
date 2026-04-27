@php
    $itemTypeLabels = [
        'rent' => '家賃',
        'common_service' => '共益費',
        'parking' => '駐車料',
        'deposit' => '敷金',
        'key_money' => '礼金',
        'other' => 'その他',
    ];
@endphp

<div class="form-grid">
    <div class="field">
        <label for="item_code">入金項目CODE<span class="required">必須</span></label>
        <input
            id="item_code"
            type="text"
            name="item_code"
            value="{{ old('item_code', $paymentItem?->item_code) }}"
            maxlength="20"
            required
        >
    </div>

    <div class="field">
        <label for="item_type">種別<span class="required">必須</span></label>
        <select id="item_type" name="item_type" required>
            @foreach ($itemTypeLabels as $value => $label)
                <option
                    value="{{ $value }}"
                    {{ old('item_type', $paymentItem?->item_type ?? 'rent') === $value ? 'selected' : '' }}
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="name">入金項目名<span class="required">必須</span></label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $paymentItem?->name) }}"
            maxlength="120"
            required
        >
    </div>

    <div class="field">
        <label for="default_amount">標準金額</label>
        <input
            id="default_amount"
            type="number"
            step="0.01"
            min="0"
            name="default_amount"
            value="{{ old('default_amount', $paymentItem?->default_amount ?? 0) }}"
        >
    </div>

    <div class="field">
        <label for="account_title_id">会計科目</label>
        <select id="account_title_id" name="account_title_id">
            <option value="">選択しない</option>
            @foreach ($accountTitles as $accountTitle)
                <option
                    value="{{ $accountTitle->id }}"
                    {{ (string) old('account_title_id', $paymentItem?->account_title_id) === (string) $accountTitle->id ? 'selected' : '' }}
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
                    {{ (string) old('sub_account_title_id', $paymentItem?->sub_account_title_id) === (string) $subAccountTitle->id ? 'selected' : '' }}
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
            value="{{ old('sort_order', $paymentItem?->sort_order ?? 0) }}"
            min="0"
            max="999999"
        >
    </div>

    <div class="field field-full">
        <label for="note">備考</label>
        <textarea id="note" name="note">{{ old('note', $paymentItem?->note) }}</textarea>
    </div>

    <div class="field field-full">
        <label>月次入金対象</label>
        <div class="checkbox-wrap">
            <input type="hidden" name="is_monthly" value="0">
            <input
                id="is_monthly"
                type="checkbox"
                name="is_monthly"
                value="1"
                {{ (string) old('is_monthly', ($paymentItem?->is_monthly ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}
            >
            <label for="is_monthly">月次入金の対象にする</label>
        </div>
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
                {{ (string) old('is_active', ($paymentItem?->is_active ?? true) ? '1' : '0') === '1' ? 'checked' : '' }}
            >
            <label for="is_active">有効</label>
        </div>
    </div>
</div>