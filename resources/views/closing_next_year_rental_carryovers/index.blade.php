@extends('layouts.app')

@section('title', '翌期賃貸データ引継ぎ')

@section('content')
    @php
        $summaryLabels = [
            'owners' => '所有者',
            'categories' => '物件区分',
            'properties' => '物件',
            'units' => '部屋・区画',
            'tenants' => '契約者',
            'payment_items' => '入金項目',
            'payment_accounts' => '入金口座',
            'contracts' => '賃貸条件',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">翌期賃貸データ引継ぎ</h2>
            <p class="page-description">年度繰越で作成した翌期帳簿へ、物件・契約・入金マスタをコピーします。</p>
        </div>
        <div class="actions">
            @if ($sourceBookId)
                <a href="{{ route('closing.next-year-rollover-creations.index', ['book_id' => $sourceBookId]) }}" class="button button-secondary">翌期帳簿作成へ</a>
                <a href="{{ route('closing.next-year-rollovers.index', ['book_id' => $sourceBookId]) }}" class="button button-secondary">年度繰越プレビューへ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この初版では、有効な所有者・物件・部屋・契約者・入金項目・入金口座・賃貸条件・月額変更履歴をコピーします。
        重複防止のため、移行先帳簿に既に賃貸管理データがある場合はコピーできません。
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

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

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('closing.next-year-rental-carryovers.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="source_book_id">移行元帳簿<span class="required">必須</span></label>
                    <select id="source_book_id" name="source_book_id" required>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $sourceBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                （{{ $book->period_start_date?->format('Y-m-d') }}〜{{ $book->period_end_date?->format('Y-m-d') }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="target_book_id">移行先帳簿<span class="required">必須</span></label>
                    <select id="target_book_id" name="target_book_id" required>
                        <option value="">選択してください</option>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $targetBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                （{{ $book->period_start_date?->format('Y-m-d') }}〜{{ $book->period_end_date?->format('Y-m-d') }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="copy_only_active">コピー範囲</label>
                    <select id="copy_only_active" name="copy_only_active">
                        <option value="1" {{ $copyOnlyActive ? 'selected' : '' }}>有効データのみ</option>
                        <option value="0" {{ !$copyOnlyActive ? 'selected' : '' }}>無効データも含める</option>
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">引継ぎ内容を確認</button>
            </div>
        </form>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">引継ぎ件数</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>種類</th>
                    <th>移行元件数</th>
                    <th>移行先既存件数</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summaryLabels as $key => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td style="text-align: right;">{{ number_format((int) ($sourceSummary[$key] ?? 0)) }}</td>
                        <td style="text-align: right; {{ (int) ($targetSummary[$key] ?? 0) > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((int) ($targetSummary[$key] ?? 0)) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">実行</h3>

        @if ($sourceBook === null || $targetBook === null)
            <div class="alert alert-error">移行元帳簿と移行先帳簿を選択してください。</div>
        @elseif ((int) $sourceBook->id === (int) $targetBook->id)
            <div class="alert alert-error">移行元帳簿と移行先帳簿が同じです。別の帳簿を選択してください。</div>
        @elseif ((int) $sourceBook->business_owner_id !== (int) $targetBook->business_owner_id)
            <div class="alert alert-error">移行元帳簿と移行先帳簿の事業主が異なります。</div>
        @elseif (!$canCopy)
            <div class="alert alert-error">
                移行先帳簿に既に賃貸管理データがあります。重複防止のため、この画面からはコピーできません。
            </div>
        @else
            <form
                method="POST"
                action="{{ route('closing.next-year-rental-carryovers.store') }}"
                onsubmit="return confirm('移行先帳簿へ賃貸管理データをコピーしますか？この処理は重複防止のため、空の移行先帳簿にだけ実行してください。');"
            >
                @csrf
                <input type="hidden" name="source_book_id" value="{{ $sourceBook->id }}">
                <input type="hidden" name="target_book_id" value="{{ $targetBook->id }}">
                <input type="hidden" name="copy_only_active" value="{{ $copyOnlyActive ? 1 : 0 }}">

                <div class="form-grid">
                    <div class="field">
                        <label>移行元</label>
                        <div>{{ $sourceBook->book_code }} / {{ $sourceBook->name }}</div>
                    </div>

                    <div class="field">
                        <label>移行先</label>
                        <div>{{ $targetBook->book_code }} / {{ $targetBook->name }}</div>
                    </div>

                    <div class="field">
                        <label>コピー範囲</label>
                        <div>{{ $copyOnlyActive ? '有効データのみ' : '無効データも含める' }}</div>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">賃貸管理データを引き継ぐ</button>
                </div>
            </form>
        @endif
    </div>
@endsection