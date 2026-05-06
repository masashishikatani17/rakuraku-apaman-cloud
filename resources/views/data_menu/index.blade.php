@extends('layouts.app')

@section('title', 'データメニュー')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">データメニュー</h2>
            <p class="page-description">Access版のデータ選択・データ新規作成・データ変更・データ年度繰越に近い導線です。</p>
        </div>
        <div class="actions">
            <a href="{{ route('main-menu.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">メインメニューへ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        帳簿選択や年度繰越など、Access版でデータ管理系に分かれていた入口をまとめています。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('data-menu.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">対象帳簿</label>
                    <select id="book_id" name="book_id">
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                （{{ $book->period_start_date?->format('Y-m-d') ?? '開始未設定' }}〜{{ $book->period_end_date?->format('Y-m-d') ?? '終了未設定' }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($selectedBook)
                    <div class="field">
                        <label>選択中の帳簿</label>
                        <div>
                            {{ $selectedBook->book_code ?: 'コード未設定' }}
                            /
                            {{ $selectedBook->name }}
                            /
                            {{ $selectedBook->status }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">帳簿を切り替える</button>
            </div>
        </form>
    </div>

    @foreach ($menuGroups as $group)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">{{ $group['title'] }}</h3>
            <p class="muted">{{ $group['description'] }}</p>

            <div class="actions" style="gap: 8px; flex-wrap: wrap;">
                @foreach ($group['items'] as $item)
                    @if (Route::has($item['route_name']))
                        <a
                            href="{{ route($item['route_name'], $item['params']) }}"
                            class="button button-secondary"
                        >
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span class="button button-secondary" style="opacity: .45;">
                            {{ $item['label'] }}（未実装）
                        </span>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach
@endsection