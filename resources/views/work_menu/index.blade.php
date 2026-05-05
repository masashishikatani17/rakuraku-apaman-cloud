@extends('layouts.app')

@section('title', '業務メニュー')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">業務メニュー</h2>
            <p class="page-description">Access版のメインメニューに近い導線で、業務別に画面を開きます。</p>
        </div>
        <div class="actions">
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
            <a href="{{ route('business-owners.index') }}" class="button button-secondary">事業主一覧へ</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は、Access版の「メインメニュー → 会計管理 → 各画面」という導線に寄せるための入口です。
        見た目や並び順は後で調整しやすいよう、まずは業務分類と画面遷移を優先しています。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('work-menu.index') }}">
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