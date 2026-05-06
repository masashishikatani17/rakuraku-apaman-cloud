@extends('layouts.app')

@section('title', '所有者登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">所有者登録</h2>
            <p class="page-description">帳簿ごとに所有者を登録します。</p>
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
            <a href="{{ route('property-owners.index') }}" class="button button-secondary">所有者一覧へ戻る</a>
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
                Access では MT_所有者マスター に「所有者CODE」「区分」「所有者名」「所有者名略称」「青色申告控除」を保存しています。
                まだ区分の表示名は確定していないため、今回はコードのまま保持します。
            </p>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('property-owners.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field field-full">
                        <label for="book_id">帳簿<span class="required">必須</span></label>
                        <select id="book_id" name="book_id" required>
                            <option value="">選択してください</option>
                            @foreach ($books as $book)
                                <option
                                    value="{{ $book->id }}"
                                    {{ (string) old('book_id', $selectedBookId) === (string) $book->id ? 'selected' : '' }}
                                >
                                    {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="owner_code">所有者CODE<span class="required">必須</span></label>
                        <input
                            id="owner_code"
                            type="number"
                            name="owner_code"
                            value="{{ old('owner_code') }}"
                            min="1"
                            max="9999"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="classification_code">区分コード</label>
                        <input
                            id="classification_code"
                            type="number"
                            name="classification_code"
                            value="{{ old('classification_code') }}"
                            min="0"
                            max="99"
                        >
                    </div>

                    <div class="field">
                        <label for="name">所有者名<span class="required">必須</span></label>
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
                        <label for="short_name">所有者名略称</label>
                        <input
                            id="short_name"
                            type="text"
                            name="short_name"
                            value="{{ old('short_name') }}"
                            maxlength="120"
                        >
                    </div>

                    <div class="field">
                        <label for="blue_return_deduction_code">青色申告控除コード</label>
                        <input
                            id="blue_return_deduction_code"
                            type="number"
                            name="blue_return_deduction_code"
                            value="{{ old('blue_return_deduction_code') }}"
                            min="0"
                            max="99"
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
                    <a href="{{ route('property-owners.index') }}" class="button button-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    @endif
@endsection