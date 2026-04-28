<table class="data-table">
    <thead>
        <tr>
            <th>科目コード</th>
            <th>科目名</th>
            <th>通常残高</th>
            <th>借方合計</th>
            <th>貸方合計</th>
            <th>貸借対照表金額</th>
            <th>状態</th>
            <th>元帳</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row->account_code }}</td>
                <td>{{ $row->account_name }}</td>
                <td>{{ $sideLabels[$row->normal_balance] ?? $row->normal_balance }}</td>
                <td style="text-align: right;">{{ number_format((float) $row->debit_total, 2) }}</td>
                <td style="text-align: right;">{{ number_format((float) $row->credit_total, 2) }}</td>
                <td style="text-align: right; {{ (float) $row->amount < 0 ? 'color: #dc2626;' : '' }}">
                    {{ number_format((float) $row->amount, 2) }}
                </td>
                <td>{{ $row->is_active ? '有効' : '停止' }}</td>
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
                <td colspan="8">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>