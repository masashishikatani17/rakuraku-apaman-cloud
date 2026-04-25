@extends('layouts.app')

@section('title', '部門登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">部門登録</h2>
            <p class="page-description">帳簿ごとに使用する部門を登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('departments.index') }}" class="button button-secondary">部門一覧へ戻る</a>
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

        <div class="card">
            <form method="POST" action="{{ route('departments.store') }}">
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
                        <label for="department_code">部門コード<span class="required">必須</span></label>
                        <input
                            id="department_code"
                            type="text"
                            name="department_code"
                            value="{{ old('department_code') }}"
                            maxlength="20"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="name">部門名<span class="required">必須</span></label>
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
                    <a href="{{ route('departments.index') }}" class="button button-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    @endif
@endsection