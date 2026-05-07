@extends('layouts.app')

@section('title', '物件別仕訳配賦')

@section('content')
    @php
        $propertyStatusLabels = [
            'unassigned' => '物件未設定だけ',
            'assigned' => '物件設定済だけ',
            'all' => 'すべて',
        ];

        $categoryLabels = [
            'all' => '収益・費用すべて',
            'revenue' => '収益',
            'expense' => '費用',
        ];

        $sideLabels = [
            'debit' => '借方',
            'credit' => '貸方',
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
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">物件別仕訳配賦</h2>
            <p class="page-description">物件が未設定の収益・費用仕訳に物件を割り当て、物件別損益の精度を高めます。</p>
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
                    href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    仕訳一覧へ
                </a>
            @endif
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
        この画面では、手入力仕訳・決算整理仕訳などの収益・費用明細に物件を後付けできます。
        賃貸入金・減価償却・借入返済の自動仕訳は、専用データから物件別集計しているため、この画面の対象から除外します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('property-journal-allocations.index') }}">
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
                    <label for="property_status">物件設定状態</label>
                    <select id="property_status" name="property_status">
                        @foreach ($propertyStatusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $propertyStatus === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="category">科目区分</label>
                    <select id="category" name="category">
                        @foreach ($categoryLabels as $value => $label)
                            <option value="{{ $value }}" {{ $category === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="property_id">物件で絞り込み</label>
                    <select id="property_id" name="property_id">
                        <option value="">指定なし</option>
                        @foreach ($properties as $property)
                            <option
                                value="{{ $property->id }}"
                                {{ (string) $selectedPropertyId === (string) $property->id ? 'selected' : '' }}
                            >
                                {{ $property->property_code }} / {{ $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('property-journal-allocations.index', ['book_id' => $selectedBookId]) : route('property-journal-allocations.index') }}"
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
            <div class="form-grid">
                <div class="field">
                    <label>表示明細数</label>
                    <div>{{ $summary['rows_count'] }} 行</div>
                </div>

                <div class="field">
                    <label>物件設定済</label>
                    <div>{{ $summary['assigned_count'] }} 行</div>
                </div>

                <div class="field">
                    <label>物件未設定</label>
                    <div style="{{ (int) $summary['unassigned_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['unassigned_count'] }} 行
                    </div>
                </div>

                <div class="field">
                    <label>収益明細金額</label>
                    <div>{{ number_format((float) $summary['revenue_amount_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>費用明細金額</label>
                    <div>{{ number_format((float) $summary['expense_amount_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>損益換算額</label>
                    <div style="{{ (float) $summary['profit_loss_amount_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['profit_loss_amount_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('property-journal-allocations.update') }}">
            @csrf
            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
            <input type="hidden" name="property_status" value="{{ $propertyStatus }}">
            <input type="hidden" name="category" value="{{ $category }}">
            <input type="hidden" name="filter_property_id" value="{{ $selectedPropertyId }}">

            <div class="card" style="margin-bottom: 16px;">
                <div class="form-grid">
                    <div class="field">
                        <label for="assign_property_id">選択した明細に設定する物件</label>
                        <select id="assign_property_id" name="property_id">
                            <option value="">物件紐づけを解除</option>
                            @foreach ($properties as $property)
                                <option value="{{ $property->id }}">
                                    {{ $property->property_code }} / {{ $property->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>一括更新</label>
                        <div>
                            <button
                                type="submit"
                                class="button"
                                onclick="return confirm('選択した仕訳明細の物件紐づけを更新しますか？');"
                            >
                                選択明細の物件を更新
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>選択</th>
                            <th>日付</th>
                            <th>伝票番号</th>
                            <th>摘要</th>
                            <th>区分</th>
                            <th>科目</th>
                            <th>借貸</th>
                            <th>金額</th>
                            <th>損益換算</th>
                            <th>現在の物件</th>
                            <th>部門</th>
                            <th>行備考</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($journalLineRows as $row)
                            <tr>
                                <td>
                                    <input type="checkbox" name="line_ids[]" value="{{ $row->line_id }}">
                                </td>
                                <td>{{ $row->entry_date ?? '—' }}</td>
                                <td>{{ $row->voucher_no ?: '—' }}</td>
                                <td>
                                    {{ $row->description_text }}
                                    <div class="muted">仕訳ID: {{ $row->journal_entry_id }} / 行: {{ $row->line_no }}</div>
                                </td>
                                <td>{{ $entryTypeLabels[$row->entry_type] ?? $row->entry_type }}</td>
                                <td>
                                    {{ $row->account_code }} / {{ $row->account_name }}
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
                                    @if ($row->property_id)
                                        {{ $row->property_code }} / {{ $row->property_name }}
                                    @else
                                        <span style="color: #dc2626;">未設定</span>
                                    @endif
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
                                <td colspan="12">条件に一致する仕訳明細がありません。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </form>
    @endif
@endsection