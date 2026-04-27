<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ContractTenant;
use App\Models\PaymentSchedule;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ContractTenantAnnualIncomeReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'contract_tenant_id' => ['nullable', 'integer', 'exists:contract_tenants,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,unpaid,partial,paid,cancelled'],
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

        $selectedContractTenantId = isset($validated['contract_tenant_id'])
            ? (int) $validated['contract_tenant_id']
            : null;

        $selectedPropertyId = isset($validated['property_id'])
            ? (int) $validated['property_id']
            : null;

        $contractTenants = $selectedBookId !== null
            ? $this->getSelectableContractTenants($selectedBookId, $selectedContractTenantId)
            : collect();

        $properties = $selectedBookId !== null
            ? $this->getSelectableProperties($selectedBookId, $selectedPropertyId)
            : collect();

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $status = $validated['status'] ?? 'all';

        $months = $this->buildMonths($dateFrom, $dateTo);
        $paymentSchedules = collect();

        if ($selectedBook !== null) {
            $paymentSchedules = $this->buildPaymentScheduleRows(
                (int) $selectedBook->id,
                $selectedContractTenantId,
                $selectedPropertyId,
                $dateFrom,
                $dateTo,
                $status
            );
        }

        return view('reports.contract_tenant_annual_incomes.index', [
            'books' => $books,
            'contractTenants' => $contractTenants,
            'properties' => $properties,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'selectedContractTenantId' => $selectedContractTenantId,
            'selectedPropertyId' => $selectedPropertyId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'months' => $months,
            'paymentSchedules' => $paymentSchedules,
            'contractTenantSummaries' => $this->buildContractTenantSummaries($paymentSchedules, $months),
            'monthlyTotals' => $this->buildMonthlyTotals($paymentSchedules, $months),
            'summary' => $this->buildSummary($paymentSchedules),
        ]);
    }

    private function buildPaymentScheduleRows(
        int $bookId,
        ?int $contractTenantId,
        ?int $propertyId,
        ?string $dateFrom,
        ?string $dateTo,
        string $status
    ): Collection {
        $query = PaymentSchedule::query()
            ->with([
                'book.businessOwner',
                'rentalContract.property.propertyCategory',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem',
                'paymentAccount',
                'receipts' => function ($query): void {
                    $query
                        ->where('status', 'confirmed')
                        ->orderBy('received_on')
                        ->orderBy('id');
                },
            ])
            ->where('book_id', $bookId)
            ->orderBy('contract_tenant_id')
            ->orderBy('target_year_month')
            ->orderBy('due_on')
            ->orderBy('rental_contract_id')
            ->orderBy('payment_item_id')
            ->orderBy('id');

        if ($contractTenantId !== null) {
            $query->where('contract_tenant_id', $contractTenantId);
        }

        if ($propertyId !== null) {
            $query->whereHas('rentalContract', function ($query) use ($propertyId): void {
                $query->where('property_id', $propertyId);
            });
        }

        if (!empty($dateFrom)) {
            $query->whereDate('due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('due_on', '<=', $dateTo);
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->get();
    }

    private function buildMonths(?string $dateFrom, ?string $dateTo): Collection
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return collect();
        }

        $start = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $end = CarbonImmutable::parse($dateTo)->startOfMonth();

        if ($start->greaterThan($end)) {
            return collect();
        }

        $months = collect();
        $cursor = $start;
        $safetyCounter = 0;

        while ($cursor->lessThanOrEqualTo($end) && $safetyCounter < 36) {
            $months->push((object) [
                'year_month' => $cursor->format('Y-m'),
                'label' => $cursor->format('Y年n月'),
            ]);

            $cursor = $cursor->addMonth();
            $safetyCounter++;
        }

        return $months;
    }

    private function buildSummary(Collection $paymentSchedules): array
    {
        $expectedTotal = round($paymentSchedules->sum(fn ($schedule) => (float) $schedule->expected_amount), 2);
        $receivedTotal = round($paymentSchedules->sum(fn ($schedule) => (float) $schedule->received_amount), 2);

        return [
            'schedules_count' => $paymentSchedules->count(),
            'contract_tenants_count' => $paymentSchedules
                ->map(fn ($schedule) => $schedule->contract_tenant_id)
                ->filter()
                ->unique()
                ->count(),
            'expected_total' => $expectedTotal,
            'received_total' => $receivedTotal,
            'remaining_total' => round(max($expectedTotal - $receivedTotal, 0), 2),
            'unpaid_count' => $paymentSchedules->where('status', 'unpaid')->count(),
            'partial_count' => $paymentSchedules->where('status', 'partial')->count(),
            'paid_count' => $paymentSchedules->where('status', 'paid')->count(),
            'cancelled_count' => $paymentSchedules->where('status', 'cancelled')->count(),
        ];
    }

    private function buildMonthlyTotals(Collection $paymentSchedules, Collection $months): array
    {
        $monthlyTotals = [];

        foreach ($months as $month) {
            $monthSchedules = $paymentSchedules->filter(
                fn ($schedule) => $this->resolveScheduleMonth($schedule) === $month->year_month
            );

            $expectedTotal = round($monthSchedules->sum(fn ($schedule) => (float) $schedule->expected_amount), 2);
            $receivedTotal = round($monthSchedules->sum(fn ($schedule) => (float) $schedule->received_amount), 2);

            $monthlyTotals[$month->year_month] = [
                'expected_total' => $expectedTotal,
                'received_total' => $receivedTotal,
                'remaining_total' => round(max($expectedTotal - $receivedTotal, 0), 2),
            ];
        }

        return $monthlyTotals;
    }

    private function buildContractTenantSummaries(Collection $paymentSchedules, Collection $months): Collection
    {
        return $paymentSchedules
            ->groupBy(fn ($schedule) => (int) ($schedule->contract_tenant_id ?? 0))
            ->map(function (Collection $tenantSchedules) use ($months) {
                $firstSchedule = $tenantSchedules->first();
                $contractTenant = $firstSchedule?->contractTenant;

                $expectedTotal = round($tenantSchedules->sum(fn ($schedule) => (float) $schedule->expected_amount), 2);
                $receivedTotal = round($tenantSchedules->sum(fn ($schedule) => (float) $schedule->received_amount), 2);

                $propertyLabels = $tenantSchedules
                    ->map(function ($schedule): ?string {
                        $property = $schedule->rentalContract?->property;
                        $propertyUnit = $schedule->rentalContract?->propertyUnit;

                        if ($property === null) {
                            return null;
                        }

                        $label = trim(($property->property_code ?: '') . ' ' . ($property->name ?: ''));

                        if ($propertyUnit !== null) {
                            $label .= ' / ' . $propertyUnit->unit_no;
                        }

                        return $label;
                    })
                    ->filter()
                    ->unique()
                    ->values();

                $monthly = [];

                foreach ($months as $month) {
                    $monthSchedules = $tenantSchedules->filter(
                        fn ($schedule) => $this->resolveScheduleMonth($schedule) === $month->year_month
                    );

                    $monthExpectedTotal = round($monthSchedules->sum(fn ($schedule) => (float) $schedule->expected_amount), 2);
                    $monthReceivedTotal = round($monthSchedules->sum(fn ($schedule) => (float) $schedule->received_amount), 2);

                    $monthly[$month->year_month] = [
                        'expected_total' => $monthExpectedTotal,
                        'received_total' => $monthReceivedTotal,
                        'remaining_total' => round(max($monthExpectedTotal - $monthReceivedTotal, 0), 2),
                    ];
                }

                return (object) [
                    'contract_tenant_id' => $contractTenant?->id,
                    'tenant_code' => $contractTenant?->tenant_code,
                    'tenant_name' => $contractTenant?->name,
                    'tenant_short_name' => $contractTenant?->short_name,
                    'property_labels' => $propertyLabels,
                    'schedules_count' => $tenantSchedules->count(),
                    'expected_total' => $expectedTotal,
                    'received_total' => $receivedTotal,
                    'remaining_total' => round(max($expectedTotal - $receivedTotal, 0), 2),
                    'monthly' => $monthly,
                ];
            })
            ->sortBy(fn ($summary) => ($summary->tenant_code ?? '') . '|' . ($summary->tenant_name ?? ''))
            ->values();
    }

    private function resolveScheduleMonth(PaymentSchedule $paymentSchedule): string
    {
        if (!empty($paymentSchedule->target_year_month)) {
            return $paymentSchedule->target_year_month;
        }

        return $paymentSchedule->due_on?->format('Y-m') ?? '';
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

    private function getSelectableContractTenants(int $bookId, ?int $selectedContractTenantId = null): Collection
    {
        $contractTenants = ContractTenant::query()
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('tenant_code')
            ->orderBy('id')
            ->get();

        if (
            $selectedContractTenantId !== null
            && !$contractTenants->contains('id', $selectedContractTenantId)
        ) {
            $selectedContractTenant = ContractTenant::query()
                ->where('book_id', $bookId)
                ->find($selectedContractTenantId);

            if ($selectedContractTenant !== null) {
                $contractTenants = $contractTenants->prepend($selectedContractTenant);
            }
        }

        return $contractTenants;
    }

    private function getSelectableProperties(int $bookId, ?int $selectedPropertyId = null): Collection
    {
        $properties = Property::query()
            ->with('propertyCategory')
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id')
            ->get();

        if (
            $selectedPropertyId !== null
            && !$properties->contains('id', $selectedPropertyId)
        ) {
            $selectedProperty = Property::query()
                ->with('propertyCategory')
                ->where('book_id', $bookId)
                ->find($selectedPropertyId);

            if ($selectedProperty !== null) {
                $properties = $properties->prepend($selectedProperty);
            }
        }

        return $properties;
    }
}