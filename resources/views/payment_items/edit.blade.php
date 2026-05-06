@extends('layouts.app')

@section('title', '入金項目修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">入金項目修正</h2>
            <p class="page-description">登録済の入金項目を修正します。</p>
        </div>
        <div class="actions">
@php
                $parentBookId = request('book_id')
                    ?? ($selectedBookId ?? null)
                    ?? ($accountTitle->book_id ?? null)
                    ?? ($journalDescription->book_id ?? null)
                    ?? ($department->book_id ?? null)
                    ?? ($propertyOwner->book_id ?? null)
                    ?? ($propertyCategory->book_id ?? null)
                    ?? ($property->book_id ?? null)
                    ?? ($contractTenant->book_id ?? null)
                    ?? ($paymentItem->book_id ?? null)
                    ?? ($paymentAccount->book_id ?? null)
                    ?? ($borrowingLoan->book_id ?? null)
                    ?? ($journalEntry->book_id ?? null);
            @endphp
            <a
                href="{{ route('master-menu.index', $parentBookId ? ['book_id' => $parentBookId] : []) }}"
                class="button button-secondary"
            >
                マスタメニューへ戻る
            </a>
            <a href="{{ route('payment-items.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金項目一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
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

    <div class="card">
        <form method="POST" action="{{ route('payment-items.update', $paymentItem) }}">
            @csrf
            @method('PUT')

            <div class="field field-full" style="margin-bottom: 16px;">
                <label>対象の帳簿</label>
                <div class="muted">
                    {{ ($selectedBook?->businessOwner?->name ?? '事業主未設定') . ' / ' . ($selectedBook?->name ?? '帳簿未設定') }}
                </div>
            </div>

            @include('payment_items._form')

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">更新する</button>
                <a href="{{ route('payment-items.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endsection