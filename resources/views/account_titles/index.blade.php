@extends('layouts.app')

@section('title', '勘定科目一覧')

@section('content')
    @php
        $categoryLabels = [
            'asset' => '資産',
            'liability' => '負債',
            'equity' => '純資産',
            'revenue' => '収益',
            'expense' => '費用',
        ];

        $normalBalanceLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">勘定科目一覧</h2>
            <p class="page-description">Access の T_勘定科目 に相当する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('account-titles.create', ['book_id' => $selectedBookId]) : route('account-titles.create') }}"
                class="button"
            >
                勘定科目を新規登録
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('account-titles.index') }}">
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
                <a href="{{ route('account-titles.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $accountTitles->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名</th>
                    <th>帳簿名</th>
                    <th>科目コード</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>通常残高</th>
                    <th>補助科目</th>
                    <th>並び順</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountTitles as $accountTitle)
                    <tr>
                        <td>{{ $accountTitle->id }}</td>
                        <td>{{ $accountTitle->book?->businessOwner?->name ?? '—' }}</td>
                        <td>{{ $accountTitle->book?->name ?? '—' }}</td>
                        <td>{{ $accountTitle->account_code }}</td>
                        <td>{{ $accountTitle->name }}</td>
                        <td>{{ $categoryLabels[$accountTitle->category] ?? $accountTitle->category }}</td>
                        <td>{{ $normalBalanceLabels[$accountTitle->normal_balance] ?? $accountTitle->normal_balance }}</td>
                        <td>{{ $accountTitle->allows_sub_account ? '使用する' : '使用しない' }}</td>
                        <td>{{ $accountTitle->sort_order }}</td>
                        <td>{{ $accountTitle->is_active ? '有効' : '停止' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">まだ勘定科目が登録されていません。「勘定科目を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection