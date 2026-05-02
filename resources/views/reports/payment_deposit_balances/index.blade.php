@extends('layouts.app')

@section('title', '預り金残高一覧')

@section('content')
    @php
        $displayLabels = [
            'remaining' => '残高ありのみ',
            'all' => '残高0も表示',
        ];

        $scheduleStatusLabels = [
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];

        $actionTypeLabels = [
            'overpayment_deposit' => '過入金預り',
            'deposit_application' => '預り金充当',
        ];

        $statusLabels = [
            'posted' => '処理済',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">預り金残高一覧</h2>
            <p class="page-description">過入金から預り金化した金額、充当済み金額、未充当残高を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('payment-overpayment-deposits.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">過入金預り仕訳へ</a>
                <a href="{{ route('payment-overpayment-deposit-applications.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">預り金充当仕訳へ</a>
                <a href="{{ route('payment-reconciliation-actions.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">入金差額処理へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は、過入金を預り金化した後の残高確認用です。
        預り金残高が残っている場合は、預り金充当仕訳または返金処理の対象になります。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.payment-deposit-balances.index') }}">
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
                    <label for="date_from">元入金予定日・処理日開始</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">元入金予定日・処理日終了</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="field">
                    <label for="display">表示方法</label>
                    <select id="display" name="display">
                        @foreach ($displayLabels as $value => $label)
                            <option value="{{ $value }}" {{ $display === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a href="{{ $selectedBookId ? route('reports.payment-deposit-balances.index', ['book_id' => $selectedBookId]) : route('reports.payment-deposit-balances.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>預り金対象件数</label>
                    <div>{{ $summary['balance_rows_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>預り金化済額</label>
                    <div>{{ number_format((float) $summary['deposited_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>充当済額</label>
                    <div>{{ number_format((float) $summary['applied_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>未充当残高</label>
                    <div style="{{ (float) $summary['remaining_total'] >= 0 ? 'color: #f97316;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['remaining_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>過充当件数</label>
                    <div style="{{ (int) $summary['over_applied_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['over_applied_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>履歴件数</label>
                    <div>{{ $summary['history_rows_count'] }} 件</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">預り金残高</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>元予定日</th>
                    <th>対象年月</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>予定額</th>
                    <th>入金済額</th>
                    <th>預り金化済</th>
                    <th>充当済</th>
                    <th>未充当残高</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($balanceRows as $row)
                    <tr>
                        <td>
                            {{ $scheduleStatusLabels[$row->payment_schedule_status] ?? $row->payment_schedule_status }}
                            @if ($row->is_over_applied)
                                <div style="color: #dc2626;">過充当</div>
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
                        <td style="text-align: right;">{{ number_format((float) $row->received_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->deposited_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->applied_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->remaining_total >= 0 ? 'color: #f97316;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->remaining_total, 2) }}
                        </td>
                        <td>
                            @if ((float) $row->remaining_total > 0)
                                <a
                                    href="{{ route('payment-overpayment-deposit-applications.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                    class="button"
                                >
                                    充当へ
                                </a>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">条件に一致する預り金残高はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">預り金関連処理履歴</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>処理日</th>
                    <th>処理種別</th>
                    <th>元予定</th>
                    <th>相手予定</th>
                    <th>金額</th>
                    <th>仕訳</th>
                    <th>備考</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($historyRows as $action)
                    <tr>
                        <td>{{ $statusLabels[$action->status] ?? $action->status }}</td>
                        <td>{{ $action->action_on?->format('Y-m-d') }}</td>
                        <td>{{ $actionTypeLabels[$action->action_type] ?? $action->action_type }}</td>
                        <td>
                            #{{ $action->source_payment_schedule_id }}
                            @if ($action->sourcePaymentSchedule?->contractTenant)
                                <div class="muted">{{ $action->sourcePaymentSchedule->contractTenant->name }}</div>
                            @endif
                            @if ($action->sourcePaymentSchedule?->paymentItem)
                                <div class="muted">{{ $action->sourcePaymentSchedule->paymentItem->name }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($action->targetPaymentSchedule)
                                #{{ $action->target_payment_schedule_id }}
                                @if ($action->targetPaymentSchedule?->contractTenant)
                                    <div class="muted">{{ $action->targetPaymentSchedule->contractTenant->name }}</div>
                                @endif
                                @if ($action->targetPaymentSchedule?->paymentItem)
                                    <div class="muted">{{ $action->targetPaymentSchedule->paymentItem->name }}</div>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td style="text-align: right;">{{ number_format((float) $action->amount, 2) }}</td>
                        <td>
                            @if ($action->journalEntry)
                                #{{ $action->journalEntry->id }}
                                <div class="muted">{{ $action->journalEntry->voucher_no ?: '伝票番号なし' }}</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $action->note ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">預り金関連処理履歴はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection