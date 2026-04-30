<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentAccount;
use App\Models\PaymentItem;
use App\Models\PaymentSchedule;
use App\Models\RentalContract;
use App\Models\RentalContractTerm;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RentalContractTermController extends Controller
{
    private const CONTRACT_AMOUNT_FIELDS = [
        'rent' => 'rent_amount',
        'common_service' => 'common_service_fee',
        'parking' => 'parking_fee',
        'other' => 'other_monthly_fee',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'target_year_month' => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
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

        $targetYearMonth = $validated['target_year_month'] ?? now()->format('Y-m');
        $selectedRentalContractId = isset($validated['rental_contract_id'])
            ? (int) $validated['rental_contract_id']
            : null;

        $contracts = collect();
        $termRows = collect();
        $previewRows = collect();

        if ($selectedBook !== null) {
            $contracts = $this->getRentalContracts((int) $selectedBook->id);
            $termRows = $this->getTermRows((int) $selectedBook->id, $selectedRentalContractId);
            $previewRows = $this->buildPreviewRows((int) $selectedBook->id, $targetYearMonth, $selectedRentalContractId);
        }

        return view('rental_contract_terms.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'targetYearMonth' => $targetYearMonth,
            'selectedRentalContractId' => $selectedRentalContractId,
            'contracts' => $contracts,
            'termRows' => $termRows,
            'previewRows' => $previewRows,
            'summary' => $this->buildSummary($previewRows),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'rental_contract_id' => [
                'required',
                'integer',
                Rule::exists('rental_contracts', 'id')->where(
                    fn ($query) => $query->where('book_id', (int) $request->input('book_id'))
                ),
            ],
            'effective_from_year_month' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'common_service_fee' => ['nullable', 'numeric', 'min:0'],
            'parking_fee' => ['nullable', 'numeric', 'min:0'],
            'other_monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_due_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'sync_contract_current' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $contractId = (int) $validated['rental_contract_id'];

        $payload = [
            'book_id' => $bookId,
            'rental_contract_id' => $contractId,
            'effective_from_year_month' => $validated['effective_from_year_month'],
            'rent_amount' => round((float) ($validated['rent_amount'] ?? 0), 2),
            'common_service_fee' => round((float) ($validated['common_service_fee'] ?? 0), 2),
            'parking_fee' => round((float) ($validated['parking_fee'] ?? 0), 2),
            'other_monthly_fee' => round((float) ($validated['other_monthly_fee'] ?? 0), 2),
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'note' => $validated['note'] ?? null,
        ];

        DB::transaction(function () use ($payload, $contractId, $validated): void {
            RentalContractTerm::query()->updateOrCreate(
                [
                    'rental_contract_id' => $contractId,
                    'effective_from_year_month' => $payload['effective_from_year_month'],
                ],
                $payload
            );

            if ((bool) ($validated['sync_contract_current'] ?? false)) {
                RentalContract::query()
                    ->where('id', $contractId)
                    ->update([
                        'rent_amount' => $payload['rent_amount'],
                        'common_service_fee' => $payload['common_service_fee'],
                        'parking_fee' => $payload['parking_fee'],
                        'other_monthly_fee' => $payload['other_monthly_fee'],
                        'payment_due_day' => $payload['payment_due_day'],
                    ]);
            }
        });

        return redirect()
            ->route('rental-contract-terms.index', [
                'book_id' => $bookId,
                'target_year_month' => $validated['effective_from_year_month'],
                'rental_contract_id' => $contractId,
            ])
            ->with('status', '月額変更履歴を登録しました。');
    }

    public function destroy(RentalContractTerm $rentalContractTerm): RedirectResponse
    {
        $bookId = (int) $rentalContractTerm->book_id;
        $contractId = (int) $rentalContractTerm->rental_contract_id;
        $yearMonth = $rentalContractTerm->effective_from_year_month;

        $rentalContractTerm->delete();

        return redirect()
            ->route('rental-contract-terms.index', [
                'book_id' => $bookId,
                'target_year_month' => $yearMonth,
                'rental_contract_id' => $contractId,
            ])
            ->with('status', '月額変更履歴を削除しました。');
    }

    public function rebuild(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'target_year_month' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'rental_contract_id' => [
                'nullable',
                'integer',
                Rule::exists('rental_contracts', 'id')->where(
                    fn ($query) => $query->where('book_id', (int) $request->input('book_id'))
                ),
            ],
            'update_unpaid' => ['nullable', 'boolean'],
            'cancel_zero_unpaid' => ['nullable', 'boolean'],
        ]);

        $bookId = (int) $validated['book_id'];
        $targetYearMonth = $validated['target_year_month'];
        $contractId = isset($validated['rental_contract_id'])
            ? (int) $validated['rental_contract_id']
            : null;

        $summary = DB::transaction(function () use ($bookId, $targetYearMonth, $contractId, $validated): array {
            return $this->rebuildSchedules(
                $bookId,
                $targetYearMonth,
                $contractId,
                (bool) ($validated['update_unpaid'] ?? true),
                (bool) ($validated['cancel_zero_unpaid'] ?? true)
            );
        });

        return redirect()
            ->route('rental-contract-terms.index', array_filter([
                'book_id' => $bookId,
                'target_year_month' => $targetYearMonth,
                'rental_contract_id' => $contractId,
            ]))
            ->with(
                'status',
                '入金予定を再作成しました。'
                . ' 作成 ' . $summary['created_count']
                . ' 件、更新 ' . $summary['updated_count']
                . ' 件、取消 ' . $summary['cancelled_count']
                . ' 件、保護 ' . $summary['locked_count']
                . ' 件、対象外 ' . $summary['skipped_count']
                . ' 件。'
            );
    }

    private function getRentalContracts(int $bookId): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
            ->where('book_id', $bookId)
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
    }

    private function getTermRows(int $bookId, ?int $contractId): Collection
    {
        $query = RentalContractTerm::query()
            ->with(['rentalContract.contractTenant', 'rentalContract.property', 'rentalContract.propertyUnit'])
            ->where('book_id', $bookId)
            ->orderByDesc('effective_from_year_month')
            ->orderBy('rental_contract_id');

        if ($contractId !== null) {
            $query->where('rental_contract_id', $contractId);
        }

        return $query->get();
    }

    private function buildPreviewRows(int $bookId, string $targetYearMonth, ?int $contractId): Collection
    {
        $contracts = $this->getTargetContracts($bookId, $targetYearMonth, $contractId);
        $paymentItems = $this->getMonthlyPaymentItemsByType($bookId);

        $rows = collect();

        foreach ($contracts as $contract) {
            $term = $this->getApplicableTerm($contract, $targetYearMonth);

            foreach (self::CONTRACT_AMOUNT_FIELDS as $itemType => $amountField) {
                $paymentItem = $paymentItems->get($itemType);
                $amount = $this->getAmountForContract($contract, $term, $amountField);
                $dueOn = $this->resolveDueOn($targetYearMonth, $term?->payment_due_day ?? $contract->payment_due_day);
                $existing = $paymentItem !== null
                    ? $this->findExistingSchedule((int) $contract->id, (int) $paymentItem->id, $targetYearMonth)
                    : null;

                $status = 'create';
                $statusLabel = '作成予定';

                if ($paymentItem === null) {
                    $status = 'missing_item';
                    $statusLabel = '入金項目なし';
                } elseif ($amount <= 0) {
                    $status = $existing !== null && !$this->hasReceiptsOrPaid($existing) ? 'cancel_zero' : 'zero_amount';
                    $statusLabel = $existing !== null && !$this->hasReceiptsOrPaid($existing)
                        ? '0円のため未入金予定を取消候補'
                        : '金額0のため対象外';
                } elseif ($existing !== null) {
                    if ($this->hasReceiptsOrPaid($existing)) {
                        $status = 'locked';
                        $statusLabel = '入金済・一部入金のため保護';
                    } elseif ((float) $existing->expected_amount !== (float) $amount || $existing->due_on?->format('Y-m-d') !== $dueOn) {
                        $status = 'update';
                        $statusLabel = '未入金予定を更新候補';
                    } else {
                        $status = 'same';
                        $statusLabel = '変更なし';
                    }
                }

                $rows->push((object) [
                    'contract_id' => (int) $contract->id,
                    'tenant_code' => $contract->contractTenant?->tenant_code,
                    'tenant_name' => $contract->contractTenant?->name,
                    'property_code' => $contract->property?->property_code,
                    'property_name' => $contract->property?->name,
                    'unit_no' => $contract->propertyUnit?->unit_no,
                    'payment_item_type' => $itemType,
                    'payment_item_name' => $paymentItem?->name,
                    'target_year_month' => $targetYearMonth,
                    'due_on' => $dueOn,
                    'amount' => $amount,
                    'existing_amount' => $existing?->expected_amount,
                    'existing_due_on' => $existing?->due_on?->format('Y-m-d'),
                    'source' => $term !== null ? 'history' : 'contract',
                    'source_label' => $term !== null ? '履歴: ' . $term->effective_from_year_month : '契約マスタ',
                    'status' => $status,
                    'status_label' => $statusLabel,
                ]);
            }
        }

        return $rows;
    }

    private function rebuildSchedules(
        int $bookId,
        string $targetYearMonth,
        ?int $contractId,
        bool $updateUnpaid,
        bool $cancelZeroUnpaid
    ): array {
        $contracts = $this->getTargetContracts($bookId, $targetYearMonth, $contractId);
        $paymentItems = $this->getMonthlyPaymentItemsByType($bookId);
        $defaultPaymentAccount = $this->getDefaultPaymentAccount($bookId);

        $summary = [
            'created_count' => 0,
            'updated_count' => 0,
            'cancelled_count' => 0,
            'locked_count' => 0,
            'skipped_count' => 0,
        ];

        foreach ($contracts as $contract) {
            $term = $this->getApplicableTerm($contract, $targetYearMonth);

            foreach (self::CONTRACT_AMOUNT_FIELDS as $itemType => $amountField) {
                $paymentItem = $paymentItems->get($itemType);

                if ($paymentItem === null) {
                    $summary['skipped_count']++;
                    continue;
                }

                $amount = $this->getAmountForContract($contract, $term, $amountField);
                $dueOn = $this->resolveDueOn($targetYearMonth, $term?->payment_due_day ?? $contract->payment_due_day);
                $existing = $this->findExistingSchedule((int) $contract->id, (int) $paymentItem->id, $targetYearMonth);

                if ($amount <= 0) {
                    if ($existing !== null && !$this->hasReceiptsOrPaid($existing) && $cancelZeroUnpaid) {
                        $existing->update([
                            'due_on' => $dueOn,
                            'expected_amount' => 0,
                            'received_amount' => 0,
                            'status' => 'cancelled',
                            'note' => trim((string) ($existing->note ?? '') . "\n月額変更履歴により0円取消"),
                        ]);

                        $summary['cancelled_count']++;
                    } else {
                        $summary['skipped_count']++;
                    }

                    continue;
                }

                if ($existing !== null) {
                    if ($this->hasReceiptsOrPaid($existing)) {
                        $summary['locked_count']++;
                        continue;
                    }

                    if ($updateUnpaid) {
                        $existing->update([
                            'due_on' => $dueOn,
                            'expected_amount' => $amount,
                            'status' => 'unpaid',
                            'note' => trim((string) ($existing->note ?? '') . "\n月額変更履歴で再作成"),
                        ]);

                        $summary['updated_count']++;
                    } else {
                        $summary['skipped_count']++;
                    }

                    continue;
                }

                PaymentSchedule::query()->create([
                    'book_id' => $bookId,
                    'rental_contract_id' => $contract->id,
                    'contract_tenant_id' => $contract->contract_tenant_id,
                    'payment_item_id' => $paymentItem->id,
                    'payment_account_id' => $defaultPaymentAccount?->id,
                    'target_year_month' => $targetYearMonth,
                    'due_on' => $dueOn,
                    'expected_amount' => $amount,
                    'received_amount' => 0,
                    'status' => 'unpaid',
                    'note' => '月額変更履歴から再作成',
                ]);

                $summary['created_count']++;
            }
        }

        return $summary;
    }

    private function getTargetContracts(int $bookId, string $targetYearMonth, ?int $contractId): Collection
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $targetYearMonth . '-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $query = RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit', 'terms'])
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->where('contract_status', 'active')
            ->where(function ($query) use ($monthEnd): void {
                $query->whereNull('contract_started_on')
                    ->orWhereDate('contract_started_on', '<=', $monthEnd->format('Y-m-d'));
            })
            ->where(function ($query) use ($monthStart): void {
                $query->whereNull('contract_ended_on')
                    ->orWhereDate('contract_ended_on', '>=', $monthStart->format('Y-m-d'));
            });

        if ($contractId !== null) {
            $query->where('id', $contractId);
        }

        return $query
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
    }

    private function getApplicableTerm(RentalContract $contract, string $targetYearMonth): ?RentalContractTerm
    {
        return $contract->terms
            ->filter(fn (RentalContractTerm $term) => $term->effective_from_year_month <= $targetYearMonth)
            ->sortByDesc('effective_from_year_month')
            ->first();
    }

    private function getAmountForContract(RentalContract $contract, ?RentalContractTerm $term, string $amountField): float
    {
        return round((float) ($term?->{$amountField} ?? $contract->{$amountField} ?? 0), 2);
    }

    private function getMonthlyPaymentItemsByType(int $bookId): Collection
    {
        return PaymentItem::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->where('is_monthly', true)
            ->whereIn('item_type', array_keys(self::CONTRACT_AMOUNT_FIELDS))
            ->orderBy('sort_order')
            ->orderBy('item_code')
            ->get()
            ->keyBy('item_type');
    }

    private function getDefaultPaymentAccount(int $bookId): ?PaymentAccount
    {
        return PaymentAccount::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->first();
    }

    private function resolveDueOn(string $targetYearMonth, ?int $paymentDueDay): string
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $targetYearMonth . '-01')->startOfMonth();
        $lastDay = (int) $monthStart->endOfMonth()->format('d');
        $dueDay = $paymentDueDay ?: $lastDay;
        $safeDueDay = min(max($dueDay, 1), $lastDay);

        return $monthStart->day($safeDueDay)->format('Y-m-d');
    }

    private function findExistingSchedule(int $rentalContractId, int $paymentItemId, string $targetYearMonth): ?PaymentSchedule
    {
        return PaymentSchedule::query()
            ->withCount('receipts')
            ->where('rental_contract_id', $rentalContractId)
            ->where('payment_item_id', $paymentItemId)
            ->where('target_year_month', $targetYearMonth)
            ->orderByDesc('id')
            ->first();
    }

    private function hasReceiptsOrPaid(PaymentSchedule $schedule): bool
    {
        return (int) ($schedule->receipts_count ?? 0) > 0
            || in_array($schedule->status, ['paid', 'partial'], true)
            || (float) $schedule->received_amount > 0;
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

    private function buildSummary(Collection $previewRows): array
    {
        return [
            'rows_count' => $previewRows->count(),
            'create_count' => $previewRows->where('status', 'create')->count(),
            'update_count' => $previewRows->where('status', 'update')->count(),
            'same_count' => $previewRows->where('status', 'same')->count(),
            'locked_count' => $previewRows->where('status', 'locked')->count(),
            'cancel_zero_count' => $previewRows->where('status', 'cancel_zero')->count(),
            'missing_item_count' => $previewRows->where('status', 'missing_item')->count(),
            'zero_amount_count' => $previewRows->where('status', 'zero_amount')->count(),
        ];
    }
}