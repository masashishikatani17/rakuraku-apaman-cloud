@extends('layouts.app')

@section('title', '事業主一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">事業主一覧</h2>
            <p class="page-description">Access の MT_D1事業主名 に相当する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a href="{{ route('business-owners.create') }}" class="button">事業主を新規登録</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card">
        <p class="muted">登録件数: {{ $businessOwners->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主コード</th>
                    <th>事業主名</th>
                    <th>種別</th>
                    <th>電話番号</th>
                    <th>メールアドレス</th>
                    <th>帳簿数</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($businessOwners as $businessOwner)
                    <tr>
                        <td>{{ $businessOwner->id }}</td>
                        <td>{{ $businessOwner->owner_code ?: '—' }}</td>
                        <td>{{ $businessOwner->name }}</td>
                        <td>{{ $businessOwner->owner_type === 'corporate' ? '法人' : '個人' }}</td>
                        <td>{{ $businessOwner->phone ?: '—' }}</td>
                        <td>{{ $businessOwner->email ?: '—' }}</td>
                        <td>{{ $businessOwner->books_count }} 件</td>
                        <td>{{ $businessOwner->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('books.index', ['business_owner_id' => $businessOwner->id]) }}"
                                    class="button button-secondary"
                                >
                                    帳簿一覧
                                </a>
                                <a
                                    href="{{ route('books.create', ['business_owner_id' => $businessOwner->id]) }}"
                                    class="button"
                                >
                                    帳簿登録
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">まだ事業主が登録されていません。「事業主を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection