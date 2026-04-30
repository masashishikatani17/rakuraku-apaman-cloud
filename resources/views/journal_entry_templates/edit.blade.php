@extends('layouts.app')

@section('title', '仕訳テンプレート修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳テンプレート修正</h2>
            <p class="page-description">登録済みの仕訳テンプレートを修正します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('journal-entry-templates.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">テンプレート一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @include('journal_entry_templates.partials.form', [
        'formAction' => route('journal-entry-templates.update', $template),
        'formMethod' => 'PUT',
        'submitLabel' => '更新する',
    ])
@endsection