@extends('layouts.app')

@section('title', '仕訳物件紐づけ確認')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">仕訳物件紐づけ確認</h2>
            <p class="page-description">賃貸入金・減価償却・借入返済の自動仕訳に、物件IDが入っているか確認・補正します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('rental-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                賃貸管理メニューへ戻る
            </a>
            <a
                href="{{ route('tax-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                決算・申告メニューへ戻る
            </a>
            <a href="{{ route('journal-entries.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">仕訳一覧へ</a>
            <a href="{{ route('reports.property-owner-profit-losses.index', $selectedBookId ? ['book_id' => $selectedBookId] : []) }}" class="button button-secondary">物件・所有者別損益へ</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        既に作成済みの自動仕訳に物件IDが入っていない場合、この画面から一括補正できます。
        補正対象は、賃貸入金仕訳・減価償却仕訳・借入返済仕訳です。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('journal-property-links.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿</label>
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
                <button type="submit" class="button">表示する</button>
                <a href="{{ route('journal-property-links.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    @if (! $isReady)
        <div class="alert alert-error">
            journal_entry_lines.property_id がありません。先に仕訳明細への物件紐づけmigrationを実行してください。
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>自動仕訳件数</label>
                    <div>{{ $summary['total_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>補正が必要な件数</label>
                    <div style="{{ (int) $summary['needs_sync_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['needs_sync_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>賃貸入金</label>
                    <div>{{ $summary['rental_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>減価償却</label>
                    <div>{{ $summary['depreciation_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>借入返済</label>
                    <div>{{ $summary['loan_count'] }} 件</div>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('journal-property-links.sync') }}"
                onsubmit="return confirm('表示条件に一致する自動仕訳の物件紐づけを補正しますか？');"
                style="margin-top: 16px;"
            >
                @csrf
                @if ($selectedBookId)
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                @endif
                <button type="submit" class="button">自動仕訳の物件紐づけを補正する</button>
            </form>
        </div>

        @include('journal_property_links.partials.rows', [
            'title' => '賃貸入金仕訳',
            'rows' => $rentalRows,
        ])

        @include('journal_property_links.partials.rows', [
            'title' => '減価償却仕訳',
            'rows' => $depreciationRows,
        ])

        @include('journal_property_links.partials.rows', [
            'title' => '借入返済仕訳',
            'rows' => $loanRows,
        ])
    @endif
@endsection