<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PropertyUnit;
use App\Models\RentalContract;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OccupancyStatusReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'target_date' => ['nullable', 'date'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'occupancy_status' => ['nullable', 'in:all,occupied,vacant,ending_soon'],
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

        $targetDate = $validated['target_date'] ?? now()->format('Y-m-d');
        $target = CarbonImmutable::parse($targetDate);

        $dateFrom = $validated['date_from']
            ?? $target->startOfMonth()->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $target->endOfMonth()->format('Y-m-d');

        $occupancyStatus = $validated['occupancy_status'] ?? 'all';

        $unitRows = collect();
        $moveInRows = collect();
        $moveOutRows = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;
            $unitRows = $this->buildUnitRows($bookId, $targetDate, $dateFrom, $dateTo, $occupancyStatus);
            $moveInRows = $this->buildMoveInRows($bookId, $dateFrom, $dateTo);
            $moveOutRows = $this->buildMoveOutRows($bookId, $dateFrom, $dateTo);
        }

        return view('reports.occupancy_statuses.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'targetDate' => $targetDate,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'occupancyStatus' => $occupancyStatus,
            'unitRows' => $unitRows,
            'moveInRows' => $moveInRows,
            'moveOutRows' => $moveOutRows,
            'summary' => $this->buildSummary($unitRows, $moveInRows, $moveOutRows),
        ]);
    }

    private function buildUnitRows(
        int $bookId,
        string $targetDate,
        string $dateFrom,
        string $dateTo,
        string $occupancyStatus
    ): Collection {
        $target = CarbonImmutable::parse($targetDate);
        $periodEnd = CarbonImmutable::parse($dateTo);

        $units = PropertyUnit::query()
            ->with([
                'property.propertyCategory',
                'property.primaryOwner',
                'rentalContracts.contractTenant',
            ])
            ->whereHas('property', function ($query) use ($bookId): void {
                $query->where('book_id', $bookId);
            })
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->orderBy('unit_no')
            ->get();

        $rows = $units->map(function (PropertyUnit $unit) use ($target, $periodEnd): object {
            $activeContract = $unit->rentalContracts
                ->filter(fn (RentalContract $contract) => $this->isActiveOn($contract, $target))
                ->sortByDesc(fn (RentalContract $contract) => $contract->contract_started_on?->format('Y-m-d') ?? '')
                ->first();

            $latestEndedContract = $unit->rentalContracts
                ->filter(fn (RentalContract $contract) => $contract->contract_ended_on !== null || $contract->move_out_on !== null)
                ->sortByDesc(fn (RentalContract $contract) => ($contract->move_out_on ?? $contract->contract_ended_on)?->format('Y-m-d') ?? '')
                ->first();

            $nextPlannedContract = $unit->rentalContracts
                ->filter(fn (RentalContract $contract) => $this->startsAfter($contract, $target))
                ->sortBy(fn (RentalContract $contract) => ($contract->move_in_on ?? $contract->contract_started_on)?->format('Y-m-d') ?? '')
                ->first();

            $status = $activeContract !== null ? 'occupied' : 'vacant';
            $statusLabel = $activeContract !== null ? '入居中' : '空室';
            $endingSoon = false;

            if ($activeContract !== null) {
                $endDate = $activeContract->move_out_on ?? $activeContract->contract_ended_on;
                $endingSoon = $endDate !== null
                    && CarbonImmutable::parse($endDate)->betweenIncluded($target, $periodEnd);

                if ($endingSoon) {
                    $status = 'ending_soon';
                    $statusLabel = '退去予定';
                }
            }

            return (object) [
                'property_id' => $unit->property_id,
                'property_code' => $unit->property?->property_code,
                'property_name' => $unit->property?->name,
                'property_category_name' => $unit->property?->propertyCategory?->name,
                'owner_code' => $unit->property?->primaryOwner?->owner_code,
                'owner_name' => $unit->property?->primaryOwner?->name,
                'unit_id' => (int) $unit->id,
                'unit_no' => $unit->unit_no,
                'unit_type' => $unit->unit_type,
                'layout_code' => $unit->layout_code,
                'area_sqm' => $unit->area_sqm,
                'is_active' => (bool) $unit->is_active,
                'ended_at' => $unit->ended_at?->format('Y-m-d'),
                'status' => $status,
                'status_label' => $statusLabel,
                'active_contract' => $activeContract,
                'latest_ended_contract' => $latestEndedContract,
                'next_planned_contract' => $nextPlannedContract,
                'tenant_name' => $activeContract?->contractTenant?->name,
                'tenant_code' => $activeContract?->contractTenant?->tenant_code,
                'contract_no' => $activeContract?->contract_no,
                'move_in_on' => $activeContract?->move_in_on?->format('Y-m-d'),
                'move_out_on' => $activeContract?->move_out_on?->format('Y-m-d'),
                'contract_started_on' => $activeContract?->contract_started_on?->format('Y-m-d'),
                'contract_ended_on' => $activeContract?->contract_ended_on?->format('Y-m-d'),
                'monthly_total' => $activeContract !== null
                    ? round(
                        (float) $activeContract->rent_amount
                        + (float) $activeContract->common_service_fee
                        + (float) $activeContract->parking_fee
                        + (float) $activeContract->other_monthly_fee,
                        2
                    )
                    : 0.0,
                'next_move_in_on' => $nextPlannedContract?->move_in_on?->format('Y-m-d')
                    ?? $nextPlannedContract?->contract_started_on?->format('Y-m-d'),
                'next_tenant_name' => $nextPlannedContract?->contractTenant?->name,
                'last_move_out_on' => $latestEndedContract?->move_out_on?->format('Y-m-d')
                    ?? $latestEndedContract?->contract_ended_on?->format('Y-m-d'),
                'last_tenant_name' => $latestEndedContract?->contractTenant?->name,
            ];
        });

        if ($occupancyStatus !== 'all') {
            if ($occupancyStatus === 'occupied') {
                $rows = $rows->filter(fn ($row) => $row->status === 'occupied' || $row->status === 'ending_soon');
            } elseif ($occupancyStatus === 'ending_soon') {
                $rows = $rows->filter(fn ($row) => $row->status === 'ending_soon');
            } else {
                $rows = $rows->filter(fn ($row) => $row->status === $occupancyStatus);
            }
        }

        return $rows->values();
    }

    private function buildMoveInRows(int $bookId, string $dateFrom, string $dateTo): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
            ->where('book_id', $bookId)
            ->where(function ($query) use ($dateFrom, $dateTo): void {
                $query
                    ->whereBetween('move_in_on', [$dateFrom, $dateTo])
                    ->orWhereBetween('contract_started_on', [$dateFrom, $dateTo]);
            })
            ->orderByRaw('COALESCE(move_in_on, contract_started_on)')
            ->orderBy('id')
            ->get()
            ->map(fn (RentalContract $contract) => $this->contractToMoveRow($contract, '入居予定'));
    }

    private function buildMoveOutRows(int $bookId, string $dateFrom, string $dateTo): Collection
    {
        return RentalContract::query()
            ->with(['contractTenant', 'property', 'propertyUnit'])
            ->where('book_id', $bookId)
            ->where(function ($query) use ($dateFrom, $dateTo): void {
                $query
                    ->whereBetween('move_out_on', [$dateFrom, $dateTo])
                    ->orWhereBetween('contract_ended_on', [$dateFrom, $dateTo]);
            })
            ->orderByRaw('COALESCE(move_out_on, contract_ended_on)')
            ->orderBy('id')
            ->get()
            ->map(fn (RentalContract $contract) => $this->contractToMoveRow($contract, '退去予定'));
    }

    private function contractToMoveRow(RentalContract $contract, string $label): object
    {
        return (object) [
            'label' => $label,
            'contract_id' => (int) $contract->id,
            'contract_no' => $contract->contract_no,
            'tenant_code' => $contract->contractTenant?->tenant_code,
            'tenant_name' => $contract->contractTenant?->name,
            'property_code' => $contract->property?->property_code,
            'property_name' => $contract->property?->name,
            'unit_no' => $contract->propertyUnit?->unit_no,
            'move_in_on' => $contract->move_in_on?->format('Y-m-d'),
            'move_out_on' => $contract->move_out_on?->format('Y-m-d'),
            'contract_started_on' => $contract->contract_started_on?->format('Y-m-d'),
            'contract_ended_on' => $contract->contract_ended_on?->format('Y-m-d'),
            'contract_status' => $contract->contract_status,
            'monthly_total' => round(
                (float) $contract->rent_amount
                + (float) $contract->common_service_fee
                + (float) $contract->parking_fee
                + (float) $contract->other_monthly_fee,
                2
            ),
        ];
    }

    private function isActiveOn(RentalContract $contract, CarbonImmutable $target): bool
    {
        if (!$contract->is_active || $contract->contract_status !== 'active') {
            return false;
        }

        $startedOn = $contract->contract_started_on ?? $contract->move_in_on;
        $endedOn = $contract->move_out_on ?? $contract->contract_ended_on;

        if ($startedOn !== null && CarbonImmutable::parse($startedOn)->greaterThan($target)) {
            return false;
        }

        if ($endedOn !== null && CarbonImmutable::parse($endedOn)->lessThan($target)) {
            return false;
        }

        return true;
    }

    private function startsAfter(RentalContract $contract, CarbonImmutable $target): bool
    {
        $startDate = $contract->move_in_on ?? $contract->contract_started_on;

        return $startDate !== null
            && CarbonImmutable::parse($startDate)->greaterThan($target)
            && in_array($contract->contract_status, ['planned', 'active'], true);
    }

    private function buildSummary(Collection $unitRows, Collection $moveInRows, Collection $moveOutRows): array
    {
        return [
            'units_count' => $unitRows->count(),
            'occupied_count' => $unitRows->filter(fn ($row) => $row->status === 'occupied' || $row->status === 'ending_soon')->count(),
            'vacant_count' => $unitRows->where('status', 'vacant')->count(),
            'ending_soon_count' => $unitRows->where('status', 'ending_soon')->count(),
            'inactive_units_count' => $unitRows->where('is_active', false)->count(),
            'move_in_count' => $moveInRows->count(),
            'move_out_count' => $moveOutRows->count(),
            'monthly_total' => round($unitRows->sum(fn ($row) => (float) $row->monthly_total), 2),
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