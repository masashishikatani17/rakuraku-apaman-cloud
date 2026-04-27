@extends('layouts.app')

@section('title', '入金予定一覧')

@section('content')
    @php
        $statusLabels = [
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">入金予定一覧</h2>
            <p class="page-description">契約者ごとの入金予定を一覧表示します。</p>
        </div>
        <div class="actions">
            <a href="{{ $selectedBookId ? route('payment-schedules.create', ['book_id' => $selectedBookId]) : route('payment-schedules.create') }}" class="button">入金予定を新規登録</a>
            <a
                href="{{ $selectedBookId ? route('monthly-payment-schedules.create', ['book_id' => $selectedBookId]) : route('monthly-payment-schedules.create') }}"
                class="button"
            >
                月次入金予定生成
            </a>
            <a
                href="{{ $selectedBookId ? route('reports.property-payments.index', ['book_id' => $selectedBookId]) : route('reports.property-payments.index') }}"
                class="button button-secondary"
            >
                物件別入金一覧表
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
        <form method="GET" action="{{ route('payment-schedules.index') }}">
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

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('payment-schedules.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $paymentSchedules->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>予定日</th>
                    <th>対象年月</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>予定金額</th>
                    <th>入金済</th>
                    <th>残額</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paymentSchedules as $paymentSchedule)
                    @php
                        $remaining = (float) $paymentSchedule->expected_amount - (float) $paymentSchedule->received_amount;
                    @endphp
                    <tr>
                        <td>{{ $paymentSchedule->id }}</td>
                        <td>{{ $paymentSchedule->due_on?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $paymentSchedule->target_year_month }}</td>
                        <td>
                            {{ $paymentSchedule->contractTenant?->tenant_code ?? '—' }}
                            /
                            {{ $paymentSchedule->contractTenant?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $paymentSchedule->rentalContract?->property?->property_code ?? '—' }}
                            /
                            {{ $paymentSchedule->rentalContract?->property?->name ?? '—' }}
                            @if ($paymentSchedule->rentalContract?->propertyUnit)
                                <div class="muted">部屋: {{ $paymentSchedule->rentalContract->propertyUnit->unit_no }}</div>
                            @endif
                        </td>
                        <td>{{ $paymentSchedule->paymentItem?->name ?? '—' }}</td>
                        <td>{{ number_format((float) $paymentSchedule->expected_amount, 2) }}</td>
                        <td>{{ number_format((float) $paymentSchedule->received_amount, 2) }}</td>
                        <td>{{ number_format(max($remaining, 0), 2) }}</td>
                        <td>{{ $statusLabels[$paymentSchedule->status] ?? $paymentSchedule->status }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('payment-receipts.create', ['book_id' => $paymentSchedule->book_id]) }}" class="button">入金登録</a>
                                <a href="{{ route('payment-schedules.edit', $paymentSchedule) }}" class="button button-secondary">修正</a>
                                <form method="POST" action="{{ route('payment-schedules.destroy', $paymentSchedule) }}" onsubmit="return confirm('この入金予定を削除しますか？');" style="display:inline-block; margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background:#dc2626;">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">まだ入金予定が登録されていません。「入金予定を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection