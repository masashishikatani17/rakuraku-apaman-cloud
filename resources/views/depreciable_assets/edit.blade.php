@extends('layouts.app')

@section('title', '固定資産修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">固定資産修正</h2>
            <p class="page-description">登録済み固定資産の償却条件や仕訳科目を修正します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ (isset($selectedBookId) && $selectedBookId) ? route('tax-menu.index', ['book_id' => $selectedBookId]) : route('tax-menu.index') }}"
                class="button button-secondary"
            >
                決算・申告メニューへ戻る
            </a>
            <a
                href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId]) }}"
                class="button button-secondary"
            >
                減価償却へ戻る
            </a>
        </div>
    </div>

    @include('depreciable_assets.partials.form')
@endsection