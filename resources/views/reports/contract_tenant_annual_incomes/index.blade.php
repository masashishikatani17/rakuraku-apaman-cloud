@extends('layouts.app')

@section('title', '契約者別年間収入内訳表')

@section('content')
    @php
        $statusLabels = [
            'all' => 'すべて',
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">契約者別年間収入内訳表</h2>
            <p class="page-description">契約者ごとの月別収入・年間収入を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.property-annual-incomes.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別年間収入台帳へ
                </a>
                <a
                    href="{{ route('reports.property-payments.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別入金一覧表へ
                </a>
                <a
                    href="{{ route('contract-tenants.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    契約者台帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では「入金予定」を基準に、契約者別・月別の予定額、入金済額、未入金額を集計します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.contract-tenant-annual-incomes.index') }}">
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
                    <label for="date_from">開始日</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>

                <div class="field">
                    <label for="status">入金状態</label>
                    <select id="status" name="status">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.contract-tenant-annual-incomes.index', ['book_id' => $selectedBookId]) : route('reports.contract-tenant-annual-incomes.index') }}"
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
                    <label>表示期間</label>
                    <div class="muted">
                        {{ $dateFrom ?: '開始未指定' }}
                        〜
                        {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>対象契約者数</label>
                    <div>{{ $summary['contract_tenants_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>入金予定件数</label>
                    <div>{{ $summary['schedules_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>年間予定合計</label>
                    <div>{{ number_format((float) $summary['expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>年間入金済合計</label>
                    <div>{{ number_format((float) $summary['received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>年間未入金合計</label>
                    <div style="{{ (float) $summary['remaining_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ number_format((float) $summary['remaining_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>状態内訳</label>
                    <div class="muted">
                        未入金 {{ $summary['unpaid_count'] }} 件 /
                        一部 {{ $summary['partial_count'] }} 件 /
                        入金済 {{ $summary['paid_count'] }} 件 /
                        取消 {{ $summary['cancelled_count'] }} 件
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">月別全体集計</h3>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>区分</th>
                        @foreach ($months as $month)
                            <th>{{ $month->label }}</th>
                        @endforeach
                        <th>合計</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>予定額</td>
                        @foreach ($months as $month)
                            <td>{{ number_format((float) ($monthlyTotals[$month->year_month]['expected_total'] ?? 0), 2) }}</td>
                        @endforeach
                        <td>{{ number_format((float) $summary['expected_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>入金済</td>
                        @foreach ($months as $month)
                            <td>{{ number_format((float) ($monthlyTotals[$month->year_month]['received_total'] ?? 0), 2) }}</td>
                        @endforeach
                        <td>{{ number_format((float) $summary['received_total'], 2) }}</td>
                    </tr>
                    <tr>
                        <td>未入金</td>
                        @foreach ($months as $month)
                            @php
                                $monthRemaining = (float) ($monthlyTotals[$month->year_month]['remaining_total'] ?? 0);
                            @endphp
                            <td style="{{ $monthRemaining > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                                {{ number_format($monthRemaining, 2) }}
                            </td>
                        @endforeach
                        <td style="{{ (float) $summary['remaining_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $summary['remaining_total'], 2) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">契約者別年間収入内訳表</h3>

        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>契約者CODE</th>
                        <th>契約者名</th>
                        <th>物件 / 部屋</th>
                        @foreach ($months as $month)
                            <th>{{ $month->label }}</th>
                        @endforeach
                        <th>年間予定</th>
                        <th>年間入金済</th>
                        <th>年間未入金</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contractTenantSummaries as $contractTenantSummary)
                        <tr>
                            <td>{{ $contractTenantSummary->tenant_code ?? '—' }}</td>
                            <td>
                                {{ $contractTenantSummary->tenant_name ?? '契約者未設定' }}
                                @if ($contractTenantSummary->tenant_short_name)
                                    <div class="muted">略称: {{ $contractTenantSummary->tenant_short_name }}</div>
                                @endif
                            </td>
                            <td>
                                @forelse ($contractTenantSummary->property_labels as $propertyLabel)
                                    <div>{{ $propertyLabel }}</div>
                                @empty
                                    —
                                @endforelse
                            </td>

                            @foreach ($months as $month)
                                @php
                                    $monthSummary = $contractTenantSummary->monthly[$month->year_month] ?? [
                                        'expected_total' => 0,
                                        'received_total' => 0,
                                        'remaining_total' => 0,
                                    ];
                                @endphp
                                <td>
                                    <div>予定: {{ number_format((float) $monthSummary['expected_total'], 2) }}</div>
                                    <div>入金: {{ number_format((float) $monthSummary['received_total'], 2) }}</div>
                                    @if ((float) $monthSummary['remaining_total'] > 0)
                                        <div style="color: #dc2626;">
                                            未入: {{ number_format((float) $monthSummary['remaining_total'], 2) }}
                                        </div>
                                    @else
                                        <div class="muted">未入: 0.00</div>
                                    @endif
                                </td>
                            @endforeach

                            <td>{{ number_format((float) $contractTenantSummary->expected_total, 2) }}</td>
                            <td>{{ number_format((float) $contractTenantSummary->received_total, 2) }}</td>
                            <td style="{{ (float) $contractTenantSummary->remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                                {{ number_format((float) $contractTenantSummary->remaining_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 6 + $months->count() }}">表示できる契約者別年間収入データがありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">明細</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>予定日</th>
                    <th>対象年月</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>予定金額</th>
                    <th>入金済</th>
                    <th>未入金</th>
                    <th>状態</th>
                    <th>入金日</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paymentSchedules as $paymentSchedule)
                    @php
                        $remainingAmount = max(
                            (float) $paymentSchedule->expected_amount - (float) $paymentSchedule->received_amount,
                            0
                        );
                    @endphp

                    <tr>
                        <td>{{ $paymentSchedule->due_on?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $paymentSchedule->target_year_month }}</td>
                        <td>
                            {{ $paymentSchedule->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $paymentSchedule->contractTenant?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $paymentSchedule->rentalContract?->property?->property_code ?? '—' }}
                            /
                            {{ $paymentSchedule->rentalContract?->property?->name ?? '—' }}
                            @if ($paymentSchedule->rentalContract?->propertyUnit)
                                <div class="muted">
                                    部屋: {{ $paymentSchedule->rentalContract->propertyUnit->unit_no }}
                                </div>
                            @endif
                        </td>
                        <td>
                            {{ $paymentSchedule->paymentItem?->item_code ?? '—' }}
                            /
                            {{ $paymentSchedule->paymentItem?->name ?? '—' }}
                        </td>
                        <td>{{ number_format((float) $paymentSchedule->expected_amount, 2) }}</td>
                        <td>{{ number_format((float) $paymentSchedule->received_amount, 2) }}</td>
                        <td style="{{ $remainingAmount > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format($remainingAmount, 2) }}
                        </td>
                        <td>{{ $statusLabels[$paymentSchedule->status] ?? $paymentSchedule->status }}</td>
                        <td>
                            @forelse ($paymentSchedule->receipts as $receipt)
                                <div>
                                    {{ $receipt->received_on?->format('Y-m-d') ?? '—' }}
                                    /
                                    {{ number_format((float) $receipt->amount, 2) }}
                                </div>
                            @empty
                                —
                            @endforelse
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">指定条件に一致する入金予定がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection