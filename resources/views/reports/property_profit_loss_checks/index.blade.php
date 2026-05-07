@extends('layouts.app')

@section('title', '物件別損益チェック')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円行を非表示',
            'all' => '0円行も表示',
        ];

        $entryTypeLabels = [
            'manual' => '通常',
            'system' => '自動',
            'opening' => '開始残高',
            'closing' => '決算整理',
            'rental_payment' => '賃貸入金',
            'depreciation' => '減価償却',
            'loan_repayment' => '借入返済',
        ];

        $categoryLabels = [
            'revenue' => '収益',
            'expense' => '費用',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">物件別損益チェック</h2>
            <p class="page-description">物件別損益に含まれるデータと、まだ物件未設定の収益・費用仕訳を確認します。</p>
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
                    href="{{ route('reports.property-owner-profit-losses.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    物件・所有者別損益へ
                </a>
                <a
                    href="{{ route('property-journal-allocations.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'property_status' => 'unassigned']) }}"
                    class="button button-secondary"
                >
                    物件別仕訳配賦へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は、物件別損益の「集計対象」と「未配賦の仕訳」を確認するための画面です。
        物件別損益に反映される自動系データと、手入力・決算整理などの物件紐づけ仕訳を分けて確認できます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.property-profit-loss-checks.index') }}">
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
                    href="{{ $selectedBookId ? route('reports.property-profit-loss-checks.index', ['book_id' => $selectedBookId]) : route('reports.property-profit-loss-checks.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if (! $isReady)
        <div class="alert alert-error">
            journal_entry_lines.property_id がありません。先に仕訳明細への物件紐づけmigrationを実行してください。
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">集計対象サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>物件紐づけ済みPL仕訳行</label>
                    <div>{{ $summary['property_linked_lines_count'] }} 行</div>
                </div>

                <div class="field">
                    <label>物件未配賦PL仕訳行</label>
                    <div style="{{ (int) $summary['unassigned_lines_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['unassigned_lines_count'] }} 行
                    </div>
                </div>

                <div class="field">
                    <label>物件紐づけ仕訳損益</label>
                    <div style="{{ (float) $summary['property_linked_profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['property_linked_profit_loss_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>未配賦仕訳損益</label>
                    <div style="{{ (float) $summary['unassigned_profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['unassigned_profit_loss_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>賃貸収入予定額</label>
                    <div>{{ number_format((float) $summary['auto_source_summary']['rental_expected_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>賃貸収入入金済額</label>
                    <div>{{ number_format((float) $summary['auto_source_summary']['rental_received_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>減価償却費</label>
                    <div>{{ number_format((float) $summary['auto_source_summary']['depreciation_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>借入金利子</label>
                    <div>{{ number_format((float) $summary['auto_source_summary']['loan_interest_total'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">物件紐づけ済み仕訳の物件別損益</h3>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>物件CODE</th>
                        <th>物件名</th>
                        <th>所有者</th>
                        <th>仕訳行数</th>
                        <th>収益</th>
                        <th>費用</th>
                        <th>損益</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($propertyRows as $row)
                        <tr>
                            <td>{{ $row->property_code ?? '—' }}</td>
                            <td>{{ $row->property_name ?? '物件未設定' }}</td>
                            <td>
                                {{ $row->owner_code ?? '—' }}
                                /
                                {{ $row->owner_name ?? '所有者未設定' }}
                            </td>
                            <td>{{ $row->lines_count }} 行</td>
                            <td style="text-align: right;">{{ number_format((float) $row->revenue_total, 2) }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->expense_total, 2) }}</td>
                            <td style="text-align: right; {{ (float) $row->profit_loss_total >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                                {{ number_format((float) $row->profit_loss_total, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">物件紐づけ済みの収益・費用仕訳はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 style="margin-top: 0;">物件未配賦の収益・費用仕訳</h3>

            @if ($unassignedLineRows->isNotEmpty())
                <div class="alert alert-error">
                    物件未設定の収益・費用仕訳があります。物件別損益を正確にするには「物件別仕訳配賦へ」から物件を設定してください。
                </div>
            @endif

            <table class="data-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>伝票番号</th>
                        <th>摘要</th>
                        <th>区分</th>
                        <th>科目</th>
                        <th>借貸</th>
                        <th>金額</th>
                        <th>損益換算</th>
                        <th>部門</th>
                        <th>行備考</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($unassignedLineRows as $row)
                        <tr>
                            <td>{{ $row->entry_date ?? '—' }}</td>
                            <td>{{ $row->voucher_no ?: '—' }}</td>
                            <td>
                                {{ $row->description_text }}
                                <div class="muted">仕訳ID: {{ $row->journal_entry_id }} / 行: {{ $row->line_no }}</div>
                            </td>
                            <td>{{ $entryTypeLabels[$row->entry_type] ?? $row->entry_type }}</td>
                            <td>
                                {{ $row->account_code }} / {{ $row->account_name }}
                                <div class="muted">{{ $categoryLabels[$row->category] ?? $row->category }}</div>
                                @if ($row->sub_account_name)
                                    <div class="muted">補助: {{ $row->sub_account_code }} {{ $row->sub_account_name }}</div>
                                @endif
                            </td>
                            <td>{{ $sideLabels[$row->side] ?? $row->side }}</td>
                            <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                            <td style="text-align: right; {{ (float) $row->profit_loss_amount >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                                {{ number_format((float) $row->profit_loss_amount, 2) }}
                            </td>
                            <td>
                                @if ($row->department_name)
                                    {{ $row->department_code }} / {{ $row->department_name }}
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>{{ $row->line_note ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">物件未配賦の収益・費用仕訳はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
@endsection