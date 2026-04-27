--- a/app/Http/Controllers/MonthlyPaymentScheduleController.php
 b/app/Http/Controllers/MonthlyPaymentScheduleController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentAccount;
use App\Models\PaymentItem;
use App\Models\PaymentSchedule;
use App\Models\RentalContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyPaymentScheduleController extends Controller
{
    private const CONTRACT_AMOUNT_FIELDS = [
        'rent' => 'rent_amount',
        'common_service' => 'common_service_fee',
        'parking' => 'parking_fee',
        'other' => 'other_monthly_fee',
    ];

    public function create(Request $request): View
    {
        $books = $this->getSelectableBooks();

        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : ($books->first()?->id);

        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        if ($selectedBook === null && $books->isNotEmpty()) {
            $selectedBook = $books->first();
            $selectedBookId = (int) $selectedBook->id;
        }

        $targetYearMonth = $request->input('target_year_month', now()->format('Y-m'));

        $summary = $selectedBookId !== null
            ? $this->previewMonthlySchedules($selectedBookId, $targetYearMonth)
            : $this->emptySummary();

        return view('monthly_payment_schedules.create', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'targetYearMonth' => $targetYearMonth,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'target_year_month' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
        ]);

        $bookId = (int) $validated['book_id'];
        $targetYearMonth = $validated['target_year_month'];

        $summary = DB::transaction(function () use ($bookId, $targetYearMonth): array {
            return $this->generateMonthlySchedules($bookId, $targetYearMonth);
        });

        return redirect()
            ->route('monthly-payment-schedules.create', [
                'book_id' => $bookId,
                'target_year_month' => $targetYearMonth,
            ])
            ->with(
                'status',
                '月次入金予定を作成しました。作成 '
                . $summary['created_count']
                . ' 件、既存 '
                . $summary['skipped_existing_count']
                . ' 件、対象外 '
                . $summary['skipped_zero_count']
                . ' 件です。'
            );
    }

    private function previewMonthlySchedules(int $bookId, string $targetYearMonth): array
    {
        $contracts = $this->getTargetContracts($bookId, $targetYearMonth);
        $paymentItems = $this->getMonthlyPaymentItemsByType($bookId);
        $defaultPaymentAccount = $this->getDefaultPaymentAccount($bookId);

        $rows = collect();
        $creatableCount = 0;
        $existingCount = 0;
        $missingItemCount = 0;
        $zeroAmountCount = 0;

        foreach ($contracts as $contract) {
            foreach (self::CONTRACT_AMOUNT_FIELDS as $itemType => $amountField) {
                $amount = round((float) $contract->{$amountField}, 2);
                $paymentItem = $paymentItems->get($itemType);

                $status = 'create';
                $statusLabel = '作成予定';
                $dueOn = $this->resolveDueOn($targetYearMonth, $contract->payment_due_day);

                if ($amount <= 0) {
                    $status = 'zero_amount';
                    $statusLabel = '金額0のため対象外';
                    $zeroAmountCount++;
                } elseif ($paymentItem === null) {
                    $status = 'missing_item';
                    $statusLabel = '入金項目なし';
                    $missingItemCount++;
                } elseif ($this->scheduleExists((int) $contract->id, (int) $paymentItem->id, $dueOn)) {
                    $status = 'existing';
                    $statusLabel = '作成済';
                    $existingCount++;
                } else {
                    $creatableCount++;
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
                    'payment_account_name' => $defaultPaymentAccount?->name,
                    'due_on' => $dueOn,
                    'amount' => $amount,
                    'status' => $status,
                    'status_label' => $statusLabel,
                ]);
            }
        }

        return [
            'contracts_count' => $contracts->count(),
            'rows' => $rows,
            'creatable_count' => $creatableCount,
            'existing_count' => $existingCount,
            'missing_item_count' => $missingItemCount,
            'zero_amount_count' => $zeroAmountCount,
            'default_payment_account' => $defaultPaymentAccount,
        ];
    }

    private function generateMonthlySchedules(int $bookId, string $targetYearMonth): array
    {
        $contracts = $this->getTargetContracts($bookId, $targetYearMonth);
        $paymentItems = $this->getMonthlyPaymentItemsByType($bookId);
        $defaultPaymentAccount = $this->getDefaultPaymentAccount($bookId);

        $createdCount = 0;
        $skippedExistingCount = 0;
        $skippedMissingItemCount = 0;
        $skippedZeroCount = 0;

        foreach ($contracts as $contract) {
            foreach (self::CONTRACT_AMOUNT_FIELDS as $itemType => $amountField) {
                $amount = round((float) $contract->{$amountField}, 2);

                if ($amount <= 0) {
                    $skippedZeroCount++;
                    continue;
                }

                $paymentItem = $paymentItems->get($itemType);

                if ($paymentItem === null) {
                    $skippedMissingItemCount++;
                    continue;
                }

                $dueOn = $this->resolveDueOn($targetYearMonth, $contract->payment_due_day);

                if ($this->scheduleExists((int) $contract->id, (int) $paymentItem->id, $dueOn)) {
                    $skippedExistingCount++;
                    continue;
                }

                PaymentSchedule::create([
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
                    'note' => '月次一括生成で作成',
                ]);

                $createdCount++;
            }
        }

        return [
            'created_count' => $createdCount,
            'skipped_existing_count' => $skippedExistingCount,
            'skipped_missing_item_count' => $skippedMissingItemCount,
            'skipped_zero_count' => $skippedZeroCount,
        ];
    }

    private function getTargetContracts(int $bookId, string $targetYearMonth): Collection
    {
        $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $targetYearMonth . '-01')->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
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
            })
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
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

    private function scheduleExists(int $rentalContractId, int $paymentItemId, string $dueOn): bool
    {
        return PaymentSchedule::query()
            ->where('rental_contract_id', $rentalContractId)
            ->where('payment_item_id', $paymentItemId)
            ->whereDate('due_on', $dueOn)
            ->exists();
    }

    private function emptySummary(): array
    {
        return [
            'contracts_count' => 0,
            'rows' => collect(),
            'creatable_count' => 0,
            'existing_count' => 0,
            'missing_item_count' => 0,
            'zero_amount_count' => 0,
            'default_payment_account' => null,
        ];
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
}