@extends('layouts.app')

@section('title', '物件区分一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">物件区分一覧</h2>
            <p class="page-description">Access の MT_物件区分 に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('property-categories.create', ['book_id' => $selectedBookId]) : route('property-categories.create') }}"
                class="button"
            >
                物件区分を新規登録
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

    @if (session('error'))
        <div class="alert alert-error">
            {{ session('error') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('property-categories.index') }}">
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
                <a href="{{ route('property-categories.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $propertyCategories->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名 / 帳簿名</th>
                    <th>物件区分CODE</th>
                    <th>物件区分名</th>
                    <th>使用物件数</th>
                    <th>並び順</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($propertyCategories as $propertyCategory)
                    <tr>
                        <td>{{ $propertyCategory->id }}</td>
                        <td>
                            {{ $propertyCategory->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $propertyCategory->book?->name ?? '—' }}
                        </td>
                        <td>{{ $propertyCategory->category_code }}</td>
                        <td>{{ $propertyCategory->name }}</td>
                        <td>{{ $propertyCategory->properties_count }} 件</td>
                        <td>{{ $propertyCategory->sort_order }}</td>
                        <td>{{ $propertyCategory->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('property-categories.edit', $propertyCategory) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('property-categories.destroy', $propertyCategory) }}"
                                    onsubmit="return confirm('この物件区分を削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="button"
                                        style="background: #dc2626;"
                                    >
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">まだ物件区分が登録されていません。「物件区分を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection