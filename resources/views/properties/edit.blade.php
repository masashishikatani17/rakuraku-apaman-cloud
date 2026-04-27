@extends('layouts.app')

@section('title', '物件修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">物件修正</h2>
            <p class="page-description">登録済の物件を修正します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('properties.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">物件一覧へ戻る</a>
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

    <div class="card" style="margin-bottom: 16px;">
        <p class="muted">
            今回は MT_物件マスター の中心項目を先に実装します。共同所有者の持分、部屋詳細、修繕履歴は次の段階で追加します。
        </p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('properties.update', $property) }}">
            @csrf
            @method('PUT')

            <div class="field field-full" style="margin-bottom: 16px;">
                <label>対象の帳簿</label>
                <div class="muted">
                    {{ ($selectedBook?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($selectedBook?->name ?? '帳簿未設定') }}
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="property_category_id">物件区分<span class="required">必須</span></label>
                    <select id="property_category_id" name="property_category_id" required>
                        @foreach ($propertyCategories as $propertyCategory)
                            <option
                                value="{{ $propertyCategory->id }}"
                                {{ (string) old('property_category_id', $property->property_category_id) === (string) $propertyCategory->id ? 'selected' : '' }}
                            >
                                {{ $propertyCategory->category_code . ' / ' . $propertyCategory->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="property_code">物件CODE<span class="required">必須</span></label>
                    <input
                        id="property_code"
                        type="text"
                        name="property_code"
                        value="{{ old('property_code', $property->property_code) }}"
                        maxlength="20"
                        required
                    >
                </div>

                <div class="field">
                    <label for="name">物件名<span class="required">必須</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name', $property->name) }}"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field">
                    <label for="short_name">物件名略称</label>
                    <input
                        id="short_name"
                        type="text"
                        name="short_name"
                        value="{{ old('short_name', $property->short_name) }}"
                        maxlength="120"
                    >
                </div>

                <div class="field">
                    <label for="name_reading">物件名ヨミ</label>
                    <input
                        id="name_reading"
                        type="text"
                        name="name_reading"
                        value="{{ old('name_reading', $property->name_reading) }}"
                        maxlength="120"
                    >
                </div>

                <div class="field">
                    <label for="postal_code_1">郵便番号1</label>
                    <input
                        id="postal_code_1"
                        type="text"
                        name="postal_code_1"
                        value="{{ old('postal_code_1', $property->postal_code_1) }}"
                        maxlength="3"
                    >
                </div>

                <div class="field">
                    <label for="postal_code_2">郵便番号2</label>
                    <input
                        id="postal_code_2"
                        type="text"
                        name="postal_code_2"
                        value="{{ old('postal_code_2', $property->postal_code_2) }}"
                        maxlength="4"
                    >
                </div>

                <div class="field field-full">
                    <label for="address">所在地</label>
                    <input
                        id="address"
                        type="text"
                        name="address"
                        value="{{ old('address', $property->address) }}"
                        maxlength="255"
                    >
                </div>

                <div class="field">
                    <label for="primary_owner_id">所有者<span class="required">必須</span></label>
                    <select id="primary_owner_id" name="primary_owner_id" required>
                        @foreach ($propertyOwners as $propertyOwner)
                            <option
                                value="{{ $propertyOwner->id }}"
                                {{ (string) old('primary_owner_id', $property->primary_owner_id) === (string) $propertyOwner->id ? 'selected' : '' }}
                            >
                                {{ $propertyOwner->owner_code . ' / ' . $propertyOwner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="representative_owner_id">代表者</label>
                    <select id="representative_owner_id" name="representative_owner_id">
                        <option value="">選択しない</option>
                        @foreach ($propertyOwners as $propertyOwner)
                            <option
                                value="{{ $propertyOwner->id }}"
                                {{ (string) old('representative_owner_id', $property->representative_owner_id) === (string) $propertyOwner->id ? 'selected' : '' }}
                            >
                                {{ $propertyOwner->owner_code . ' / ' . $propertyOwner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="ownership_form">所有形態</label>
                    <input
                        id="ownership_form"
                        type="text"
                        name="ownership_form"
                        value="{{ old('ownership_form', $property->ownership_form) }}"
                        maxlength="50"
                    >
                </div>

                <div class="field">
                    <label for="right_form">権利形態</label>
                    <input
                        id="right_form"
                        type="text"
                        name="right_form"
                        value="{{ old('right_form', $property->right_form) }}"
                        maxlength="50"
                    >
                </div>

                <div class="field">
                    <label for="built_at">築年月日</label>
                    <input
                        id="built_at"
                        type="date"
                        name="built_at"
                        value="{{ old('built_at', $property->built_at?->format('Y-m-d')) }}"
                    >
                </div>

                <div class="field">
                    <label for="structure">建物構造</label>
                    <input
                        id="structure"
                        type="text"
                        name="structure"
                        value="{{ old('structure', $property->structure) }}"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="floors">階数</label>
                    <input
                        id="floors"
                        type="text"
                        name="floors"
                        value="{{ old('floors', $property->floors) }}"
                        maxlength="50"
                    >
                </div>

                <div class="field">
                    <label for="layout_summary">間取り等</label>
                    <input
                        id="layout_summary"
                        type="text"
                        name="layout_summary"
                        value="{{ old('layout_summary', $property->layout_summary) }}"
                        maxlength="100"
                    >
                </div>

                <div class="field">
                    <label for="building_area_sqm">建物面積平米</label>
                    <input
                        id="building_area_sqm"
                        type="number"
                        step="0.01"
                        min="0"
                        name="building_area_sqm"
                        value="{{ old('building_area_sqm', $property->building_area_sqm) }}"
                    >
                </div>

                <div class="field">
                    <label for="land_area_sqm">土地面積平米</label>
                    <input
                        id="land_area_sqm"
                        type="number"
                        step="0.01"
                        min="0"
                        name="land_area_sqm"
                        value="{{ old('land_area_sqm', $property->land_area_sqm) }}"
                    >
                </div>

                <div class="field">
                    <label for="residential_floor_area">床面積住居</label>
                    <input
                        id="residential_floor_area"
                        type="number"
                        step="0.01"
                        min="0"
                        name="residential_floor_area"
                        value="{{ old('residential_floor_area', $property->residential_floor_area) }}"
                    >
                </div>

                <div class="field">
                    <label for="business_floor_area">床面積事業</label>
                    <input
                        id="business_floor_area"
                        type="number"
                        step="0.01"
                        min="0"
                        name="business_floor_area"
                        value="{{ old('business_floor_area', $property->business_floor_area) }}"
                    >
                </div>

                <div class="field">
                    <label for="parking_monthly_indoor">駐車台数月極室内</label>
                    <input
                        id="parking_monthly_indoor"
                        type="number"
                        min="0"
                        name="parking_monthly_indoor"
                        value="{{ old('parking_monthly_indoor', $property->parking_monthly_indoor) }}"
                    >
                </div>

                <div class="field">
                    <label for="parking_monthly_outdoor">駐車台数月極室外</label>
                    <input
                        id="parking_monthly_outdoor"
                        type="number"
                        min="0"
                        name="parking_monthly_outdoor"
                        value="{{ old('parking_monthly_outdoor', $property->parking_monthly_outdoor) }}"
                    >
                </div>

                <div class="field">
                    <label for="parking_hourly">駐車台数時間貸</label>
                    <input
                        id="parking_hourly"
                        type="number"
                        min="0"
                        name="parking_hourly"
                        value="{{ old('parking_hourly', $property->parking_hourly) }}"
                    >
                </div>

                <div class="field">
                    <label for="parking_total">駐車台数合計</label>
                    <input
                        id="parking_total"
                        type="number"
                        min="0"
                        name="parking_total"
                        value="{{ old('parking_total', $property->parking_total) }}"
                    >
                </div>

                <div class="field">
                    <label for="sort_order">並び順</label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="{{ old('sort_order', $property->sort_order) }}"
                        min="0"
                        max="999999"
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $property->note) }}</textarea>
                </div>

                <div class="field field-full">
                    <label for="note2">備考2</label>
                    <textarea id="note2" name="note2">{{ old('note2', $property->note2) }}</textarea>
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
                            {{ (string) old('is_active', $property->is_active ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">更新する</button>
                <a href="{{ route('properties.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection