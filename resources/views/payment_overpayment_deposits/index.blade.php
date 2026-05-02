@extends('layouts.app')

@section('title', '過入金預り仕訳')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">過入金預り仕訳</h2>
            <p class="page-description">予定額を超えて入金された金額を、収益から預り金へ振り替える仕訳を作成します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('payment-reconciliation-actions.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">入金差額処理へ</a>
                <a href="{{ route('payment-reconciliation-checks.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button button-secondary">入金差額チェックへ</a>
                <a href="{{ route('payment-overpayment-deposit-applications.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="button">預り金充当仕訳へ</a>
                <a href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">仕訳一覧へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
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
        過入金をいったん預り金として残す場合に使います。
        仕訳は「借方: 入金項目の収益科目」「貸方: 選択した預り金科目」で作成します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('payment-overpayment-deposits.index') }}">
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
                <a href="{{ $selectedBookId ? route('payment-overpayment-deposits.index', ['book_id' => $selectedBookId]) : route('payment-overpayment-deposits.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>未処理過入金件数</label>
                    <div style="color: #f97316;">{{ $summary['overpayment_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>未処理過入金額</label>
                    <div style="color: #f97316;">{{ number_format((float) $summary['overpayment_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>預り金仕訳件数</label>
                    <div>{{ $summary['posted_actions_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>預り金振替済額</label>
                    <div>{{ number_format((float) $summary['posted_amount_total'], 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">未処理過入金</h3>

        @if ($liabilityAccountTitles->isEmpty())
            <div class="alert alert-error">
                預り金科目として使う負債科目がありません。先に勘定科目マスタで「預り金」などの負債科目を登録してください。
            </div>
        @endif

        <table class="data-table">
            <thead>
                <tr>
                    <th>予定日</th>
                    <th>契約者 / 物件</th>
                    <th>入金項目</th>
                    <th>収益科目</th>
                    <th>予定額</th>
                    <th>確定入金</th>
                    <th>処理済過入金</th>
                    <th>未処理過入金</th>
                    <th>預り金仕訳</th>
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
                        <td>{{ $row->revenue_account_name ?? '未設定' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->expected_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->confirmed_received_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->already_processed_amount, 2) }}</td>
                        <td style="text-align: right; color: #f97316;">{{ number_format((float) $row->overpayment_amount, 2) }}</td>
                        <td>
                            <form method="POST" action="{{ route('payment-overpayment-deposits.store') }}">
                                @csrf
                                <input type="hidden" name="book_id" value="{{ $row->book_id }}">
                                <input type="hidden" name="source_payment_schedule_id" value="{{ $row->payment_schedule_id }}">

                                <div class="field">
                                    <label>仕訳日</label>
                                    <input type="date" name="action_on" value="{{ now()->format('Y-m-d') }}" required>
                                </div>

                                <div class="field">
                                    <label>預り金科目</label>
                                    <select name="deposit_liability_account_title_id" required>
                                        <option value="">選択してください</option>
                                        @foreach ($liabilityAccountTitles as $accountTitle)
                                            <option value="{{ $accountTitle->id }}">
                                                {{ $accountTitle->account_code }} / {{ $accountTitle->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="field">
                                    <label>振替額</label>
                                    <input type="number" name="amount" value="{{ $row->overpayment_amount }}" min="0.01" step="0.01" required>
                                </div>

                                <div class="field">
                                    <label>摘要</label>
                                    <input type="text" name="description_text" value="{{ $row->default_description }}" maxlength="255">
                                </div>

                                <div class="field">
                                    <label>備考</label>
                                    <input type="text" name="note" value="過入金預り">
                                </div>

                                <button type="submit" class="button" {{ $liabilityAccountTitles->isEmpty() ? 'disabled' : '' }}>預り金仕訳作成</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">未処理の過入金はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">過入金預り仕訳履歴</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>処理日</th>
                    <th>元予定</th>
                    <th>契約者</th>
                    <th>金額</th>
                    <th>仕訳</th>
                    <th>備考</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($actions as $action)
                    <tr>
                        <td>{{ $action->statusLabel() }}</td>
                        <td>{{ $action->action_on?->format('Y-m-d') }}</td>
                        <td>#{{ $action->source_payment_schedule_id }}</td>
                        <td>{{ $action->sourcePaymentSchedule?->contractTenant?->name ?? '—' }}</td>
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
                        <td>
                            @if ($action->status === 'posted')
                                <form
                                    method="POST"
                                    action="{{ route('payment-overpayment-deposits.destroy', $action) }}"
                                    onsubmit="return confirm('この過入金預り仕訳を取り消しますか？');"
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
                        <td colspan="8">過入金預り仕訳履歴はまだありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection