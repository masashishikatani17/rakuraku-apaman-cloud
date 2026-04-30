@extends('layouts.app')

@section('title', '退去精算修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">退去精算修正</h2>
            <p class="page-description">登録済みの退去精算を修正します。</p>
        </div>
        <div class="actions">
            <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">退去精算一覧へ戻る</a>
            <a href="{{ route('rental-contract-move-outs.index', ['book_id' => $selectedBookId, 'rental_contract_id' => $settlement->rental_contract_id]) }}" class="button button-secondary">退去処理へ</a>
        </div>
    </div>

    @include('rental_move_out_settlements.partials.form', [
        'formAction' => route('rental-move-out-settlements.update', $settlement),
        'formMethod' => 'PUT',
        'submitLabel' => '更新する',
    ])
@endsection