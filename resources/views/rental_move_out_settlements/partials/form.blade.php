@if ($books->isEmpty())
    <div class="alert alert-error">
        帳簿がまだ登録されていません。先に帳簿を登録してください。
    </div>
@elseif ($selectedBook === null)
    <div class="alert alert-error">
        対象の帳簿を選択してください。
    </div>
@else
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
        <form method="GET" action="{{ $settlement ? route('rental-move-out-settlements.edit', $settlement) : route('rental-move-out-settlements.create') }}">
            <div class="form-grid">
                <div class="field">
                    <label for="book_id_selector">帳簿</label>
                    <select id="book_id_selector" name="book_id">
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
                    <label for="rental_contract_id_selector">賃貸条件</label>
                    <select id="rental_contract_id_selector" name="rental_contract_id">
                        <option value="">選択してください</option>
                        @foreach ($contracts as $contract)
                            <option
                                value="{{ $contract->id }}"
                                {{ (string) $selectedRentalContractId === (string) $contract->id ? 'selected' : '' }}
                            >
                                {{ ($contract->contract_no ?: '契約番号なし') }}
                                /
                                {{ $contract->contractTenant?->name ?? '契約者不明' }}
                                /
                                {{ $contract->property?->name ?? '物件不明' }}
                                @if ($contract->propertyUnit?->unit_no)
                                    {{ $contract->propertyUnit->unit_no }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            @unless($settlement)
                <div class="actions" style="margin-top: 16px;">
                    <button type="submit" class="button">この契約で入力する</button>
                </div>
            @endunless
        </form>
    </div>

    <div class="card">
        <form method="POST" action="{{ $formAction }}">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <input type="hidden" name="book_id" value="{{ $selectedBookId }}">

            <div class="form-grid">
                <div class="field field-full">
                    <label for="rental_contract_id">賃貸条件<span class="required">必須</span></label>
                    <select id="rental_contract_id" name="rental_contract_id" required>
                        <option value="">選択してください</option>
                        @foreach ($contracts as $contract)
                            <option
                                value="{{ $contract->id }}"
                                {{ (string) old('rental_contract_id', $settlement?->rental_contract_id ?? $selectedRentalContractId) === (string) $contract->id ? 'selected' : '' }}
                            >
                                {{ ($contract->contract_no ?: '契約番号なし') }}
                                /
                                {{ $contract->contractTenant?->name ?? '契約者不明' }}
                                /
                                {{ $contract->property?->name ?? '物件不明' }}
                                @if ($contract->propertyUnit?->unit_no)
                                    {{ $contract->propertyUnit->unit_no }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="settlement_on">精算日<span class="required">必須</span></label>
                    <input id="settlement_on" type="date" name="settlement_on" value="{{ old('settlement_on', $settlement?->settlement_on?->format('Y-m-d') ?? $defaults['settlement_on']) }}" required>
                </div>

                <div class="field">
                    <label for="move_out_on">退去日</label>
                    <input id="move_out_on" type="date" name="move_out_on" value="{{ old('move_out_on', $settlement?->move_out_on?->format('Y-m-d') ?? $defaults['move_out_on']) }}">
                </div>

                <div class="field">
                    <label for="status">状態<span class="required">必須</span></label>
                    <select id="status" name="status" required>
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" {{ old('status', $settlement?->status ?? $defaults['status']) === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                <h3 style="margin-top: 0;">預り金・返還原資</h3>

                <div class="form-grid">
                    <div class="field">
                        <label for="deposit_amount">敷金</label>
                        <input id="deposit_amount" type="number" name="deposit_amount" value="{{ old('deposit_amount', $settlement?->deposit_amount ?? $defaults['deposit_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="guarantee_deposit_amount">保証金</label>
                        <input id="guarantee_deposit_amount" type="number" name="guarantee_deposit_amount" value="{{ old('guarantee_deposit_amount', $settlement?->guarantee_deposit_amount ?? $defaults['guarantee_deposit_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="prepaid_rent_amount">前受・預り家賃等</label>
                        <input id="prepaid_rent_amount" type="number" name="prepaid_rent_amount" value="{{ old('prepaid_rent_amount', $settlement?->prepaid_rent_amount ?? $defaults['prepaid_rent_amount']) }}" min="0" step="0.01">
                    </div>
                </div>
            </div>

            <div style="margin-top: 24px; padding: 16px; border: 1px solid #dbe3f0; border-radius: 12px;">
                <h3 style="margin-top: 0;">請求・控除</h3>

                <div class="form-grid">
                    <div class="field">
                        <label for="unpaid_rent_amount">未収家賃</label>
                        <input id="unpaid_rent_amount" type="number" name="unpaid_rent_amount" value="{{ old('unpaid_rent_amount', $settlement?->unpaid_rent_amount ?? $defaults['unpaid_rent_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="restoration_cost_amount">原状回復費</label>
                        <input id="restoration_cost_amount" type="number" name="restoration_cost_amount" value="{{ old('restoration_cost_amount', $settlement?->restoration_cost_amount ?? $defaults['restoration_cost_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="cleaning_cost_amount">クリーニング費用</label>
                        <input id="cleaning_cost_amount" type="number" name="cleaning_cost_amount" value="{{ old('cleaning_cost_amount', $settlement?->cleaning_cost_amount ?? $defaults['cleaning_cost_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="key_replacement_cost_amount">鍵交換費用</label>
                        <input id="key_replacement_cost_amount" type="number" name="key_replacement_cost_amount" value="{{ old('key_replacement_cost_amount', $settlement?->key_replacement_cost_amount ?? $defaults['key_replacement_cost_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="other_charge_amount">その他請求額</label>
                        <input id="other_charge_amount" type="number" name="other_charge_amount" value="{{ old('other_charge_amount', $settlement?->other_charge_amount ?? $defaults['other_charge_amount']) }}" min="0" step="0.01">
                    </div>

                    <div class="field">
                        <label for="refund_transfer_fee_amount">振込手数料等</label>
                        <input id="refund_transfer_fee_amount" type="number" name="refund_transfer_fee_amount" value="{{ old('refund_transfer_fee_amount', $settlement?->refund_transfer_fee_amount ?? $defaults['refund_transfer_fee_amount']) }}" min="0" step="0.01">
                    </div>
                </div>
            </div>

            @if ($settlement)
                <div class="card" style="margin-top: 24px; background: #f8fafc;">
                    <div class="form-grid">
                        <div class="field">
                            <label>預り金等合計</label>
                            <div>{{ number_format($settlement->totalDepositAmount(), 2) }}</div>
                        </div>

                        <div class="field">
                            <label>請求控除合計</label>
                            <div>{{ number_format($settlement->totalChargeAmount(), 2) }}</div>
                        </div>

                        <div class="field">
                            <label>返還額</label>
                            <div style="color: #166534;">{{ number_format((float) $settlement->refund_amount, 2) }}</div>
                        </div>

                        <div class="field">
                            <label>追加請求額</label>
                            <div style="color: #dc2626;">{{ number_format((float) $settlement->additional_billing_amount, 2) }}</div>
                        </div>
                    </div>
                </div>
            @else
                <div class="alert alert-success" style="margin-top: 24px; background: #f8fafc; color: #334155; border-color: #cbd5e1;">
                    登録時に「敷金 + 保証金 + 前受等 - 請求控除」の差額から、返還額または追加請求額を自動計算します。
                </div>
            @endif

            <div class="field field-full" style="margin-top: 24px;">
                <label for="note">備考</label>
                <textarea id="note" name="note">{{ old('note', $settlement?->note) }}</textarea>
            </div>

            <div class="actions" style="margin-top: 24px;">
                <button type="submit" class="button">{{ $submitLabel }}</button>
                <a href="{{ route('rental-move-out-settlements.index', ['book_id' => $selectedBookId]) }}" class="button button-secondary">キャンセル</a>
            </div>
        </form>
    </div>
@endif