@extends('layouts.app')

@section('title', '勘定科目修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">勘定科目修正</h2>
            <p class="page-description">消費税区分・不動産所得決算書区分を含めて勘定科目を修正します。</p>
        </div>
        <div class="actions">
@php
                $parentBookId = request('book_id')
                    ?? ($selectedBookId ?? null)
                    ?? ($accountTitle->book_id ?? null)
                    ?? ($journalDescription->book_id ?? null)
                    ?? ($department->book_id ?? null)
                    ?? ($propertyOwner->book_id ?? null)
                    ?? ($propertyCategory->book_id ?? null)
                    ?? ($property->book_id ?? null)
                    ?? ($contractTenant->book_id ?? null)
                    ?? ($paymentItem->book_id ?? null)
                    ?? ($paymentAccount->book_id ?? null)
                    ?? ($borrowingLoan->book_id ?? null)
                    ?? ($journalEntry->book_id ?? null);
            @endphp
            <a
                href="{{ route('master-menu.index', $parentBookId ? ['book_id' => $parentBookId] : []) }}"
                class="button button-secondary"
            >
                マスタメニューへ戻る
            </a>
            <a href="{{ route('account-titles.index', ['book_id' => $accountTitle->book_id]) }}" class="button button-secondary">勘定科目一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
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

    <div class="card">
        <form method="POST" action="{{ route('account-titles.update', $accountTitle) }}">
            @csrf
            @method('PUT')

            <div class="form-grid">
                <div class="field field-full">
                    <label>帳簿</label>
                    <div class="muted">
                        {{ ($accountTitle->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($accountTitle->book?->name ?? '帳簿未設定') }}
                    </div>
                </div>

                <div class="field">
                    <label for="account_code">勘定科目コード<span class="required">必須</span></label>
                    <input
                        id="account_code"
                        type="text"
                        name="account_code"
                        value="{{ old('account_code', $accountTitle->account_code) }}"
                        maxlength="20"
                        required
                    >
                </div>

                <div class="field">
                    <label for="name">勘定科目名<span class="required">必須</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name', $accountTitle->name) }}"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field">
                    <label for="category">区分<span class="required">必須</span></label>
                    <select id="category" name="category" required>
                        <option value="asset" {{ old('category', $accountTitle->category) === 'asset' ? 'selected' : '' }}>資産</option>
                        <option value="liability" {{ old('category', $accountTitle->category) === 'liability' ? 'selected' : '' }}>負債</option>
                        <option value="equity" {{ old('category', $accountTitle->category) === 'equity' ? 'selected' : '' }}>純資産</option>
                        <option value="revenue" {{ old('category', $accountTitle->category) === 'revenue' ? 'selected' : '' }}>収益</option>
                        <option value="expense" {{ old('category', $accountTitle->category) === 'expense' ? 'selected' : '' }}>費用</option>
                    </select>
                </div>

                <div class="field">
                    <label for="normal_balance">通常残高<span class="required">必須</span></label>
                    <select id="normal_balance" name="normal_balance" required>
                        <option value="debit" {{ old('normal_balance', $accountTitle->normal_balance) === 'debit' ? 'selected' : '' }}>借方</option>
                        <option value="credit" {{ old('normal_balance', $accountTitle->normal_balance) === 'credit' ? 'selected' : '' }}>貸方</option>
                    </select>
                </div>

                <div class="field">
                    <label for="consumption_tax_category">消費税区分</label>
                    <select id="consumption_tax_category" name="consumption_tax_category">
                        @foreach ($consumptionTaxCategoryLabels as $value => $label)
                            <option value="{{ $value }}" {{ old('consumption_tax_category', $accountTitle->consumption_tax_category ?? 'auto') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <div class="muted">自動判定を選ぶと、従来どおり科目名から概算判定します。</div>
                </div>

                <div class="field">
                    <label for="consumption_tax_rate">消費税率</label>
                    <input
                        id="consumption_tax_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        name="consumption_tax_rate"
                        value="{{ old('consumption_tax_rate', $accountTitle->consumption_tax_rate) }}"
                        placeholder="例: 10.00"
                    >
                    <div class="muted">空欄の場合は、消費税集計画面で指定した税率を使います。</div>
                </div>

                <div class="field field-full">
                    <label for="real_estate_statement_category">不動産所得決算書区分</label>
                    <select id="real_estate_statement_category" name="real_estate_statement_category">
                        @foreach ($realEstateStatementCategoryLabels as $value => $label)
                            <option value="{{ $value }}" {{ old('real_estate_statement_category', $accountTitle->real_estate_statement_category ?? 'auto') === $value ? 'selected' : '' }}>
                                {{ $label }}
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
                        value="{{ old('sort_order', $accountTitle->sort_order) }}"
                        min="0"
                        max="999999"
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $accountTitle->note) }}</textarea>
                </div>

                <div class="field field-full">
                    <label>補助科目</label>
                    <div class="checkbox-wrap">
                        <input type="hidden" name="allows_sub_account" value="0">
                        <input
                            id="allows_sub_account"
                            type="checkbox"
                            name="allows_sub_account"
                            value="1"
                            {{ old('allows_sub_account', $accountTitle->allows_sub_account ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="allows_sub_account">この勘定科目で補助科目を使用する</label>
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
                            {{ old('is_active', $accountTitle->is_active ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">更新する</button>
                <a href="{{ route('account-titles.index', ['book_id' => $accountTitle->book_id]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection