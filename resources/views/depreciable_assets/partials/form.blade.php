@php
    $isEdit = $depreciableAsset !== null;
    $method = $isEdit ? 'PUT' : 'POST';
    $action = $isEdit
        ? route('depreciable-assets.update', $depreciableAsset)
        : route('depreciable-assets.store');

    $selectedPropertyId = old('property_id', $depreciableAsset?->property_id);
    $selectedAssetAccountTitleId = old('asset_account_title_id', $depreciableAsset?->asset_account_title_id);
    $selectedAccumulatedDepreciationAccountTitleId = old('accumulated_depreciation_account_title_id', $depreciableAsset?->accumulated_depreciation_account_title_id);
    $selectedDepreciationExpenseAccountTitleId = old('depreciation_expense_account_title_id', $depreciableAsset?->depreciation_expense_account_title_id);
    $selectedDepartmentId = old('department_id', $depreciableAsset?->department_id);
    $selectedStatus = old('status', $depreciableAsset?->status ?? 'active');
@endphp

@if ($errors->any())
    <div class="alert alert-danger">
        <ul style="margin: 0; padding-left: 20px;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}">
    @csrf
    @if ($isEdit)
        @method($method)
        <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">基本情報</h3>

        <div class="form-grid">
            <div class="field">
                <label for="book_id">帳簿<span class="required">必須</span></label>
                @if ($isEdit)
                    <div class="muted">
                        {{ ($selectedBook?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($selectedBook?->name ?? '帳簿未設定') }}
                    </div>
                @else
                    <select id="book_id" name="book_id" required>
                        @foreach ($books as $book)
                            <option
                                value="{{ $book->id }}"
                                {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}
                            >
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                            </option>
                        @endforeach
                    </select>
                    <p class="muted">帳簿を変更すると、この帳簿の物件・科目を読み直します。</p>
                @endif
            </div>

            <div class="field">
                <label for="asset_code">固定資産コード<span class="required">必須</span></label>
                <input
                    id="asset_code"
                    type="text"
                    name="asset_code"
                    value="{{ old('asset_code', $depreciableAsset?->asset_code) }}"
                    maxlength="30"
                    required
                >
            </div>

            <div class="field">
                <label for="name">固定資産名<span class="required">必須</span></label>
                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name', $depreciableAsset?->name) }}"
                    maxlength="120"
                    required
                >
            </div>

            <div class="field">
                <label for="property_id">関連物件</label>
                <select id="property_id" name="property_id">
                    <option value="">未設定</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}" {{ (string) $selectedPropertyId === (string) $property->id ? 'selected' : '' }}>
                            {{ $property->property_code }} / {{ $property->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="department_id">部門</label>
                <select id="department_id" name="department_id">
                    <option value="">未設定</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" {{ (string) $selectedDepartmentId === (string) $department->id ? 'selected' : '' }}>
                            {{ $department->department_code }} / {{ $department->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="status">状態<span class="required">必須</span></label>
                <select id="status" name="status" required>
                    <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>使用中</option>
                    <option value="disposed" {{ $selectedStatus === 'disposed' ? 'selected' : '' }}>除却・売却済</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">償却条件</h3>

        <div class="form-grid">
            <div class="field">
                <label for="acquisition_date">取得日<span class="required">必須</span></label>
                <input
                    id="acquisition_date"
                    type="date"
                    name="acquisition_date"
                    value="{{ old('acquisition_date', $depreciableAsset?->acquisition_date?->format('Y-m-d') ?? $selectedBook?->period_start_date?->format('Y-m-d')) }}"
                    required
                >
            </div>

            <div class="field">
                <label for="depreciation_start_date">償却開始日</label>
                <input
                    id="depreciation_start_date"
                    type="date"
                    name="depreciation_start_date"
                    value="{{ old('depreciation_start_date', $depreciableAsset?->depreciation_start_date?->format('Y-m-d')) }}"
                >
                <p class="muted">未入力の場合は取得日を使います。</p>
            </div>

            <div class="field">
                <label for="acquisition_cost">取得価額<span class="required">必須</span></label>
                <input
                    id="acquisition_cost"
                    type="number"
                    step="0.01"
                    min="0"
                    name="acquisition_cost"
                    value="{{ old('acquisition_cost', $depreciableAsset?->acquisition_cost) }}"
                    required
                >
            </div>

            <div class="field">
                <label for="salvage_value">残存価額</label>
                <input
                    id="salvage_value"
                    type="number"
                    step="0.01"
                    min="0"
                    name="salvage_value"
                    value="{{ old('salvage_value', $depreciableAsset?->salvage_value ?? 0) }}"
                >
            </div>

            <div class="field">
                <label for="useful_life_years">耐用年数<span class="required">必須</span></label>
                <input
                    id="useful_life_years"
                    type="number"
                    min="1"
                    max="100"
                    name="useful_life_years"
                    value="{{ old('useful_life_years', $depreciableAsset?->useful_life_years) }}"
                    required
                >
            </div>

            <div class="field">
                <label for="depreciation_method">償却方法<span class="required">必須</span></label>
                <select id="depreciation_method" name="depreciation_method" required>
                    <option value="straight_line" {{ old('depreciation_method', $depreciableAsset?->depreciation_method ?? 'straight_line') === 'straight_line' ? 'selected' : '' }}>
                        定額法
                    </option>
                </select>
            </div>

            <div class="field">
                <label for="business_use_ratio">事業使用割合<span class="required">必須</span></label>
                <input
                    id="business_use_ratio"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100"
                    name="business_use_ratio"
                    value="{{ old('business_use_ratio', $depreciableAsset?->business_use_ratio ?? 100) }}"
                    required
                >
                <p class="muted">100%の場合は全額事業用として計算します。</p>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">仕訳科目</h3>

        <div class="form-grid">
            <div class="field">
                <label for="asset_account_title_id">固定資産科目<span class="required">必須</span></label>
                <select id="asset_account_title_id" name="asset_account_title_id" required>
                    <option value="">選択してください</option>
                    @foreach ($assetAccountTitles as $accountTitle)
                        <option value="{{ $accountTitle->id }}" {{ (string) $selectedAssetAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}>
                            {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="accumulated_depreciation_account_title_id">減価償却累計額科目</label>
                <select id="accumulated_depreciation_account_title_id" name="accumulated_depreciation_account_title_id">
                    <option value="">未設定: 固定資産科目を直接減額</option>
                    @foreach ($allAccountTitles as $accountTitle)
                        <option value="{{ $accountTitle->id }}" {{ (string) $selectedAccumulatedDepreciationAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}>
                            {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="depreciation_expense_account_title_id">減価償却費科目<span class="required">必須</span></label>
                <select id="depreciation_expense_account_title_id" name="depreciation_expense_account_title_id" required>
                    <option value="">選択してください</option>
                    @foreach ($expenseAccountTitles as $accountTitle)
                        <option value="{{ $accountTitle->id }}" {{ (string) $selectedDepreciationExpenseAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}>
                            {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">備考</h3>
        <div class="field">
            <label for="note">備考</label>
            <textarea id="note" name="note" rows="4">{{ old('note', $depreciableAsset?->note) }}</textarea>
        </div>
    </div>

    <div class="actions">
        <button type="submit" class="button">{{ $isEdit ? '固定資産を更新する' : '固定資産を登録する' }}</button>
        <a
            href="{{ $selectedBookId ? route('depreciable-assets.index', ['book_id' => $selectedBookId]) : route('depreciable-assets.index') }}"
            class="button button-secondary"
        >
            一覧へ戻る
        </a>
    </div>
</form>