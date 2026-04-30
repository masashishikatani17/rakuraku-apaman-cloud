<table class="data-table">
    <thead>
        <tr>
            <th>日付</th>
            <th>契約者</th>
            <th>物件 / 部屋</th>
            <th>契約番号</th>
            <th>契約状態</th>
            <th>月額</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>
                    @if ($dateType === 'move_out')
                        {{ $row->move_out_on ?? $row->contract_ended_on ?? '—' }}
                    @else
                        {{ $row->move_in_on ?? $row->contract_started_on ?? '—' }}
                    @endif
                </td>
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
                        <div class="muted">部屋・区画: {{ $row->unit_no }}</div>
                    @endif
                </td>
                <td>{{ $row->contract_no ?: '—' }}</td>
                <td>{{ $contractStatusLabels[$row->contract_status] ?? $row->contract_status }}</td>
                <td style="text-align: right;">{{ number_format((float) $row->monthly_total, 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>