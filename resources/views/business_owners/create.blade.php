@extends('layouts.app')

@section('title', '事業主登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">事業主登録</h2>
            <p class="page-description">一覧に表示する事業主を登録します。</p>
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
            <a href="{{ route('business-owners.index') }}" class="button button-secondary">一覧へ戻る</a>
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
        <form method="POST" action="{{ route('business-owners.store') }}">
            @csrf

            <div class="form-grid">
                <div class="field">
                    <label for="owner_code">事業主コード</label>
                    <input
                        id="owner_code"
                        type="text"
                        name="owner_code"
                        value="{{ old('owner_code') }}"
                        maxlength="20"
                    >
                </div>

                <div class="field">
                    <label for="owner_type">種別<span class="required">必須</span></label>
                    <select id="owner_type" name="owner_type" required>
                        <option value="individual" {{ old('owner_type', 'individual') === 'individual' ? 'selected' : '' }}>個人</option>
                        <option value="corporate" {{ old('owner_type') === 'corporate' ? 'selected' : '' }}>法人</option>
                    </select>
                </div>

                <div class="field">
                    <label for="name">事業主名<span class="required">必須</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field">
                    <label for="name_kana">事業主名カナ</label>
                    <input
                        id="name_kana"
                        type="text"
                        name="name_kana"
                        value="{{ old('name_kana') }}"
                        maxlength="120"
                    >
                </div>

                <div class="field">
                    <label for="postal_code">郵便番号</label>
                    <input
                        id="postal_code"
                        type="text"
                        name="postal_code"
                        value="{{ old('postal_code') }}"
                        maxlength="8"
                    >
                </div>

                <div class="field">
                    <label for="phone">電話番号</label>
                    <input
                        id="phone"
                        type="text"
                        name="phone"
                        value="{{ old('phone') }}"
                        maxlength="30"
                    >
                </div>

                <div class="field field-full">
                    <label for="address_line1">住所1</label>
                    <input
                        id="address_line1"
                        type="text"
                        name="address_line1"
                        value="{{ old('address_line1') }}"
                        maxlength="255"
                    >
                </div>

                <div class="field field-full">
                    <label for="address_line2">住所2</label>
                    <input
                        id="address_line2"
                        type="text"
                        name="address_line2"
                        value="{{ old('address_line2') }}"
                        maxlength="255"
                    >
                </div>

                <div class="field field-full">
                    <label for="email">メールアドレス</label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        maxlength="255"
                    >
                </div>

                <div class="field field-full">
                    <label for="memo">メモ</label>
                    <textarea id="memo" name="memo">{{ old('memo') }}</textarea>
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
                            {{ old('is_active', '1') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">登録する</button>
                <a href="{{ route('business-owners.index') }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection