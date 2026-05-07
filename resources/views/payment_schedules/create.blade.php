@extends('layouts.app')

@section('title', '入金予定登録')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">入金予定登録</h2>
            <p class="page-description">契約に対する入金予定を登録します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('payment-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                入金管理メニューへ戻る
            </a>
            <a href="{{ route('payment-schedules.index') }}" class="button button-secondary">入金予定一覧へ戻る</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if ($books->isEmpty())
        <div class="alert alert-error">帳簿がまだ登録されていません。先に帳簿を登録してください。</div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('payment-schedules.create') }}">
                <div class="form-grid">
                    <div class="field field-full">
                        <label for="book_id_selector">入力する帳簿</label>
                        <select id="book_id_selector" name="book_id">
                            @foreach ($books as $book)
                                <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                    {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="actions" style="margin-top:16px;">
                    <button type="submit" class="button">この帳簿で入力する</button>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">入金予定を入力する帳簿を選択してください。</div>
        @elseif ($rentalContracts->isEmpty())
            <div class="alert alert-error">この帳簿には契約がありません。先に契約者台帳から契約を登録してください。</div>
        @elseif ($paymentItems->isEmpty())
            <div class="alert alert-error">この帳簿には入金項目がありません。先に入金項目を登録してください。</div>
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
                <form method="POST" action="{{ route('payment-schedules.store') }}">
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

                    <div class="field field-full" style="margin-bottom:16px;">
                        <label>選択中の帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    @include('payment_schedules._form')

                    <div class="actions" style="margin-top:24px;">
                        <button type="submit" class="button">登録する</button>
                        <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
                    </div>
                </form>
            </div>
        @endif
    @endif
@endsection