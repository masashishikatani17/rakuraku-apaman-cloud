@extends('layouts.app')

@section('title', '借入金修正')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">借入金修正</h2>
            <p class="page-description">借入条件を修正し、返済予定表を作り直します。</p>
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
            <a href="{{ route('borrowing-loans.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">
                借入金台帳へ戻る
            </a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('borrowing-loans.update', $borrowingLoan) }}">
            @csrf
            @method('PUT')
            @include('borrowing_loans.partials.form', [
                'borrowingLoan' => $borrowingLoan,
                'selectedBookId' => $selectedBookId,
            ])

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">更新する</button>
                <a href="{{ route('borrowing-loans.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">
                    キャンセル
                </a>
            </div>
        </form>
    </div>
@endsection