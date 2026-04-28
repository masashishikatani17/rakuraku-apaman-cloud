@extends('layouts.app')

@section('title', 'CSV出力')

@section('content')
    <div class="page-header">
        <div>
            <h2 class="page-title">CSV出力</h2>
            <p class="page-description">帳簿データ、仕訳、試算表、入金、物件、固定資産などをCSVで出力します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    仕訳一覧へ
                </a>
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版ではExcelで開きやすいようにUTF-8 BOM付きCSVを出力します。
        期間指定がある出力は、開始日・終了日で対象データを絞り込みます。
    </div>

    @if ($availableExportTypes === [])
        <div class="alert alert-danger">
            出力可能なCSVがありません。migrationが未実行の可能性があります。
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('csv-exports.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿<span class="required">必須</span></label>
                    <select id="book_id" name="book_id" required>
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

                <div class="field">
                    <label for="export_type">出力対象<span class="required">必須</span></label>
                    <select id="export_type" name="export_type" required>
                        @foreach ($availableExportTypes as $value => $label)
                            <option value="{{ $value }}" {{ $exportType === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">開始日</label>
                    <input
                        id="date_from"
                        type="date"
                        name="date_from"
                        value="{{ $dateFrom }}"
                    >
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">条件を表示</button>
                <button
                    type="submit"
                    formaction="{{ route('csv-exports.download') }}"
                    class="button"
                    {{ $availableExportTypes === [] ? 'disabled' : '' }}
                >
                    CSVをダウンロード
                </button>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>選択中の帳簿</label>
                    <div class="muted">
                        {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                    </div>
                </div>

                <div class="field">
                    <label>出力期間</label>
                    <div class="muted">
                        {{ $dateFrom ?: '開始未指定' }}
                        〜
                        {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>選択中の出力</label>
                    <div>{{ $availableExportTypes[$exportType] ?? '未選択' }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0;">出力できるCSV</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>出力対象</th>
                    <th>内容</th>
                    <th>期間指定</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($availableExportTypes as $value => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $exportDescriptions[$value] ?? '' }}</td>
                        <td>
                            @if (in_array($value, ['account_titles', 'properties', 'rental_contracts', 'depreciable_assets'], true))
                                <span class="muted">期間指定なし</span>
                            @else
                                開始日・終了日で絞り込み
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3">出力可能なCSVがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection