@extends('layouts.app')

@section('title', '退去処理')

@section('content')
    @php
        $contractStatusLabels = [
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];

        $scheduleStatusLabels = [
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">退去処理</h2>
            <p class="page-description">賃貸条件を終了し、退去後の未入金予定を安全に取消します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸条件一覧へ</a>
                <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金予定一覧へ</a>
                <a href="{{ route('rental-contract-terms.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">月額変更履歴へ</a>
                <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">退去精算へ</a>
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
        退去処理では、賃貸条件を「終了」にし、退去後の未入金予定を取消できます。
        入金済・一部入金・入金実績がある予定は保護し、自動取消しません。
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('rental-contract-move-outs.index') }}">
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
                        <label for="rental_contract_id">賃貸条件<span class="required">必須</span></label>
                        <select id="rental_contract_id" name="rental_contract_id" required>
                            <option value="">選択してください</option>
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
                                    /
                                    {{ $contractStatusLabels[$contract->contract_status] ?? $contract->contract_status }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="move_out_on">退去日<span class="required">必須</span></label>
                        <input id="move_out_on" type="date" name="move_out_on" value="{{ $moveOutOn }}" required>
                    </div>

                    <div class="field">
                        <label for="stop_from_on">入金予定停止日<span class="required">必須</span></label>
                        <input id="stop_from_on" type="date" name="stop_from_on" value="{{ $stopFromOn }}" required>
                        <div class="muted">通常は退去日の翌日を指定します。</div>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">退去後予定を確認する</button>
                    <a href="{{ $selectedBookId ? route('rental-contract-move-outs.index', ['book_id' => $selectedBookId]) : route('rental-contract-move-outs.index') }}" class="button button-secondary">条件を初期化</a>
                </div>
            </form>
        </div>

        @if ($selectedRentalContract)
            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-top: 0;">対象賃貸条件</h3>

                <div class="form-grid">
                    <div class="field">
                        <label>契約者</label>
                        <div>{{ $selectedRentalContract->contractTenant?->tenant_code ?? '—' }} / {{ $selectedRentalContract->contractTenant?->name ?? '—' }}</div>
                    </div>

                    <div class="field">
                        <label>物件・部屋</label>
                        <div>
                            {{ $selectedRentalContract->property?->property_code ?? '—' }}
                            /
                            {{ $selectedRentalContract->property?->name ?? '—' }}
                            @if ($selectedRentalContract->propertyUnit?->unit_no)
                                {{ $selectedRentalContract->propertyUnit->unit_no }}
                            @endif
                        </div>
                    </div>

                    <div class="field">
                        <label>現在の契約状態</label>
                        <div>
                            {{ $contractStatusLabels[$selectedRentalContract->contract_status] ?? $selectedRentalContract->contract_status }}
                            /
                            {{ $selectedRentalContract->is_active ? '有効' : '停止' }}
                        </div>
                    </div>

                    <div class="field">
                        <label>契約期間</label>
                        <div>
                            {{ $selectedRentalContract->contract_started_on?->format('Y-m-d') ?? '—' }}
                            〜
                            {{ $selectedRentalContract->contract_ended_on?->format('Y-m-d') ?? '—' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-bottom: 16px;">
                <h3 style="margin-top: 0;">退去後入金予定サマリー</h3>

                <div class="form-grid">
                    <div class="field">
                        <label>対象予定</label>
                        <div>{{ $summary['rows_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>取消候補</label>
                        <div style="color: #f97316;">{{ $summary['cancel_candidates_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>保護</label>
                        <div style="{{ (int) $summary['protected_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ $summary['protected_count'] }} 件
                        </div>
                    </div>

                    <div class="field">
                        <label>予定額合計</label>
                        <div>{{ number_format((float) $summary['expected_total'], 2) }}</div>
                    </div>

                    <div class="field">
                        <label>入金済額合計</label>
                        <div>{{ number_format((float) $summary['received_total'], 2) }}</div>
                    </div>
                </div>

                <form
                    method="POST"
                    action="{{ route('rental-contract-move-outs.store') }}"
                    onsubmit="return confirm('退去処理を実行しますか？契約を終了し、対象の未入金予定を取消します。');"
                    style="margin-top: 16px;"
                >
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                    <input type="hidden" name="rental_contract_id" value="{{ $selectedRentalContractId }}">
                    <input type="hidden" name="move_out_on" value="{{ $moveOutOn }}">
                    <input type="hidden" name="stop_from_on" value="{{ $stopFromOn }}">

                    <div class="checkbox-wrap" style="margin-bottom: 12px;">
                        <input type="hidden" name="cancel_future_unpaid" value="0">
                        <input id="cancel_future_unpaid" type="checkbox" name="cancel_future_unpaid" value="1" checked>
                        <label for="cancel_future_unpaid">退去後の未入金予定を取消にする</label>
                    </div>

                    <div class="field field-full" style="margin-bottom: 16px;">
                        <label for="note">退去処理メモ</label>
                        <textarea id="note" name="note" placeholder="鍵返却、精算予定などを入力できます。">{{ old('note') }}</textarea>
                    </div>

                    <button type="submit" class="button" style="background: #dc2626;">退去処理を実行する</button>
                    <a
                        href="{{ route('rental-move-out-settlements.create', ['book_id' => $selectedBookId, 'rental_contract_id' => $selectedRentalContractId]) }}"
                        class="button button-secondary"
                    >
                        退去精算を登録
                    </a>
                 </form>
                </form>
            </div>

            <div class="card">
                <h3 style="margin-top: 0;">退去後の入金予定</h3>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>状態</th>
                            <th>対象年月</th>
                            <th>予定日</th>
                            <th>入金項目</th>
                            <th>入金口座</th>
                            <th>予定額</th>
                            <th>入金済</th>
                            <th>予定状態</th>
                            <th>入金実績</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scheduleRows as $row)
                            <tr>
                                <td>
                                    @if ($row->is_protected)
                                        <span style="color: #dc2626;">保護</span>
                                    @else
                                        <span style="color: #f97316;">取消候補</span>
                                    @endif
                                </td>
                                <td>{{ $row->target_year_month }}</td>
                                <td>{{ $row->due_on }}</td>
                                <td>{{ $row->payment_item_name ?? '—' }}</td>
                                <td>{{ $row->payment_account_name ?? '—' }}</td>
                                <td style="text-align: right;">{{ number_format((float) $row->expected_amount, 2) }}</td>
                                <td style="text-align: right;">{{ number_format((float) $row->received_amount, 2) }}</td>
                                <td>{{ $scheduleStatusLabels[$row->status] ?? $row->status }}</td>
                                <td>{{ $row->receipts_count }} 件</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">退去後に対象となる入金予定はありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection