@extends('layouts.app')

@section('title', '決算整理仕訳修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">決算整理仕訳修正</h2>
            <p class="page-description">登録済みの決算整理仕訳を修正します。</p>
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

    @include('closing_adjustment_journals.partials.form', [
        'formAction' => route('closing-adjustment-journals.update', $journalEntry),
        'formMethod' => 'PUT',
        'buttonLabel' => '決算整理仕訳を更新する',
    ])
@endsection