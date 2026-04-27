@extends('layouts.app')

@section('title', '入金一覧')

@section('content')
    @php
        $statusLabels = [
            'confirmed' => '確定',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">入金一覧</h2>
            <p class="page-description">入金予定に対して登録された入金実績を一覧表示します。</p>
        </div>
        <div class="actions">
            <a href="{{ $selectedBookId ? route('payment-receipts.create', ['book_id' => $selectedBookId]) : route('payment-receipts.create') }}" class="button">入金を新規登録</a>
            <a
                href="{{ $selectedBookId ? route('rental-payment-journals.index', ['book_id' => $selectedBookId]) : route('rental-payment-journals.index') }}"
                class="button button-secondary"
            >
                賃貸仕訳処理へ
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('payment-receipts.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿で絞り込み</label>
                    <select id="book_id" name="book_id">
                        <option value="">すべて表示</option>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top:16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('payment-receipts.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $paymentReceipts->count() }} 件</p>

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
                    <th>入金者名</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paymentReceipts as $paymentReceipt)
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
                                <div class="muted">部屋: {{ $paymentReceipt->rentalContract->propertyUnit->unit_no }}</div>
                            @endif
                        </td>
                        <td>{{ $paymentReceipt->paymentItem?->name ?? '—' }}</td>
                        <td>{{ $paymentReceipt->paymentAccount?->name ?? '—' }}</td>
                        <td>{{ number_format((float) $paymentReceipt->amount, 2) }}</td>
                        <td>{{ $paymentReceipt->payer_name ?: '—' }}</td>
                        <td>{{ $statusLabels[$paymentReceipt->status] ?? $paymentReceipt->status }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('payment-receipts.edit', $paymentReceipt) }}" class="button button-secondary">修正</a>
                                <form method="POST" action="{{ route('payment-receipts.destroy', $paymentReceipt) }}" onsubmit="return confirm('この入金を削除しますか？');" style="display:inline-block; margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background:#dc2626;">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">まだ入金が登録されていません。「入金を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection