<table class="data-table">
    <thead>
        <tr>
            <th>科目CODE</th>
            <th>科目名</th>
            <th>区分</th>
            <th>通常残高</th>
            <th>仕訳金額</th>
            <th>判定</th>
            <th>税抜相当額</th>
            <th>消費税相当額</th>
            <th>税込相当額</th>
            <th>判定理由</th>
            <th>元帳</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row->account_code }}</td>
                <td>
                    {{ $row->account_name }}
                    @unless ($row->is_active)
                        <div class="muted">停止中</div>
                    @endunless
                </td>
                <td>{{ $categoryLabels[$row->category] ?? $row->category }}</td>
                <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                    {{ number_format((float) $row->amount, 2) }}
                </td>
                <td>
                    @if ($row->taxable)
                        <span style="color: #166534;">{{ $row->tax_target_label }}</span>
                    @else
                        <span class="muted">{{ $row->tax_target_label }}</span>
                    @endif
                </td>
                <td style="text-align: right;">{{ number_format((float) $row->tax_base_amount, 2) }}</td>
                <td style="text-align: right;">{{ number_format((float) $row->consumption_tax_amount, 2) }}</td>
                <td style="text-align: right;">{{ number_format((float) $row->tax_included_amount, 2) }}</td>
                <td class="muted">{{ $row->tax_reason }}</td>
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
                <td colspan="11">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>