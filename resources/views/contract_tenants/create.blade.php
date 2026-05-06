@extends('layouts.app')

@section('title', '契約者登録')

@section('content')
    @php
        $statusLabels = [
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">契約者登録</h2>
            <p class="page-description">契約者基本情報と賃貸条件をまとめて登録します。</p>
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
            <a href="{{ route('contract-tenants.index') }}" class="button button-secondary">契約者台帳へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>

        <div class="actions">
            <a href="{{ route('books.create') }}" class="button">帳簿を登録する</a>
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('contract-tenants.create') }}">
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="book_id_selector">入力する帳簿</label>
                        <select id="book_id_selector" name="book_id">
                            @foreach ($books as $book)
                                <option
                                    value="{{ $book->id }}"
                                    {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}
                                >
                                    {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">この帳簿で入力する</button>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">
                契約者を入力する帳簿を選択してください。
            </div>
        @elseif ($properties->isEmpty())
            <div class="alert alert-error">
                この帳簿には物件がまだ登録されていません。先に物件を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('properties.create', ['book_id' => $selectedBookId]) }}" class="button">物件を登録する</a>
            </div>
        @else
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
                <p class="muted">
                    今回は契約者基本情報と賃貸条件の中心項目を先に実装します。
                    入金項目、入金口座、賃貸仕訳への連動は次の段階で追加します。
                </p>
            </div>

            <div class="card">
                <form method="POST" action="{{ route('contract-tenants.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

                    <div class="field field-full" style="margin-bottom: 16px;">
                        <label>選択中の帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    <h3>契約者基本情報</h3>

                    <div class="form-grid">
                        <div class="field">
                            <label for="tenant_code">契約者CODE<span class="required">必須</span></label>
                            <input
                                id="tenant_code"
                                type="text"
                                name="tenant_code"
                                value="{{ old('tenant_code') }}"
                                maxlength="20"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="status">契約者状態<span class="required">必須</span></label>
                            <select id="status" name="status" required>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}" {{ old('status', 'active') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="name">契約者名<span class="required">必須</span></label>
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
                            <label for="short_name">契約者名略称</label>
                            <input
                                id="short_name"
                                type="text"
                                name="short_name"
                                value="{{ old('short_name') }}"
                                maxlength="120"
                            >
                        </div>

                        <div class="field">
                            <label for="name_kana">契約者名カナ</label>
                            <input
                                id="name_kana"
                                type="text"
                                name="name_kana"
                                value="{{ old('name_kana') }}"
                                maxlength="120"
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

                        <div class="field">
                            <label for="mobile">携帯番号</label>
                            <input
                                id="mobile"
                                type="text"
                                name="mobile"
                                value="{{ old('mobile') }}"
                                maxlength="30"
                            >
                        </div>

                        <div class="field">
                            <label for="email">メールアドレス</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                maxlength="255"
                            >
                        </div>

                        <div class="field">
                            <label for="postal_code_1">郵便番号1</label>
                            <input
                                id="postal_code_1"
                                type="text"
                                name="postal_code_1"
                                value="{{ old('postal_code_1') }}"
                                maxlength="3"
                            >
                        </div>

                        <div class="field">
                            <label for="postal_code_2">郵便番号2</label>
                            <input
                                id="postal_code_2"
                                type="text"
                                name="postal_code_2"
                                value="{{ old('postal_code_2') }}"
                                maxlength="4"
                            >
                        </div>

                        <div class="field field-full">
                            <label for="address">住所</label>
                            <input
                                id="address"
                                type="text"
                                name="address"
                                value="{{ old('address') }}"
                                maxlength="255"
                            >
                        </div>

                        <div class="field">
                            <label for="emergency_contact_name">緊急連絡先名</label>
                            <input
                                id="emergency_contact_name"
                                type="text"
                                name="emergency_contact_name"
                                value="{{ old('emergency_contact_name') }}"
                                maxlength="120"
                            >
                        </div>

                        <div class="field">
                            <label for="emergency_contact_phone">緊急連絡先電話番号</label>
                            <input
                                id="emergency_contact_phone"
                                type="text"
                                name="emergency_contact_phone"
                                value="{{ old('emergency_contact_phone') }}"
                                maxlength="30"
                            >
                        </div>

                        <div class="field">
                            <label for="sort_order">並び順</label>
                            <input
                                id="sort_order"
                                type="number"
                                name="sort_order"
                                value="{{ old('sort_order', 0) }}"
                                min="0"
                                max="999999"
                            >
                        </div>

                        <div class="field field-full">
                            <label for="tenant_note">契約者備考</label>
                            <textarea id="tenant_note" name="tenant_note">{{ old('tenant_note') }}</textarea>
                        </div>

                        <div class="field field-full">
                            <label>契約者状態</label>
                            <div class="checkbox-wrap">
                                <input type="hidden" name="tenant_is_active" value="0">
                                <input
                                    id="tenant_is_active"
                                    type="checkbox"
                                    name="tenant_is_active"
                                    value="1"
                                    {{ old('tenant_is_active', '1') === '1' ? 'checked' : '' }}
                                >
                                <label for="tenant_is_active">有効</label>
                            </div>
                        </div>
                    </div>

                    <h3 style="margin-top: 32px;">賃貸条件</h3>

                    <div class="form-grid">
                        <div class="field">
                            <label for="property_id">物件<span class="required">必須</span></label>
                            <select id="property_id" name="property_id" required>
                                <option value="">選択してください</option>
                                @foreach ($properties as $property)
                                    <option
                                        value="{{ $property->id }}"
                                        {{ (string) old('property_id') === (string) $property->id ? 'selected' : '' }}
                                    >
                                        {{ $property->property_code . ' / ' . $property->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="property_unit_id">部屋・区画</label>
                            <select id="property_unit_id" name="property_unit_id">
                                <option value="">選択しない</option>
                                @foreach ($propertyUnits as $propertyUnit)
                                    <option
                                        value="{{ $propertyUnit->id }}"
                                        {{ (string) old('property_unit_id') === (string) $propertyUnit->id ? 'selected' : '' }}
                                    >
                                        {{ ($propertyUnit->property?->property_code ?? '') . ' / ' . $propertyUnit->unit_no }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="contract_no">契約番号</label>
                            <input
                                id="contract_no"
                                type="text"
                                name="contract_no"
                                value="{{ old('contract_no') }}"
                                maxlength="30"
                            >
                        </div>

                        <div class="field">
                            <label for="contract_status">契約状態<span class="required">必須</span></label>
                            <select id="contract_status" name="contract_status" required>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}" {{ old('contract_status', 'active') === $value ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="contract_started_on">契約開始日</label>
                            <input id="contract_started_on" type="date" name="contract_started_on" value="{{ old('contract_started_on') }}">
                        </div>

                        <div class="field">
                            <label for="contract_ended_on">契約終了日</label>
                            <input id="contract_ended_on" type="date" name="contract_ended_on" value="{{ old('contract_ended_on') }}">
                        </div>

                        <div class="field">
                            <label for="move_in_on">入居日</label>
                            <input id="move_in_on" type="date" name="move_in_on" value="{{ old('move_in_on') }}">
                        </div>

                        <div class="field">
                            <label for="move_out_on">退去日</label>
                            <input id="move_out_on" type="date" name="move_out_on" value="{{ old('move_out_on') }}">
                        </div>

                        <div class="field">
                            <label for="rent_amount">賃料</label>
                            <input id="rent_amount" type="number" step="0.01" min="0" name="rent_amount" value="{{ old('rent_amount', 0) }}">
                        </div>

                        <div class="field">
                            <label for="common_service_fee">共益費</label>
                            <input id="common_service_fee" type="number" step="0.01" min="0" name="common_service_fee" value="{{ old('common_service_fee', 0) }}">
                        </div>

                        <div class="field">
                            <label for="parking_fee">駐車料</label>
                            <input id="parking_fee" type="number" step="0.01" min="0" name="parking_fee" value="{{ old('parking_fee', 0) }}">
                        </div>

                        <div class="field">
                            <label for="other_monthly_fee">その他月額</label>
                            <input id="other_monthly_fee" type="number" step="0.01" min="0" name="other_monthly_fee" value="{{ old('other_monthly_fee', 0) }}">
                        </div>

                        <div class="field">
                            <label for="deposit_amount">敷金</label>
                            <input id="deposit_amount" type="number" step="0.01" min="0" name="deposit_amount" value="{{ old('deposit_amount', 0) }}">
                        </div>

                        <div class="field">
                            <label for="key_money_amount">礼金</label>
                            <input id="key_money_amount" type="number" step="0.01" min="0" name="key_money_amount" value="{{ old('key_money_amount', 0) }}">
                        </div>

                        <div class="field">
                            <label for="guarantee_deposit_amount">保証金</label>
                            <input id="guarantee_deposit_amount" type="number" step="0.01" min="0" name="guarantee_deposit_amount" value="{{ old('guarantee_deposit_amount', 0) }}">
                        </div>

                        <div class="field">
                            <label for="payment_due_day">入金予定日</label>
                            <input id="payment_due_day" type="number" name="payment_due_day" value="{{ old('payment_due_day') }}" min="1" max="31">
                        </div>

                        <div class="field">
                            <label for="payment_method">入金方法</label>
                            <input id="payment_method" type="text" name="payment_method" value="{{ old('payment_method') }}" maxlength="50">
                        </div>

                        <div class="field field-full">
                            <label for="contract_note">賃貸条件備考</label>
                            <textarea id="contract_note" name="contract_note">{{ old('contract_note') }}</textarea>
                        </div>

                        <div class="field field-full">
                            <label>賃貸条件状態</label>
                            <div class="checkbox-wrap">
                                <input type="hidden" name="contract_is_active" value="0">
                                <input
                                    id="contract_is_active"
                                    type="checkbox"
                                    name="contract_is_active"
                                    value="1"
                                    {{ old('contract_is_active', '1') === '1' ? 'checked' : '' }}
                                >
                                <label for="contract_is_active">有効</label>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 24px;">
                        <button type="submit" class="button">登録する</button>
                        <a href="{{ route('contract-tenants.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        @endif
    @endif
@endsection