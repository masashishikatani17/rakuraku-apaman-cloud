@extends('layouts.app')

@section('title', '補助科目一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">補助科目一覧</h2>
            <p class="page-description">補助科目マスタを一覧表示します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('sub-account-titles.create', ['book_id' => $selectedBookId, 'account_title_id' => $selectedAccountTitleId]) }}"
                class="button"
            >
                補助科目を新規登録
            </a>
            <a
                href="{{ $selectedBookId ? route('reports.sub-accounts.index', ['book_id' => $selectedBookId]) : route('reports.sub-accounts.index') }}"
                class="button button-secondary"
            >
                補助科目一覧表
            </a>
            <a
                href="{{ $selectedBookId ? route('sub-account-ledgers.index', ['book_id' => $selectedBookId]) : route('sub-account-ledgers.index') }}"
                class="button button-secondary"
            >
                補助科目別元帳
            </a>
            <a
                href="{{ $selectedBookId ? route('account-titles.index', ['book_id' => $selectedBookId]) : route('account-titles.index') }}"
                class="button button-secondary"
            >
                勘定科目一覧へ戻る
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('sub-account-titles.index') }}">
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

                <div class="field">
                    <label for="account_title_id">勘定科目で絞り込み</label>
                    <select id="account_title_id" name="account_title_id">
                        <option value="">すべて表示</option>
                        @foreach ($accountTitles as $accountTitle)
                            <option
                                value="{{ $accountTitle->id }}"
                                {{ (string) $selectedAccountTitleId === (string) $accountTitle->id ? 'selected' : '' }}
                            >
                                {{ ($accountTitle->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($accountTitle->book?->name ?? '帳簿未設定') . ' / ' . $accountTitle->account_code . ' ' . $accountTitle->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('sub-account-titles.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $subAccountTitles->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名</th>
                    <th>帳簿名</th>
                    <th>勘定科目</th>
                    <th>補助科目コード</th>
                    <th>補助科目名</th>
                    <th>並び順</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($subAccountTitles as $subAccountTitle)
                    <tr>
                        <td>{{ $subAccountTitle->id }}</td>
                        <td>{{ $subAccountTitle->accountTitle?->book?->businessOwner?->name ?? '—' }}</td>
                        <td>{{ $subAccountTitle->accountTitle?->book?->name ?? '—' }}</td>
                        <td>
                            {{ $subAccountTitle->accountTitle?->account_code ?? '—' }}
                            {{ $subAccountTitle->accountTitle?->name ?? '' }}
                        </td>
                        <td>{{ $subAccountTitle->sub_account_code }}</td>
                        <td>{{ $subAccountTitle->name }}</td>
                        <td>{{ $subAccountTitle->sort_order }}</td>
                        <td>{{ $subAccountTitle->is_active ? '有効' : '停止' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">まだ補助科目が登録されていません。「補助科目を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection