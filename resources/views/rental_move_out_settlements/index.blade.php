@extends('layouts.app')

@section('title', '退去精算一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">退去精算一覧</h2>
            <p class="page-description">敷金・保証金・原状回復費などの退去精算を一覧確認します。</p>
        </div>
        <div class="actions">
            <a href="{{ $selectedBookId ? route('rental-move-out-settlements.create', ['book_id' => $selectedBookId]) : route('rental-move-out-settlements.create') }}" class="button">退去精算を登録</a>
            <a href="{{ $selectedBookId ? route('rental-contract-move-outs.index', ['book_id' => $selectedBookId]) : route('rental-contract-move-outs.index') }}" class="button button-secondary">退去処理へ</a>
            <a href="{{ $selectedBookId ? route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) : route('reports.rental-contracts.index') }}" class="button button-secondary">賃貸条件一覧へ</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('rental-move-out-settlements.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿</label>
                    <select id="book_id" name="book_id">
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
                    <label for="status">状態</label>
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
                <a href="{{ $selectedBookId ? route('rental-move-out-settlements.index', ['book_id' => $selectedBookId]) : route('rental-move-out-settlements.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div class="form-grid">
            <div class="field">
                <label>精算件数</label>
                <div>{{ $summary['rows_count'] }} 件</div>
            </div>
            <div class="field">
                <label>確定</label>
                <div>{{ $summary['confirmed_count'] }} 件</div>
            </div>
            <div class="field">
                <label>預り金等合計</label>
                <div>{{ number_format((float) $summary['deposit_total'], 2) }}</div>
            </div>
            <div class="field">
                <label>請求控除合計</label>
                <div>{{ number_format((float) $summary['charge_total'], 2) }}</div>
            </div>
            <div class="field">
                <label>返還額合計</label>
                <div style="color: #166534;">{{ number_format((float) $summary['refund_total'], 2) }}</div>
            </div>
            <div class="field">
                <label>追加請求合計</label>
                <div style="color: #dc2626;">{{ number_format((float) $summary['additional_billing_total'], 2) }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>精算日</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>退去日</th>
                    <th>預り金等</th>
                    <th>請求控除</th>
                    <th>返還額</th>
                    <th>追加請求</th>
                    <th>仕訳</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($settlements as $settlement)
                    <tr>
                        <td>{{ $settlement->statusLabel() }}</td>
                        <td>{{ $settlement->settlement_on?->format('Y-m-d') }}</td>
                        <td>
                            {{ $settlement->rentalContract?->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $settlement->rentalContract?->contractTenant?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $settlement->rentalContract?->property?->property_code ?? '—' }}
                            /
                            {{ $settlement->rentalContract?->property?->name ?? '—' }}
                            @if ($settlement->rentalContract?->propertyUnit?->unit_no)
                                <div class="muted">部屋: {{ $settlement->rentalContract->propertyUnit->unit_no }}</div>
                            @endif
                        </td>
                        <td>{{ $settlement->move_out_on?->format('Y-m-d') ?? '—' }}</td>
                        <td style="text-align: right;">{{ number_format($settlement->totalDepositAmount(), 2) }}</td>
                        <td style="text-align: right;">{{ number_format($settlement->totalChargeAmount(), 2) }}</td>
                        <td style="text-align: right; color: #166534;">{{ number_format((float) $settlement->refund_amount, 2) }}</td>
                        <td style="text-align: right; color: #dc2626;">{{ number_format((float) $settlement->additional_billing_amount, 2) }}</td>
                        <td>
                            @if ($settlement->journalEntry)
                                <div>作成済 #{{ $settlement->journalEntry->id }}</div>
                                <div class="muted">{{ $settlement->journalEntry->voucher_no ?: '伝票番号なし' }}</div>
                            @else
                                <span class="muted">未作成</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('rental-move-out-settlements.show', $settlement) }}" class="button button-secondary">詳細</a>
                                @if ($settlement->journalEntry)
                                    <form
                                        method="POST"
                                        action="{{ route('rental-move-out-settlements.journal.destroy', $settlement) }}"
                                        onsubmit="return confirm('この退去精算仕訳を取り消しますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="button" style="background: #f97316;">仕訳取消</button>
                                    </form>
                                @else
                                    <a href="{{ route('rental-move-out-settlements.journal.create', $settlement) }}" class="button">仕訳作成</a>
                                @endif
                                <a href="{{ route('rental-move-out-settlements.edit', $settlement) }}" class="button button-secondary">修正</a>
                                <form
                                    method="POST"
                                    action="{{ route('rental-move-out-settlements.destroy', $settlement) }}"
                                    onsubmit="return confirm('この退去精算を削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background: #dc2626;">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">退去精算はまだ登録されていません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection