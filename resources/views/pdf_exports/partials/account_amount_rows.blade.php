<table>
    <thead>
        <tr>
            <th>科目CODE</th>
            <th>科目名</th>
            <th>区分</th>
            <th>借方合計</th>
            <th>貸方合計</th>
            <th>金額</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ $row->account_code }}</td>
                <td>{{ $row->account_name }}</td>
                <td>{{ $row->category }}</td>
                <td class="text-end">{{ number_format((float) $row->debit_total, 2) }}</td>
                <td class="text-end">{{ number_format((float) $row->credit_total, 2) }}</td>
                <td class="text-end">{{ number_format((float) $row->amount, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
