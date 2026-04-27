@extends('layouts.app')

@section('title', '月次入金予定生成')

@section('content')
    @php
        $itemTypeLabels = [
            'rent' => '家賃',
            'common_service' => '共益費',
            'parking' => '駐車料',
            'other' => 'その他月額',
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
            <h2 class="page-title">月次入金予定生成</h2>
            <p class="page-description">賃貸条件から、指定月の家賃・共益費・駐車料などの入金予定をまとめて作成します。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('payment-schedules.index', ['book_id' => $selectedBookId]) : route('payment-schedules.index') }}"
                class="button button-secondary"
            >
                入金予定一覧へ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、契約状態が「契約中」の賃貸条件を対象にします。
        入金項目マスタの種別が rent / common_service / parking / other のものを使って予定を作成します。
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if ($books->isEmpty())
        <div class="alert alert-error">
            帳簿がまだ登録されていません。先に帳簿を登録してください。
        </div>
    @else
        <div class="card" style="margin-bottom: 16px;">
            <form method="GET" action="{{ route('monthly-payment-schedules.create') }}">
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
                        <label for="target_year_month">対象年月<span class="required">必須</span></label>
                        <input
                            id="target_year_month"
                            type="month"
                            name="target_year_month"
                            value="{{ $targetYearMonth }}"
                            required
                        >
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">生成内容を確認する</button>
                </div>
            </form>
        </div>

        @if ($selectedBook === null)
            <div class="alert alert-error">
                対象の帳簿を選択してください。
            </div>
        @else
            <div class="card" style="margin-bottom: 16px;">
                <div class="form-grid">
                    <div class="field">
                        <label>対象帳簿</label>
                        <div class="muted">
                            {{ ($selectedBook->businessOwner?->name ?? '事業主未設定') . ' / ' . $selectedBook->name }}
                        </div>
                    </div>

                    <div class="field">
                        <label>対象年月</label>
                        <div class="muted">{{ $targetYearMonth }}</div>
                    </div>

                    <div class="field">
                        <label>対象契約数</label>
                        <div>{{ $summary['contracts_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>標準入金口座</label>
                        <div class="muted">
                            @if ($summary['default_payment_account'])
                                {{ $summary['default_payment_account']->account_code }}
                                /
                                {{ $summary['default_payment_account']->name }}
                            @else
                                未設定
                            @endif
                        </div>
                    </div>
                </div>

                <div class="form-grid" style="margin-top: 16px;">
                    <div class="field">
                        <label>作成予定</label>
                        <div style="color: #166534;">{{ $summary['creatable_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>作成済</label>
                        <div class="muted">{{ $summary['existing_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>入金項目なし</label>
                        <div style="color: #dc2626;">{{ $summary['missing_item_count'] }} 件</div>
                    </div>

                    <div class="field">
                        <label>金額0</label>
                        <div class="muted">{{ $summary['zero_amount_count'] }} 件</div>
                    </div>
                </div>
            </div>

            @if ($summary['missing_item_count'] > 0)
                <div class="alert alert-error">
                    入金項目マスタが不足しています。
                    家賃・共益費・駐車料・その他月額を自動生成するには、入金項目マスタの種別に
                    rent / common_service / parking / other を登録してください。
                </div>
            @endif

            <div class="card" style="margin-bottom: 16px;">
                <form
                    method="POST"
                    action="{{ route('monthly-payment-schedules.store') }}"
                    onsubmit="return confirm('表示されている作成予定の入金予定を生成しますか？');"
                >
                    @csrf
                    <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                    <input type="hidden" name="target_year_month" value="{{ $targetYearMonth }}">

                    <div class="actions">
                        <button
                            type="submit"
                            class="button"
                            {{ $summary['creatable_count'] === 0 ? 'disabled' : '' }}
                        >
                            月次入金予定を生成する
                        </button>

                        <a
                            href="{{ route('payment-schedules.index', ['book_id' => $selectedBookId]) }}"
                            class="button button-secondary"
                        >
                            入金予定一覧を見る
                        </a>
                    </div>
                </form>
            </div>

            <div class="card">
                <p class="muted">生成候補: {{ $summary['rows']->count() }} 件</p>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>契約者</th>
                            <th>物件 / 部屋</th>
                            <th>入金項目種別</th>
                            <th>入金項目</th>
                            <th>予定日</th>
                            <th>予定金額</th>
                            <th>状態</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['rows'] as $row)
                            <tr>
                                <td>
                                    {{ $row->tenant_code ?? '—' }}
                                    /
                                    {{ $row->tenant_name ?? '—' }}
                                </td>
                                <td>
                                    {{ $row->property_code ?? '—' }}
                                    /
                                    {{ $row->property_name ?? '—' }}
                                    @if ($row->unit_no)
                                        <div class="muted">部屋: {{ $row->unit_no }}</div>
                                    @endif
                                </td>
                                <td>{{ $itemTypeLabels[$row->payment_item_type] ?? $row->payment_item_type }}</td>
                                <td>{{ $row->payment_item_name ?? '—' }}</td>
                                <td>{{ $row->due_on }}</td>
                                <td>{{ number_format((float) $row->amount, 2) }}</td>
                                <td style="color: {{ $statusColors[$row->status] ?? '#111827' }};">
                                    {{ $row->status_label }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">生成候補がありません。契約者台帳・賃貸条件・入金項目を確認してください。</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @endif
@endsection