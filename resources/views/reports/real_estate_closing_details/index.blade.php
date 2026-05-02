@extends('layouts.app')

@section('title', '不動産所得決算書 内訳確認')

@section('content')
    @php
        $displayLabels = [
            'non_zero' => '0円科目を非表示',
            'all' => '0円科目も表示',
        ];

        $categoryLabels = [
            'revenue' => '収入',
            'expense' => '必要経費',
        ];

        $reconciliationStatusColors = [
            'OK' => '#166534',
            '確認' => '#dc2626',
        ];

        $scheduleStatusLabels = [
            'unpaid' => '未入金',
            'partial' => '一部入金',
            'paid' => '入金済',
            'cancelled' => '取消',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">不動産所得決算書 内訳確認</h2>
            <p class="page-description">申告書へ載せる前に、収入・必要経費の区分、台帳との差額、補正予定を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('reports.real-estate-income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    不動産所得集計へ
                </a>
                <a
                    href="{{ route('reports.blue-return-statement-previews.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    青色申告決算書プレビューへ
                </a>
                <a
                    href="{{ route('depreciable-assets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    減価償却へ
                </a>
                <a
                    href="{{ route('borrowing-loans.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    借入金台帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        この画面は、提出用PDFではなく、決算書作成前の確認画面です。
        今回の初版では補正額は保存せず、会計集計額・補正額0円・申告用金額を並べて表示します。
        次の段階で、補正額と補正理由を保存できるようにします。
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
        <form method="GET" action="{{ route('reports.real-estate-closing-details.index') }}">
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
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">終了日</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
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
                    href="{{ $selectedBookId ? route('reports.real-estate-closing-details.index', ['book_id' => $selectedBookId]) : route('reports.real-estate-closing-details.index') }}"
                    class="button button-secondary"
                >
                    条件を初期化
                </a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">申告用集計サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>収入金額</label>
                    <div>{{ number_format((float) $summary['revenue_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>必要経費</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>不動産所得</label>
                    <div style="{{ (float) $summary['income_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['income_total'], 2) }}
                    </div>
                </div>

                <div class="field">
                    <label>補正額合計</label>
                    <div>{{ number_format((float) $summary['adjustment_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>確認必要科目</label>
                    <div style="{{ (int) $summary['review_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['review_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>台帳差額あり</label>
                    <div style="{{ (int) $summary['reconciliation_warning_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['reconciliation_warning_count'] }} 件
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">台帳・仕訳の突合</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>確認項目</th>
                    <th>仕訳側</th>
                    <th>仕訳側金額</th>
                    <th>台帳・予定側</th>
                    <th>台帳側金額</th>
                    <th>差額</th>
                    <th>判定</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reconciliationRows as $row)
                    <tr>
                        <td>{{ $row->label }}</td>
                        <td>{{ $row->accounting_label }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td>{{ $row->ledger_label }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->ledger_amount, 2) }}</td>
                        <td style="text-align: right; {{ abs((float) $row->difference) >= 0.005 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $row->difference, 2) }}
                        </td>
                        <td style="color: {{ $reconciliationStatusColors[$row->status] ?? '#111827' }};">
                            {{ $row->status }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">突合対象データがありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="alert alert-success" style="margin-top: 16px; background: #f8fafc; color: #334155; border-color: #cbd5e1;">
            賃貸収入は、仕訳側の賃貸系収入と入金予定の入金済額を比較します。
            減価償却費は固定資産台帳、借入金利子は借入金台帳と比較します。
        </div>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">決算書区分別 内訳確認</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>決算書区分</th>
                    <th>区分</th>
                    <th>科目数</th>
                    <th>会計集計額</th>
                    <th>補正額</th>
                    <th>申告用金額</th>
                    <th>確認</th>
                    <th>内訳科目</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categoryRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->accounts_count }} 科目</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->adjustment_amount, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->filing_amount < 0 ? 'color: #dc2626;' : '' }}">
                            {{ number_format((float) $row->filing_amount, 2) }}
                        </td>
                        <td style="{{ (int) $row->needs_review_count > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ (int) $row->needs_review_count > 0 ? '確認必要 ' . $row->needs_review_count . '件' : 'OK' }}
                        </td>
                        <td>
                            @foreach ($row->accounts as $account)
                                <div>
                                    {{ $account->account_code }}
                                    {{ $account->account_name }}
                                    <span class="muted">
                                        会計 {{ number_format((float) $account->accounting_amount, 2) }}
                                        /
                                        申告 {{ number_format((float) $account->filing_amount, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">決算書区分別の内訳がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">収入内訳: 入金予定・入金実績ベース</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>決算書区分</th>
                    <th>入金項目</th>
                    <th>対応科目</th>
                    <th>件数</th>
                    <th>予定額</th>
                    <th>入金済</th>
                    <th>未入金</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($incomeSourceRows as $row)
                    <tr>
                        <td>{{ $row->statement_category_label }}</td>
                        <td>{{ $row->item_code }} / {{ $row->payment_item_name }}</td>
                        <td>
                            @if ($row->account_code || $row->account_name)
                                {{ trim(($row->account_code ?? '') . ' ' . ($row->account_name ?? '')) }}
                            @else
                                <span class="muted">未設定</span>
                            @endif
                        </td>
                        <td>{{ $row->schedules_count }} 件</td>
                        <td style="text-align: right;">{{ number_format((float) $row->expected_total, 2) }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->received_total, 2) }}</td>
                        <td style="text-align: right; {{ (float) $row->remaining_total > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ number_format((float) $row->remaining_total, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">入金予定・入金実績ベースの収入内訳がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">科目別 申告用金額確認</h3>

        <form method="POST" action="{{ route('reports.real-estate-closing-details.adjustments.update') }}">
            @csrf
            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
            <input type="hidden" name="display" value="{{ $display }}">

            <div class="alert alert-success" style="background: #f8fafc; color: #334155; border-color: #cbd5e1;">
                補正額は申告用金額へ反映され、青色申告決算書プレビューにも反映されます。
                補正不要の科目は0円・理由空欄のままで構いません。
            </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>科目CODE</th>
                    <th>科目名</th>
                    <th>区分</th>
                    <th>決算書区分</th>
                    <th>会計集計額</th>
                    <th>補正額</th>
                    <th>補正理由</th>
                    <th>申告用金額</th>
                    <th>確認</th>
                    <th>元帳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accountRows as $row)
                    <tr>
                        <input type="hidden" name="adjustments[{{ $row->account_title_id }}][account_title_id]" value="{{ $row->account_title_id }}">
                        <input type="hidden" name="adjustments[{{ $row->account_title_id }}][statement_category]" value="{{ $row->statement_category }}">
                        <td>{{ $row->account_code }}</td>
                        <td>{{ $row->account_name }}</td>
                        <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                        <td>{{ $row->statement_category_label }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->accounting_amount, 2) }}</td>
                        <td style="text-align: right;">
                            <input
                                type="number"
                                name="adjustments[{{ $row->account_title_id }}][adjustment_amount]"
                                value="{{ old('adjustments.' . $row->account_title_id . '.adjustment_amount', $row->adjustment_amount) }}"
                                step="0.01"
                                style="max-width: 140px; text-align: right;"
                            >
                        </td>
                        <td>
                            <input
                                type="text"
                                name="adjustments[{{ $row->account_title_id }}][reason]"
                                value="{{ old('adjustments.' . $row->account_title_id . '.reason', $row->adjustment_reason) }}"
                                maxlength="255"
                                placeholder="例: 家事按分、税務調整など"
                            >
                        </td>
                        <td style="text-align: right;">{{ number_format((float) $row->filing_amount, 2) }}</td>
                        <td style="{{ $row->needs_review ? 'color: #dc2626;' : 'color: #166534;' }}">
                            {{ $row->needs_review ? '対象外区分に金額あり' : 'OK' }}
                        </td>
                        <td>
                            <a
                                href="{{ route('general-ledgers.index', ['book_id' => $selectedBookId, 'account_title_id' => $row->account_title_id, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                                class="button button-secondary"
                            >
                                元帳
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">科目別の確認対象がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">補正額を保存</button>
            </div>
        </form>
    </div>
@endsection