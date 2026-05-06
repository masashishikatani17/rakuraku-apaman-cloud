@extends('layouts.app')

@section('title', '補助科目登録')

@section('content')
    @php
        $backBookId = $selectedBookId ?? request('book_id');
        $backAccountTitleId = $selectedAccountTitleId ?? request('account_title_id');

        $subAccountBackParams = array_filter([
            'book_id' => $backBookId,
            'account_title_id' => $backAccountTitleId,
        ], fn ($value) => $value !== null && $value !== '');

        $accountTitleBackParams = array_filter([
            'book_id' => $backBookId,
        ], fn ($value) => $value !== null && $value !== '');
    @endphp
    <div class="page-header">
        <div>
            <h2 class="page-title">補助科目登録</h2>
            <p class="page-description">補助科目を使用する勘定科目に対して補助科目を登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('sub-account-titles.index', $subAccountBackParams) }}" class="button button-secondary">補助科目一覧へ戻る</a>
            <a href="{{ route('account-titles.index', $accountTitleBackParams) }}" class="button button-secondary">勘定科目一覧へ戻る</a>
        </div>
    </div>

    @if ($accountTitles->isEmpty())
        <div class="alert alert-error">
            補助科目を登録できる勘定科目がありません。先に「補助科目を使用する」が ON の勘定科目を登録してください。
        </div>

        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('account-titles.create', ['book_id' => $selectedBookId]) : route('account-titles.create') }}"
                class="button"
            >
                勘定科目を登録する
            </a>
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
            <form method="POST" action="{{ route('sub-account-titles.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field field-full">
                        <label for="account_title_id">勘定科目<span class="required">必須</span></label>
                        <select id="account_title_id" name="account_title_id" required>
                            <option value="">選択してください</option>
                            @foreach ($accountTitles as $accountTitle)
                                <option
                                    value="{{ $accountTitle->id }}"
                                    {{ (string) old('account_title_id', $selectedAccountTitleId) === (string) $accountTitle->id ? 'selected' : '' }}
                                >
                                    {{ ($accountTitle->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($accountTitle->book?->name ?? '帳簿未設定') . ' / ' . $accountTitle->account_code . ' ' . $accountTitle->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="sub_account_code">補助科目コード<span class="required">必須</span></label>
                        <input
                            id="sub_account_code"
                            type="text"
                            name="sub_account_code"
                            value="{{ old('sub_account_code') }}"
                            maxlength="20"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="name">補助科目名<span class="required">必須</span></label>
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
                    <a href="{{ route('sub-account-titles.index', $subAccountBackParams) }}" class="button button-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    @endif
@endsection