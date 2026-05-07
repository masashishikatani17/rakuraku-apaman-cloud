@extends('layouts.app')

@section('title', '決算整理仕訳登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">決算整理仕訳登録</h2>
            <p class="page-description">期末の未払・前払・未収・前受・減価償却などの調整仕訳を登録します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('tax-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                決算・申告メニューへ戻る
            </a>
            <a
                href="{{ route('accounting-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                会計管理メニューへ戻る
            </a>
            <a href="{{ route('closing-adjustment-journals.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">決算整理仕訳一覧へ戻る</a>
            <a href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">仕訳一覧へ</a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>

        <div class="actions">
            <a href="{{ route('books.create') }}" class="button">帳簿を登録する</a>
        </div>
    @else
        <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
            決算整理仕訳は通常仕訳と同じ形式で保存しますが、内部的には <strong>entry_type = closing</strong> として区別します。
            登録後は損益計算書・貸借対照表・残高試算表に反映されます。
        </div>

        @if ($errors->any())
            <div class="alert alert-error">
                <strong>入力内容を確認してください。</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('closing-adjustment-journals.create') }}">
                <div class="form-grid">
                    <div class="field">
                        <label for="book_id_switch">帳簿を切り替える</label>
                        <select id="book_id_switch" name="book_id">
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
                    <button type="submit" class="button button-secondary">帳簿を切り替える</button>
                </div>
            </form>
        </div>

        @include('closing_adjustment_journals.partials.form', [
            'formAction' => route('closing-adjustment-journals.store'),
            'formMethod' => 'POST',
            'buttonLabel' => '決算整理仕訳を登録する',
        ])
    @endif
@endsection