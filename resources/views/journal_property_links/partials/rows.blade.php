<div class="card" style="margin-bottom: 16px;">
    <h3 style="margin-top: 0;">{{ $title }}</h3>

    <table class="data-table">
        <thead>
            <tr>
                <th>状態</th>
                <th>元データ</th>
                <th>仕訳ID</th>
                <th>伝票番号</th>
                <th>仕訳日</th>
                <th>期待する物件</th>
                <th>現在の仕訳明細物件</th>
                <th>明細数</th>
                <th>不一致数</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        @if ($row->needs_sync)
                            <span style="color: #dc2626;">補正必要</span>
                        @else
                            <span style="color: #166534;">OK</span>
                        @endif
                    </td>
                    <td>
                        <div>{{ $row->source_type }} #{{ $row->source_id ?? '—' }}</div>
                        <div class="muted">{{ $row->source_label }}</div>
                    </td>
                    <td>{{ $row->journal_entry_id ?? '—' }}</td>
                    <td>{{ $row->voucher_no ?: '—' }}</td>
                    <td>{{ $row->entry_date ?: '—' }}</td>
                    <td>{{ $row->expected_property_label }}</td>
                    <td>
                        @forelse ($row->actual_property_labels as $label)
                            <div>{{ $label }}</div>
                        @empty
                            <span class="muted">明細なし</span>
                        @endforelse
                    </td>
                    <td>{{ $row->lines_count }} 行</td>
                    <td style="{{ (int) $row->mismatch_count > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                        {{ $row->mismatch_count }} 行
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">対象データがありません。</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>