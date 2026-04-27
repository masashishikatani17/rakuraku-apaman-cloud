@extends('layouts.app')

@section('title', '物件登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">物件登録</h2>
            <p class="page-description">物件区分・所有者を指定して物件マスタを登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('properties.index') }}" class="button button-secondary">物件一覧へ戻る</a>
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
            <form method="GET" action="{{ route('properties.create') }}">
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
                物件を入力する帳簿を選択してください。
            </div>
        @elseif ($propertyCategories->isEmpty())
            <div class="alert alert-error">
                この帳簿には物件区分がまだ登録されていません。先に物件区分を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('property-categories.create', ['book_id' => $selectedBookId]) }}" class="button">物件区分を登録する</a>
            </div>
        @elseif ($propertyOwners->isEmpty())
            <div class="alert alert-error">
                この帳簿には所有者がまだ登録されていません。先に所有者を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('property-owners.create', ['book_id' => $selectedBookId]) }}" class="button">所有者を登録する</a>
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
                    今回は MT_物件マスター の中心項目を先に実装します。共同所有者の持分、部屋詳細、修繕履歴は次の段階で追加します。
                </p>
            </div>

            <div class="card">
                <form method="POST" action="{{ route('properties.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

                    <div class="field field-full" style="margin-bottom: 16px;">
                        <label>選択中の帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="property_category_id">物件区分<span class="required">必須</span></label>
                            <select id="property_category_id" name="property_category_id" required>
                                <option value="">選択してください</option>
                                @foreach ($propertyCategories as $propertyCategory)
                                    <option
                                        value="{{ $propertyCategory->id }}"
                                        {{ (string) old('property_category_id') === (string) $propertyCategory->id ? 'selected' : '' }}
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
                                value="{{ old('property_code') }}"
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
                                value="{{ old('name') }}"
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
                                value="{{ old('short_name') }}"
                                maxlength="120"
                            >
                        </div>

                        <div class="field">
                            <label for="name_reading">物件名ヨミ</label>
                            <input
                                id="name_reading"
                                type="text"
                                name="name_reading"
                                value="{{ old('name_reading') }}"
                                maxlength="120"
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
                            <label for="address">所在地</label>
                            <input
                                id="address"
                                type="text"
                                name="address"
                                value="{{ old('address') }}"
                                maxlength="255"
                            >
                        </div>

                        <div class="field">
                            <label for="primary_owner_id">所有者<span class="required">必須</span></label>
                            <select id="primary_owner_id" name="primary_owner_id" required>
                                <option value="">選択してください</option>
                                @foreach ($propertyOwners as $propertyOwner)
                                    <option
                                        value="{{ $propertyOwner->id }}"
                                        {{ (string) old('primary_owner_id') === (string) $propertyOwner->id ? 'selected' : '' }}
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
                                        {{ (string) old('representative_owner_id') === (string) $propertyOwner->id ? 'selected' : '' }}
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
                                value="{{ old('ownership_form') }}"
                                maxlength="50"
                            >
                        </div>

                        <div class="field">
                            <label for="right_form">権利形態</label>
                            <input
                                id="right_form"
                                type="text"
                                name="right_form"
                                value="{{ old('right_form') }}"
                                maxlength="50"
                            >
                        </div>

                        <div class="field">
                            <label for="built_at">築年月日</label>
                            <input
                                id="built_at"
                                type="date"
                                name="built_at"
                                value="{{ old('built_at') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="structure">建物構造</label>
                            <input
                                id="structure"
                                type="text"
                                name="structure"
                                value="{{ old('structure') }}"
                                maxlength="100"
                            >
                        </div>

                        <div class="field">
                            <label for="floors">階数</label>
                            <input
                                id="floors"
                                type="text"
                                name="floors"
                                value="{{ old('floors') }}"
                                maxlength="50"
                            >
                        </div>

                        <div class="field">
                            <label for="layout_summary">間取り等</label>
                            <input
                                id="layout_summary"
                                type="text"
                                name="layout_summary"
                                value="{{ old('layout_summary') }}"
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
                                value="{{ old('building_area_sqm') }}"
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
                                value="{{ old('land_area_sqm') }}"
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
                                value="{{ old('residential_floor_area') }}"
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
                                value="{{ old('business_floor_area') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="parking_monthly_indoor">駐車台数月極室内</label>
                            <input
                                id="parking_monthly_indoor"
                                type="number"
                                min="0"
                                name="parking_monthly_indoor"
                                value="{{ old('parking_monthly_indoor') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="parking_monthly_outdoor">駐車台数月極室外</label>
                            <input
                                id="parking_monthly_outdoor"
                                type="number"
                                min="0"
                                name="parking_monthly_outdoor"
                                value="{{ old('parking_monthly_outdoor') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="parking_hourly">駐車台数時間貸</label>
                            <input
                                id="parking_hourly"
                                type="number"
                                min="0"
                                name="parking_hourly"
                                value="{{ old('parking_hourly') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="parking_total">駐車台数合計</label>
                            <input
                                id="parking_total"
                                type="number"
                                min="0"
                                name="parking_total"
                                value="{{ old('parking_total') }}"
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
                            <label for="note">備考</label>
                            <textarea id="note" name="note">{{ old('note') }}</textarea>
                        </div>

                        <div class="field field-full">
                            <label for="note2">備考2</label>
                            <textarea id="note2" name="note2">{{ old('note2') }}</textarea>
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
                        <a href="{{ route('properties.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        @endif
    @endif
@endsection