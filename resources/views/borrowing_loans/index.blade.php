@extends('layouts.app')

@section('title', '借入金台帳')

@section('content')
    @php
        $statusLabels = [
            'all' => 'すべて',
            'active' => '返済中',
            'paid_off' => '完済',
        ];

        $repaymentMethodLabels = [
            'equal_principal' => '元金均等',
            'equal_payment' => '元利均等',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">借入金台帳</h2>
            <p class="page-description">借入金を登録し、返済予定表と元金・利息の返済仕訳を作成します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('borrowing-loans.create', ['book_id' => $selectedBookId]) }}"
                    class="button"
                >
                    借入金を登録
                </a>
                <a
                    href="{{ route('journal-entries.index', ['book_id' => $selectedBookId]) }}"
                    class="button button-secondary"
                >
                    仕訳一覧へ
                </a>
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
                <a
                    href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    損益計算書へ
                </a>
            @endif
            <a
                href="{{ (isset($selectedBookId) && $selectedBookId) ? route('master-menu.index', ['book_id' => $selectedBookId]) : route('master-menu.index') }}"
                class="button button-secondary"
            >
                マスタメニューへ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では、元金均等・元利均等の返済予定表を作成します。
        「借入返済仕訳を作成・更新」を押すと、<strong>entry_type = loan_repayment</strong> の仕訳を作成し、元金返済を借入金の借方、利息を支払利息、支払総額を預金等の貸方に登録します。
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('borrowing-loans.index') }}">
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
                    <label for="status">状態</label>
                    <select id="status" name="status">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a
                    href="{{ $selectedBookId ? route('borrowing-loans.index', ['book_id' => $selectedBookId]) : route('borrowing-loans.index') }}"
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
                    <label>対象期間</label>
                    <div class="muted">{{ $dateFrom ?: '開始未指定' }} 〜 {{ $dateTo ?: '終了未指定' }}</div>
                </div>

                <div class="field">
                    <label>借入件数</label>
                    <div>{{ $summary['loans_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>当初借入額合計</label>
                    <div>{{ number_format((float) $summary['principal_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>期間末借入残高</label>
                    <div>{{ number_format((float) $summary['remaining_principal_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>作成済み返済仕訳</label>
                    <div>{{ $summary['journal_count'] }} 件</div>
                </div>
            </div>

            <div class="form-grid" style="margin-top: 16px;">
                <div class="field">
                    <label>期間中元金返済</label>
                    <div>{{ number_format((float) $summary['period_principal_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>期間中利息</label>
                    <div>{{ number_format((float) $summary['period_interest_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>期間中支払総額</label>
                    <div>{{ number_format((float) $summary['period_total'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <form
                method="POST"
                action="{{ route('borrowing-loans.repayment-journals.store') }}"
                onsubmit="return confirm('表示中の返済予定について、借入返済仕訳を作成・更新しますか？');"
            >
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">

                <p class="muted" style="margin-top: 0;">
                    表示期間内の返済予定について、返済予定日を仕訳日付として借入返済仕訳を作成・更新します。
                </p>

                <div class="actions">
                    <button type="submit" class="button">借入返済仕訳を作成・更新</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">借入金一覧</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>借入コード</th>
                    <th>借入名</th>
                    <th>借入先</th>
                    <th>物件 / 部門</th>
                    <th>借入日</th>
                    <th>当初借入額</th>
                    <th>年利</th>
                    <th>返済方法</th>
                    <th>期間中元金</th>
                    <th>期間中利息</th>
                    <th>期間末残高</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($loanRows as $row)
                    @php $loan = $row->loan; @endphp
                    <tr>
                        <td>{{ $loan->loan_code }}</td>
                        <td>
                            {{ $loan->name }}
                            <div class="muted">{{ $statusLabels[$loan->status] ?? $loan->status }}</div>
                        </td>
                        <td>{{ $loan->lender_name ?: '—' }}</td>
                        <td>
                            @if ($loan->property)
                                {{ $loan->property->property_code }} / {{ $loan->property->name }}
                            @else
                                —
                            @endif
                            @if ($loan->department)
                                <div class="muted">部門: {{ $loan->department->department_code }} {{ $loan->department->name }}</div>
                            @endif
                        </td>
                        <td>{{ $loan->borrowed_on?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ number_format((float) $loan->principal_amount, 2) }}</td>
                        <td>{{ number_format((float) $loan->annual_interest_rate, 4) }}%</td>
                        <td>
                            {{ $repaymentMethodLabels[$loan->repayment_method] ?? $loan->repayment_method }}
                            <div class="muted">{{ $loan->term_months }} 回 / 毎月 {{ $loan->monthly_repayment_day }} 日</div>
                        </td>
                        <td>{{ number_format((float) $row->period_principal_total, 2) }}</td>
                        <td>{{ number_format((float) $row->period_interest_total, 2) }}</td>
                        <td>{{ number_format((float) $row->remaining_principal_after_period, 2) }}</td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('borrowing-loans.edit', $loan) }}" class="button button-secondary">修正</a>
                                <form
                                    method="POST"
                                    action="{{ route('borrowing-loans.destroy', $loan) }}"
                                    onsubmit="return confirm('この借入金を削除しますか？');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="button" style="background: #dc2626;">削除</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">指定条件に一致する借入金がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">返済予定表</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>返済日</th>
                    <th>借入コード</th>
                    <th>借入名</th>
                    <th>回数</th>
                    <th>元金</th>
                    <th>利息</th>
                    <th>支払総額</th>
                    <th>返済後残高</th>
                    <th>仕訳</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($repaymentRows as $row)
                    @php
                        $loan = $row->loan;
                        $repayment = $row->repayment;
                    @endphp
                    <tr>
                        <td>{{ $repayment->due_on?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $loan->loan_code }}</td>
                        <td>{{ $loan->name }}</td>
                        <td>{{ $repayment->period_no }} 回</td>
                        <td>{{ number_format((float) $repayment->principal_amount, 2) }}</td>
                        <td>{{ number_format((float) $repayment->interest_amount, 2) }}</td>
                        <td>{{ number_format((float) $repayment->total_amount, 2) }}</td>
                        <td>{{ number_format((float) $repayment->remaining_principal_after, 2) }}</td>
                        <td>
                            @if ($repayment->journalEntry)
                                {{ $repayment->journalEntry->voucher_no }}
                                <div class="muted">ID: {{ $repayment->journalEntry->id }}</div>
                            @else
                                <span class="muted">未作成</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">指定期間に返済予定がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection