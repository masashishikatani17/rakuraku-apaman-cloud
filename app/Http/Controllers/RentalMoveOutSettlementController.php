<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\JournalEntry;
use App\Models\RentalContract;
use App\Models\RentalMoveOutSettlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RentalMoveOutSettlementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'status' => ['nullable', 'in:all,draft,confirmed,cancelled'],
        ]);

        $requestedBookId = isset($validated['book_id'])
            ? (int) $validated['book_id']
            : null;

        $books = $this->getSelectableBooks($requestedBookId);
        $selectedBookId = $requestedBookId ?? ($books->first()?->id);
        $status = $validated['status'] ?? 'all';

        $settlements = collect();

        if ($selectedBookId !== null) {
            $settlementsQuery = RentalMoveOutSettlement::query()
                ->with([
                    'book.businessOwner',
                    'journalEntry',
                    'rentalContract.contractTenant',
                    'rentalContract.property',
                    'rentalContract.propertyUnit',
                ])
                ->where('book_id', $selectedBookId)
                ->orderByDesc('settlement_on')
                ->orderByDesc('id');

            if ($status !== 'all') {
                $settlementsQuery->where('status', $status);
            }

            $settlements = $settlementsQuery->get();
        }

        return view('rental_move_out_settlements.index', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
            'status' => $status,
            'settlements' => $settlements,
            'summary' => $this->buildSummary($settlements),
            'statusLabels' => ['all' => 'すべて'] + RentalMoveOutSettlement::STATUSES,
        ]);
    }

    public function show(RentalMoveOutSettlement $rentalMoveOutSettlement): View
    {
        $rentalMoveOutSettlement->load([
            'book.businessOwner',
            'rentalContract.contractTenant',
            'rentalContract.property',
            'rentalContract.propertyUnit',
            'journalEntry.lines.accountTitle',
            'journalEntry.lines.property',
        ]);

        return view('rental_move_out_settlements.show', [
            'settlement' => $rentalMoveOutSettlement,
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'rental_contract_id' => ['nullable', 'integer', 'exists:rental_contracts,id'],
        ]);

        $requestedBookId = isset($validated['book_id'])
            ? (int) $validated['book_id']
            : null;

        $books = $this->getSelectableBooks($requestedBookId);
        $selectedBookId = $requestedBookId ?? ($books->first()?->id);

        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        if ($selectedBook === null && $selectedBookId !== null) {
            $selectedBook = Book::query()
                ->with('businessOwner')
                ->find($selectedBookId);
        }

        $contracts = collect();
        $selectedRentalContractId = isset($validated['rental_contract_id'])
            ? (int) $validated['rental_contract_id']
            : null;

        $selectedRentalContract = null;

        if ($selectedBook !== null) {
            $contracts = $this->getRentalContracts((int) $selectedBook->id);
            $selectedRentalContract = $selectedRentalContractId !== null
                ? $contracts->firstWhere('id', $selectedRentalContractId)
                : null;

            if ($selectedRentalContract === null && $selectedRentalContractId !== null) {
                $selectedRentalContract = RentalContract::query()
                    ->with(['contractTenant', 'property', 'propertyUnit'])
                    ->where('book_id', (int) $selectedBook->id)
                    ->find($selectedRentalContractId);
            }
        }

        return view('rental_move_out_settlements.create', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'contracts' => $contracts,
            'selectedRentalContractId' => $selectedRentalContractId,
            'selectedRentalContract' => $selectedRentalContract,
            'settlement' => null,
            'statusLabels' => RentalMoveOutSettlement::STATUSES,
            'defaults' => $this->defaultsFromContract($selectedRentalContract),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);
        $this->normalizeAmounts($validated);

        RentalMoveOutSettlement::query()->create($validated);

        return redirect()
            ->route('rental-move-out-settlements.index', ['book_id' => $validated['book_id']])
            ->with('status', '退去精算を登録しました。');
    }

    public function edit(RentalMoveOutSettlement $rentalMoveOutSettlement): View
    {
        $rentalMoveOutSettlement->load(['rentalContract.contractTenant', 'rentalContract.property', 'rentalContract.propertyUnit']);

        $bookId = (int) $rentalMoveOutSettlement->book_id;
        $books = $this->getSelectableBooks($bookId);
        $selectedBook = $books->firstWhere('id', $bookId);
        $contracts = $this->getRentalContracts($bookId);

        return view('rental_move_out_settlements.edit', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $bookId,
            'contracts' => $contracts,
            'selectedRentalContractId' => (int) $rentalMoveOutSettlement->rental_contract_id,
            'selectedRentalContract' => $rentalMoveOutSettlement->rentalContract,
            'settlement' => $rentalMoveOutSettlement,
            'statusLabels' => RentalMoveOutSettlement::STATUSES,
            'defaults' => $this->defaultsFromContract($rentalMoveOutSettlement->rentalContract),
        ]);
    }

    public function update(Request $request, RentalMoveOutSettlement $rentalMoveOutSettlement): RedirectResponse
    {
        $validated = $this->validatePayload($request, $rentalMoveOutSettlement);
        $this->normalizeAmounts($validated);

        $rentalMoveOutSettlement->update($validated);

        return redirect()
            ->route('rental-move-out-settlements.index', ['book_id' => $validated['book_id']])
            ->with('status', '退去精算を更新しました。');
    }

    public function destroy(RentalMoveOutSettlement $rentalMoveOutSettlement): RedirectResponse
    {
        $bookId = (int) $rentalMoveOutSettlement->book_id;
        $rentalMoveOutSettlement->delete();

        return redirect()
            ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
            ->with('status', '退去精算を削除しました。');
    }

    public function createJournal(RentalMoveOutSettlement $rentalMoveOutSettlement): View
    {
        $rentalMoveOutSettlement->load([
            'rentalContract.contractTenant',
            'rentalContract.property',
            'rentalContract.propertyUnit',
            'journalEntry',
        ]);

        $bookId = (int) $rentalMoveOutSettlement->book_id;
        $accountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        return view('rental_move_out_settlements.create_journal', [
            'settlement' => $rentalMoveOutSettlement,
            'assetAccountTitles' => $accountTitles->where('category', 'asset')->values(),
            'liabilityAccountTitles' => $accountTitles->where('category', 'liability')->values(),
            'revenueAccountTitles' => $accountTitles->where('category', 'revenue')->values(),
            'descriptionText' => $this->makeJournalDescriptionText($rentalMoveOutSettlement),
        ]);
    }

    public function storeJournal(Request $request, RentalMoveOutSettlement $rentalMoveOutSettlement): RedirectResponse
    {
        $rentalMoveOutSettlement->load(['rentalContract.contractTenant', 'rentalContract.property', 'journalEntry']);

        if ($rentalMoveOutSettlement->journal_entry_id !== null) {
            return redirect()
                ->route('rental-move-out-settlements.index', ['book_id' => $rentalMoveOutSettlement->book_id])
                ->with('error', 'この退去精算はすでに仕訳作成済みです。');
        }

        $bookId = (int) $rentalMoveOutSettlement->book_id;
        $totalDepositAmount = $rentalMoveOutSettlement->totalDepositAmount();
        $totalChargeAmount = $rentalMoveOutSettlement->totalChargeAmount();
        $refundAmount = round((float) $rentalMoveOutSettlement->refund_amount, 2);
        $additionalBillingAmount = round((float) $rentalMoveOutSettlement->additional_billing_amount, 2);

        if ($totalDepositAmount <= 0 && $totalChargeAmount <= 0) {
            return redirect()
                ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
                ->with('error', '仕訳にできる退去精算金額がありません。');
        }

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'voucher_no' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('journal_entries', 'voucher_no')->where(fn ($query) => $query->where('book_id', $bookId)),
            ],
            'description_text' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'deposit_liability_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(fn ($query) => $query->where('book_id', $bookId)->where('category', 'liability')),
            ],
            'settlement_revenue_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(fn ($query) => $query->where('book_id', $bookId)->where('category', 'revenue')),
            ],
            'refund_payment_account_title_id' => [
                $refundAmount > 0 ? 'required' : 'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(fn ($query) => $query->where('book_id', $bookId)->where('category', 'asset')),
            ],
            'additional_receivable_account_title_id' => [
                $additionalBillingAmount > 0 ? 'required' : 'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(fn ($query) => $query->where('book_id', $bookId)->where('category', 'asset')),
            ],
        ]);

        DB::transaction(function () use (
            $rentalMoveOutSettlement,
            $validated,
            $bookId,
            $totalDepositAmount,
            $totalChargeAmount,
            $refundAmount,
            $additionalBillingAmount
        ): void {
            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['entry_date'],
                'voucher_no' => $validated['voucher_no'] ?? $this->makeVoucherNo($rentalMoveOutSettlement),
                'description_text' => $validated['description_text'],
                'note' => trim((string) ($validated['note'] ?? '') . "\n退去精算ID: " . $rentalMoveOutSettlement->id),
                'total_amount' => round($totalDepositAmount + $additionalBillingAmount, 2),
                'entry_type' => 'move_out_settlement',
                'status' => 'posted',
            ]);

            $propertyId = $rentalMoveOutSettlement->rentalContract?->property_id;
            $lineNo = 1;
            $lines = [];

            if ($totalDepositAmount > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'debit',
                    'account_title_id' => $validated['deposit_liability_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $totalDepositAmount,
                    'line_note' => '敷金・保証金等の取崩',
                ];
            }

            if ($additionalBillingAmount > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'debit',
                    'account_title_id' => $validated['additional_receivable_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $additionalBillingAmount,
                    'line_note' => '退去精算追加請求',
                ];
            }

            if ($totalChargeAmount > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'credit',
                    'account_title_id' => $validated['settlement_revenue_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $totalChargeAmount,
                    'line_note' => '未収家賃・原状回復費等',
                ];
            }

            if ($refundAmount > 0) {
                $lines[] = [
                    'line_no' => $lineNo++,
                    'side' => 'credit',
                    'account_title_id' => $validated['refund_payment_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $refundAmount,
                    'line_note' => '敷金等返還',
                ];
            }

            $journalEntry->lines()->createMany($lines);

            $rentalMoveOutSettlement->update([
                'journal_entry_id' => $journalEntry->id,
                'status' => 'confirmed',
            ]);
        });

        return redirect()
            ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
            ->with('status', '退去精算仕訳を作成しました。');
    }

    public function destroyJournal(RentalMoveOutSettlement $rentalMoveOutSettlement): RedirectResponse
    {
        $rentalMoveOutSettlement->load(['journalEntry']);
        $bookId = (int) $rentalMoveOutSettlement->book_id;
        $journalEntry = $rentalMoveOutSettlement->journalEntry;

        if ($journalEntry === null) {
            $rentalMoveOutSettlement->update(['journal_entry_id' => null]);

            return redirect()
                ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
                ->with('status', '仕訳が見つからなかったため、退去精算側の仕訳紐づけだけ解除しました。');
        }

        if ($journalEntry->entry_type !== 'move_out_settlement') {
            return redirect()
                ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
                ->with('error', 'この仕訳は退去精算から作成された仕訳ではないため、この画面からは取消できません。');
        }

        DB::transaction(function () use ($rentalMoveOutSettlement, $journalEntry): void {
            $rentalMoveOutSettlement->update([
                'journal_entry_id' => null,
                'status' => 'draft',
            ]);

            $journalEntry->delete();
        });

        return redirect()
            ->route('rental-move-out-settlements.index', ['book_id' => $bookId])
            ->with('status', '退去精算仕訳を取り消しました。');
    }

    private function validatePayload(Request $request, ?RentalMoveOutSettlement $settlement = null): array
    {
        $bookId = (int) $request->input('book_id');

        $contractRule = Rule::exists('rental_contracts', 'id')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        $uniqueContractRule = Rule::unique('rental_move_out_settlements', 'rental_contract_id');

        if ($settlement !== null) {
            $uniqueContractRule = $uniqueContractRule->ignore($settlement->id);
        }

        return $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'rental_contract_id' => ['required', 'integer', $contractRule, $uniqueContractRule],
            'settlement_on' => ['required', 'date'],
            'move_out_on' => ['nullable', 'date'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'guarantee_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'prepaid_rent_amount' => ['nullable', 'numeric', 'min:0'],
            'unpaid_rent_amount' => ['nullable', 'numeric', 'min:0'],
            'restoration_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'cleaning_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'key_replacement_cost_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charge_amount' => ['nullable', 'numeric', 'min:0'],
            'refund_transfer_fee_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys(RentalMoveOutSettlement::STATUSES))],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function normalizeAmounts(array &$validated): void
    {
        $amountFields = [
            'deposit_amount',
            'guarantee_deposit_amount',
            'prepaid_rent_amount',
            'unpaid_rent_amount',
            'restoration_cost_amount',
            'cleaning_cost_amount',
            'key_replacement_cost_amount',
            'other_charge_amount',
            'refund_transfer_fee_amount',
        ];

        foreach ($amountFields as $field) {
            $validated[$field] = round((float) ($validated[$field] ?? 0), 2);
        }

        $depositTotal = round(
            $validated['deposit_amount']
            + $validated['guarantee_deposit_amount']
            + $validated['prepaid_rent_amount'],
            2
        );

        $chargeTotal = round(
            $validated['unpaid_rent_amount']
            + $validated['restoration_cost_amount']
            + $validated['cleaning_cost_amount']
            + $validated['key_replacement_cost_amount']
            + $validated['other_charge_amount']
            + $validated['refund_transfer_fee_amount'],
            2
        );

        $balance = round($depositTotal - $chargeTotal, 2);

        $validated['refund_amount'] = $balance >= 0 ? $balance : 0;
        $validated['additional_billing_amount'] = $balance < 0 ? abs($balance) : 0;
    }

    private function defaultsFromContract(?RentalContract $contract): array
    {
        return [
            'settlement_on' => now()->format('Y-m-d'),
            'move_out_on' => $contract?->move_out_on?->format('Y-m-d') ?? $contract?->contract_ended_on?->format('Y-m-d'),
            'deposit_amount' => $contract?->deposit_amount ?? 0,
            'guarantee_deposit_amount' => $contract?->guarantee_deposit_amount ?? 0,
            'prepaid_rent_amount' => 0,
            'unpaid_rent_amount' => 0,
            'restoration_cost_amount' => 0,
            'cleaning_cost_amount' => 0,
            'key_replacement_cost_amount' => 0,
            'other_charge_amount' => 0,
            'refund_transfer_fee_amount' => 0,
            'status' => 'draft',
        ];
    }

    private function getRentalContracts(int $bookId): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
            ->where('book_id', $bookId)
            ->orderByRaw("CASE WHEN contract_status = 'ended' THEN 0 WHEN contract_status = 'active' THEN 1 ELSE 2 END")
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
    }

    private function getSelectableBooks(?int $selectedBookId = null): Collection
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        if ($selectedBookId !== null && !$books->contains('id', $selectedBookId)) {
            $selectedBook = Book::query()
                ->with('businessOwner')
                ->find($selectedBookId);

            if ($selectedBook !== null) {
                $books = $books->prepend($selectedBook);
            }
        }

        return $books;
    }

    private function buildSummary(Collection $settlements): array
    {
        return [
            'rows_count' => $settlements->count(),
            'draft_count' => $settlements->where('status', 'draft')->count(),
            'confirmed_count' => $settlements->where('status', 'confirmed')->count(),
            'cancelled_count' => $settlements->where('status', 'cancelled')->count(),
            'deposit_total' => round($settlements->sum(fn (RentalMoveOutSettlement $settlement) => $settlement->totalDepositAmount()), 2),
            'charge_total' => round($settlements->sum(fn (RentalMoveOutSettlement $settlement) => $settlement->totalChargeAmount()), 2),
            'refund_total' => round($settlements->sum(fn (RentalMoveOutSettlement $settlement) => (float) $settlement->refund_amount), 2),
            'additional_billing_total' => round($settlements->sum(fn (RentalMoveOutSettlement $settlement) => (float) $settlement->additional_billing_amount), 2),
        ];
    }
+
+    private function makeJournalDescriptionText(RentalMoveOutSettlement $settlement): string
+    {
+        $tenantName = $settlement->rentalContract?->contractTenant?->name ?? '契約者不明';
+        $propertyName = $settlement->rentalContract?->property?->name;
+        $unitNo = $settlement->rentalContract?->propertyUnit?->unit_no;
+
+        return mb_substr(trim('退去精算 / ' . $tenantName . ' / ' . $propertyName . ' / ' . $unitNo), 0, 255);
+    }
+
+    private function makeVoucherNo(RentalMoveOutSettlement $settlement): string
+    {
+        $baseVoucherNo = 'MO' . str_pad((string) $settlement->id, 8, '0', STR_PAD_LEFT);
+        $voucherNo = $baseVoucherNo;
+        $suffix = 1;
+
+        while (JournalEntry::query()->where('book_id', $settlement->book_id)->where('voucher_no', $voucherNo)->exists()) {
+            $voucherNo = mb_substr($baseVoucherNo, 0, 16) . '-' . $suffix;
+            $suffix++;
+        }
+
+        return $voucherNo;
+    }
}