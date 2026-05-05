@extends('layouts.app')

@section('title', '翌期入金予定生成')

@section('content')
    @php
        $displayLabels = [
            'creatable' => '作成予定のみ',
            'all' => '全件表示',
        ];

        $statusColors = [
            'create' => '#166534',
            'existing' => '#6b7280',
            'missing_item' => '#dc2626',
            'zero_amount' => '#6b7280',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">翌期入金予定生成</h2>
            <p class="page-description">翌期帳簿の賃貸条件から、期間内の月次入金予定を一括生成します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('monthly-payment-schedules.create', ['book_id' => $selectedBookId]) }}" class="button button-secondary">月次入金予定生成へ</a>
                <a href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">入金予定一覧へ</a>
                <a href="{{ route('closing.next-year-rental-carryovers.index', ['target_book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸データ引継ぎへ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        月額変更履歴がある場合は、対象年月以前で最新の月額変更履歴を使います。
        既に同じ契約・入金項目・予定日の入金予定がある場合は、重複作成しません。
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
        <form method="GET" action="{{ route('closing.next-year-payment-schedule-builds.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">翌期帳簿<span class="required">必須</span></label>
                    <select id="book_id" name="book_id" required>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                                （{{ $book->period_start_date?->format('Y-m-d') }}〜{{ $book->period_end_date?->format('Y-m-d') }}）
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="date_from">生成開始日<span class="required">必須</span></label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}" required>
                </div>

                <div class="field">
                    <label for="date_to">生成終了日<span class="required">必須</span></label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}" required>
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
                <button type="submit" class="button">プレビュー</button>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">生成サマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>対象月数</label>
                    <div>{{ $summary['months_count'] }} ヶ月</div>
                </div>

                <div class="field">
                    <label>対象契約数</label>
                    <div>{{ $summary['contracts_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>作成予定</label>
                    <div style="color: #166534;">{{ $summary['creatable_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>作成予定額</label>
                    <div>{{ number_format((float) $summary['creatable_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>作成済</label>
                    <div>{{ $summary['existing_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>入金項目なし</label>
                    <div style="{{ (int) $summary['missing_item_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $summary['missing_item_count'] }} 件
                    </div>
                </div>

                <div class="field">
                    <label>金額0</label>
                    <div>{{ $summary['zero_amount_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>入金口座</label>
                    <div>{{ $summary['default_payment_account']?->name ?? '未設定' }}</div>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('closing.next-year-payment-schedule-builds.store') }}"
                onsubmit="return confirm('表示中の期間で翌期入金予定を作成しますか？既存予定は重複作成しません。');"
                style="margin-top: 16px;"
            >
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">

                <button type="submit" class="button" {{ (int) $summary['creatable_count'] <= 0 ? 'disabled' : '' }}>
                    翌期入金予定を作成
                </button>
            </form>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0;">生成候補</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>対象年月</th>
                    <th>予定日</th>
                    <th>契約者</th>
                    <th>物件 / 部屋</th>
                    <th>入金項目</th>
                    <th>入金口座</th>
                    <th>金額</th>
                    <th>月額履歴</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['rows'] as $row)
                    <tr>
                        <td style="color: {{ $statusColors[$row->status] ?? '#111827' }};">
                            {{ $row->status_label }}
                        </td>
                        <td>{{ $row->target_year_month }}</td>
                        <td>{{ $row->due_on }}</td>
                        <td>
                            {{ $row->tenant_code ?? '—' }}
                            /
                            {{ $row->tenant_name ?? '—' }}
                            @if ($row->contract_no)
                                <div class="muted">契約: {{ $row->contract_no }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $row->property_code ?? '—' }}
                            /
                            {{ $row->property_name ?? '—' }}
                            @if ($row->unit_no)
                                <div class="muted">部屋: {{ $row->unit_no }}</div>
                            @endif
                        </td>
                        <td>{{ $row->payment_item_name ?? $row->payment_item_type }}</td>
                        <td>{{ $row->payment_account_name ?? '—' }}</td>
                        <td style="text-align: right;">{{ number_format((float) $row->amount, 2) }}</td>
                        <td>
                            @if ($row->term_id)
                                #{{ $row->term_id }} / {{ $row->term_year_month }}
                            @else
                                <span class="muted">契約基本額</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">生成候補はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection