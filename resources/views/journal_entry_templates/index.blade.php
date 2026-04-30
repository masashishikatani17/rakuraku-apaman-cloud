@extends('layouts.app')

@section('title', '仕訳テンプレート一覧')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳テンプレート一覧</h2>
            <p class="page-description">よく使う仕訳パターンを登録し、テンプレートから仕訳を作成します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('journal-entry-templates.create', ['book_id' => $selectedBookId]) : route('journal-entry-templates.create') }}"
                class="button"
            >
                テンプレートを登録
            </a>
            <a
                href="{{ $selectedBookId ? route('journal-entries.index', ['book_id' => $selectedBookId]) : route('journal-entries.index') }}"
                class="button button-secondary"
            >
                仕訳一覧へ
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('journal-entry-templates.index') }}">
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
                <a href="{{ route('journal-entry-templates.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $templates->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>コード</th>
                    <th>テンプレート名</th>
                    <th>帳簿</th>
                    <th>摘要</th>
                    <th>明細</th>
                    <th>並び順</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>{{ $template->template_code }}</td>
                        <td>{{ $template->name }}</td>
                        <td>{{ ($template->book?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($template->book?->name ?? '帳簿不明') }}</td>
                        <td>{{ $template->description_text }}</td>
                        <td>
                            <div>借方: {{ $template->debitLines->count() }} 行</div>
                            <div>貸方: {{ $template->creditLines->count() }} 行</div>
                            <div class="muted">合計: {{ $template->lines_count }} 行</div>
                        </td>
                        <td>{{ $template->sort_order }}</td>
                        <td>{{ $template->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('journal-entry-templates.journal.create', $template) }}"
                                    class="button"
                                >
                                    仕訳作成
                                </a>
                                <a
                                    href="{{ route('journal-entry-templates.edit', $template) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('journal-entry-templates.destroy', $template) }}"
                                    onsubmit="return confirm('このテンプレートを削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background: #dc2626;">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">まだ仕訳テンプレートが登録されていません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection