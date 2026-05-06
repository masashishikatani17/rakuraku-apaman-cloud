@extends('layouts.app')

@section('title', '入金口座一覧')

@section('content')
    @php
        $accountTypeLabels = [
            'ordinary' => '普通',
            'current' => '当座',
            'savings' => '貯蓄',
            'other' => 'その他',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">入金口座一覧</h2>
            <p class="page-description">Access の MT_入金口座 に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('payment-accounts.create', ['book_id' => $selectedBookId]) : route('payment-accounts.create') }}"
                class="button"
            >
                入金口座を新規登録
            </a>
            <a
                href="{{ (isset($selectedBookId) && $selectedBookId) ? route('master-menu.index', ['book_id' => $selectedBookId]) : route('master-menu.index') }}"
                class="button button-secondary"
            >
                マスタメニューへ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('payment-accounts.index') }}">
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
                <a href="{{ route('payment-accounts.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $paymentAccounts->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主 / 帳簿</th>
                    <th>入金口座CODE</th>
                    <th>入金口座名</th>
                    <th>金融機関</th>
                    <th>口座情報</th>
                    <th>会計科目</th>
                    <th>補助科目</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paymentAccounts as $paymentAccount)
                    <tr>
                        <td>{{ $paymentAccount->id }}</td>
                        <td>
                            {{ $paymentAccount->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $paymentAccount->book?->name ?? '—' }}
                        </td>
                        <td>{{ $paymentAccount->account_code }}</td>
                        <td>{{ $paymentAccount->name }}</td>
                        <td>
                            {{ $paymentAccount->bank_name ?: '—' }}
                            @if ($paymentAccount->branch_name)
                                <div class="muted">支店: {{ $paymentAccount->branch_name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $accountTypeLabels[$paymentAccount->account_type] ?? ($paymentAccount->account_type ?: '—') }}
                            @if ($paymentAccount->account_number)
                                <div class="muted">番号: {{ $paymentAccount->account_number }}</div>
                            @endif
                            @if ($paymentAccount->account_holder)
                                <div class="muted">名義: {{ $paymentAccount->account_holder }}</div>
                            @endif
                        </td>
                        <td>
                            @if ($paymentAccount->accountTitle)
                                {{ $paymentAccount->accountTitle->account_code }} / {{ $paymentAccount->accountTitle->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($paymentAccount->subAccountTitle)
                                {{ $paymentAccount->subAccountTitle->sub_account_code }} / {{ $paymentAccount->subAccountTitle->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $paymentAccount->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('payment-accounts.edit', $paymentAccount) }}" class="button button-secondary">修正</a>

                                <form
                                    method="POST"
                                    action="{{ route('payment-accounts.destroy', $paymentAccount) }}"
                                    onsubmit="return confirm('この入金口座を削除しますか？');"
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
                        <td colspan="10">まだ入金口座が登録されていません。「入金口座を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection