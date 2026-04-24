@extends('layouts.app')

@section('title', '帳簿登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">帳簿登録</h2>
            <p class="page-description">帳簿本体と帳簿設定をまとめて登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
            <a href="{{ route('business-owners.index') }}" class="button button-secondary">事業主一覧へ戻る</a>
        </div>
    </div>

    @if ($businessOwners->isEmpty())
        <div class="alert alert-error">
            事業主がまだ登録されていません。先に事業主を登録してください。
        </div>

        <div class="actions">
            <a href="{{ route('business-owners.create') }}" class="button">事業主を登録する</a>
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
            <form method="POST" action="{{ route('books.store') }}">
                @csrf

                <div class="form-grid">
                    <div class="field">
                        <label for="business_owner_id">事業主<span class="required">必須</span></label>
                        <select id="business_owner_id" name="business_owner_id" required>
                            <option value="">選択してください</option>
                            @foreach ($businessOwners as $businessOwner)
                                <option
                                    value="{{ $businessOwner->id }}"
                                    {{ (string) old('business_owner_id', $selectedBusinessOwnerId) === (string) $businessOwner->id ? 'selected' : '' }}
                                >
                                    {{ $businessOwner->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="book_code">帳簿コード</label>
                        <input
                            id="book_code"
                            type="text"
                            name="book_code"
                            value="{{ old('book_code') }}"
                            maxlength="20"
                        >
                    </div>

                    <div class="field field-full">
                        <label for="name">帳簿名<span class="required">必須</span></label>
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
                        <label for="period_start_date">会計期間開始日<span class="required">必須</span></label>
                        <input
                            id="period_start_date"
                            type="date"
                            name="period_start_date"
                            value="{{ old('period_start_date') }}"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="period_end_date">会計期間終了日<span class="required">必須</span></label>
                        <input
                            id="period_end_date"
                            type="date"
                            name="period_end_date"
                            value="{{ old('period_end_date') }}"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="status">状態<span class="required">必須</span></label>
                        <select id="status" name="status" required>
                            <option value="draft" {{ old('status', 'draft') === 'draft' ? 'selected' : '' }}>下書き</option>
                            <option value="open" {{ old('status') === 'open' ? 'selected' : '' }}>運用中</option>
                            <option value="closed" {{ old('status') === 'closed' ? 'selected' : '' }}>締了</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="closing_month">決算月</label>
                        <select id="closing_month" name="closing_month">
                            <option value="">選択してください</option>
                            @for ($month = 1; $month <= 12; $month++)
                                <option
                                    value="{{ $month }}"
                                    {{ (string) old('closing_month') === (string) $month ? 'selected' : '' }}
                                >
                                    {{ $month }}月
                                </option>
                            @endfor
                        </select>
                    </div>

                    <div class="field">
                        <label for="migration_source">移行元</label>
                        <input
                            id="migration_source"
                            type="text"
                            name="migration_source"
                            value="{{ old('migration_source', 'access') }}"
                            maxlength="30"
                        >
                    </div>

                    <div class="field">
                        <label for="db_version">DBバージョン</label>
                        <input
                            id="db_version"
                            type="text"
                            name="db_version"
                            value="{{ old('db_version') }}"
                            maxlength="30"
                        >
                    </div>

                    <div class="field">
                        <label for="accounting_method">会計方式<span class="required">必須</span></label>
                        <select id="accounting_method" name="accounting_method" required>
                            <option value="double_entry" {{ old('accounting_method', 'double_entry') === 'double_entry' ? 'selected' : '' }}>複式簿記</option>
                            <option value="single_entry" {{ old('accounting_method') === 'single_entry' ? 'selected' : '' }}>単式簿記</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="tax_processing_method">税処理方法</label>
                        <select id="tax_processing_method" name="tax_processing_method">
                            <option value="">未設定</option>
                            <option value="inclusive" {{ old('tax_processing_method') === 'inclusive' ? 'selected' : '' }}>税込</option>
                            <option value="exclusive" {{ old('tax_processing_method') === 'exclusive' ? 'selected' : '' }}>税抜</option>
                            <option value="separate" {{ old('tax_processing_method') === 'separate' ? 'selected' : '' }}>別処理</option>
                        </select>
                    </div>

                    <div class="field">
                        <label for="rounding_mode">端数処理<span class="required">必須</span></label>
                        <select id="rounding_mode" name="rounding_mode" required>
                            <option value="round" {{ old('rounding_mode', 'round') === 'round' ? 'selected' : '' }}>四捨五入</option>
                            <option value="floor" {{ old('rounding_mode') === 'floor' ? 'selected' : '' }}>切り捨て</option>
                            <option value="ceil" {{ old('rounding_mode') === 'ceil' ? 'selected' : '' }}>切り上げ</option>
                        </select>
                    </div>

                    <div class="field field-full">
                        <label for="memo">帳簿メモ</label>
                        <textarea id="memo" name="memo">{{ old('memo') }}</textarea>
                    </div>

                    <div class="field field-full">
                        <label for="notes">帳簿設定メモ</label>
                        <textarea id="notes" name="notes">{{ old('notes') }}</textarea>
                    </div>

                    <div class="field field-full">
                        <label>帳簿の状態</label>
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

                    <div class="field field-full">
                        <label>帳簿設定</label>
                        <div class="checkbox-wrap">
                            <input type="hidden" name="is_department_enabled" value="0">
                            <input
                                id="is_department_enabled"
                                type="checkbox"
                                name="is_department_enabled"
                                value="1"
                                {{ old('is_department_enabled', '0') === '1' ? 'checked' : '' }}
                            >
                            <label for="is_department_enabled">部門管理を使う</label>
                        </div>

                        <div class="checkbox-wrap" style="margin-top: 8px;">
                            <input type="hidden" name="is_sub_account_enabled" value="0">
                            <input
                                id="is_sub_account_enabled"
                                type="checkbox"
                                name="is_sub_account_enabled"
                                value="1"
                                {{ old('is_sub_account_enabled', '1') === '1' ? 'checked' : '' }}
                            >
                            <label for="is_sub_account_enabled">補助科目を使う</label>
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top: 24px;">
                    <button type="submit" class="button">登録する</button>
                    <a href="{{ route('books.index') }}" class="button button-secondary">キャンセル</a>
                </div>
            </form>
        </div>
    @endif
@endsection