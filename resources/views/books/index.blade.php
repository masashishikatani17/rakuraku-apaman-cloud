@extends('layouts.app')

@section('title', '帳簿一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">帳簿一覧</h2>
            <p class="page-description">事業主ごとの帳簿を一覧表示します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBusinessOwnerId ? route('books.create', ['business_owner_id' => $selectedBusinessOwnerId]) : route('books.create') }}"
                class="button"
            >
                帳簿を新規登録
            </a>
            <a href="{{ route('business-owners.index') }}" class="button button-secondary">事業主一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('books.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="business_owner_id">事業主で絞り込み</label>
                    <select id="business_owner_id" name="business_owner_id">
                        <option value="">すべて表示</option>
                        @foreach ($businessOwners as $businessOwner)
                            <option
                                value="{{ $businessOwner->id }}"
                                {{ (string) $selectedBusinessOwnerId === (string) $businessOwner->id ? 'selected' : '' }}
                            >
                                {{ $businessOwner->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('books.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $books->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名</th>
                    <th>帳簿コード</th>
                    <th>帳簿名</th>
                    <th>会計期間</th>
                    <th>状態</th>
                    <th>決算月</th>
                    <th>所有者数</th>
                    <th>物件区分数</th>
                    <th>物件数</th>
                    <th>契約者数</th>
                    <th>契約数</th>
                    <th>入金項目数</th>
                    <th>入金口座数</th>
                    <th>入金予定数</th>
                    <th>入金数</th>
                    <th>勘定科目数</th>
                    <th>摘要数</th>
                    <th>部門数</th>
                    <th>仕訳数</th>
                    <th>使用中</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $book->id }}</td>
                        <td>{{ $book->businessOwner?->name ?? '—' }}</td>
                        <td>{{ $book->book_code ?: '—' }}</td>
                        <td>{{ $book->name }}</td>
                        <td>
                            {{ $book->period_start_date?->format('Y-m-d') ?? '—' }}
                            〜
                            {{ $book->period_end_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>
                            @if ($book->status === 'open')
                                運用中
                            @elseif ($book->status === 'closed')
                                締了
                            @else
                                下書き
                            @endif
                        </td>
                        <td>{{ $book->setting?->closing_month ? $book->setting->closing_month . '月' : '—' }}</td>
                        <td>{{ $book->property_owners_count }} 件</td>
                        <td>{{ $book->property_categories_count }} 件</td>
                        <td>{{ $book->properties_count }} 件</td>
                        <td>{{ $book->contract_tenants_count }} 件</td>
                        <td>{{ $book->rental_contracts_count }} 件</td>
                        <td>{{ $book->payment_items_count }} 件</td>
                        <td>{{ $book->payment_accounts_count }} 件</td>
                        <td>{{ $book->payment_schedules_count }} 件</td>
                        <td>{{ $book->payment_receipts_count }} 件</td>
                        <td>{{ $book->account_titles_count }} 件</td>
                        <td>{{ $book->journal_descriptions_count }} 件</td>
                        <td>{{ $book->departments_count }} 件</td>
                        <td>{{ $book->journal_entries_count }} 件</td>
                        <td>{{ $book->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('property-owners.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    所有者一覧
                                </a>
                                <a
                                    href="{{ route('property-owners.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    所有者登録
                                </a>
                                <a
                                    href="{{ route('property-categories.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    物件区分一覧
                                </a>
                                <a
                                    href="{{ route('property-categories.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    物件区分登録
                                </a>
                                <a
                                    href="{{ route('properties.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    物件一覧
                                </a>
                                <a
                                    href="{{ route('properties.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    物件登録
                                </a>
                                <a
                                    href="{{ route('contract-tenants.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    契約者台帳
                                </a>
                                <a
                                    href="{{ route('contract-tenants.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    契約者登録
                                </a>
                                <a
                                    href="{{ route('payment-items.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    入金項目一覧
                                </a>
                                <a
                                    href="{{ route('payment-items.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    入金項目登録
                                </a>
                                <a
                                    href="{{ route('payment-accounts.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    入金口座一覧
                                </a>
                                <a
                                    href="{{ route('payment-accounts.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    入金口座登録
                                </a>
                                <a
                                    href="{{ route('payment-schedules.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    入金予定一覧
                                </a>
                                <a
                                    href="{{ route('payment-schedules.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    入金予定登録
                                </a>
                                <a
                                    href="{{ route('payment-receipts.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    入金一覧
                                </a>
                                <a
                                    href="{{ route('payment-receipts.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    入金登録
                                </a>
                                <a
                                    href="{{ route('account-titles.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    勘定科目一覧
                                </a>
                                <a
                                    href="{{ route('account-titles.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    勘定科目登録
                                </a>
                                <a
                                    href="{{ route('journal-descriptions.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    摘要一覧
                                </a>
                                <a
                                    href="{{ route('journal-descriptions.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    摘要登録
                                </a>
                                <a
                                    href="{{ route('departments.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    部門一覧
                                </a>
                                <a
                                    href="{{ route('departments.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    部門登録
                                </a>
                                <a
                                    href="{{ route('journal-entries.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    仕訳一覧
                                </a>
                                <a
                                    href="{{ route('journal-entries.create', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    仕訳登録
                                </a>
                                <a
                                    href="{{ route('trial-balances.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    残高試算表
                                </a>
                                <a
                                    href="{{ route('general-ledgers.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    総勘定元帳
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="22">まだ帳簿が登録されていません。「帳簿を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection