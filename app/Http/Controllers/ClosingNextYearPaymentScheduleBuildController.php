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

class ClosingNextYearPaymentScheduleBuildController extends Controller
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'display' => ['nullable', 'in:creatable,all'],
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

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $display = $validated['display'] ?? 'creatable';

        $summary = $selectedBook !== null
            ? $this->previewSchedules((int) $selectedBook->id, $dateFrom, $dateTo, $display)
            : $this->emptySummary();

        return view('closing_next_year_payment_schedule_builds.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $bookId = (int) $validated['book_id'];
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        $summary = DB::transaction(function () use ($bookId, $dateFrom, $dateTo): array {
            return $this->generateSchedules($bookId, $dateFrom, $dateTo);
        });

        return redirect()
            ->route('closing.next-year-payment-schedule-builds.index', [
                'book_id' => $bookId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'display' => 'all',
            ])
            ->with(
                'status',
                '翌期入金予定を作成しました。作成 '
                . $summary['created_count']
                . ' 件、既存 '
                . $summary['existing_count']
                . ' 件、金額0 '
                . $summary['zero_amount_count']
                . ' 件、入金項目なし '
                . $summary['missing_item_count']
                . ' 件です。'
            );
    }

    private function previewSchedules(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): array
    {
        $rows = $this->buildRows($bookId, $dateFrom, $dateTo);

        if ($display === 'creatable') {
            $displayRows = $rows
                ->filter(fn (object $row): bool => $row->status === 'create')
                ->values();
        } else {
            $displayRows = $rows;
        }

        return $this->buildSummary($rows, $displayRows, $bookId);
    }

    private function generateSchedules(int $bookId, string $dateFrom, string $dateTo): array
    {
        $rows = $this->buildRows($bookId, $dateFrom, $dateTo);
        $createdCount = 0;

        foreach ($rows as $row) {
            if ($row->status !== 'create') {
                continue;
            }

            PaymentSchedule::query()->create([
                'book_id' => $bookId,
                'rental_contract_id' => $row->contract_id,
                'contract_tenant_id' => $row->contract_tenant_id,
                'payment_item_id' => $row->payment_item_id,
                'payment_account_id' => $row->payment_account_id,
                'target_year_month' => $row->target_year_month,
                'due_on' => $row->due_on,
                'expected_amount' => $row->amount,
                'received_amount' => 0,
                'status' => 'unpaid',
                'note' => '翌期入金予定生成で作成',
            ]);

            $createdCount++;
        }

        $summary = $this->buildSummary($rows, $rows, $bookId);
        $summary['created_count'] = $createdCount;

        return $summary;
    }

    private function buildRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return collect();
        }

        $months = $this->buildTargetMonths($dateFrom, $dateTo);
        $contracts = $this->getContracts($bookId);
        $paymentItems = $this->getMonthlyPaymentItemsByType($bookId);
        $defaultPaymentAccount = $this->getDefaultPaymentAccount($bookId);
        $rows = collect();

        foreach ($months as $targetYearMonth) {
            $monthStart = CarbonImmutable::createFromFormat('Y-m-d', $targetYearMonth . '-01')->startOfMonth();
            $monthEnd = $monthStart->endOfMonth();

            foreach ($contracts as $contract) {
                if (!$this->contractAppliesToMonth($contract, $monthStart, $monthEnd)) {
                    continue;
                }

                $term = $this->resolveTermForMonth($contract, $targetYearMonth);

                foreach (self::CONTRACT_AMOUNT_FIELDS as $itemType => $amountField) {
                    $amount = round((float) ($term?->{$amountField} ?? $contract->{$amountField} ?? 0), 2);
                    $paymentDueDay = $term?->payment_due_day ?? $contract->payment_due_day;
                    $paymentItem = $paymentItems->get($itemType);
                    $dueOn = $this->resolveDueOn($targetYearMonth, $paymentDueDay);
                    $status = 'create';
                    $statusLabel = '作成予定';

                    if ($amount <= 0) {
                        $status = 'zero_amount';
                        $statusLabel = '金額0のため対象外';
                    } elseif ($paymentItem === null) {
                        $status = 'missing_item';
                        $statusLabel = '入金項目なし';
                    } elseif ($this->scheduleExists((int) $contract->id, (int) $paymentItem->id, $dueOn)) {
                        $status = 'existing';
                        $statusLabel = '作成済';
                    }

                    $rows->push((object) [
                        'target_year_month' => $targetYearMonth,
                        'contract_id' => (int) $contract->id,
                        'contract_tenant_id' => (int) $contract->contract_tenant_id,
                        'contract_no' => $contract->contract_no,
                        'tenant_code' => $contract->contractTenant?->tenant_code,
                        'tenant_name' => $contract->contractTenant?->name,
                        'property_code' => $contract->property?->property_code,
                        'property_name' => $contract->property?->name,
                        'unit_no' => $contract->propertyUnit?->unit_no,
                        'payment_item_type' => $itemType,
                        'payment_item_id' => $paymentItem?->id,
                        'payment_item_name' => $paymentItem?->name,
                        'payment_account_id' => $defaultPaymentAccount?->id,
                        'payment_account_name' => $defaultPaymentAccount?->name,
                        'due_on' => $dueOn,
                        'amount' => $amount,
                        'status' => $status,
                        'status_label' => $statusLabel,
                        'term_id' => $term?->id,
                        'term_year_month' => $term?->effective_from_year_month,
                    ]);
                }
            }
        }

        return $rows;
    }

    private function buildTargetMonths(string $dateFrom, string $dateTo): Collection
    {
        $start = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $end = CarbonImmutable::parse($dateTo)->startOfMonth();
        $months = collect();

        while ($start->lessThanOrEqualTo($end)) {
            $months->push($start->format('Y-m'));
            $start = $start->addMonthNoOverflow();
        }

        return $months;
    }

    private function getContracts(int $bookId): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit', 'terms'])
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->whereIn('contract_status', ['active', 'planned'])
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id')
            ->get();
    }

    private function contractAppliesToMonth(RentalContract $contract, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): bool
    {
        $startDate = $contract->move_in_on ?? $contract->contract_started_on;
        $endDate = $contract->move_out_on ?? $contract->contract_ended_on;

        if ($startDate !== null && CarbonImmutable::parse($startDate)->greaterThan($monthEnd)) {
            return false;
        }

        if ($endDate !== null && CarbonImmutable::parse($endDate)->lessThan($monthStart)) {
            return false;
        }

        return true;
    }

    private function resolveTermForMonth(RentalContract $contract, string $targetYearMonth): ?RentalContractTerm
    {
        return $contract->terms
            ->filter(fn (RentalContractTerm $term): bool => $term->effective_from_year_month <= $targetYearMonth)
            ->sortByDesc('effective_from_year_month')
            ->first();
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

    private function buildSummary(Collection $allRows, Collection $displayRows, int $bookId): array
    {
        return [
            'rows' => $displayRows,
            'months_count' => $allRows->pluck('target_year_month')->unique()->count(),
            'contracts_count' => $allRows->pluck('contract_id')->unique()->count(),
            'creatable_count' => $allRows->where('status', 'create')->count(),
            'existing_count' => $allRows->where('status', 'existing')->count(),
            'missing_item_count' => $allRows->where('status', 'missing_item')->count(),
            'zero_amount_count' => $allRows->where('status', 'zero_amount')->count(),
            'creatable_total' => round($allRows->where('status', 'create')->sum(fn (object $row): float => (float) $row->amount), 2),
            'display_count' => $displayRows->count(),
            'default_payment_account' => $this->getDefaultPaymentAccount($bookId),
            'created_count' => 0,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'rows' => collect(),
            'months_count' => 0,
            'contracts_count' => 0,
            'creatable_count' => 0,
            'existing_count' => 0,
            'missing_item_count' => 0,
            'zero_amount_count' => 0,
            'creatable_total' => 0.0,
            'display_count' => 0,
            'default_payment_account' => null,
            'created_count' => 0,
        ];
    }

    private function getSelectableBooks(?int $selectedBookId = null): Collection
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('period_start_date')
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