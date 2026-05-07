@extends('layouts.app')

@section('title', '部屋・区画修正')

@section('content')
    @php
        $unitTypeOptions = [
            'room' => '部屋',
            'parking' => '駐車場',
            'other' => 'その他',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">部屋・区画修正</h2>
            <p class="page-description">登録済の部屋・区画を修正します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('rental-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                賃貸管理メニューへ戻る
            </a>
            <a
                href="{{ route('property-units.index', ['book_id' => $selectedBookId, 'property_id' => $selectedPropertyId]) }}"
                class="button button-secondary"
            >
                部屋・区画一覧へ戻る
            </a>
            <a href="{{ route('properties.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">物件一覧へ戻る</a>
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
            今回は部屋番号・面積・間取り・駐車場区分・解約日など、構造項目を先に実装します。
            賃貸条件の細かな列は、契約・賃貸条件の段階で別に持つ形にします。
        </p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('property-units.update', $propertyUnit) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

            <div class="field field-full" style="margin-bottom: 16px;">
                <label>対象の物件</label>
                <div class="muted">
                    {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . ($selectedBook->name ?? '帳簿未設定') }}
                    <br>
                    {{ ($selectedProperty?->property_code ?? '物件未設定') . ' / ' . ($selectedProperty?->name ?? '—') }}
                </div>
            </div>

            <div class="form-grid">
                <div class="field field-full">
                    <label for="property_id">物件<span class="required">必須</span></label>
                    <select id="property_id" name="property_id" required>
                        @foreach ($properties as $property)
                            <option
                                value="{{ $property->id }}"
                                {{ (string) old('property_id', $propertyUnit->property_id) === (string) $property->id ? 'selected' : '' }}
                            >
                                {{ $property->property_code . ' / ' . $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="unit_no">部屋番号 / 区画番号<span class="required">必須</span></label>
                    <input
                        id="unit_no"
                        type="text"
                        name="unit_no"
                        value="{{ old('unit_no', $propertyUnit->unit_no) }}"
                        maxlength="50"
                        required
                    >
                </div>

                <div class="field">
                    <label for="unit_type">種別<span class="required">必須</span></label>
                    <select id="unit_type" name="unit_type" required>
                        @foreach ($unitTypeOptions as $value => $label)
                            <option
                                value="{{ $value }}"
                                {{ old('unit_type', $propertyUnit->unit_type) === $value ? 'selected' : '' }}
                            >
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="area_sqm">面積</label>
                    <input
                        id="area_sqm"
                        type="number"
                        step="0.01"
                        min="0"
                        name="area_sqm"
                        value="{{ old('area_sqm', $propertyUnit->area_sqm) }}"
                    >
                </div>

                <div class="field">
                    <label for="layout_code">間取りCODE</label>
                    <input
                        id="layout_code"
                        type="text"
                        name="layout_code"
                        value="{{ old('layout_code', $propertyUnit->layout_code) }}"
                        maxlength="20"
                    >
                </div>

                <div class="field">
                    <label for="parking_category_code">駐車場区分コード</label>
                    <input
                        id="parking_category_code"
                        type="text"
                        name="parking_category_code"
                        value="{{ old('parking_category_code', $propertyUnit->parking_category_code) }}"
                        maxlength="20"
                    >
                </div>

                <div class="field">
                    <label for="ended_at">解約日</label>
                    <input
                        id="ended_at"
                        type="date"
                        name="ended_at"
                        value="{{ old('ended_at', $propertyUnit->ended_at?->format('Y-m-d')) }}"
                    >
                </div>

                <div class="field">
                    <label for="sort_order">並び順</label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="{{ old('sort_order', $propertyUnit->sort_order) }}"
                        min="0"
                        max="999999"
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $propertyUnit->note) }}</textarea>
                </div>

                <div class="field field-full">
                    <label>新規登録フラグ</label>
                    <div class="checkbox-wrap">
                        <input type="hidden" name="is_new_registration" value="0">
                        <input
                            id="is_new_registration"
                            type="checkbox"
                            name="is_new_registration"
                            value="1"
                            {{ (string) old('is_new_registration', $propertyUnit->is_new_registration ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_new_registration">新規登録として扱う</label>
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
                            {{ (string) old('is_active', $propertyUnit->is_active ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">更新する</button>
                <a
                    href="{{ route('property-units.index', ['book_id' => $selectedBookId, 'property_id' => $selectedPropertyId]) }}"
                    class="button button-secondary"
                >
                    キャンセル
                </a>
            </div>
        </form>
    </div>
@endsection