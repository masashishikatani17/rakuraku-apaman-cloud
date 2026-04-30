@extends('layouts.app')

@section('title', '賃貸条件一覧')

@section('content')
    @php
        $contractStatusLabels = [
            'all' => 'すべて',
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];

        $activeFilterLabels = [
            'all' => 'すべて',
            'active' => '有効のみ',
            'inactive' => '停止のみ',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">賃貸条件一覧</h2>
            <p class="page-description">契約者ごとの賃貸条件、月額、敷金・礼金・保証金を一覧確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('contract-tenants.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    契約者台帳へ
                </a>
                <a
                    href="{{ route('reports.property-ledgers.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    物件台帳へ
                </a>
                <a
                    href="{{ route('reports.property-annual-incomes.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    物件別年間収入台帳へ
                </a>
                <a
                    href="{{ route('rental-contract-move-outs.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    退去処理へ
                </a>
                <a
                    href="{{ route('reports.occupancy-statuses.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    空室・入退去予定へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、契約者台帳で登録した最新の賃貸条件を一覧表示します。
        更新・再契約の履歴管理は、次の段階で拡張します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.rental-contracts.index') }}">
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
                    <label for="contract_tenant_id">契約者</label>
                    <select id="contract_tenant_id" name="contract_tenant_id">
                        <option value="">すべて表示</option>
                        @foreach ($contractTenants as $contractTenant)
                            <option
                                value="{{ $contractTenant->id }}"
                                {{ (string) $selectedContractTenantId === (string) $contractTenant->id ? 'selected' : '' }}
                            >
                                {{ $contractTenant->tenant_code }} / {{ $contractTenant->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="property_id">物件</label>
                    <select id="property_id" name="property_id">
                        <option value="">すべて表示</option>
                        @foreach ($properties as $property)
                            <option
                                value="{{ $property->id }}"
                                {{ (string) $selectedPropertyId === (string) $property->id ? 'selected' : '' }}
                            >
                                {{ $property->property_code }} / {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="contract_status">契約状態</label>
                    <select id="contract_status" name="contract_status">
                        @foreach ($contractStatusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $contractStatus === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="is_active">有効状態</label>
                    <select id="is_active" name="is_active">
                        @foreach ($activeFilterLabels as $value => $label)
                            <option value="{{ $value }}" {{ $activeFilter === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">契約期間開始</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">契約期間終了</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) : route('reports.rental-contracts.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>選択中の帳簿</label>
                    <div class="muted">
                        {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                    </div>
                </div>

                <div class="field">
                    <label>契約件数</label>
                    <div>{{ $summary['contracts_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>契約者数</label>
                    <div>{{ $summary['tenant_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>物件数</label>
                    <div>{{ $summary['property_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>契約状態内訳</label>
                    <div class="muted">
                        契約中 {{ $summary['active_count'] }} 件 /
                        予定 {{ $summary['planned_count'] }} 件 /
                        終了 {{ $summary['ended_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>月額合計</label>
                    <div>{{ number_format((float) $summary['monthly_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>敷金合計</label>
                    <div>{{ number_format((float) $summary['deposit_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>保証金合計</label>
                    <div>{{ number_format((float) $summary['guarantee_deposit_total'], 2) }}</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>家賃合計</label>
                    <div>{{ number_format((float) $summary['rent_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>共益費合計</label>
                    <div>{{ number_format((float) $summary['common_service_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>駐車料合計</label>
                    <div>{{ number_format((float) $summary['parking_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>その他月額合計</label>
                    <div>{{ number_format((float) $summary['other_monthly_total'], 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>所有者</th>
                    <th>契約番号</th>
                    <th>契約状態</th>
                    <th>契約期間</th>
                    <th>入退去</th>
                    <th>月額内訳</th>
                    <th>一時金</th>
                    <th>入金条件</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rentalContracts as $rentalContract)
                    @php
                        $monthlyTotal =
                            (float) $rentalContract->rent_amount
                            + (float) $rentalContract->common_service_fee
                            + (float) $rentalContract->parking_fee
                            + (float) $rentalContract->other_monthly_fee;
                    @endphp

                    <tr>
                        <td>
                            {{ $rentalContract->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $rentalContract->contractTenant?->name ?? '—' }}
                            @if ($rentalContract->contractTenant?->short_name)
                                <div class="muted">
                                    略称: {{ $rentalContract->contractTenant->short_name }}
                                </div>
                            @endif
                        </td>
                        <td>
                            {{ $rentalContract->property?->property_code ?? '—' }}
                            /
                            {{ $rentalContract->property?->name ?? '—' }}
                            @if ($rentalContract->propertyUnit)
                                <div class="muted">
                                    部屋・区画: {{ $rentalContract->propertyUnit->unit_no }}
                                </div>
                            @endif
                            @if ($rentalContract->property?->propertyCategory)
                                <div class="muted">
                                    区分: {{ $rentalContract->property->propertyCategory->name }}
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($rentalContract->property?->primaryOwner)
                                {{ $rentalContract->property->primaryOwner->owner_code }}
                                /
                                {{ $rentalContract->property->primaryOwner->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $rentalContract->contract_no ?: '—' }}</td>
                        <td>
                            {{ $contractStatusLabels[$rentalContract->contract_status] ?? $rentalContract->contract_status }}
                            <div class="muted">
                                {{ $rentalContract->is_active ? '有効' : '停止' }}
                            </div>
                        </td>
                        <td>
                            {{ $rentalContract->contract_started_on?->format('Y-m-d') ?? '—' }}
                            〜
                            {{ $rentalContract->contract_ended_on?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>
                            入居: {{ $rentalContract->move_in_on?->format('Y-m-d') ?? '—' }}
                            <br>
                            退去: {{ $rentalContract->move_out_on?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>
                            <strong>{{ number_format($monthlyTotal, 2) }}</strong>
                            <div class="muted">
                                家賃 {{ number_format((float) $rentalContract->rent_amount, 2) }}
                                /
                                共益費 {{ number_format((float) $rentalContract->common_service_fee, 2) }}
                                /
                                駐車 {{ number_format((float) $rentalContract->parking_fee, 2) }}
                                /
                                その他 {{ number_format((float) $rentalContract->other_monthly_fee, 2) }}
                            </div>
                        </td>
                        <td>
                            敷金 {{ number_format((float) $rentalContract->deposit_amount, 2) }}
                            <br>
                            礼金 {{ number_format((float) $rentalContract->key_money_amount, 2) }}
                            <br>
                            保証金 {{ number_format((float) $rentalContract->guarantee_deposit_amount, 2) }}
                        </td>
                        <td>
                            入金予定日:
                            {{ $rentalContract->payment_due_day ? $rentalContract->payment_due_day . '日' : '—' }}
                            <br>
                            入金方法:
                            {{ $rentalContract->payment_method ?: '—' }}
                            @if ($rentalContract->note)
                                <div class="muted">
                                    備考: {{ $rentalContract->note }}
                                </div>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if ($rentalContract->contractTenant)
                                    <a
                                        href="{{ route('contract-tenants.edit', $rentalContract->contractTenant) }}"
                                        class="button button-secondary"
                                    >
                                        契約者修正
                                    </a>
                                @endif
                                @if ($rentalContract->property)
                                    <a
                                        href="{{ route('reports.property-ledgers.index', ['book_id' => $rentalContract->book_id, 'property_id' => $rentalContract->property_id]) }}"
                                        class="button button-secondary"
                                    >
                                        物件台帳
                                    </a>
                                    @if ($rentalContract->contract_status !== 'ended')
                                        <a
                                            href="{{ route('rental-contract-move-outs.index', ['book_id' => $rentalContract->book_id, 'rental_contract_id' => $rentalContract->id]) }}"
                                            class="button"
                                            style="background: #dc2626;"
                                        >
                                            退去処理
                                        </a>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">指定条件に一致する賃貸条件がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection