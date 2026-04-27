@extends('layouts.app')

@section('title', '物件別入金一覧表')

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
            <h2 class="page-title">物件別入金一覧表</h2>
            <p class="page-description">物件ごとの入金予定・入金済額・未入金額を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    入金予定一覧へ
                </a>
                <a
                    href="{{ route('payment-receipts.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    入金一覧へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では「入金予定」を基準に表示します。入金済額は、入金実績により更新された予定側の入金済金額を使います。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.property-payments.index') }}">
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
                    href="{{ $selectedBookId ? route('reports.property-payments.index', ['book_id' => $selectedBookId]) : route('reports.property-payments.index') }}"
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
                    <label>入金予定件数</label>
                    <div>{{ $summary['schedules_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>状態</label>
                    <div>{{ $statusLabels[$status] ?? $status }}</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>予定合計</label>
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

                <div class="field">
                    <label>内訳</label>
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
        <h3 style="margin-top: 0;">物件別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>物件CODE</th>
                    <th>物件名</th>
                    <th>物件区分</th>
                    <th>件数</th>
                    <th>予定合計</th>
                    <th>入金済合計</th>
                    <th>未入金合計</th>
                    <th>状態内訳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($propertySummaries as $propertySummary)
                    <tr>
                        <td>{{ $propertySummary->property_code ?? '—' }}</td>
                        <td>{{ $propertySummary->property_name ?? '物件未設定' }}</td>
                        <td>{{ $propertySummary->property_category_name ?? '—' }}</td>
                        <td>{{ $propertySummary->schedules_count }} 件</td>
                        <td>{{ number_format((float) $propertySummary->expected_total, 2) }}</td>
                        <td>{{ number_format((float) $propertySummary->received_total, 2) }}</td>
                        <td style="{{ (float) $propertySummary->remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $propertySummary->remaining_total, 2) }}
                        </td>
                        <td class="muted">
                            未入金 {{ $propertySummary->unpaid_count }} /
                            一部 {{ $propertySummary->partial_count }} /
                            入金済 {{ $propertySummary->paid_count }} /
                            取消 {{ $propertySummary->cancelled_count }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">物件別集計を表示できる入金予定がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">明細</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>予定日</th>
                    <th>対象年月</th>
                    <th>物件 / 部屋</th>
                    <th>契約者</th>
                    <th>入金項目</th>
                    <th>予定金額</th>
                    <th>入金済</th>
                    <th>未入金</th>
                    <th>状態</th>
                    <th>入金日</th>
                    <th>備考</th>
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
                            {{ $paymentSchedule->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $paymentSchedule->contractTenant?->name ?? '—' }}
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
                        <td>{{ $paymentSchedule->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">指定条件に一致する入金予定がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection