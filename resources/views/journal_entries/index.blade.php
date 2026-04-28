@extends('layouts.app')

@section('title', '仕訳一覧')

@section('content')
    @php
        $entryTypeLabels = [
            'manual' => '通常',
            'system' => '自動',
            'opening' => '開始残高',
            'closing' => '決算整理',
            'depreciation' => '減価償却',
            'loan_repayment' => '借入返済',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳一覧</h2>
            <p class="page-description">登録済の仕訳を一覧表示し、修正・削除できます。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('journal-entries.create', ['book_id' => $selectedBookId]) : route('journal-entries.create') }}"
                class="button"
            >
                仕訳を新規登録
            </a>
            <a
                href="{{ $selectedBookId ? route('closing-adjustment-journals.index', ['book_id' => $selectedBookId]) : route('closing-adjustment-journals.index') }}"
                class="button button-secondary"
            >
                決算整理仕訳へ
            </a>
            <a
                href="{{ $selectedBookId ? route('depreciable-assets.index', ['book_id' => $selectedBookId]) : route('depreciable-assets.index') }}"
                class="button button-secondary"
            >
                減価償却へ
            </a>
            <a
                href="{{ $selectedBookId ? route('borrowing-loans.index', ['book_id' => $selectedBookId]) : route('borrowing-loans.index') }}"
                class="button button-secondary"
            >
                借入金台帳へ
            </a>
            <a
                href="{{ $selectedBookId ? route('csv-exports.index', ['book_id' => $selectedBookId]) : route('csv-exports.index') }}"
                class="button button-secondary"
            >
                CSV出力へ
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('journal-entries.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿で絞り込み</label>
                    <select id="book_id" name="book_id">
                        <option value="">すべて表示</option>
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
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('journal-entries.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $journalEntries->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>日付</th>
                    <th>伝票番号</th>
                    <th>事業主 / 帳簿</th>
                    <th>摘要文</th>
                    <th>借方</th>
                    <th>貸方</th>
                    <th>金額</th>
                    <th>区分</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($journalEntries as $journalEntry)
                    @php
                        $debitLine = $journalEntry->debitLines->first();
                        $creditLine = $journalEntry->creditLines->first();
                    @endphp
                    <tr>
                        <td>{{ $journalEntry->id }}</td>
                        <td>{{ $journalEntry->entry_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $journalEntry->voucher_no ?: '—' }}</td>
                        <td>
                            {{ $journalEntry->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $journalEntry->book?->name ?? '—' }}
                        </td>
                        <td>{{ $journalEntry->description_text }}</td>
                        <td>
                            @if ($debitLine)
                                {{ $debitLine->accountTitle?->account_code ?? '' }}
                                {{ $debitLine->accountTitle?->name ?? '—' }}
                                @if ($debitLine->subAccountTitle)
                                    <div class="muted">
                                        補助: {{ $debitLine->subAccountTitle->sub_account_code }} {{ $debitLine->subAccountTitle->name }}
                                    </div>
                                @endif
                                @if ($debitLine->department)
                                    <div class="muted">
                                        部門: {{ $debitLine->department->department_code }} {{ $debitLine->department->name }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($creditLine)
                                {{ $creditLine->accountTitle?->account_code ?? '' }}
                                {{ $creditLine->accountTitle?->name ?? '—' }}
                                @if ($creditLine->subAccountTitle)
                                    <div class="muted">
                                        補助: {{ $creditLine->subAccountTitle->sub_account_code }} {{ $creditLine->subAccountTitle->name }}
                                    </div>
                                @endif
                                @if ($creditLine->department)
                                    <div class="muted">
                                        部門: {{ $creditLine->department->department_code }} {{ $creditLine->department->name }}
                                    </div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ number_format((float) $journalEntry->total_amount, 2) }}</td>
                        <td>{{ $entryTypeLabels[$journalEntry->entry_type] ?? $journalEntry->entry_type }}</td>
                        <td>{{ $journalEntry->status === 'posted' ? '登録済' : '下書き' }}</td>
                        <td>
                            <div class="actions">
                                @if ($journalEntry->entry_type === 'closing')
                                    <a href="{{ route('closing-adjustment-journals.edit', $journalEntry) }}" class="button button-secondary">
                                        決算修正
                                    </a>
                                @else
                                    <a href="{{ route('journal-entries.edit', $journalEntry) }}" class="button button-secondary">
                                        修正
                                    </a>
                                @endif

                                <form
                                    method="POST"
                                    action="{{ $journalEntry->entry_type === 'closing' ? route('closing-adjustment-journals.destroy', $journalEntry) : route('journal-entries.destroy', $journalEntry) }}"
                                    onsubmit="return confirm('この仕訳を削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="button"
                                        style="background: #dc2626;"
                                    >
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">まだ仕訳が登録されていません。「仕訳を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection