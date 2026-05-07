@extends('layouts.app')

@section('title', 'PDF出力')

@section('content')
    @php
        $previewParams = [
            'book_id' => $selectedBookId,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'report_type' => $reportType,
            'display' => $display,
            'paper_size' => $paperSize,
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">PDF出力</h2>
            <p class="page-description">各帳票を印刷用レイアウトで表示し、ブラウザの印刷機能からPDF保存します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('utility-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                ユーティリティメニューへ戻る
            </a>
            <a
                href="{{ route('output-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                帳票・出力メニューへ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では外部PDFライブラリを追加せず、<strong>印刷プレビュー画面</strong>を作成します。
        プレビュー画面で「印刷する」を押し、プリンター選択で「PDFに保存」を選んでください。
        サーバー側でPDFファイルを直接生成する方式は、帳票レイアウトが固まった後に追加します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('pdf-exports.index') }}">
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
                    <label for="report_type">帳票</label>
                    <select id="report_type" name="report_type">
                        @foreach ($reportTypeLabels as $value => $label)
                            <option value="{{ $value }}" {{ $reportType === $value ? 'selected' : '' }}>
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
                    <label for="date_to">終了日・基準日</label>
                    <input
                        id="date_to"
                        type="date"
                        name="date_to"
                        value="{{ $dateTo }}"
                    >
                </div>

                <div class="field">
                    <label for="display">表示方法</label>
                    <select id="display" name="display">
                        @foreach ($displayLabels as $value => $label)
                            <option value="{{ $value }}" {{ $display === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="paper_size">用紙・向き</label>
                    <select id="paper_size" name="paper_size">
                        @foreach ($paperSizeLabels as $value => $label)
                            <option value="{{ $value }}" {{ $paperSize === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">条件を反映する</button>

                @if ($selectedBookId)
                    <a
                        href="{{ route('pdf-exports.preview', $previewParams) }}"
                        class="button"
                        target="_blank"
                        rel="noopener"
                    >
                        印刷プレビューを開く
                    </a>
                @endif
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card">
            <h3 style="margin-top: 0;">現在の出力条件</h3>

            <table class="data-table">
                <tbody>
                    <tr>
                        <th>帳簿</th>
                        <td>{{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}</td>
                    </tr>
                    <tr>
                        <th>帳票</th>
                        <td>{{ $reportTypeLabels[$reportType] ?? $reportType }}</td>
                    </tr>
                    <tr>
                        <th>期間</th>
                        <td>{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</td>
                    </tr>
                    <tr>
                        <th>表示方法</th>
                        <td>{{ $displayLabels[$display] ?? $display }}</td>
                    </tr>
                    <tr>
                        <th>用紙・向き</th>
                        <td>{{ $paperSizeLabels[$paperSize] ?? $paperSize }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
@endsection