@extends('layouts.app')

@section('title', '空室・入退去予定一覧')

@section('content')
    @php
        $occupancyStatusLabels = [
            'all' => 'すべて',
            'occupied' => '入居中',
            'vacant' => '空室',
            'ending_soon' => '退去予定',
        ];

        $statusColors = [
            'occupied' => '#166534',
            'vacant' => '#dc2626',
            'ending_soon' => '#f97316',
        ];

        $contractStatusLabels = [
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">空室・入退去予定一覧</h2>
            <p class="page-description">基準日時点の入居状況と、指定期間内の入居・退去予定を確認します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">賃貸条件一覧へ</a>
                <a href="{{ route('rental-contract-move-outs.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">退去処理へ</a>
                <a href="{{ route('monthly-payment-schedules.create', ['book_id' => $selectedBookId]) }}" class="button button-secondary">月次入金予定生成へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        空室判定は、部屋・区画に紐づく賃貸条件の契約状態、契約開始日、契約終了日、入退去日から判定します。
        退去予定がある契約は、指定期間内に終了予定として表示します。
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('reports.occupancy-statuses.index') }}">
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
                    <label for="target_date">基準日<span class="required">必須</span></label>
                    <input id="target_date" type="date" name="target_date" value="{{ $targetDate }}" required>
                </div>

                <div class="field">
                    <label for="date_from">入退去予定開始</label>
                    <input id="date_from" type="date" name="date_from" value="{{ $dateFrom }}">
                </div>

                <div class="field">
                    <label for="date_to">入退去予定終了</label>
                    <input id="date_to" type="date" name="date_to" value="{{ $dateTo }}">
                </div>

                <div class="field">
                    <label for="occupancy_status">入居状態</label>
                    <select id="occupancy_status" name="occupancy_status">
                        @foreach ($occupancyStatusLabels as $value => $label)
                            <option value="{{ $value }}" {{ $occupancyStatus === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
                <a href="{{ $selectedBookId ? route('reports.occupancy-statuses.index', ['book_id' => $selectedBookId]) : route('reports.occupancy-statuses.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <div class="form-grid">
                <div class="field">
                    <label>部屋・区画数</label>
                    <div>{{ $summary['units_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>入居中</label>
                    <div style="color: #166534;">{{ $summary['occupied_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>空室</label>
                    <div style="color: #dc2626;">{{ $summary['vacant_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>退去予定</label>
                    <div style="color: #f97316;">{{ $summary['ending_soon_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>期間内入居予定</label>
                    <div>{{ $summary['move_in_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>期間内退去予定</label>
                    <div>{{ $summary['move_out_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>入居中月額合計</label>
                    <div>{{ number_format((float) $summary['monthly_total'], 2) }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">部屋・区画別 入居状況</h3>

        <table class="data-table">
            <thead>
                <tr>
                    <th>状態</th>
                    <th>物件 / 部屋</th>
                    <th>物件区分</th>
                    <th>所有者</th>
                    <th>現在の契約者</th>
                    <th>契約期間</th>
                    <th>入退去</th>
                    <th>月額</th>
                    <th>次回入居予定</th>
                    <th>直近退去</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($unitRows as $row)
                    <tr>
                        <td style="color: {{ $statusColors[$row->status] ?? '#111827' }};">
                            {{ $row->status_label }}
                            @unless ($row->is_active)
                                <div class="muted">区画停止</div>
                            @endunless
                        </td>
                        <td>
                            {{ $row->property_code ?? '—' }}
                            /
                            {{ $row->property_name ?? '—' }}
                            <div class="muted">
                                部屋・区画: {{ $row->unit_no }}
                                @if ($row->layout_code)
                                    / 間取り: {{ $row->layout_code }}
                                @endif
                            </div>
                        </td>
                        <td>{{ $row->property_category_name ?? '—' }}</td>
                        <td>
                            {{ $row->owner_code ?? '—' }}
                            /
                            {{ $row->owner_name ?? '所有者未設定' }}
                        </td>
                        <td>
                            @if ($row->tenant_name)
                                {{ $row->tenant_code ?? '—' }}
                                /
                                {{ $row->tenant_name }}
                                @if ($row->contract_no)
                                    <div class="muted">契約番号: {{ $row->contract_no }}</div>
                                @endif
                            @else
                                <span style="color: #dc2626;">空室</span>
                            @endif
                        </td>
                        <td>
                            {{ $row->contract_started_on ?? '—' }}
                            〜
                            {{ $row->contract_ended_on ?? '—' }}
                        </td>
                        <td>
                            入居: {{ $row->move_in_on ?? '—' }}
                            <br>
                            退去: {{ $row->move_out_on ?? '—' }}
                        </td>
                        <td style="text-align: right;">{{ number_format((float) $row->monthly_total, 2) }}</td>
                        <td>
                            @if ($row->next_move_in_on)
                                {{ $row->next_move_in_on }}
                                <div class="muted">{{ $row->next_tenant_name ?? '契約者不明' }}</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($row->last_move_out_on)
                                {{ $row->last_move_out_on }}
                                <div class="muted">{{ $row->last_tenant_name ?? '契約者不明' }}</div>
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">条件に一致する部屋・区画がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">期間内 入居予定</h3>

        @include('reports.occupancy_statuses.partials.contract_rows', [
            'rows' => $moveInRows,
            'emptyMessage' => '指定期間内の入居予定はありません。',
            'dateType' => 'move_in',
        ])
    </div>

    <div class="card">
        <h3 style="margin-top: 0;">期間内 退去予定</h3>

        @include('reports.occupancy_statuses.partials.contract_rows', [
            'rows' => $moveOutRows,
            'emptyMessage' => '指定期間内の退去予定はありません。',
            'dateType' => 'move_out',
        ])
    </div>
@endsection