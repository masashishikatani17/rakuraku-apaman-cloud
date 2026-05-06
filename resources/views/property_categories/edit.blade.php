@extends('layouts.app')

@section('title', '物件区分修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">物件区分修正</h2>
            <p class="page-description">登録済の物件区分を修正します。</p>
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
            <a href="{{ route('property-categories.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">物件区分一覧へ戻る</a>
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
        <form method="POST" action="{{ route('property-categories.update', $propertyCategory) }}">
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
                    <label for="category_code">物件区分CODE<span class="required">必須</span></label>
                    <input
                        id="category_code"
                        type="text"
                        name="category_code"
                        value="{{ old('category_code', $propertyCategory->category_code) }}"
                        maxlength="20"
                        required
                    >
                </div>

                <div class="field">
                    <label for="name">物件区分名<span class="required">必須</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name', $propertyCategory->name) }}"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field">
                    <label for="sort_order">並び順</label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="{{ old('sort_order', $propertyCategory->sort_order) }}"
                        min="0"
                        max="999999"
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $propertyCategory->note) }}</textarea>
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
                            {{ (string) old('is_active', $propertyCategory->is_active ? '1' : '0') === '1' ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">更新する</button>
                <a href="{{ route('property-categories.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection