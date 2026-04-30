@extends('layouts.app')

@section('title', '仕訳テンプレート登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳テンプレート登録</h2>
            <p class="page-description">よく使う仕訳パターンを登録します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('journal-entry-templates.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">テンプレート一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @include('journal_entry_templates.partials.form', [
        'formAction' => route('journal-entry-templates.store'),
        'formMethod' => 'POST',
        'submitLabel' => '登録する',
    ])
@endsection