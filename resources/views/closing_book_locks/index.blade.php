@extends('layouts.app')

@section('title', '年度締め・帳簿ロック')

@section('content')
    @php
        $statusLabels = [
            'all' => 'すべて',
            'draft' => '下書き',
            'open' => '運用中',
            'closed' => '締了',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">年度締め・帳簿ロック</h2>
            <p class="page-description">年度処理が終わった帳簿を締了にし、登録・修正・削除を止めます。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('data-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                データメニューへ戻る
            </a>
            <a
                href="{{ route('utility-menu.index', array_filter(['book_id' => ($selectedBookId ?? request('book_id') ?? request('source_book_id'))], fn ($value) => $value !== null && $value !== '')) }}"
                class="button button-secondary"
            >
                ユーティリティメニューへ戻る
            </a>
            <a href="{{ route('closing.next-year-rollovers.index') }}" class="button button-secondary">年度繰越プレビューへ</a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ戻る</a>
        </div>
    </div>

    <div class="alert alert-success" style="background: #eff6ff; color: #1e3a8a; border-color: #bfdbfe;">
        締了にした帳簿は、通常の登録・修正・削除をブロックします。
        閲覧と帳票確認は引き続き可能です。修正が必要な場合は、この画面で「運用中に戻す」を実行してください。
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
        <form method="GET" action="{{ route('closing.book-locks.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="business_owner_id">事業主</label>
                    <select id="business_owner_id" name="business_owner_id">
                        <option value="">すべて表示</option>
                        @foreach ($businessOwners as $businessOwner)
                            <option
                                value="{{ $businessOwner->id }}"
                                {{ (string) $selectedBusinessOwnerId === (string) $businessOwner->id ? 'selected' : '' }}
                            >
                                {{ $businessOwner->name }}
                            </option>
                        @endforeach
                    </select>
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
                <a href="{{ route('closing.book-locks.index') }}" class="button button-secondary">条件を初期化</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <h3 style="margin-top: 0;">サマリー</h3>

        <div class="form-grid">
            <div class="field">
                <label>帳簿件数</label>
                <div>{{ $summary['books_count'] }} 件</div>
            </div>

            <div class="field">
                <label>運用中</label>
                <div>{{ $summary['open_count'] }} 件</div>
            </div>

            <div class="field">
                <label>締了</label>
                <div>{{ $summary['closed_count'] }} 件</div>
            </div>

            <div class="field">
                <label>締め不可候補</label>
                <div style="{{ (int) $summary['cannot_close_count'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                    {{ $summary['cannot_close_count'] }} 件
                </div>
            </div>

            <div class="field">
                <label>未投稿仕訳</label>
                <div style="{{ (int) $summary['unposted_journal_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                    {{ $summary['unposted_journal_total'] }} 件
                </div>
            </div>

            <div class="field">
                <label>貸借不一致仕訳</label>
                <div style="{{ (int) $summary['unbalanced_journal_total'] > 0 ? 'color: #dc2626;' : 'color: #166534;' }}">
                    {{ $summary['unbalanced_journal_total'] }} 件
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主</th>
                    <th>帳簿</th>
                    <th>期間</th>
                    <th>状態</th>
                    <th>仕訳数</th>
                    <th>入金予定/入金</th>
                    <th>締め前確認</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($books as $row)
                    <tr>
                        <td>{{ $row->book->id }}</td>
                        <td>{{ $row->book->businessOwner?->name ?? '—' }}</td>
                        <td>
                            {{ $row->book->book_code ?: '—' }}
                            /
                            {{ $row->book->name }}
                        </td>
                        <td>
                            {{ $row->book->period_start_date?->format('Y-m-d') ?? '—' }}
                            〜
                            {{ $row->book->period_end_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td>{{ $row->status_label }}</td>
                        <td>{{ $row->journal_entries_count }} 件</td>
                        <td>
                            予定 {{ $row->payment_schedules_count }} 件
                            /
                            入金 {{ $row->payment_receipts_count }} 件
                        </td>
                        <td>
                            <div>未投稿仕訳: {{ $row->unposted_journal_count }} 件</div>
                            <div>貸借不一致: {{ $row->unbalanced_journal_count }} 件</div>
                            <div>確定入金仕訳未作成: {{ $row->confirmed_receipts_without_journal_count }} 件</div>
                        </td>
                        <td>
                            <div class="actions">
                                <a href="{{ route('journal-entries.index', ['book_id' => $row->book->id]) }}" class="button button-secondary">仕訳</a>
                                <a href="{{ route('reports.blue-return-statement-previews.index', ['book_id' => $row->book->id]) }}" class="button button-secondary">決算確認</a>

                                @if ($row->can_close)
                                    <form
                                        method="POST"
                                        action="{{ route('closing.book-locks.close', $row->book) }}"
                                        onsubmit="return confirm('この帳簿を年度締め済みにしますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        <input type="hidden" name="note" value="年度締め画面から締了">
                                        <button type="submit" class="button">締了にする</button>
                                    </form>
                                @elseif ($row->can_reopen)
                                    <form
                                        method="POST"
                                        action="{{ route('closing.book-locks.reopen', $row->book) }}"
                                        onsubmit="return confirm('この帳簿を運用中に戻しますか？');"
                                        style="display: inline-block; margin: 0;"
                                    >
                                        @csrf
                                        <input type="hidden" name="note" value="年度締め画面から再開">
                                        <button type="submit" class="button" style="background: #f97316;">運用中に戻す</button>
                                    </form>
                                @else
                                    <span class="muted">確認後に締了可能</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">条件に一致する帳簿はありません。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection