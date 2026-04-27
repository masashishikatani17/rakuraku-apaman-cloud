@extends('layouts.app')

@section('title', '所有者一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">所有者一覧</h2>
            <p class="page-description">Access の MT_所有者マスター に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('property-owners.create', ['book_id' => $selectedBookId]) : route('property-owners.create') }}"
                class="button"
            >
                所有者を新規登録
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
        <form method="GET" action="{{ route('property-owners.index') }}">
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
                <a href="{{ route('property-owners.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $propertyOwners->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名 / 帳簿名</th>
                    <th>所有者CODE</th>
                    <th>区分</th>
                    <th>所有者名</th>
                    <th>所有者名略称</th>
                    <th>青色申告控除</th>
                    <th>並び順</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($propertyOwners as $propertyOwner)
                    <tr>
                        <td>{{ $propertyOwner->id }}</td>
                        <td>
                            {{ $propertyOwner->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $propertyOwner->book?->name ?? '—' }}
                        </td>
                        <td>{{ $propertyOwner->owner_code }}</td>
                        <td>{{ $propertyOwner->classification_code ?? '—' }}</td>
                        <td>{{ $propertyOwner->name }}</td>
                        <td>{{ $propertyOwner->short_name ?: '—' }}</td>
                        <td>{{ $propertyOwner->blue_return_deduction_code ?? '—' }}</td>
                        <td>{{ $propertyOwner->sort_order }}</td>
                        <td>{{ $propertyOwner->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('property-owners.edit', $propertyOwner) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('property-owners.destroy', $propertyOwner) }}"
                                    onsubmit="return confirm('この所有者を削除しますか？');"
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
                        <td colspan="10">まだ所有者が登録されていません。「所有者を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection