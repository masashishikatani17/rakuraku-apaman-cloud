@extends('layouts.app')

@section('title', '物件一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">物件一覧</h2>
            <p class="page-description">Access の MT_物件マスター に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('properties.create', ['book_id' => $selectedBookId]) : route('properties.create') }}"
                class="button"
            >
                物件を新規登録
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
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
        <form method="GET" action="{{ route('properties.index') }}">
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
                <a href="{{ route('properties.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $properties->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名 / 帳簿名</th>
                    <th>物件区分</th>
                    <th>物件CODE</th>
                    <th>物件名</th>
                    <th>所有者</th>
                    <th>代表者</th>
                    <th>所在地</th>
                    <th>築年月日</th>
                    <th>建物構造 / 階数</th>
                    <th>駐車合計</th>
                    <th>部屋・区画数</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($properties as $property)
                    <tr>
                        <td>{{ $property->id }}</td>
                        <td>
                            {{ $property->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $property->book?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $property->propertyCategory?->category_code ?? '—' }}
                            /
                            {{ $property->propertyCategory?->name ?? '—' }}
                        </td>
                        <td>{{ $property->property_code }}</td>
                        <td>
                            {{ $property->name }}
                            @if ($property->short_name)
                                <div class="muted">略称: {{ $property->short_name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $property->primaryOwner?->owner_code ?? '—' }}
                            /
                            {{ $property->primaryOwner?->name ?? '—' }}
                        </td>
                        <td>
                            @if ($property->representativeOwner)
                                {{ $property->representativeOwner->owner_code }}
                                /
                                {{ $property->representativeOwner->name }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $property->address ?: '—' }}</td>
                        <td>{{ $property->built_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>
                            {{ $property->structure ?: '—' }}
                            @if ($property->floors)
                                <div class="muted">{{ $property->floors }}</div>
                            @endif
                        </td>
                        <td>{{ $property->parking_total ?? 0 }}</td>
                        <td>{{ $property->units_count }} 件</td>
                        <td>{{ $property->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('property-units.index', ['book_id' => $property->book_id, 'property_id' => $property->id]) }}"
                                    class="button button-secondary"
                                >
                                    部屋・区画一覧
                                </a>
                                <a
                                    href="{{ route('property-units.create', ['book_id' => $property->book_id, 'property_id' => $property->id]) }}"
                                    class="button"
                                >
                                    部屋・区画登録
                                </a>
                                <a
                                    href="{{ route('properties.edit', $property) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('properties.destroy', $property) }}"
                                    onsubmit="return confirm('この物件を削除しますか？');"
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
                        <td colspan="14">まだ物件が登録されていません。「物件を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection