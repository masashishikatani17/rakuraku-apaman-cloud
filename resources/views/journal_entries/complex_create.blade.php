@extends('layouts.app')

@section('title', '複合仕訳登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">複合仕訳登録</h2>
            <p class="page-description">借方・貸方を複数行で入力します。借方合計と貸方合計が一致する必要があります。</p>
        </div>
        <div class="actions">
            <a href="{{ route('journal-entries.create', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">通常仕訳入力へ</a>
            <a href="{{ route('journal-entries.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">仕訳一覧へ戻る</a>
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
            <form method="GET" action="{{ route('journal-entries.complex.create') }}">
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
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

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
                <form method="POST" action="{{ route('journal-entries.complex.store') }}">
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
                                value="{{ old('entry_date', $defaultEntryDate ?? now()->format('Y-m-d')) }}"
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

                    @php
                        $lineGroups = [
                            'debit_lines' => ['title' => '借方', 'count' => $debitRowCount ?? 5],
                            'credit_lines' => ['title' => '貸方', 'count' => $creditRowCount ?? 5],
                        ];
                    @endphp

                    @foreach ($lineGroups as $groupName => $group)
                        <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                            <h3 style="margin-top: 0;">{{ $group['title'] }}</h3>

                            <div style="overflow-x: auto;">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 220px;">勘定科目</th>
                                            <th style="min-width: 220px;">補助科目</th>
                                            <th style="min-width: 180px;">部門</th>
                                            <th style="min-width: 220px;">物件</th>
                                            <th style="min-width: 140px;">金額</th>
                                            <th style="min-width: 240px;">行備考</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for ($i = 0; $i < $group['count']; $i++)
                                            <tr>
                                                <td>
                                                    <select name="{{ $groupName }}[{{ $i }}][account_title_id]">
                                                        <option value="">選択してください</option>
                                                        @foreach ($accountTitles as $accountTitle)
                                                            <option
                                                                value="{{ $accountTitle->id }}"
                                                                {{ (string) old($groupName . '.' . $i . '.account_title_id') === (string) $accountTitle->id ? 'selected' : '' }}
                                                            >
                                                                {{ $accountTitle->account_code . ' / ' . $accountTitle->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="{{ $groupName }}[{{ $i }}][sub_account_title_id]">
                                                        <option value="">選択しない</option>
                                                        @foreach ($subAccountTitles as $subAccountTitle)
                                                            <option
                                                                value="{{ $subAccountTitle->id }}"
                                                                {{ (string) old($groupName . '.' . $i . '.sub_account_title_id') === (string) $subAccountTitle->id ? 'selected' : '' }}
                                                            >
                                                                {{ ($subAccountTitle->accountTitle?->account_code ?? '') . ' / ' . $subAccountTitle->sub_account_code . ' / ' . $subAccountTitle->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="{{ $groupName }}[{{ $i }}][department_id]">
                                                        <option value="">選択しない</option>
                                                        @foreach ($departments as $department)
                                                            <option
                                                                value="{{ $department->id }}"
                                                                {{ (string) old($groupName . '.' . $i . '.department_id') === (string) $department->id ? 'selected' : '' }}
                                                            >
                                                                {{ $department->department_code . ' / ' . $department->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="{{ $groupName }}[{{ $i }}][property_id]">
                                                        <option value="">選択しない</option>
                                                        @foreach ($properties as $property)
                                                            <option
                                                                value="{{ $property->id }}"
                                                                {{ (string) old($groupName . '.' . $i . '.property_id') === (string) $property->id ? 'selected' : '' }}
                                                            >
                                                                {{ $property->property_code . ' / ' . $property->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        name="{{ $groupName }}[{{ $i }}][amount]"
                                                        value="{{ old($groupName . '.' . $i . '.amount') }}"
                                                        min="0"
                                                        step="0.01"
                                                    >
                                                </td>
                                                <td>
                                                    <input
                                                        type="text"
                                                        name="{{ $groupName }}[{{ $i }}][line_note]"
                                                        value="{{ old($groupName . '.' . $i . '.line_note') }}"
                                                        maxlength="255"
                                                    >
                                                </td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach

                    <div class="field field-full" style="margin-top: 24px;">
                        <label>連続入力</label>
                        <div class="checkbox-wrap">
                            <input type="hidden" name="continue_input" value="0">
                            <input
                                id="continue_input"
                                type="checkbox"
                                name="continue_input"
                                value="1"
                                {{ old('continue_input', '0') === '1' ? 'checked' : '' }}
                            >
                            <label for="continue_input">登録後、同じ帳簿・同じ日付で続けて入力する</label>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 24px;">
                        <button type="submit" class="button">複合仕訳を登録する</button>
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