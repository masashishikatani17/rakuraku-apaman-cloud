@if ($books->isEmpty())
    <div class="alert alert-error">
        帳簿がまだ登録されていません。先に帳簿を登録してください。
    </div>

    <div class="actions">
        <a href="{{ route('books.create') }}" class="button">帳簿を登録する</a>
    </div>
@elseif ($selectedBook === null)
    <div class="alert alert-error">
        テンプレートを登録する帳簿を選択してください。
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
        <form method="POST" action="{{ $formAction }}">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

            <div class="field field-full" style="margin-bottom: 16px;">
                <label>対象の帳簿</label>
                <div class="muted">
                    {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label for="template_code">テンプレートコード<span class="required">必須</span></label>
                    <input
                        id="template_code"
                        type="text"
                        name="template_code"
                        value="{{ old('template_code', $template?->template_code) }}"
                        maxlength="30"
                        required
                    >
                </div>

                <div class="field">
                    <label for="name">テンプレート名<span class="required">必須</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name', $template?->name) }}"
                        maxlength="120"
                        required
                    >
                </div>

                <div class="field field-full">
                    <label for="description_text">摘要文<span class="required">必須</span></label>
                    <input
                        id="description_text"
                        type="text"
                        name="description_text"
                        value="{{ old('description_text', $template?->description_text) }}"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="field">
                    <label for="sort_order">並び順</label>
                    <input
                        id="sort_order"
                        type="number"
                        name="sort_order"
                        value="{{ old('sort_order', $template?->sort_order ?? 0) }}"
                        min="0"
                        max="999999"
                    >
                </div>

                <div class="field">
                    <label>状態</label>
                    <div class="checkbox-wrap">
                        <input type="hidden" name="is_active" value="0">
                        <input
                            id="is_active"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            {{ old('is_active', $template?->is_active ?? true) ? 'checked' : '' }}
                        >
                        <label for="is_active">有効</label>
                    </div>
                </div>

                <div class="field field-full">
                    <label for="note">備考</label>
                    <textarea id="note" name="note">{{ old('note', $template?->note) }}</textarea>
                </div>
            </div>

            @php
                $lineGroups = [
                    'debit_lines' => ['title' => '借方テンプレート', 'count' => $debitRowCount ?? 5, 'rows' => $debitLines ?? []],
                    'credit_lines' => ['title' => '貸方テンプレート', 'count' => $creditRowCount ?? 5, 'rows' => $creditLines ?? []],
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
                                    <th style="min-width: 140px;">標準金額</th>
                                    <th style="min-width: 240px;">行備考</th>
                                </tr>
                            </thead>
                            <tbody>
                                @for ($i = 0; $i < $group['count']; $i++)
                                    @php
                                        $line = $group['rows'][$i] ?? [];
                                    @endphp
                                    <tr>
                                        <td>
                                            <select name="{{ $groupName }}[{{ $i }}][account_title_id]">
                                                <option value="">選択してください</option>
                                                @foreach ($accountTitles as $accountTitle)
                                                    <option
                                                        value="{{ $accountTitle->id }}"
                                                        {{ (string) old($groupName . '.' . $i . '.account_title_id', $line['account_title_id'] ?? '') === (string) $accountTitle->id ? 'selected' : '' }}
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
                                                        {{ (string) old($groupName . '.' . $i . '.sub_account_title_id', $line['sub_account_title_id'] ?? '') === (string) $subAccountTitle->id ? 'selected' : '' }}
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
                                                        {{ (string) old($groupName . '.' . $i . '.department_id', $line['department_id'] ?? '') === (string) $department->id ? 'selected' : '' }}
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
                                                        {{ (string) old($groupName . '.' . $i . '.property_id', $line['property_id'] ?? '') === (string) $property->id ? 'selected' : '' }}
                                                    >
                                                        {{ $property->property_code . ' / ' . $property->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                name="{{ $groupName }}[{{ $i }}][default_amount]"
                                                value="{{ old($groupName . '.' . $i . '.default_amount', $line['default_amount'] ?? '') }}"
                                                min="0"
                                                step="0.01"
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="text"
                                                name="{{ $groupName }}[{{ $i }}][line_note]"
                                                value="{{ old($groupName . '.' . $i . '.line_note', $line['line_note'] ?? '') }}"
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

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">{{ $submitLabel }}</button>
                <a href="{{ route('journal-entry-templates.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">
                    キャンセル
                </a>
            </div>
        </form>
    </div>
@endif