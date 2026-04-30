@extends('layouts.app')

@section('title', '入金差額チェック')

@section('content')
    @php
        $statusLabels = [
            'all' => 'すべて',
            'unpaid' => '未入金',
            'shortage' => '不足・一部入金',
            'exact' => '予定額どおり',
            'overpaid' => '過入金',
            'cancelled' => '取消',
        ];

        $scheduleStatusLabels = [
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];

        $statusColors = [
            'unpaid' => '#6b7280',
            'shortage' => '#dc2626',
            'exact' => '#166534',
            'overpaid' => '#f97316',
            'cancelled' => '#6b7280',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">入金差額チェック</h2>
            <p class="page-description">入金予定ごとの予定額・確定入金額・不足額・過入金を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金予定一覧へ</a>
                <a href="{{ route('payment-receipts.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金一覧へ</a>
                <a href="{{ route('rental-payment-journals.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸仕訳処理へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        確定済み入金の合計額をもとに、不足入金・過入金・予定額どおりを判定します。
        今回の初版では確認画面までです。過入金の充当や不足額の繰越は次の段階で追加します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('payment-reconciliation-checks.index') }}">
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
                    <label for="date_from">予定日開始</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">予定日終了</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="field">
                    <label for="reconciliation_status">差額状態</label>
                    <select id="reconciliation_status" name="reconciliation_status">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $reconciliationStatus === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a href="{{ $selectedBookId ? route('payment-reconciliation-checks.index', ['book_id' => $selectedBookId]) : route('payment-reconciliation-checks.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>表示件数</label>
                    <div>{{ $summary['rows_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>予定額合計</label>
                    <div>{{ number_format((float) $summary['expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>確定入金合計</label>
                    <div>{{ number_format((float) $summary['received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>不足額合計</label>
                    <div style="color: #dc2626;">{{ number_format((float) $summary['remaining_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>過入金合計</label>
                    <div style="color: #f97316;">{{ number_format((float) $summary['overpaid_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>金額再計算差異</label>
                    <div style="{{ (int) $summary['mismatch_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['mismatch_count'] }} 件
                    </div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>未入金</label>
                    <div>{{ $summary['unpaid_count'] }} 件</div>
                </div>
                <div class="field">
                    <label>不足・一部入金</label>
                    <div style="color: #dc2626;">{{ $summary['shortage_count'] }} 件</div>
                </div>
                <div class="field">
                    <label>予定額どおり</label>
                    <div style="color: #166534;">{{ $summary['exact_count'] }} 件</div>
                </div>
                <div class="field">
                    <label>過入金</label>
                    <div style="color: #f97316;">{{ $summary['overpaid_count'] }} 件</div>
                </div>
                <div class="field">
                    <label>取消</label>
                    <div>{{ $summary['cancelled_count'] }} 件</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>差額状態</th>
                    <th>予定日</th>
                    <th>対象年月</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>予定額</th>
                    <th>確定入金</th>
                    <th>不足額</th>
                    <th>過入金</th>
                    <th>予定状態</th>
                    <th>確定入金件数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td style="color: {{ $statusColors[$row->reconciliation_status] ?? '#111827' }};">
                            {{ $row->reconciliation_status_label }}
                            @if ($row->amount_mismatch)
                                <div style="color: #dc2626;">再計算差異あり</div>
                            @endif
                        </td>
                        <td>{{ $row->due_on ?? '—' }}</td>
                        <td>{{ $row->target_year_month }}</td>
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
                        <td>{{ $row->payment_item_name ?? '—' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->expected_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->confirmed_received_amount, 2) }}</td>
                        <td style="text-align: right; color: #dc2626;">{{ number_format((float) $row->remaining_amount, 2) }}</td>
                        <td style="text-align: right; color: #f97316;">{{ number_format((float) $row->overpaid_amount, 2) }}</td>
                        <td>{{ $scheduleStatusLabels[$row->payment_schedule_status] ?? $row->payment_schedule_status }}</td>
                        <td>{{ $row->confirmed_receipts_count }} 件</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('payment-receipts.create', ['book_id' => $row->book_id]) }}" class="button">入金登録</a>
                                <a href="{{ route('payment-schedules.edit', $row->payment_schedule_id) }}" class="button button-secondary">予定修正</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13">指定条件に一致する入金差額はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection