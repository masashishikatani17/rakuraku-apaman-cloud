@extends('layouts.app')

@section('title', '部門一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">部門一覧</h2>
            <p class="page-description">Access の T_部門 に相当する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('departments.create', ['book_id' => $selectedBookId]) : route('departments.create') }}"
                class="button"
            >
                部門を新規登録
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
        <form method="GET" action="{{ route('departments.index') }}">
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
                <a href="{{ route('departments.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $departments->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主名</th>
                    <th>帳簿名</th>
                    <th>部門コード</th>
                    <th>部門名</th>
                    <th>並び順</th>
                    <th>状態</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($departments as $department)
                    <tr>
                        <td>{{ $department->id }}</td>
                        <td>{{ $department->book?->businessOwner?->name ?? '—' }}</td>
                        <td>{{ $department->book?->name ?? '—' }}</td>
                        <td>{{ $department->department_code }}</td>
                        <td>{{ $department->name }}</td>
                        <td>{{ $department->sort_order }}</td>
                        <td>{{ $department->is_active ? '有効' : '停止' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">まだ部門が登録されていません。「部門を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection