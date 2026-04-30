@extends('layouts.app')

@section('title', '月額変更履歴・入金予定再作成')

@section('content')
    @php
        $itemTypeLabels = [
            'rent' => '家賃',
            'common_service' => '共益費',
            'parking' => '駐車料',
            'other' => 'その他月額',
        ];

        $statusColors = [
            'create' => '#166534',
            'update' => '#1d4ed8',
            'same' => '#6b7280',
            'locked' => '#dc2626',
            'cancel_zero' => '#f97316',
            'missing_item' => '#dc2626',
            'zero_amount' => '#6b7280',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">月額変更履歴・入金予定再作成</h2>
            <p class="page-description">賃貸条件の月額変更履歴を登録し、未入金の入金予定へ反映します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('monthly-payment-schedules.create', ['book_id' => $selectedBookId, 'target_year_month' => $targetYearMonth]) }}" class="button button-secondary">月次入金予定生成へ</a>
                <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金予定一覧へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
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

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        既に入金済・一部入金の予定は保護します。未入金の予定だけ、月額変更履歴に合わせて更新・取消できます。
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('rental-contract-terms.index') }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="book_id">帳簿<span class="required">必須</span></label>
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
                    </div>

                    <div class="field">
                        <label for="target_year_month">対象年月<span class="required">必須</span></label>
                        <input
                            id="target_year_month"
                            type="month"
                            name="target_year_month"
                            value="{{ $targetYearMonth }}"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="rental_contract_id">賃貸条件で絞り込み</label>
                        <select id="rental_contract_id" name="rental_contract_id">
                            <option value="">すべて</option>
                            @foreach ($contracts as $contract)
                                <option
                                    value="{{ $contract->id }}"
                                    {{ (string) $selectedRentalContractId === (string) $contract->id ? 'selected' : '' }}
                                >
                                    {{ ($contract->contract_no ?: '契約番号なし') }}
                                    /
                                    {{ $contract->contractTenant?->name ?? '契約者不明' }}
                                    /
                                    {{ $contract->property?->name ?? '物件不明' }}
                                    @if ($contract->propertyUnit?->unit_no)
                                        {{ $contract->propertyUnit->unit_no }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">表示する</button>
                    <a href="{{ $selectedBookId ? route('rental-contract-terms.index', ['book_id' => $selectedBookId]) : route('rental-contract-terms.index') }}" class="button button-secondary">条件を初期化</a>
                </div>
            </form>
        </div>

        @if ($selectedBook)
            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-top: 0;">月額変更履歴を登録</h3>

                <form method="POST" action="{{ route('rental-contract-terms.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

                    <div class="form-grid">
                        <div class="field field-full">
                            <label for="term_rental_contract_id">賃貸条件<span class="required">必須</span></label>
                            <select id="term_rental_contract_id" name="rental_contract_id" required>
                                <option value="">選択してください</option>
                                @foreach ($contracts as $contract)
                                    <option value="{{ $contract->id }}" {{ (string) old('rental_contract_id', $selectedRentalContractId) === (string) $contract->id ? 'selected' : '' }}>
                                        {{ ($contract->contract_no ?: '契約番号なし') }}
                                        /
                                        {{ $contract->contractTenant?->name ?? '契約者不明' }}
                                        /
                                        {{ $contract->property?->name ?? '物件不明' }}
                                        @if ($contract->propertyUnit?->unit_no)
                                            {{ $contract->propertyUnit->unit_no }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label for="effective_from_year_month">適用開始年月<span class="required">必須</span></label>
                            <input id="effective_from_year_month" type="month" name="effective_from_year_month" value="{{ old('effective_from_year_month', $targetYearMonth) }}" required>
                        </div>

                        <div class="field">
                            <label for="rent_amount">賃料</label>
                            <input id="rent_amount" type="number" name="rent_amount" value="{{ old('rent_amount', 0) }}" min="0" step="0.01">
                        </div>

                        <div class="field">
                            <label for="common_service_fee">共益費</label>
                            <input id="common_service_fee" type="number" name="common_service_fee" value="{{ old('common_service_fee', 0) }}" min="0" step="0.01">
                        </div>

                        <div class="field">
                            <label for="parking_fee">駐車料</label>
                            <input id="parking_fee" type="number" name="parking_fee" value="{{ old('parking_fee', 0) }}" min="0" step="0.01">
                        </div>

                        <div class="field">
                            <label for="other_monthly_fee">その他月額</label>
                            <input id="other_monthly_fee" type="number" name="other_monthly_fee" value="{{ old('other_monthly_fee', 0) }}" min="0" step="0.01">
                        </div>

                        <div class="field">
                            <label for="payment_due_day">入金予定日</label>
                            <input id="payment_due_day" type="number" name="payment_due_day" value="{{ old('payment_due_day') }}" min="1" max="31">
                        </div>

                        <div class="field field-full">
                            <label for="note">備考</label>
                            <textarea id="note" name="note">{{ old('note') }}</textarea>
                        </div>

                        <div class="field field-full">
                            <label>契約マスタ反映</label>
                            <div class="checkbox-wrap">
                                <input type="hidden" name="sync_contract_current" value="0">
                                <input id="sync_contract_current" type="checkbox" name="sync_contract_current" value="1" {{ old('sync_contract_current', '1') === '1' ? 'checked' : '' }}>
                                <label for="sync_contract_current">賃貸条件マスタの現在月額にも反映する</label>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="margin-top: 16px;">
                        <button type="submit" class="button">月額変更履歴を登録</button>
                    </div>
                </form>
            </div>

            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-top: 0;">入金予定の再作成プレビュー</h3>

                <div class="form-grid" style="margin-bottom: 16px;">
                    <div class="field">
                        <label>作成候補</label>
                        <div style="color: #166534;">{{ $summary['create_count'] }} 件</div>
                    </div>
                    <div class="field">
                        <label>更新候補</label>
                        <div style="color: #1d4ed8;">{{ $summary['update_count'] }} 件</div>
                    </div>
                    <div class="field">
                        <label>0円取消候補</label>
                        <div style="color: #f97316;">{{ $summary['cancel_zero_count'] }} 件</div>
                    </div>
                    <div class="field">
                        <label>保護</label>
                        <div style="color: #dc2626;">{{ $summary['locked_count'] }} 件</div>
                    </div>
                    <div class="field">
                        <label>変更なし</label>
                        <div class="muted">{{ $summary['same_count'] }} 件</div>
                    </div>
                    <div class="field">
                        <label>入金項目なし</label>
                        <div style="color: #dc2626;">{{ $summary['missing_item_count'] }} 件</div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('rental-contract-terms.rebuild') }}"
                    onsubmit="return confirm('未入金の入金予定を月額変更履歴に合わせて再作成しますか？');"
                    style="margin-bottom: 16px;"
                >
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                    <input type="hidden" name="target_year_month" value="{{ $targetYearMonth }}">
                    @if ($selectedRentalContractId)
                        <input type="hidden" name="rental_contract_id" value="{{ $selectedRentalContractId }}">
                    @endif

                    <div class="checkbox-wrap" style="margin-bottom: 8px;">
                        <input type="hidden" name="update_unpaid" value="0">
                        <input id="update_unpaid" type="checkbox" name="update_unpaid" value="1" checked>
                        <label for="update_unpaid">未入金予定の金額・予定日を更新する</label>
                    </div>

                    <div class="checkbox-wrap" style="margin-bottom: 16px;">
                        <input type="hidden" name="cancel_zero_unpaid" value="0">
                        <input id="cancel_zero_unpaid" type="checkbox" name="cancel_zero_unpaid" value="1" checked>
                        <label for="cancel_zero_unpaid">0円になった未入金予定を取消にする</label>
                    </div>

                    <button type="submit" class="button">入金予定を再作成する</button>
                </form>

                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>契約者</th>
                                <th>物件 / 部屋</th>
                                <th>入金項目</th>
                                <th>適用元</th>
                                <th>予定日</th>
                                <th>予定金額</th>
                                <th>既存予定日</th>
                                <th>既存金額</th>
                                <th>状態</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($previewRows as $row)
                                <tr>
                                    <td>
                                        {{ $row->tenant_code ?? '—' }}
                                        /
                                        {{ $row->tenant_name ?? '—' }}
                                    </td>
                                    <td>
                                        {{ $row->property_code ?? '—' }}
                                        /
                                        {{ $row->property_name ?? '—' }}
                                        @if ($row->unit_no)
                                            <div class="muted">部屋: {{ $row->unit_no }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $itemTypeLabels[$row->payment_item_type] ?? $row->payment_item_type }} / {{ $row->payment_item_name ?? '—' }}</td>
                                    <td>{{ $row->source_label }}</td>
                                    <td>{{ $row->due_on }}</td>
                                    <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                                    <td>{{ $row->existing_due_on ?? '—' }}</td>
                                    <td style="text-align: right;">{{ $row->existing_amount !== null ? number_format((float) $row->existing_amount, 2) : '—' }}</td>
                                    <td style="color: {{ $statusColors[$row->status] ?? '#111827' }};">{{ $row->status_label }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">対象データがありません。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 style="margin-top: 0;">登録済み月額変更履歴</h3>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>適用開始</th>
                            <th>賃貸条件</th>
                            <th>賃料</th>
                            <th>共益費</th>
                            <th>駐車料</th>
                            <th>その他月額</th>
                            <th>予定日</th>
                            <th>備考</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($termRows as $term)
                            <tr>
                                <td>{{ $term->effective_from_year_month }}</td>
                                <td>
                                    {{ $term->rentalContract?->contractTenant?->name ?? '契約者不明' }}
                                    /
                                    {{ $term->rentalContract?->property?->name ?? '物件不明' }}
                                    @if ($term->rentalContract?->propertyUnit?->unit_no)
                                        {{ $term->rentalContract->propertyUnit->unit_no }}
                                    @endif
                                </td>
                                <td style="text-align: right;">{{ number_format((float) $term->rent_amount, 2) }}</td>
                                <td style="text-align: right;">{{ number_format((float) $term->common_service_fee, 2) }}</td>
                                <td style="text-align: right;">{{ number_format((float) $term->parking_fee, 2) }}</td>
                                <td style="text-align: right;">{{ number_format((float) $term->other_monthly_fee, 2) }}</td>
                                <td>{{ $term->payment_due_day ?? '契約マスタ' }}</td>
                                <td>{{ $term->note ?: '—' }}</td>
                                <td>
                                    <form
                                        method="POST"
                                        action="{{ route('rental-contract-terms.destroy', $term) }}"
                                        onsubmit="return confirm('この月額変更履歴を削除しますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button" style="background: #dc2626;">削除</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">月額変更履歴はまだ登録されていません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection