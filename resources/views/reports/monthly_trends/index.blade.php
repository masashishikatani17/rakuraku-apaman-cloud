@extends('layouts.app')

@section('title', '月次推移表')

@section('content')
    @php
        $categoryLabels = [
            'revenue' => '収益',
            'expense' => '費用',
        ];

        $categoryFilterLabels = [
            'all' => '収益・費用すべて',
            'revenue' => '収益のみ',
            'expense' => '費用のみ',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">月次推移表</h2>
            <p class="page-description">月別に収益・費用・差引損益を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    残高試算表へ
                </a>
                <a
                    href="{{ route('department-trial-balances.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    部門別試算表へ
                </a>
                <a
                    href="{{ route('journal-diaries.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    仕訳日記帳へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、登録済み仕訳のPL科目、つまり収益・費用だけを対象に集計します。
        月別集計と科目別月次推移を同じ画面で確認できます。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.monthly-trends.index') }}">
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
                    <label for="category">表示区分</label>
                    <select id="category" name="category">
                        @foreach ($categoryFilterLabels as $value => $label)
                            <option value="{{ $value }}" {{ $category === $value ? 'selected' : '' }}>
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
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('reports.monthly-trends.index', ['book_id' => $selectedBookId]) : route('reports.monthly-trends.index') }}"
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
                    <label>表示区分</label>
                    <div>{{ $categoryFilterLabels[$category] ?? $category }}</div>
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
                    <label>対象月数 / 科目数</label>
                    <div>{{ $summary['months_count'] }} か月 / {{ $summary['accounts_count'] }} 科目</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>収益合計</label>
                    <div>{{ number_format((float) $summary['revenue_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>費用合計</label>
                    <div>{{ number_format((float) $summary['expense_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>差引損益</label>
                    <div style="{{ (float) $summary['profit_loss_total'] >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                        {{ number_format((float) $summary['profit_loss_total'], 2) }}
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">月別集計</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>年月</th>
                    <th>収益</th>
                    <th>費用</th>
                    <th>差引損益</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($monthlySummaries as $monthlySummary)
                    <tr>
                        <td>{{ $monthlySummary->label }}</td>
                        <td>{{ number_format((float) $monthlySummary->revenue_total, 2) }}</td>
                        <td>{{ number_format((float) $monthlySummary->expense_total, 2) }}</td>
                        <td style="{{ (float) $monthlySummary->profit_loss_total >= 0 ? 'color: #166534;' : 'color: #dc2626;' }}">
                            {{ number_format((float) $monthlySummary->profit_loss_total, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">表示できる月別集計がありません。帳簿と表示期間を確認してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">科目別月次推移</h3>

        <div style="overflow-x: auto;">
            <table class="data-table" style="min-width: {{ 520 + ($months->count() * 130) }}px;">
                <thead>
                    <tr>
                        <th>科目コード</th>
                        <th>科目名</th>
                        <th>区分</th>
                        @foreach ($months as $month)
                            <th>{{ $month->label }}</th>
                        @endforeach
                        <th>合計</th>
                        <th>元帳</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accountMonthlyRows as $row)
                        <tr>
                            <td>{{ $row->account_code }}</td>
                            <td>
                                {{ $row->account_name }}
                                @unless ($row->is_active)
                                    <div class="muted">停止中</div>
                                @endunless
                            </td>
                            <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                            @foreach ($months as $month)
                                @php
                                    $amount = (float) ($row->monthly[$month->year_month]['amount'] ?? 0);
                                @endphp
                                <td style="text-align: right; {{ $amount < 0 ? 'color: #dc2626;' : '' }}">
                                    {{ number_format($amount, 2) }}
                                </td>
                            @endforeach
                            <td style="text-align: right; {{ (float) $row->total_amount < 0 ? 'color: #dc2626;' : '' }}">
                                {{ number_format((float) $row->total_amount, 2) }}
                            </td>
                            <td>
                                <a
                                    href="{{ route('general-ledgers.index', [
                                        'book_id' => $selectedBookId,
                                        'account_title_id' => $row->account_title_id,
                                        'date_from' => $dateFrom,
                                        'date_to' => $dateTo,
                                    ]) }}"
                                    class="button button-secondary"
                                >
                                    元帳
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 5 + $months->count() }}">
                                指定条件に一致する月次推移データがありません。登録済み仕訳の収益科目・費用科目を確認してください。
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
