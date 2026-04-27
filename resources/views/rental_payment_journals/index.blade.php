@extends('layouts.app')

@section('title', '賃貸仕訳処理')

@section('content')
    @php
        $receiptStatusLabels = [
            'confirmed' => '確定',
            'cancelled' => '取消',
        ];

        $eligibleCount = $paymentReceipts->filter(function ($paymentReceipt) {
            $paymentAccount = $paymentReceipt->paymentAccount ?? $paymentReceipt->paymentSchedule?->paymentAccount;
            $paymentItem = $paymentReceipt->paymentItem;

            return $paymentReceipt->status === 'confirmed'
                && $paymentReceipt->journal_entry_id === null
                && $paymentAccount?->accountTitle !== null
                && $paymentItem?->accountTitle !== null;
        })->count();
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">賃貸仕訳処理</h2>
            <p class="page-description">入金実績から賃貸入金の会計仕訳を作成・取消します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('payment-receipts.index', ['book_id' => $selectedBookId]) : route('payment-receipts.index') }}"
                class="button button-secondary"
            >
                入金一覧へ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、確定済の入金1件につき、借方1行・貸方1行の仕訳を作成します。
        今回から、選択中の帳簿について未作成の賃貸仕訳を一括作成できます。
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('rental-payment-journals.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿で絞り込み</label>
                    <select id="book_id" name="book_id">
                        <option value="">すべて表示</option>
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
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('rental-payment-journals.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <div class="form-grid">
            <div class="field">
                <label>表示中の対象入金件数</label>
                <div>{{ $paymentReceipts->count() }} 件</div>
            </div>

            <div class="field">
                <label>一括作成できる件数</label>
                <div style="{{ $eligibleCount > 0 ? 'color: #166534;' : '' }}">
                    {{ $eligibleCount }} 件
                </div>
            </div>
        </div>

        <div class="actions" style="margin-top: 16px;">
            @if ($selectedBookId)
                <form
                    method="POST"
                    action="{{ route('rental-payment-journals.bulk-store') }}"
                    onsubmit="return confirm('選択中の帳簿について、未作成の賃貸入金仕訳を一括作成しますか？');"
                    style="display: inline-block; margin: 0;"
                >
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                    <button
                        type="submit"
                        class="button"
                        {{ $eligibleCount === 0 ? 'disabled' : '' }}
                    >
                        未作成仕訳を一括作成
                    </button>
                </form>
            @else
                <div class="muted">
                    一括作成を行う場合は、まず帳簿で絞り込んでください。
                </div>
            @endif
        </div>
    </div>

    <div class="card">
        <p class="muted">対象入金件数: {{ $paymentReceipts->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>入金日</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>入金口座</th>
                    <th>入金額</th>
                    <th>仕訳内容</th>
                    <th>仕訳状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paymentReceipts as $paymentReceipt)
                    @php
                        $paymentAccount = $paymentReceipt->paymentAccount ?? $paymentReceipt->paymentSchedule?->paymentAccount;
                        $paymentItem = $paymentReceipt->paymentItem;

                        $hasDebitAccount = $paymentAccount?->accountTitle !== null;
                        $hasCreditAccount = $paymentItem?->accountTitle !== null;

                        $canCreateJournal =
                            $paymentReceipt->status === 'confirmed'
                            && $paymentReceipt->journal_entry_id === null
                            && $hasDebitAccount
                            && $hasCreditAccount;

                        $canCancelJournal =
                            $paymentReceipt->journalEntry !== null
                            && $paymentReceipt->journalEntry->entry_type === 'rental_payment';
                    @endphp

                    <tr>
                        <td>{{ $paymentReceipt->id }}</td>
                        <td>{{ $paymentReceipt->received_on?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            {{ $paymentReceipt->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $paymentReceipt->contractTenant?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $paymentReceipt->rentalContract?->property?->property_code ?? '—' }}
                            /
                            {{ $paymentReceipt->rentalContract?->property?->name ?? '—' }}
                            @if ($paymentReceipt->rentalContract?->propertyUnit)
                                <div class="muted">
                                    部屋: {{ $paymentReceipt->rentalContract->propertyUnit->unit_no }}
                                </div>
                            @endif
                        </td>
                        <td>
                            {{ $paymentItem?->item_code ?? '—' }}
                            /
                            {{ $paymentItem?->name ?? '—' }}
                        </td>
                        <td>
                            @if ($paymentAccount)
                                {{ $paymentAccount->account_code }}
                                /
                                {{ $paymentAccount->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ number_format((float) $paymentReceipt->amount, 2) }}</td>
                        <td>
                            <div>
                                借方:
                                @if ($paymentAccount?->accountTitle)
                                    {{ $paymentAccount->accountTitle->account_code }}
                                    {{ $paymentAccount->accountTitle->name }}
                                    @if ($paymentAccount->subAccountTitle)
                                        <div class="muted">
                                            補助:
                                            {{ $paymentAccount->subAccountTitle->sub_account_code }}
                                            {{ $paymentAccount->subAccountTitle->name }}
                                        </div>
                                    @endif
                                @else
                                    <span style="color:#dc2626;">未設定</span>
                                @endif
                            </div>

                            <div style="margin-top: 8px;">
                                貸方:
                                @if ($paymentItem?->accountTitle)
                                    {{ $paymentItem->accountTitle->account_code }}
                                    {{ $paymentItem->accountTitle->name }}
                                    @if ($paymentItem->subAccountTitle)
                                        <div class="muted">
                                            補助:
                                            {{ $paymentItem->subAccountTitle->sub_account_code }}
                                            {{ $paymentItem->subAccountTitle->name }}
                                        </div>
                                    @endif
                                @else
                                    <span style="color:#dc2626;">未設定</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($paymentReceipt->journalEntry)
                                作成済
                                <div class="muted">
                                    仕訳ID: {{ $paymentReceipt->journalEntry->id }}
                                    @if ($paymentReceipt->journalEntry->voucher_no)
                                        / 伝票番号: {{ $paymentReceipt->journalEntry->voucher_no }}
                                    @endif
                                </div>
                            @elseif ($paymentReceipt->journal_entry_id !== null)
                                紐づけ不整合
                                <div class="muted">
                                    仕訳ID {{ $paymentReceipt->journal_entry_id }} が見つかりません。取消で紐づけを解除できます。
                                </div>
                            @elseif ($paymentReceipt->status !== 'confirmed')
                                作成不可
                                <div class="muted">
                                    入金状態: {{ $receiptStatusLabels[$paymentReceipt->status] ?? $paymentReceipt->status }}
                                </div>
                            @elseif (!$hasDebitAccount || !$hasCreditAccount)
                                作成不可
                                <div class="muted">会計科目の紐づけが不足しています。</div>
                            @else
                                未作成
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                @if ($paymentReceipt->journalEntry)
                                    <a
                                        href="{{ route('journal-entries.edit', $paymentReceipt->journalEntry) }}"
                                        class="button button-secondary"
                                    >
                                        仕訳を見る
                                    </a>

                                    @if ($canCancelJournal)
                                        <form
                                            method="POST"
                                            action="{{ route('rental-payment-journals.destroy', $paymentReceipt) }}"
                                            onsubmit="return confirm('この入金から作成した賃貸仕訳を取り消しますか？');"
                                            style="display: inline-block; margin: 0;"
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="button"
                                                style="background: #dc2626;"
                                            >
                                                仕訳取消
                                            </button>
                                        </form>
                                    @endif
                                @elseif ($paymentReceipt->journal_entry_id !== null)
                                    <form
                                        method="POST"
                                        action="{{ route('rental-payment-journals.destroy', $paymentReceipt) }}"
                                        onsubmit="return confirm('見つからない仕訳との紐づけを解除しますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="button"
                                            style="background: #dc2626;"
                                        >
                                            紐づけ解除
                                        </button>
                                    </form>
                                @elseif ($canCreateJournal)
                                    <form
                                        method="POST"
                                        action="{{ route('rental-payment-journals.store', $paymentReceipt) }}"
                                        onsubmit="return confirm('この入金から賃貸仕訳を作成しますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        <button type="submit" class="button">
                                            仕訳作成
                                        </button>
                                    </form>
                                @else
                                    <span class="muted">対応不要</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">入金実績がありません。先に入金を登録してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection