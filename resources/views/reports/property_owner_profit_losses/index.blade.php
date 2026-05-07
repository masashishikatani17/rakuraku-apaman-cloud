@extends('layouts.app')

@section('title', '物件別・所有者別損益集計')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円行を非表示',
            'all' => '0円行も表示',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">物件別・所有者別損益集計</h2>
            <p class="page-description">賃貸収入、減価償却費、借入金利子を、物件別・所有者別に確認します。</p>
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
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
                <a
                    href="{{ route('reports.property-annual-incomes.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別年間収入台帳へ
                </a>
                <a
                    href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    減価償却へ
                </a>
                <a
                    href="{{ route('borrowing-loans.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    借入金台帳へ
                </a>
                <a
                    href="{{ route('property-journal-allocations.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別仕訳配賦へ
                </a>
                <a
                    href="{{ route('reports.property-profit-loss-checks.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件別損益チェックへ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、物件別の賃貸収入は入金予定、減価償却費は固定資産台帳、借入金利子は借入金台帳から集計します。
        今回から仕訳明細にも物件を紐づけられるため、物件を設定した収益・費用仕訳は「物件紐づけ仕訳損益」として表示します。
        賃貸入金仕訳・減価償却仕訳・借入返済仕訳には物件IDを自動付与しますが、この画面では二重計上を避けるため、
        「物件紐づけ仕訳損益」からはそれらの自動仕訳を除外し、手入力・決算整理などの物件紐づけ仕訳だけを集計します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.property-owner-profit-losses.index') }}">
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
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.property-owner-profit-losses.index', ['book_id' => $selectedBookId]) : route('reports.property-owner-profit-losses.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
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
                    <label>表示期間</label>
                    <div class="muted">
                        {{ $dateFrom ?: '開始未指定' }}
                        〜
                        {{ $dateTo ?: '終了未指定' }}
                    </div>
                </div>

                <div class="field">
                    <label>物件数</label>
                    <div>{{ $summary['property_rows_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>所有者数</label>
                    <div>{{ $summary['owner_rows_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>賃貸収入予定額</label>
                    <div>{{ number_format((float) $summary['rental_expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>賃貸収入入金済額</label>
                    <div>{{ number_format((float) $summary['rental_received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>減価償却費</label>
                    <div>{{ number_format((float) $summary['depreciation_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>借入金利子</label>
                    <div>{{ number_format((float) $summary['loan_interest_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>物件紐づけ仕訳損益</label>
                    <div>{{ number_format((float) $summary['journal_profit_loss_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>参考所得・予定額基準</label>
                    <div style="{{ (float) $summary['estimated_income_by_expected_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_income_by_expected_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>参考所得・入金済基準</label>
                    <div style="{{ (float) $summary['estimated_income_by_received_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_income_by_received_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>参考所得・予定額基準+物件仕訳</label>
                    <div style="{{ (float) $summary['estimated_income_with_journal_by_expected_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_income_with_journal_by_expected_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>参考所得・入金済基準+物件仕訳</label>
                    <div style="{{ (float) $summary['estimated_income_with_journal_by_received_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['estimated_income_with_journal_by_received_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">所有者別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>所有者CODE</th>
                    <th>所有者名</th>
                    <th>物件数</th>
                    <th>予定収入</th>
                    <th>入金済収入</th>
                    <th>未入金</th>
                    <th>減価償却費</th>
                    <th>借入金利子</th>
                    <th>物件仕訳損益</th>
                    <th>参考所得・予定額基準</th>
                    <th>参考所得・入金済基準</th>
                    <th>予定額基準+物件仕訳</th>
                    <th>入金済基準+物件仕訳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ownerRows as $row)
                    <tr>
                        <td>{{ $row->owner_code ?? '—' }}</td>
                        <td>{{ $row->owner_name }}</td>
                        <td>{{ $row->properties_count }} 件</td>
                        <td style="text-align: right;">{{ number_format((float) $row->rental_expected_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->rental_received_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->rental_remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $row->rental_remaining_total, 2) }}
                        </td>
                        <td style="text-align: right;">{{ number_format((float) $row->depreciation_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->loan_interest_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->journal_profit_loss_total >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->journal_profit_loss_total, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_by_expected >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_by_expected, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_by_received >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_by_received, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_with_journal_by_expected >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_with_journal_by_expected, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_with_journal_by_received >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_with_journal_by_received, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13">所有者別に集計できるデータがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">物件別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>物件CODE</th>
                    <th>物件名</th>
                    <th>物件区分</th>
                    <th>所有者</th>
                    <th>入金予定件数</th>
                    <th>予定収入</th>
                    <th>入金済収入</th>
                    <th>未入金</th>
                    <th>固定資産数</th>
                    <th>減価償却費</th>
                    <th>返済件数</th>
                    <th>借入金利子</th>
                    <th>物件仕訳行数</th>
                    <th>物件仕訳損益</th>
                    <th>参考所得・予定額基準</th>
                    <th>参考所得・入金済基準</th>
                    <th>予定額基準+物件仕訳</th>
                    <th>入金済基準+物件仕訳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($propertyRows as $row)
                    <tr>
                        <td>{{ $row->property_code }}</td>
                        <td>{{ $row->property_name }}</td>
                        <td>{{ $row->property_category_name }}</td>
                        <td>
                            {{ $row->owner_code ?? '—' }}
                            /
                            {{ $row->owner_name }}
                        </td>
                        <td>{{ $row->rental_schedules_count }} 件</td>
                        <td style="text-align: right;">{{ number_format((float) $row->rental_expected_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->rental_received_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->rental_remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $row->rental_remaining_total, 2) }}
                        </td>
                        <td>{{ $row->depreciable_assets_count }} 件</td>
                        <td style="text-align: right;">{{ number_format((float) $row->depreciation_total, 2) }}</td>
                        <td>{{ $row->loan_repayments_count }} 件</td>
                        <td style="text-align: right;">{{ number_format((float) $row->loan_interest_total, 2) }}</td>
                        <td>{{ $row->journal_lines_count }} 行</td>
                        <td style="text-align: right; {{ (float) $row->journal_profit_loss_total >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->journal_profit_loss_total, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_by_expected >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_by_expected, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_by_received >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_by_received, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_with_journal_by_expected >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_with_journal_by_expected, 2) }}
                        </td>
                        <td style="text-align: right; {{ (float) $row->estimated_income_with_journal_by_received >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $row->estimated_income_with_journal_by_received, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="18">物件別に集計できるデータがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection