@extends('layouts.app')

@section('title', '契約者台帳')

@section('content')
    @php
        $statusLabels = [
            'active' => '契約中',
            'planned' => '予定',
            'ended' => '終了',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">契約者台帳</h2>
            <p class="page-description">Access の T_契約者データ_基本情報 / 賃貸条件に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ $selectedBookId ? route('contract-tenants.create', ['book_id' => $selectedBookId]) : route('contract-tenants.create') }}"
                class="button"
            >
                契約者を新規登録
            </a>
            <a
                href="{{ $selectedBookId ? route('reports.contract-tenant-annual-incomes.index', ['book_id' => $selectedBookId]) : route('reports.contract-tenant-annual-incomes.index') }}"
                class="button button-secondary"
            >
                契約者別年間収入内訳表
            </a>
            <a
                href="{{ $selectedBookId ? route('reports.rental-contracts.index', ['book_id' => $selectedBookId]) : route('reports.rental-contracts.index') }}"
                class="button button-secondary"
            >
                賃貸条件一覧
            </a>
            <a
                href="{{ (isset($selectedBookId) && $selectedBookId) ? route('master-menu.index', ['book_id' => $selectedBookId]) : route('master-menu.index') }}"
                class="button button-secondary"
            >
                マスタメニューへ戻る
            </a>
            <a href="{{ route('books.index') }}" class="button button-secondary">帳簿一覧へ</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('contract-tenants.index') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id">帳簿で絞り込み</label>
                    <select id="book_id" name="book_id">
                        <option value="">すべて表示</option>
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
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('contract-tenants.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $contractTenants->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主 / 帳簿</th>
                    <th>契約者CODE</th>
                    <th>契約者名</th>
                    <th>状態</th>
                    <th>物件 / 部屋</th>
                    <th>契約期間</th>
                    <th>月額</th>
                    <th>敷金 / 保証金</th>
                    <th>連絡先</th>
                    <th>契約数</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($contractTenants as $contractTenant)
                    @php
                        $contract = $contractTenant->latestRentalContract;
                        $monthlyTotal = $contract
                            ? (float) $contract->rent_amount
                                + (float) $contract->common_service_fee
                                + (float) $contract->parking_fee
                                + (float) $contract->other_monthly_fee
                            : 0;
                    @endphp
                    <tr>
                        <td>{{ $contractTenant->id }}</td>
                        <td>
                            {{ $contractTenant->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $contractTenant->book?->name ?? '—' }}
                        </td>
                        <td>{{ $contractTenant->tenant_code }}</td>
                        <td>
                            {{ $contractTenant->name }}
                            @if ($contractTenant->short_name)
                                <div class="muted">略称: {{ $contractTenant->short_name }}</div>
                            @endif
                        </td>
                        <td>{{ $statusLabels[$contractTenant->status] ?? $contractTenant->status }}</td>
                        <td>
                            @if ($contract)
                                {{ $contract->property?->property_code ?? '—' }}
                                /
                                {{ $contract->property?->name ?? '—' }}
                                @if ($contract->propertyUnit)
                                    <div class="muted">部屋: {{ $contract->propertyUnit->unit_no }}</div>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($contract)
                                {{ $contract->contract_started_on?->format('Y-m-d') ?? '—' }}
                                〜
                                {{ $contract->contract_ended_on?->format('Y-m-d') ?? '—' }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ number_format($monthlyTotal, 2) }}</td>
                        <td>
                            @if ($contract)
                                敷金: {{ number_format((float) $contract->deposit_amount, 2) }}
                                <br>
                                保証金: {{ number_format((float) $contract->guarantee_deposit_amount, 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            {{ $contractTenant->phone ?: '—' }}
                            @if ($contractTenant->mobile)
                                <div class="muted">携帯: {{ $contractTenant->mobile }}</div>
                            @endif
                        </td>
                        <td>{{ $contractTenant->rental_contracts_count }} 件</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('contract-tenants.edit', $contractTenant) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('contract-tenants.destroy', $contractTenant) }}"
                                    onsubmit="return confirm('この契約者を削除しますか？ 関連する賃貸条件も削除されます。');"
                                    style="display: inline-block; margin: 0;"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="button"
                                        style="background: #dc2626;"
                                    >
                                        削除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">まだ契約者が登録されていません。「契約者を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection