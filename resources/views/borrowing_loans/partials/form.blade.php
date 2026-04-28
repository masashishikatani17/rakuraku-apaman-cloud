@php
    $loan = $borrowingLoan;
    $repaymentMethodLabels = [
        'equal_principal' => '元金均等',
        'equal_payment' => '元利均等',
    ];
    $statusLabels = [
        'active' => '返済中',
        'paid_off' => '完済',
    ];
@endphp

<div class="form-grid">
    <div class="field">
        <label for="book_id">帳簿<span class="required">必須</span></label>
        <select id="book_id" name="book_id" required {{ $loan ? 'disabled' : '' }}>
            @foreach ($books as $book)
                <option
                    value="{{ $book->id }}"
                    {{ (string) old('book_id', $selectedBookId) === (string) $book->id ? 'selected' : '' }}
                >
                    {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                </option>
            @endforeach
        </select>
        @if ($loan)
            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
        @endif
    </div>

    <div class="field">
        <label for="loan_code">借入コード<span class="required">必須</span></label>
        <input
            id="loan_code"
            type="text"
            name="loan_code"
            value="{{ old('loan_code', $loan?->loan_code) }}"
            required
        >
    </div>

    <div class="field">
        <label for="name">借入名<span class="required">必須</span></label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $loan?->name) }}"
            required
        >
    </div>

    <div class="field">
        <label for="lender_name">借入先</label>
        <input
            id="lender_name"
            type="text"
            name="lender_name"
            value="{{ old('lender_name', $loan?->lender_name) }}"
        >
    </div>

    <div class="field">
        <label for="property_id">関連物件</label>
        <select id="property_id" name="property_id">
            <option value="">指定なし</option>
            @foreach ($properties as $property)
                <option
                    value="{{ $property->id }}"
                    {{ (string) old('property_id', $loan?->property_id) === (string) $property->id ? 'selected' : '' }}
                >
                    {{ $property->property_code }} / {{ $property->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="department_id">部門</label>
        <select id="department_id" name="department_id">
            <option value="">指定なし</option>
            @foreach ($departments as $department)
                <option
                    value="{{ $department->id }}"
                    {{ (string) old('department_id', $loan?->department_id) === (string) $department->id ? 'selected' : '' }}
                >
                    {{ $department->department_code }} / {{ $department->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="borrowed_on">借入日<span class="required">必須</span></label>
        <input
            id="borrowed_on"
            type="date"
            name="borrowed_on"
            value="{{ old('borrowed_on', $loan?->borrowed_on?->format('Y-m-d') ?? $selectedBook?->period_start_date?->format('Y-m-d')) }}"
            required
        >
    </div>

    <div class="field">
        <label for="principal_amount">当初借入額<span class="required">必須</span></label>
        <input
            id="principal_amount"
            type="number"
            step="0.01"
            min="0"
            name="principal_amount"
            value="{{ old('principal_amount', $loan?->principal_amount) }}"
            required
        >
    </div>

    <div class="field">
        <label for="annual_interest_rate">年利率(%)</label>
        <input
            id="annual_interest_rate"
            type="number"
            step="0.0001"
            min="0"
            max="100"
            name="annual_interest_rate"
            value="{{ old('annual_interest_rate', $loan?->annual_interest_rate ?? 0) }}"
        >
    </div>

    <div class="field">
        <label for="term_months">返済回数(月)<span class="required">必須</span></label>
        <input
            id="term_months"
            type="number"
            min="1"
            max="600"
            name="term_months"
            value="{{ old('term_months', $loan?->term_months ?? 120) }}"
            required
        >
    </div>

    <div class="field">
        <label for="repayment_start_date">返済開始日<span class="required">必須</span></label>
        <input
            id="repayment_start_date"
            type="date"
            name="repayment_start_date"
            value="{{ old('repayment_start_date', $loan?->repayment_start_date?->format('Y-m-d') ?? $selectedBook?->period_start_date?->format('Y-m-d')) }}"
            required
        >
    </div>

    <div class="field">
        <label for="monthly_repayment_day">毎月返済日<span class="required">必須</span></label>
        <input
            id="monthly_repayment_day"
            type="number"
            min="1"
            max="31"
            name="monthly_repayment_day"
            value="{{ old('monthly_repayment_day', $loan?->monthly_repayment_day ?? 27) }}"
            required
        >
    </div>

    <div class="field">
        <label for="repayment_method">返済方法<span class="required">必須</span></label>
        <select id="repayment_method" name="repayment_method" required>
            @foreach ($repaymentMethodLabels as $value => $label)
                <option value="{{ $value }}" {{ old('repayment_method', $loan?->repayment_method ?? 'equal_principal') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="status">状態<span class="required">必須</span></label>
        <select id="status" name="status" required>
            @foreach ($statusLabels as $value => $label)
                <option value="{{ $value }}" {{ old('status', $loan?->status ?? 'active') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<h3 style="margin-top: 24px;">仕訳科目</h3>

<div class="form-grid">
    <div class="field">
        <label for="principal_account_title_id">借入金科目<span class="required">必須</span></label>
        <select id="principal_account_title_id" name="principal_account_title_id" required>
            <option value="">選択してください</option>
            @foreach ($liabilityAccountTitles as $accountTitle)
                <option
                    value="{{ $accountTitle->id }}"
                    {{ (string) old('principal_account_title_id', $loan?->principal_account_title_id) === (string) $accountTitle->id ? 'selected' : '' }}
                >
                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="interest_expense_account_title_id">支払利息科目<span class="required">必須</span></label>
        <select id="interest_expense_account_title_id" name="interest_expense_account_title_id" required>
            <option value="">選択してください</option>
            @foreach ($expenseAccountTitles as $accountTitle)
                <option
                    value="{{ $accountTitle->id }}"
                    {{ (string) old('interest_expense_account_title_id', $loan?->interest_expense_account_title_id) === (string) $accountTitle->id ? 'selected' : '' }}
                >
                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="field">
        <label for="payment_account_title_id">返済元口座科目<span class="required">必須</span></label>
        <select id="payment_account_title_id" name="payment_account_title_id" required>
            <option value="">選択してください</option>
            @foreach ($assetAccountTitles as $accountTitle)
                <option
                    value="{{ $accountTitle->id }}"
                    {{ (string) old('payment_account_title_id', $loan?->payment_account_title_id) === (string) $accountTitle->id ? 'selected' : '' }}
                >
                    {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                </option>
            @endforeach
        </select>
    </div>
</div>

<div class="field" style="margin-top: 16px;">
    <label for="note">備考</label>
    <textarea id="note" name="note" rows="4">{{ old('note', $loan?->note) }}</textarea>
</div>

<div class="alert alert-success" style="background: #f8fafc; color: #334155; border-color: #cbd5e1; margin-top: 16px;">
    登録・更新すると返済予定表を作り直します。すでに返済仕訳を作成済みの借入金は、初版では安全のため修正・削除を止めます。
</div>