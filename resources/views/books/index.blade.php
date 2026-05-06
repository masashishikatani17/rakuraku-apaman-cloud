@extends('layouts.app')

@section('title', '帳簿一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">帳簿一覧</h2>
            <p class="page-description">Access版のデータ選択に近い、帳簿を選ぶための入口です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('main-menu.index') }}"
                class="button"
            >
                メインメニューへ
            </a>
            <a
                href="{{ $selectedBusinessOwnerId ? route('books.create', ['business_owner_id' => $selectedBusinessOwnerId]) : route('books.create') }}"
                class="button button-secondary"
            >
                帳簿を新規登録
            </a>
            <a
                href="{{ route('closing.book-locks.index', $selectedBusinessOwnerId ? ['business_owner_id' => $selectedBusinessOwnerId] : []) }}"
                class="button button-secondary"
            >
                年度締め・帳簿ロック
            </a>
            <a href="{{ route('business-owners.index') }}" class="button button-secondary">事業主一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        各業務画面へは「メインメニュー」から進みます。
        この画面では、帳簿の選択、状態確認、新規作成、年度締めへの導線だけを残しています。
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
                    <th>事業主</th>
                    <th>帳簿</th>
                    <th>会計期間</th>
                    <th>状態</th>
                    <th>決算月</th>
                    <th>主要件数</th>
                    <th>使用中</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $book)
                    <tr>
                        <td>{{ $book->id }}</td>
                        <td>{{ $book->businessOwner?->name ?? '—' }}</td>
                        <td>
                            <div>{{ $book->book_code ?: '—' }}</div>
                            <div class="muted">{{ $book->name }}</div>
                        </td>
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
                        <td>
                            <div>物件 {{ $book->properties_count }} 件 / 契約 {{ $book->rental_contracts_count }} 件</div>
                            <div>入金予定 {{ $book->payment_schedules_count }} 件 / 入金 {{ $book->payment_receipts_count }} 件</div>
                            <div>勘定科目 {{ $book->account_titles_count }} 件 / 仕訳 {{ $book->journal_entries_count }} 件</div>
                        </td>
                        <td>{{ $book->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('main-menu.index', ['book_id' => $book->id]) }}"
                                    class="button"
                                >
                                    メインメニュー
                                </a>
                                <a
                                    href="{{ route('opening-balances.index', ['book_id' => $book->id]) }}"
                                    class="button button-secondary"
                                >
                                    開始残高
                                </a>
                                <a
                                    href="{{ route('closing.book-locks.index', ['business_owner_id' => $book->business_owner_id]) }}"
                                    class="button button-secondary"
                                >
                                    年度締め
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">まだ帳簿が登録されていません。「帳簿を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection