@extends('layouts.app')

@section('title', '仕訳登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳登録</h2>
            <p class="page-description">借方1行・貸方1行の最小版で仕訳を登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('journal-entries.index') }}" class="button button-secondary">仕訳一覧へ戻る</a>
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
            <form method="GET" action="{{ route('journal-entries.create') }}">
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
                仕訳を入力する帳簿を選択してください。
            </div>
        @elseif ($accountTitles->isEmpty())
            <div class="alert alert-error">
                この帳簿には勘定科目がまだ登録されていません。先に勘定科目を登録してください。
            </div>

            <div class="actions">
                <a href="{{ route('account-titles.create', ['book_id' => $selectedBookId]) }}" class="button">勘定科目を登録する</a>
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
                <form method="POST" action="{{ route('journal-entries.store') }}">
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
                            <label for="entry_date">伝票日付<span class="required">必須</span></label>
                            <input
                                id="entry_date"
                                type="date"
                                name="entry_date"
                                value="{{ old('entry_date', now()->format('Y-m-d')) }}"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="voucher_no">伝票番号</label>
                            <input
                                id="voucher_no"
                                type="text"
                                name="voucher_no"
                                value="{{ old('voucher_no') }}"
                                maxlength="20"
                            >
                        </div>

                        <div class="field field-full">
                            <label for="journal_description_id">登録済摘要</label>
                            <select id="journal_description_id" name="journal_description_id">
                                <option value="">選択しない</option>
                                @foreach ($journalDescriptions as $journalDescription)
                                    <option
                                        value="{{ $journalDescription->id }}"
                                        {{ (string) old('journal_description_id') === (string) $journalDescription->id ? 'selected' : '' }}
                                    >
                                        {{ ($journalDescription->description_code ?: 'コードなし') . ' / ' . $journalDescription->description_text }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field field-full">
                            <label for="description_text">摘要文<span class="required">必須</span></label>
                            <input
                                id="description_text"
                                type="text"
                                name="description_text"
                                value="{{ old('description_text') }}"
                                maxlength="255"
                                placeholder="登録済摘要を使う場合は空でも可"
                            >
                        </div>

                        <div class="field field-full">
                            <label for="note">備考</label>
                            <textarea id="note" name="note">{{ old('note') }}</textarea>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                        <h3 style="margin-top: 0;">借方</h3>
                        <div class="form-grid">
                            <div class="field">
                                <label for="debit_account_title_id">勘定科目<span class="required">必須</span></label>
                                <select id="debit_account_title_id" name="debit_account_title_id" required>
                                    <option value="">選択してください</option>
                                    @foreach ($accountTitles as $accountTitle)
                                        <option
                                            value="{{ $accountTitle->id }}"
                                            {{ (string) old('debit_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}
                                        >
                                            {{ $accountTitle->account_code . ' / ' . $accountTitle->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="debit_sub_account_title_id">補助科目</label>
                                <select id="debit_sub_account_title_id" name="debit_sub_account_title_id">
                                    <option value="">選択しない</option>
                                    @foreach ($subAccountTitles as $subAccountTitle)
                                        <option
                                            value="{{ $subAccountTitle->id }}"
                                            {{ (string) old('debit_sub_account_title_id') === (string) $subAccountTitle->id ? 'selected' : '' }}
                                        >
                                            {{ ($subAccountTitle->accountTitle?->account_code ?? '') . ' / ' . $subAccountTitle->sub_account_code . ' / ' . $subAccountTitle->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="debit_department_id">部門</label>
                                <select id="debit_department_id" name="debit_department_id">
                                    <option value="">選択しない</option>
                                    @foreach ($departments as $department)
                                        <option
                                            value="{{ $department->id }}"
                                            {{ (string) old('debit_department_id') === (string) $department->id ? 'selected' : '' }}
                                        >
                                            {{ $department->department_code . ' / ' . $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="debit_amount">金額<span class="required">必須</span></label>
                                <input
                                    id="debit_amount"
                                    type="number"
                                    name="debit_amount"
                                    value="{{ old('debit_amount') }}"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div class="field field-full">
                                <label for="debit_line_note">行備考</label>
                                <input
                                    id="debit_line_note"
                                    type="text"
                                    name="debit_line_note"
                                    value="{{ old('debit_line_note') }}"
                                    maxlength="255"
                                >
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                        <h3 style="margin-top: 0;">貸方</h3>
                        <div class="form-grid">
                            <div class="field">
                                <label for="credit_account_title_id">勘定科目<span class="required">必須</span></label>
                                <select id="credit_account_title_id" name="credit_account_title_id" required>
                                    <option value="">選択してください</option>
                                    @foreach ($accountTitles as $accountTitle)
                                        <option
                                            value="{{ $accountTitle->id }}"
                                            {{ (string) old('credit_account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}
                                        >
                                            {{ $accountTitle->account_code . ' / ' . $accountTitle->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="credit_sub_account_title_id">補助科目</label>
                                <select id="credit_sub_account_title_id" name="credit_sub_account_title_id">
                                    <option value="">選択しない</option>
                                    @foreach ($subAccountTitles as $subAccountTitle)
                                        <option
                                            value="{{ $subAccountTitle->id }}"
                                            {{ (string) old('credit_sub_account_title_id') === (string) $subAccountTitle->id ? 'selected' : '' }}
                                        >
                                            {{ ($subAccountTitle->accountTitle?->account_code ?? '') . ' / ' . $subAccountTitle->sub_account_code . ' / ' . $subAccountTitle->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="credit_department_id">部門</label>
                                <select id="credit_department_id" name="credit_department_id">
                                    <option value="">選択しない</option>
                                    @foreach ($departments as $department)
                                        <option
                                            value="{{ $department->id }}"
                                            {{ (string) old('credit_department_id') === (string) $department->id ? 'selected' : '' }}
                                        >
                                            {{ $department->department_code . ' / ' . $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="field">
                                <label for="credit_amount">金額<span class="required">必須</span></label>
                                <input
                                    id="credit_amount"
                                    type="number"
                                    name="credit_amount"
                                    value="{{ old('credit_amount') }}"
                                    min="0.01"
                                    step="0.01"
                                    required
                                >
                            </div>

                            <div class="field field-full">
                                <label for="credit_line_note">行備考</label>
                                <input
                                    id="credit_line_note"
                                    type="text"
                                    name="credit_line_note"
                                    value="{{ old('credit_line_note') }}"
                                    maxlength="255"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 24px;">
                        <button type="submit" class="button">登録する</button>
                        <a
                            href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}"
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