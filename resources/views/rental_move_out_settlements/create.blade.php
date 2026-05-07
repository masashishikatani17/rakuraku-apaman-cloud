@extends('layouts.app')

@section('title', '退去精算登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">退去精算登録</h2>
            <p class="page-description">敷金・保証金・原状回復費などを入力し、返還額または追加請求額を計算します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('rental-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                賃貸管理メニューへ戻る
            </a>
            <a href="{{ route('rental-move-out-settlements.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">退去精算一覧へ戻る</a>
            <a href="{{ route('rental-contract-move-outs.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">退去処理へ</a>
        </div>
    </div>

    @include('rental_move_out_settlements.partials.form', [
        'formAction' => route('rental-move-out-settlements.store'),
        'formMethod' => 'POST',
        'submitLabel' => '登録する',
    ])
@endsection