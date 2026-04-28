@extends('layouts.app')

@section('title', '固定資産登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">固定資産登録</h2>
            <p class="page-description">減価償却の対象になる固定資産を登録します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('depreciable-assets.index', ['book_id' => $selectedBookId]) : route('depreciable-assets.index') }}"
                class="button button-secondary"
            >
                減価償却へ戻る
            </a>
        </div>
    </div>

    @include('depreciable_assets.partials.form')
@endsection