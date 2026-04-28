@extends('layouts.app')

@section('title', '減価償却')

@section('content')
    @php
        $statusLabels = [
            'all' => 'すべて',
            'active' => '使用中',
            'disposed' => '除却・売却済',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">減価償却</h2>
            <p class="page-description">固定資産を登録し、指定期間の減価償却費を計算して仕訳を作成します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a
                    href="{{ route('depreciable-assets.create', ['book_id' => $selectedBookId]) }}"
                    class="button"
                >
                    固定資産を登録
                </a>
                <a
                    href="{{ route('closing-adjustment-journals.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    決算整理仕訳へ
                </a>
                <a
                    href="{{ route('reports.income-statements.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    損益計算書へ
                </a>
                <a
                    href="{{ route('reports.balance-sheets.index', ['book_id' => $selectedBookId, 'date_from' => $dateFrom, 'date_to' => $dateTo]) }}"
                    class="button button-secondary"
                >
                    貸借対照表へ
                </a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        初版では定額法だけに対応します。固定資産ごとに取得価額・耐用年数・事業使用割合を登録し、対象期間の月数で按分した減価償却費を計算します。
        「減価償却仕訳を作成・更新」を押すと、<strong>entry_type = depreciation</strong> の仕訳を作成します。
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
        <form method="GET" action="{{ route('depreciable-assets.index') }}">
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
                    href="{{ $selectedBookId ? route('depreciable-assets.index', ['book_id' => $selectedBookId]) : route('depreciable-assets.index') }}"
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
                    <label>固定資産数</label>
                    <div>{{ $summary['assets_count'] }} 件</div>
                </div>

                <div class="field">
                    <label>取得価額合計</label>
                    <div>{{ number_format((float) $summary['acquisition_cost_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>当期償却費合計</label>
                    <div>{{ number_format((float) $summary['period_depreciation_total'], 2) }}</div>
                </div>

                <div class="field">
                    <label>作成済み償却仕訳</label>
                    <div>{{ $summary['journal_count'] }} 件</div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 16px;">
            <form
                method="POST"
                action="{{ route('depreciable-assets.depreciation-journals.store') }}"
                onsubmit="return confirm('表示中の使用中固定資産について、減価償却仕訳を作成・更新しますか？');"
            >
                @csrf
                <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">

                <div class="form-grid">
                    <div class="field">
                        <label for="entry_date">仕訳日付<span class="required">必須</span></label>
                        <input
                            id="entry_date"
                            type="date"
                            name="entry_date"
                            value="{{ old('entry_date', $dateTo) }}"
                            required
                        >
                    </div>

                    <div class="field">
                        <label>作成する仕訳</label>
                        <div class="muted">
                            借方: 減価償却費 /
                            貸方: 減価償却累計額、未設定の場合は固定資産科目を直接減額
                        </div>
                    </div>
                </div>

                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">減価償却仕訳を作成・更新</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>資産コード</th>
                    <th>資産名</th>
                    <th>物件</th>
                    <th>取得日</th>
                    <th>取得価額</th>
                    <th>残存価額</th>
                    <th>耐用年数</th>
                    <th>事業割合</th>
                    <th>当期月数</th>
                    <th>当期償却費</th>
                    <th>期末帳簿価額</th>
                    <th>作成済み仕訳</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assetRows as $row)
                    @php
                        $asset = $row->asset;
                        $depreciation = $row->depreciation;
                    @endphp
                    <tr>
                        <td>{{ $asset->asset_code }}</td>
                        <td>
                            {{ $asset->name }}
                            <div class="muted">{{ $statusLabels[$asset->status] ?? $asset->status }}</div>
                        </td>
                        <td>
                            @if ($asset->property)
                                {{ $asset->property->property_code }} / {{ $asset->property->name }}
                            @else
                                —
                            @endif
                            @if ($asset->department)
                                <div class="muted">部門: {{ $asset->department->department_code }} {{ $asset->department->name }}</div>
                            @endif
                        </td>
                        <td>{{ $asset->acquisition_date?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ number_format((float) $asset->acquisition_cost, 2) }}</td>
                        <td>{{ number_format((float) $asset->salvage_value, 2) }}</td>
                        <td>{{ $asset->useful_life_years }} 年</td>
                        <td>{{ number_format((float) $asset->business_use_ratio, 2) }}%</td>
                        <td>{{ $depreciation['period_months'] }} か月</td>
                        <td>{{ number_format((float) $depreciation['period_depreciation_amount'], 2) }}</td>
                        <td>{{ number_format((float) $depreciation['book_value_after_period'], 2) }}</td>
                        <td>
                            @if ($row->journal_entry)
                                <div>{{ $row->journal_entry->voucher_no }}</div>
                                <div class="muted">ID: {{ $row->journal_entry->id }}</div>
                            @else
                                <span class="muted">未作成</span>
                            @endif
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('depreciable-assets.edit', $asset) }}" class="button button-secondary">修正</a>
                                <form
                                    method="POST"
                                    action="{{ route('depreciable-assets.destroy', $asset) }}"
                                    onsubmit="return confirm('この固定資産を削除しますか？');"
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
                        <td colspan="13">指定条件に一致する固定資産がありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection