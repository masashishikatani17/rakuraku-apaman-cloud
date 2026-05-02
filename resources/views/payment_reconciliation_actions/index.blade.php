@extends('layouts.app')

@section('title', '入金差額処理')

@section('content')
    @php
        $actionStatusLabels = $statusLabels ?? [];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">入金差額処理</h2>
            <p class="page-description">不足入金を翌月以降へ繰り越し、過入金を別の入金予定へ充当します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('payment-reconciliation-checks.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">入金差額チェックへ</a>
                <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金予定一覧へ</a>
                <a href="{{ route('payment-receipts.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金一覧へ</a>
                <a href="{{ route('payment-overpayment-deposits.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button">過入金預り仕訳へ</a>
                <a href="{{ route('payment-overpayment-deposit-applications.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">預り金充当仕訳へ</a>
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
        初版では、不足額は新しい入金予定として繰り越します。過入金は別の未入金・一部入金予定へ充当し、充当先に入金実績を作成します。
        過入金元の入金実績自体は削除せず、差額処理記録で調整します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('payment-reconciliation-actions.index') }}">
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
                    <label for="date_from">予定日・処理日開始</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">予定日・処理日終了</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a href="{{ $selectedBookId ? route('payment-reconciliation-actions.index', ['book_id' => $selectedBookId]) : route('payment-reconciliation-actions.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>未処理不足件数</label>
                    <div style="color: #dc2626;">{{ $summary['shortage_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>未処理不足額</label>
                    <div style="color: #dc2626;">{{ number_format((float) $summary['shortage_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>未処理過入金件数</label>
                    <div style="color: #f97316;">{{ $summary['overpayment_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>未処理過入金額</label>
                    <div style="color: #f97316;">{{ number_format((float) $summary['overpayment_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差額処理件数</label>
                    <div>{{ $summary['actions_count'] }} 件</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">不足入金の翌月以降繰越</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>予定日</th>
                    <th>契約者 / 物件</th>
                    <th>入金項目</th>
                    <th>予定額</th>
                    <th>確定入金</th>
                    <th>繰越済</th>
                    <th>未処理不足</th>
                    <th>繰越処理</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($shortageRows as $row)
                    <tr>
                        <td>{{ $row->due_on }}</td>
                        <td>
                            {{ $row->tenant_name ?? '—' }}
                            <div class="muted">
                                {{ $row->property_name ?? '物件不明' }}
                                @if ($row->unit_no)
                                    / {{ $row->unit_no }}
                                @endif
                            </div>
                        </td>
                        <td>{{ $row->payment_item_name ?? '—' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->expected_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->confirmed_received_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->shortage_carryover_amount, 2) }}</td>
                        <td style="text-align: right; color: #dc2626;">{{ number_format((float) $row->remaining_after_carryover, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('payment-reconciliation-actions.shortage-carryover') }}">
                                @csrf
                                <input type="hidden" name="book_id" value="{{ $row->book_id }}">
                                <input type="hidden" name="source_payment_schedule_id" value="{{ $row->payment_schedule_id }}">

                                <div class="field">
                                    <label>繰越年月</label>
                                    <input type="month" name="target_year_month" value="{{ $row->default_next_year_month }}" required>
                                </div>

                                <div class="field">
                                    <label>繰越予定日</label>
                                    <input type="date" name="due_on" value="{{ $row->default_next_due_on }}" required>
                                </div>

                                <div class="field">
                                    <label>繰越額</label>
                                    <input type="number" name="amount" value="{{ $row->remaining_after_carryover }}" min="0.01" step="0.01" required>
                                </div>

                                <div class="field">
                                    <label>備考</label>
                                    <input type="text" name="note" value="不足額繰越">
                                </div>

                                <button type="submit" class="button">繰越作成</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">未処理の不足入金はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">過入金の充当</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>予定日</th>
                    <th>契約者 / 物件</th>
                    <th>入金項目</th>
                    <th>予定額</th>
                    <th>確定入金</th>
                    <th>充当後入金</th>
                    <th>未処理過入金</th>
                    <th>充当処理</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($overpaymentRows as $row)
                    <tr>
                        <td>{{ $row->due_on }}</td>
                        <td>
                            {{ $row->tenant_name ?? '—' }}
                            <div class="muted">
                                {{ $row->property_name ?? '物件不明' }}
                                @if ($row->unit_no)
                                    / {{ $row->unit_no }}
                                @endif
                            </div>
                        </td>
                        <td>{{ $row->payment_item_name ?? '—' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->expected_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->confirmed_received_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->net_received_amount, 2) }}</td>
                        <td style="text-align: right; color: #f97316;">{{ number_format((float) $row->overpaid_after_application, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('payment-reconciliation-actions.overpayment-apply') }}">
                                @csrf
                                <input type="hidden" name="book_id" value="{{ $row->book_id }}">
                                <input type="hidden" name="source_payment_schedule_id" value="{{ $row->payment_schedule_id }}">

                                <div class="field">
                                    <label>充当日</label>
                                    <input type="date" name="action_on" value="{{ now()->format('Y-m-d') }}" required>
                                </div>

                                <div class="field">
                                    <label>充当先予定</label>
                                    <select name="target_payment_schedule_id" required>
                                        <option value="">選択してください</option>
                                        @foreach ($targetSchedules as $targetSchedule)
                                            @if ((int) $targetSchedule->id !== (int) $row->payment_schedule_id)
                                                <option value="{{ $targetSchedule->id }}">
                                                    {{ $targetSchedule->label }}
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label>充当額</label>
                                    <input type="number" name="amount" value="{{ $row->overpaid_after_application }}" min="0.01" step="0.01" required>
                                </div>

                                <div class="field">
                                    <label>備考</label>
                                    <input type="text" name="note" value="過入金充当">
                                </div>

                                <button type="submit" class="button">充当する</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">未処理の過入金はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">差額処理履歴</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>処理日</th>
                    <th>処理種別</th>
                    <th>金額</th>
                    <th>元予定</th>
                    <th>相手予定</th>
                    <th>作成入金/予定</th>
                    <th>備考</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actions as $action)
                    <tr>
                        <td>{{ $action->statusLabel() }}</td>
                        <td>{{ $action->action_on?->format('Y-m-d') }}</td>
                        <td>{{ $action->actionTypeLabel() }}</td>
                        <td style="text-align: right;">{{ number_format((float) $action->amount, 2) }}</td>
                        <td>
                            #{{ $action->source_payment_schedule_id }}
                            @if ($action->sourcePaymentSchedule?->contractTenant)
                                <div class="muted">{{ $action->sourcePaymentSchedule->contractTenant->name }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($action->targetPaymentSchedule)
                                #{{ $action->target_payment_schedule_id }}
                                @if ($action->targetPaymentSchedule?->contractTenant)
                                    <div class="muted">{{ $action->targetPaymentSchedule->contractTenant->name }}</div>
                                @endif
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($action->payment_receipt_id)
                                入金 #{{ $action->payment_receipt_id }}
                            @elseif ($action->created_payment_schedule_id)
                                予定 #{{ $action->created_payment_schedule_id }}
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>{{ $action->note ?: '—' }}</td>
                        <td>
                            @if ($action->status === 'posted')
                                <form
                                    method="POST"
                                    action="{{ route('payment-reconciliation-actions.destroy', $action) }}"
                                    onsubmit="return confirm('この入金差額処理を取り消しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background: #dc2626;">取消</button>
                                </form>
                            @else
                                <span class="muted">取消済</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">差額処理履歴はまだありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection