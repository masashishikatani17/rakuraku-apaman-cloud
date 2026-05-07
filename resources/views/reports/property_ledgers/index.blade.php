@extends('layouts.app')

@section('title', '物件台帳')

@section('content')
    @php
        $activeFilterLabels = [
            'all' => 'すべて',
            'active' => '有効のみ',
            'inactive' => '停止のみ',
        ];

        $unitTypeLabels = [
            'room' => '部屋',
            'parking' => '駐車場',
            'other' => 'その他',
        ];

        $contractStatusLabels = [
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">物件台帳</h2>
            <p class="page-description">物件の基本情報、部屋・区画、契約状況、入金概要をまとめて確認します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('rental-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                賃貸管理メニューへ戻る
            </a>
            <a
                href="{{ route('output-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                帳票・出力メニューへ戻る
            </a>
            @if ($selectedBookId)
                <a
                    href="{{ route('properties.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    物件一覧へ
                </a>
                <a
                    href="{{ route('reports.property-payments.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    物件別入金一覧表へ
                </a>
                <a
                    href="{{ route('reports.property-annual-incomes.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    物件別年間収入台帳へ
                </a>
                <a
                    href="{{ route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    賃貸条件一覧へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、物件マスタ、物件マスター詳細、所有者、契約、入金予定の情報をまとめて表示します。
        PDF印刷用の帳票レイアウトは次段階で追加します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.property-ledgers.index') }}">
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
                    <label for="property_id">物件</label>
                    <select id="property_id" name="property_id">
                        <option value="">すべて表示</option>
                        @foreach ($propertiesForSelect as $property)
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
                    <label for="is_active">状態</label>
                    <select id="is_active" name="is_active">
                        @foreach ($activeFilterLabels as $value => $label)
                            <option value="{{ $value }}" {{ $activeFilter === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.property-ledgers.index', ['book_id' => $selectedBookId]) : route('reports.property-ledgers.index') }}"
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
                    <label>表示状態</label>
                    <div>{{ $activeFilterLabels[$activeFilter] ?? $activeFilter }}</div>
                </div>

                <div class="field">
                    <label>物件数</label>
                    <div>{{ $summary['properties_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>部屋・区画数</label>
                    <div>
                        {{ $summary['units_count'] }} 件
                        <span class="muted">有効 {{ $summary['active_units_count'] }} 件</span>
                    </div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>契約数</label>
                    <div>
                        {{ $summary['contracts_count'] }} 件
                        <span class="muted">契約中 {{ $summary['active_contracts_count'] }} 件</span>
                    </div>
                </div>

                <div class="field">
                    <label>入金予定合計</label>
                    <div>{{ number_format((float) $summary['expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>入金済合計</label>
                    <div>{{ number_format((float) $summary['received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>未入金合計</label>
                    <div style="{{ (float) $summary['remaining_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ number_format((float) $summary['remaining_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    @forelse ($propertyRows as $row)
        @php
            $property = $row->property;
            $paymentSummary = $row->payment_summary;
        @endphp

        <div class="card" style="margin-bottom: 24px;">
            <div class="page-header" style="margin-bottom: 16px;">
                <div>
                    <h3 style="margin: 0;">
                        {{ $property->property_code }} / {{ $property->name }}
                    </h3>
                    <p class="muted" style="margin: 6px 0 0;">
                        {{ $property->propertyCategory?->name ?? '物件区分未設定' }}
                        /
                        {{ $property->is_active ? '有効' : '停止' }}
                    </p>
                </div>
                <div class="actions">
                    <a
                        href="{{ route('properties.edit', $property) }}"
                        class="button button-secondary"
                    >
                        物件修正
                    </a>
                    <a
                        href="{{ route('property-units.index', ['book_id' => $property->book_id, 'property_id' => $property->id]) }}"
                        class="button button-secondary"
                    >
                        部屋・区画一覧
                    </a>
                    <a
                        href="{{ route('reports.property-payments.index', ['book_id' => $property->book_id, 'property_id' => $property->id]) }}"
                        class="button button-secondary"
                    >
                        入金一覧表
                    </a>
                </div>
            </div>

            <h4>物件基本情報</h4>

            <div class="form-grid">
                <div class="field">
                    <label>物件名略称</label>
                    <div>{{ $property->short_name ?: '—' }}</div>
                </div>

                <div class="field">
                    <label>物件名ヨミ</label>
                    <div>{{ $property->name_reading ?: '—' }}</div>
                </div>

                <div class="field">
                    <label>所有者</label>
                    <div>
                        {{ $property->primaryOwner?->owner_code ?? '—' }}
                        /
                        {{ $property->primaryOwner?->name ?? '—' }}
                    </div>
                </div>

                <div class="field">
                    <label>代表者</label>
                    <div>
                        @if ($property->representativeOwner)
                            {{ $property->representativeOwner->owner_code }}
                            /
                            {{ $property->representativeOwner->name }}
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div class="field field-full">
                    <label>所在地</label>
                    <div>
                        @if ($property->postal_code_1 || $property->postal_code_2)
                            〒{{ $property->postal_code_1 }}-{{ $property->postal_code_2 }}
                            <br>
                        @endif
                        {{ $property->address ?: '—' }}
                    </div>
                </div>

                <div class="field">
                    <label>所有形態</label>
                    <div>{{ $property->ownership_form ?: '—' }}</div>
                </div>

                <div class="field">
                    <label>権利形態</label>
                    <div>{{ $property->right_form ?: '—' }}</div>
                </div>

                <div class="field">
                    <label>築年月日</label>
                    <div>{{ $property->built_at?->format('Y-m-d') ?? '—' }}</div>
                </div>

                <div class="field">
                    <label>構造 / 階数</label>
                    <div>
                        {{ $property->structure ?: '—' }}
                        @if ($property->floors)
                            / {{ $property->floors }}
                        @endif
                    </div>
                </div>

                <div class="field">
                    <label>土地面積</label>
                    <div>{{ $property->land_area_sqm !== null ? number_format((float) $property->land_area_sqm, 2) . '㎡' : '—' }}</div>
                </div>

                <div class="field">
                    <label>建物面積</label>
                    <div>{{ $property->building_area_sqm !== null ? number_format((float) $property->building_area_sqm, 2) . '㎡' : '—' }}</div>
                </div>

                <div class="field">
                    <label>住居床面積</label>
                    <div>{{ $property->residential_floor_area !== null ? number_format((float) $property->residential_floor_area, 2) . '㎡' : '—' }}</div>
                </div>

                <div class="field">
                    <label>事業床面積</label>
                    <div>{{ $property->business_floor_area !== null ? number_format((float) $property->business_floor_area, 2) . '㎡' : '—' }}</div>
                </div>

                <div class="field">
                    <label>駐車台数</label>
                    <div>
                        合計 {{ $property->parking_total ?? 0 }}
                        <div class="muted">
                            月極室内 {{ $property->parking_monthly_indoor ?? 0 }} /
                            月極室外 {{ $property->parking_monthly_outdoor ?? 0 }} /
                            時間貸 {{ $property->parking_hourly ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label>間取り等</label>
                    <div>{{ $property->layout_summary ?: '—' }}</div>
                </div>

                <div class="field field-full">
                    <label>備考</label>
                    <div>{{ $property->note ?: '—' }}</div>
                </div>
            </div>

            <h4 style="margin-top: 24px;">部屋・区画</h4>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>部屋番号 / 区画番号</th>
                        <th>種別</th>
                        <th>面積</th>
                        <th>間取りCODE</th>
                        <th>駐車場区分</th>
                        <th>解約日</th>
                        <th>状態</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($property->units as $unit)
                        <tr>
                            <td>{{ $unit->unit_no }}</td>
                            <td>{{ $unitTypeLabels[$unit->unit_type] ?? $unit->unit_type }}</td>
                            <td>{{ $unit->area_sqm !== null ? number_format((float) $unit->area_sqm, 2) . '㎡' : '—' }}</td>
                            <td>{{ $unit->layout_code ?: '—' }}</td>
                            <td>{{ $unit->parking_category_code ?: '—' }}</td>
                            <td>{{ $unit->ended_at?->format('Y-m-d') ?? '—' }}</td>
                            <td>{{ $unit->is_active ? '有効' : '停止' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">部屋・区画が登録されていません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <h4 style="margin-top: 24px;">契約状況</h4>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>契約者</th>
                        <th>部屋・区画</th>
                        <th>契約状態</th>
                        <th>契約期間</th>
                        <th>入退去</th>
                        <th>月額</th>
                        <th>敷金 / 礼金 / 保証金</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($property->rentalContracts as $contract)
                        @php
                            $monthlyTotal =
                                (float) $contract->rent_amount
                                + (float) $contract->common_service_fee
                                + (float) $contract->parking_fee
                                + (float) $contract->other_monthly_fee;
                        @endphp
                        <tr>
                            <td>
                                {{ $contract->contractTenant?->tenant_code ?? '—' }}
                                /
                                {{ $contract->contractTenant?->name ?? '—' }}
                            </td>
                            <td>{{ $contract->propertyUnit?->unit_no ?? '—' }}</td>
                            <td>
                                {{ $contractStatusLabels[$contract->contract_status] ?? $contract->contract_status }}
                                /
                                {{ $contract->is_active ? '有効' : '停止' }}
                            </td>
                            <td>
                                {{ $contract->contract_started_on?->format('Y-m-d') ?? '—' }}
                                〜
                                {{ $contract->contract_ended_on?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td>
                                入居 {{ $contract->move_in_on?->format('Y-m-d') ?? '—' }}
                                <br>
                                退去 {{ $contract->move_out_on?->format('Y-m-d') ?? '—' }}
                            </td>
                            <td>
                                {{ number_format($monthlyTotal, 2) }}
                                <div class="muted">
                                    家賃 {{ number_format((float) $contract->rent_amount, 2) }} /
                                    共益費 {{ number_format((float) $contract->common_service_fee, 2) }} /
                                    駐車 {{ number_format((float) $contract->parking_fee, 2) }} /
                                    その他 {{ number_format((float) $contract->other_monthly_fee, 2) }}
                                </div>
                            </td>
                            <td>
                                敷金 {{ number_format((float) $contract->deposit_amount, 2) }}
                                <br>
                                礼金 {{ number_format((float) $contract->key_money_amount, 2) }}
                                <br>
                                保証金 {{ number_format((float) $contract->guarantee_deposit_amount, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">契約が登録されていません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <h4 style="margin-top: 24px;">入金概要</h4>

            <div class="form-grid">
                <div class="field">
                    <label>入金予定件数</label>
                    <div>{{ $paymentSummary['schedules_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>予定合計</label>
                    <div>{{ number_format((float) $paymentSummary['expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>入金済合計</label>
                    <div>{{ number_format((float) $paymentSummary['received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>未入金合計</label>
                    <div style="{{ (float) $paymentSummary['remaining_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ number_format((float) $paymentSummary['remaining_total'], 2) }}
                    </div>
                </div>

                <div class="field field-full">
                    <label>状態内訳</label>
                    <div class="muted">
                        未入金 {{ $paymentSummary['unpaid_count'] }} 件 /
                        一部 {{ $paymentSummary['partial_count'] }} 件 /
                        入金済 {{ $paymentSummary['paid_count'] }} 件
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card">
            指定条件に一致する物件がありません。
        </div>
    @endforelse
@endsection