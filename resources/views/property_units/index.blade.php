@extends('layouts.app')

@section('title', '部屋・区画一覧')

@section('content')
    @php
        $unitTypeLabels = [
            'room' => '部屋',
            'parking' => '駐車場',
            'other' => 'その他',
        ];
    @endphp

    <div class="page-header">
        <div>
            <h2 class="page-title">部屋・区画一覧</h2>
            <p class="page-description">Access の MT_物件マスター詳細 に対応する最初の一覧画面です。</p>
        </div>
        <div class="actions">
            <a
                href="{{ route('property-units.create', ['book_id' => $selectedBookId, 'property_id' => $selectedPropertyId]) }}"
                class="button"
            >
                部屋・区画を新規登録
            </a>
            <a
                href="{{ $selectedBookId ? route('properties.index', ['book_id' => $selectedBookId]) : route('properties.index') }}"
                class="button button-secondary"
            >
                物件一覧へ戻る
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card" style="margin-bottom: 16px;">
        <p class="muted">
            今回は部屋番号・面積・間取り・駐車場区分・解約日など、構造情報に近い項目を先に実装します。
            賃貸条件の細かな列は、次の契約・賃貸条件側へ寄せて実装します。
        </p>
    </div>

    <div class="card" style="margin-bottom: 16px;">
        <form method="GET" action="{{ route('property-units.index') }}">
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

                <div class="field">
                    <label for="property_id">物件で絞り込み</label>
                    <select id="property_id" name="property_id">
                        <option value="">すべて表示</option>
                        @foreach ($properties as $property)
                            <option
                                value="{{ $property->id }}"
                                {{ (string) $selectedPropertyId === (string) $property->id ? 'selected' : '' }}
                            >
                                {{ $property->property_code . ' / ' . $property->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="actions" style="margin-top: 16px;">
                <button type="submit" class="button">絞り込む</button>
                <a href="{{ route('property-units.index') }}" class="button button-secondary">条件をクリア</a>
            </div>
        </form>
    </div>

    <div class="card">
        <p class="muted">登録件数: {{ $propertyUnits->count() }} 件</p>

        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>事業主 / 帳簿</th>
                    <th>物件</th>
                    <th>部屋番号 / 区画番号</th>
                    <th>種別</th>
                    <th>面積</th>
                    <th>間取りCODE</th>
                    <th>駐車場区分</th>
                    <th>解約日</th>
                    <th>新規登録</th>
                    <th>状態</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($propertyUnits as $propertyUnit)
                    <tr>
                        <td>{{ $propertyUnit->id }}</td>
                        <td>
                            {{ $propertyUnit->property?->book?->businessOwner?->name ?? '—' }}
                            /
                            {{ $propertyUnit->property?->book?->name ?? '—' }}
                        </td>
                        <td>
                            {{ $propertyUnit->property?->property_code ?? '—' }}
                            /
                            {{ $propertyUnit->property?->name ?? '—' }}
                        </td>
                        <td>{{ $propertyUnit->unit_no }}</td>
                        <td>{{ $unitTypeLabels[$propertyUnit->unit_type] ?? $propertyUnit->unit_type }}</td>
                        <td>{{ $propertyUnit->area_sqm !== null ? number_format((float) $propertyUnit->area_sqm, 2) : '—' }}</td>
                        <td>{{ $propertyUnit->layout_code ?: '—' }}</td>
                        <td>{{ $propertyUnit->parking_category_code ?: '—' }}</td>
                        <td>{{ $propertyUnit->ended_at?->format('Y-m-d') ?? '—' }}</td>
                        <td>{{ $propertyUnit->is_new_registration ? 'はい' : 'いいえ' }}</td>
                        <td>{{ $propertyUnit->is_active ? '有効' : '停止' }}</td>
                        <td>
                            <div class="actions">
                                <a
                                    href="{{ route('property-units.edit', $propertyUnit) }}"
                                    class="button button-secondary"
                                >
                                    修正
                                </a>

                                <form
                                    method="POST"
                                    action="{{ route('property-units.destroy', $propertyUnit) }}"
                                    onsubmit="return confirm('この部屋・区画を削除しますか？');"
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
                        <td colspan="12">まだ部屋・区画が登録されていません。「部屋・区画を新規登録」から最初の1件を作成してください。</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection