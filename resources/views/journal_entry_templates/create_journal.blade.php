@extends('layouts.app')

@section('title', 'テンプレートから仕訳作成')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">テンプレートから仕訳作成</h2>
            <p class="page-description">登録済みテンプレートを使って仕訳を作成します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('journal-entry-templates.index', ['book_id' => $template->book_id]) }}" class="button button-secondary">テンプレート一覧へ戻る</a>
            <a href="{{ route('journal-entries.index', ['book_id' => $template->book_id]) }}" class="button button-secondary">仕訳一覧へ</a>
        </div>
    </div>

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

    <div class="card" style="margin-bottom: 16px;">
        <div class="form-grid">
            <div class="field">
                <label>テンプレート</label>
                <div>{{ $template->template_code }} / {{ $template->name }}</div>
            </div>
            <div class="field">
                <label>帳簿</label>
                <div>{{ ($template->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($template->book?->name ?? '帳簿不明') }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('journal-entry-templates.journal.store', $template) }}">
            @csrf

            <div class="form-grid">
                <div class="field">
                    <label for="entry_date">伝票日付<span class="required">必須</span></label>
                    <input
                        id="entry_date"
                        type="date"
                        name="entry_date"
                        value="{{ old('entry_date', $entryDate) }}"
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
                    <label for="description_text">摘要文<span class="required">必須</span></label>
                    <input
                        id="description_text"
                        type="text"
                        name="description_text"
                        value="{{ old('description_text', $template->description_text) }}"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $template->note) }}</textarea>
                </div>
            </div>

            @foreach (['debit' => '借方', 'credit' => '貸方'] as $side => $label)
                <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                    <h3 style="margin-top: 0;">{{ $label }}</h3>

                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>科目</th>
                                <th>補助</th>
                                <th>部門</th>
                                <th>物件</th>
                                <th>金額</th>
                                <th>行備考</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($template->lines->where('side', $side) as $line)
                                <tr>
                                    <td>{{ $line->accountTitle?->account_code }} / {{ $line->accountTitle?->name }}</td>
                                    <td>
                                        @if ($line->subAccountTitle)
                                            {{ $line->subAccountTitle->sub_account_code }} / {{ $line->subAccountTitle->name }}
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line->department)
                                            {{ $line->department->department_code }} / {{ $line->department->name }}
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line->property)
                                            {{ $line->property->property_code }} / {{ $line->property->name }}
                                        @else
                                            <span class="muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <input
                                            type="number"
                                            name="line_amounts[{{ $line->id }}]"
                                            value="{{ old('line_amounts.' . $line->id, $line->default_amount) }}"
                                            min="0"
                                            step="0.01"
                                        >
                                    </td>
                                    <td>
                                        <input
                                            type="text"
                                            name="line_notes[{{ $line->id }}]"
                                            value="{{ old('line_notes.' . $line->id, $line->line_note) }}"
                                            maxlength="255"
                                        >
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">{{ $label }}テンプレート明細がありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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
                    <label for="continue_input">登録後、このテンプレートで続けて入力する</label>
                </div>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">仕訳を作成する</button>
                <a href="{{ route('journal-entry-templates.index', ['book_id' => $template->book_id]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection