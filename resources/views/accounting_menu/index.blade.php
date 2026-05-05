@extends('layouts.app')

@section('title', '会計管理メニュー')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">会計管理メニュー</h2>
            <p class="page-description">Access版の FN_会計管理 に近い導線で、会計入力・帳簿・推移表を開きます。</p>
        </div>
        <div class="actions">
            <a href="{{ route('work-menu.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">メインメニューへ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        決算・申告、年度繰越、消費税、賃貸管理は別メニューに分けています。
        この画面は、Access版の会計管理直下に近い「仕訳・出納帳・元帳・試算表・推移表」を中心にしています。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('accounting-menu.index') }}">
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