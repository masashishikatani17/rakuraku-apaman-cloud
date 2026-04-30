@extends('layouts.app')

@section('title', '退去精算詳細')

@section('content')
    @php
        $depositRows = [
            ['label' => '敷金', 'amount' => (float) $settlement->deposit_amount],
            ['label' => '保証金', 'amount' => (float) $settlement->guarantee_deposit_amount],
            ['label' => '前受・預り家賃等', 'amount' => (float) $settlement->prepaid_rent_amount],
        ];

        $chargeRows = [
            ['label' => '未収家賃', 'amount' => (float) $settlement->unpaid_rent_amount],
            ['label' => '原状回復費', 'amount' => (float) $settlement->restoration_cost_amount],
            ['label' => 'クリーニング費用', 'amount' => (float) $settlement->cleaning_cost_amount],
            ['label' => '鍵交換費用', 'amount' => (float) $settlement->key_replacement_cost_amount],
            ['label' => 'その他請求額', 'amount' => (float) $settlement->other_charge_amount],
            ['label' => '振込手数料等', 'amount' => (float) $settlement->refund_transfer_fee_amount],
        ];

        $depositTotal = $settlement->totalDepositAmount();
        $chargeTotal = $settlement->totalChargeAmount();
        $balance = round($depositTotal - $chargeTotal, 2);

        $journalSideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];

        $statusColors = [
            'draft' => '#6b7280',
            'confirmed' => '#166534',
            'cancelled' => '#dc2626',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">退去精算詳細</h2>
            <p class="page-description">退去精算の内訳、返還額・追加請求額、作成済み仕訳を確認します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $settlement->book_id]) }}" class="button button-secondary">退去精算一覧へ戻る</a>
            <a href="{{ route('rental-move-out-settlements.edit', $settlement) }}" class="button button-secondary">退去精算を修正</a>
            @if ($settlement->journalEntry)
                <a href="{{ route('journal-entries.index', ['book_id' => $settlement->book_id]) }}" class="button button-secondary">仕訳一覧へ</a>
            @else
                <a href="{{ route('rental-move-out-settlements.journal.create', $settlement) }}" class="button">退去精算仕訳を作成</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">基本情報</h3>

        <div class="form-grid">
            <div class="field">
                <label>状態</label>
                <div style="color: {{ $statusColors[$settlement->status] ?? '#111827' }};">
                    {{ $settlement->statusLabel() }}
                </div>
            </div>

            <div class="field">
                <label>精算日</label>
                <div>{{ $settlement->settlement_on?->format('Y-m-d') ?? '—' }}</div>
            </div>

            <div class="field">
                <label>退去日</label>
                <div>{{ $settlement->move_out_on?->format('Y-m-d') ?? '—' }}</div>
            </div>

            <div class="field">
                <label>帳簿</label>
                <div>{{ ($settlement->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($settlement->book?->name ?? '帳簿不明') }}</div>
            </div>

            <div class="field">
                <label>契約者</label>
                <div>
                    {{ $settlement->rentalContract?->contractTenant?->tenant_code ?? '—' }}
                    /
                    {{ $settlement->rentalContract?->contractTenant?->name ?? '—' }}
                </div>
            </div>

            <div class="field">
                <label>物件・部屋</label>
                <div>
                    {{ $settlement->rentalContract?->property?->property_code ?? '—' }}
                    /
                    {{ $settlement->rentalContract?->property?->name ?? '—' }}
                    @if ($settlement->rentalContract?->propertyUnit?->unit_no)
                        {{ $settlement->rentalContract->propertyUnit->unit_no }}
                    @endif
                </div>
            </div>
        </div>

        @if ($settlement->note)
            <div class="field field-full" style="margin-top: 16px;">
                <label>備考</label>
                <div class="muted" style="white-space: pre-wrap;">{{ $settlement->note }}</div>
            </div>
        @endif
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">精算結果</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>区分</th>
                    <th>金額</th>
                    <th>説明</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>預り金等合計</td>
                    <td style="text-align: right;">{{ number_format($depositTotal, 2) }}</td>
                    <td>敷金 + 保証金 + 前受・預り家賃等</td>
                </tr>
                <tr>
                    <td>請求控除合計</td>
                    <td style="text-align: right;">{{ number_format($chargeTotal, 2) }}</td>
                    <td>未収家賃 + 原状回復費 + クリーニング費用 + その他請求等</td>
                </tr>
                <tr>
                    <td>差額</td>
                    <td style="text-align: right; {{ $balance >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format($balance, 2) }}
                    </td>
                    <td>預り金等合計 - 請求控除合計</td>
                </tr>
                <tr>
                    <td><strong>返還額</strong></td>
                    <td style="text-align: right; color: #166534;">
                        <strong>{{ number_format((float) $settlement->refund_amount, 2) }}</strong>
                    </td>
                    <td>差額がプラスの場合に返還します。</td>
                </tr>
                <tr>
                    <td><strong>追加請求額</strong></td>
                    <td style="text-align: right; color: #dc2626;">
                        <strong>{{ number_format((float) $settlement->additional_billing_amount, 2) }}</strong>
                    </td>
                    <td>差額がマイナスの場合に追加請求します。</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">預り金・返還原資の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>金額</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($depositRows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>合計</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format($depositTotal, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">請求・控除の内訳</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>金額</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($chargeRows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row['amount'], 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>合計</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format($chargeTotal, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">会計仕訳</h3>

        @if ($settlement->journalEntry)
            <div class="form-grid" style="margin-bottom: 16px;">
                <div class="field">
                    <label>仕訳ID</label>
                    <div>{{ $settlement->journalEntry->id }}</div>
                </div>

                <div class="field">
                    <label>仕訳日</label>
                    <div>{{ $settlement->journalEntry->entry_date?->format('Y-m-d') ?? '—' }}</div>
                </div>

                <div class="field">
                    <label>伝票番号</label>
                    <div>{{ $settlement->journalEntry->voucher_no ?: '—' }}</div>
                </div>

                <div class="field">
                    <label>摘要</label>
                    <div>{{ $settlement->journalEntry->description_text }}</div>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>借貸</th>
                        <th>科目</th>
                        <th>物件</th>
                        <th>金額</th>
                        <th>行備考</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($settlement->journalEntry->lines as $line)
                        <tr>
                            <td>{{ $journalSideLabels[$line->side] ?? $line->side }}</td>
                            <td>
                                {{ $line->accountTitle?->account_code ?? '—' }}
                                /
                                {{ $line->accountTitle?->name ?? '—' }}
                            </td>
                            <td>
                                @if ($line->property)
                                    {{ $line->property->property_code }} / {{ $line->property->name }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td style="text-align: right;">{{ number_format((float) $line->amount, 2) }}</td>
                            <td>{{ $line->line_note ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <form
                method="POST"
                action="{{ route('rental-move-out-settlements.journal.destroy', $settlement) }}"
                onsubmit="return confirm('この退去精算仕訳を取り消しますか？');"
                style="margin-top: 16px;"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="button" style="background: #f97316;">仕訳取消</button>
            </form>
        @else
            <div class="alert alert-success" style="background: #f8fafc; color: #334155; border-color: #cbd5e1;">
                まだ退去精算仕訳は作成されていません。精算内容を確認してから仕訳作成してください。
            </div>

            <a href="{{ route('rental-move-out-settlements.journal.create', $settlement) }}" class="button">
                退去精算仕訳を作成
            </a>
        @endif
    </div>
@endsection