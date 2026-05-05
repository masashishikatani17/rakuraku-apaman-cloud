@extends('layouts.app')

@section('title', '消費税区分レビュー')

@section('content')
    @php
        $displayLabels = [
            'review' => '確認が必要な科目',
            'auto' => '自動判定の科目',
            'all' => 'すべての科目',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">消費税区分レビュー</h2>
            <p class="page-description">勘定科目の消費税区分と税率を、申告用集計の前に確認・一括補正します。</p>
        </div>
        <div class="actions">
            @if ($selectedBookId)
                <a href="{{ route('reports.consumption-tax-filing.index', ['book_id' => $selectedBookId, 'default_tax_rate' => $defaultTaxRate]) }}" class="button button-secondary">消費税申告用集計へ</a>
                <a href="{{ route('reports.consumption-tax.index', ['book_id' => $selectedBookId, 'tax_rate' => $defaultTaxRate]) }}" class="button button-secondary">消費税集計へ</a>
                <a href="{{ route('account-titles.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">勘定科目一覧へ</a>
            @endif
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #fff7ed; color: #9a3412; border-color: #fed7aa;">
        初期候補は科目名からの推定です。住宅家賃、土地貸付、保険料、租税公課、給与、利息などは実務判断が必要です。
        自動判定のまま残すより、申告前に勘定科目マスタへ消費税区分を保存しておくと集計精度が上がります。
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
        <form method="GET" action="{{ route('consumption-tax-category-reviews.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿<span class="required">必須</span></label>
                    <select id="book_id" name="book_id" required>
                        @foreach ($books as $book)
                            <option value="{{ $book->id }}" {{ (string) $selectedBookId === (string) $book->id ? 'selected' : '' }}>
                                {{ ($book->businessOwner?->name ?? '事業主未設定') . ' / ' . $book->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="display">表示対象</label>
                    <select id="display" name="display">
                        @foreach ($displayLabels as $value => $label)
                            <option value="{{ $value }}" {{ $display === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="default_tax_rate">候補税率（%）</label>
                    <input id="default_tax_rate" type="number" name="default_tax_rate" value="{{ $defaultTaxRate }}" step="0.1" min="0" max="100">
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">表示する</button>
            </div>
        </form>
    </div>

    @if ($selectedBook)
        <div class="card" style="margin-bottom: 16px;">
            <h3 style="margin-top: 0;">レビューサマリー</h3>

            <div class="form-grid">
                <div class="field">
                    <label>表示科目数</label>
                    <div>{{ $summary['rows_count'] }} 科目</div>
                </div>

                <div class="field">
                    <label>確認必要</label>
                    <div style="{{ (int) $summary['review_count'] > 0 ? 'color: #f97316;' : 'color: #166534;' }}">
                        {{ $summary['review_count'] }} 科目
                    </div>
                </div>

                <div class="field">
                    <label>自動判定</label>
                    <div style="{{ (int) $summary['auto_count'] > 0 ? 'color: #f97316;' : 'color: #166534;' }}">
                        {{ $summary['auto_count'] }} 科目
                    </div>
                </div>

                <div class="field">
                    <label>課税売上候補</label>
                    <div>{{ $summary['taxable_sales_count'] }} 科目</div>
                </div>

                <div class="field">
                    <label>課税仕入候補</label>
                    <div>{{ $summary['taxable_purchase_count'] }} 科目</div>
                </div>

                <div class="field">
                    <label>対象外候補</label>
                    <div>{{ $summary['not_applicable_count'] }} 科目</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <h3 style="margin-top: 0;">消費税区分の一括補正</h3>

        <form method="POST" action="{{ route('consumption-tax-category-reviews.update') }}">
            @csrf
            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">
            <input type="hidden" name="display" value="{{ $display }}">
            <input type="hidden" name="default_tax_rate" value="{{ $defaultTaxRate }}">

            <table class="data-table">
                <thead>
                    <tr>
                        <th>反映</th>
                        <th>科目CODE</th>
                        <th>科目名</th>
                        <th>区分</th>
                        <th>現在の消費税区分</th>
                        <th>候補区分</th>
                        <th>候補税率</th>
                        <th>理由</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <input type="hidden" name="account_titles[{{ $row->account_title_id }}][account_title_id]" value="{{ $row->account_title_id }}">
                                <input type="checkbox" name="account_titles[{{ $row->account_title_id }}][apply]" value="1" {{ $row->needs_review ? 'checked' : '' }}>
                            </td>
                            <td>{{ $row->account_code }}</td>
                            <td>
                                {{ $row->account_name }}
                                @if (!$row->is_active)
                                    <div class="muted">停止中</div>
                                @endif
                            </td>
                            <td>{{ $row->category_label }}</td>
                            <td>
                                {{ $row->current_consumption_tax_category_label }}
                                @if ($row->current_consumption_tax_rate !== null)
                                    <div class="muted">税率 {{ number_format((float) $row->current_consumption_tax_rate, 2) }}%</div>
                                @else
                                    <div class="muted">税率未設定</div>
                                @endif
                            </td>
                            <td>
                                <select name="account_titles[{{ $row->account_title_id }}][consumption_tax_category]">
                                    @foreach ($categoryLabels as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            {{ old('account_titles.' . $row->account_title_id . '.consumption_tax_category', $row->suggested_consumption_tax_category) === $value ? 'selected' : '' }}
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input
                                    type="number"
                                    name="account_titles[{{ $row->account_title_id }}][consumption_tax_rate]"
                                    value="{{ old('account_titles.' . $row->account_title_id . '.consumption_tax_rate', $row->suggested_consumption_tax_rate) }}"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    style="max-width: 100px; text-align: right;"
                                >
                            </td>
                            <td>
                                {{ $row->reason }}
                                @if ($row->needs_review)
                                    <div style="color: #f97316;">確認対象</div>
                                @else
                                    <div style="color: #166534;">現在設定と候補は概ね一致</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">表示対象の勘定科目はありません。</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button" {{ $rows->isEmpty() ? 'disabled' : '' }}>チェックした科目へ候補を反映</button>
            </div>
        </form>
    </div>
@endsection