@extends('layouts.app')

@section('title', '部屋・区画登録')

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
            <h2 class="page-title">部屋・区画登録</h2>
            <p class="page-description">物件の中の部屋・区画を登録します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('rental-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                賃貸管理メニューへ戻る
            </a>
            <a href="{{ route('property-units.index') }}" class="button button-secondary">部屋・区画一覧へ戻る</a>
            <a href="{{ route('properties.index') }}" class="button button-secondary">物件一覧へ戻る</a>
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
            <form method="GET" action="{{ route('property-units.create') }}">
                <div class="form-grid">
                    <div class="field">
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

                    <div class="field">
                        <label for="property_id_selector">入力する物件</label>
                        <select id="property_id_selector" name="property_id">
                            <option value="">選択してください</option>
                            @foreach ($properties as $property)
                                <option
                                    value="{{ $property->id }}"
                                    {{ (string) $selectedPropertyId === (string) $property->id ? 'selected' : '' }}
                                >
                                    {{ $property->property_code . ' / ' . $property->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">この条件で入力する</button>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">
                部屋・区画を入力する帳簿を選択してください。
            </div>
        @elseif ($properties->isEmpty())
            <div class="alert alert-error">
                この帳簿には物件がまだ登録されていません。先に物件を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('properties.create', ['book_id' => $selectedBookId]) }}" class="button">物件を登録する</a>
            </div>
        @elseif ($selectedProperty === null)
            <div class="alert alert-error">
                部屋・区画を入力する物件を選択してください。
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
                    今回は部屋番号・面積・間取り・駐車場区分・解約日など、構造項目を先に実装します。
                    賃貸条件の細かな列は、契約・賃貸条件の段階で別に持つ形にします。
                </p>
            </div>

            <div class="card">
                <form method="POST" action="{{ route('property-units.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                    <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}">

                    <div class="field field-full" style="margin-bottom: 16px;">
                        <label>選択中の物件</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                            <br>
                            {{ $selectedProperty->property_code . ' / ' . $selectedProperty->name }}
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="unit_no">部屋番号 / 区画番号<span class="required">必須</span></label>
                            <input
                                id="unit_no"
                                type="text"
                                name="unit_no"
                                value="{{ old('unit_no') }}"
                                maxlength="50"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="unit_type">種別<span class="required">必須</span></label>
                            <select id="unit_type" name="unit_type" required>
                                @foreach ($unitTypeOptions as $value => $label)
                                    <option value="{{ $value }}" {{ old('unit_type', 'room') === $value ? 'selected' : '' }}>
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
                                value="{{ old('area_sqm') }}"
                            >
                        </div>

                        <div class="field">
                            <label for="layout_code">間取りCODE</label>
                            <input
                                id="layout_code"
                                type="text"
                                name="layout_code"
                                value="{{ old('layout_code') }}"
                                maxlength="20"
                            >
                        </div>

                        <div class="field">
                            <label for="parking_category_code">駐車場区分コード</label>
                            <input
                                id="parking_category_code"
                                type="text"
                                name="parking_category_code"
                                value="{{ old('parking_category_code') }}"
                                maxlength="20"
                            >
                        </div>

                        <div class="field">
                            <label for="ended_at">解約日</label>
                            <input
                                id="ended_at"
                                type="date"
                                name="ended_at"
                                value="{{ old('ended_at') }}"
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
                            <label>新規登録フラグ</label>
                            <div class="checkbox-wrap">
                                <input type="hidden" name="is_new_registration" value="0">
                                <input
                                    id="is_new_registration"
                                    type="checkbox"
                                    name="is_new_registration"
                                    value="1"
                                    {{ old('is_new_registration', '0') === '1' ? 'checked' : '' }}
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
                                    {{ old('is_active', '1') === '1' ? 'checked' : '' }}
                                >
                                <label for="is_active">有効</label>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 24px;">
                        <button type="submit" class="button">登録する</button>
                        <a
                            href="{{ route('property-units.index', ['book_id' => $selectedBookId, 'property_id' => $selectedPropertyId]) }}"
                            class="button button-secondary"
                        >
                            キャンセル
                        </a>
                    </div>
                </form>
            </div>
        @endif
    @endif
@endsection