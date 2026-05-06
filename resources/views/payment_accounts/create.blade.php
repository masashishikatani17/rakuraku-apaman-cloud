@extends('layouts.app')

@section('title', '入金口座登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">入金口座登録</h2>
            <p class="page-description">入金を受ける銀行口座と、会計科目の紐づけを登録します。</p>
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
            <a href="{{ route('payment-accounts.index') }}" class="button button-secondary">入金口座一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">帳簿がまだ登録されていません。先に帳簿を登録してください。</div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('payment-accounts.create') }}">
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="book_id_selector">入力する帳簿</label>
                        <select id="book_id_selector" name="book_id">
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
                    <button type="submit" class="button">この帳簿で入力する</button>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">入金口座を入力する帳簿を選択してください。</div>
        @else
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
                <form method="POST" action="{{ route('payment-accounts.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

                    <div class="field field-full" style="margin-bottom: 16px;">
                        <label>選択中の帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    @include('payment_accounts._form')

                    <div class="actions" style="margin-top: 24px;">
                        <button type="submit" class="button">登録する</button>
                        <a href="{{ route('payment-accounts.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        @endif
    @endif
@endsection