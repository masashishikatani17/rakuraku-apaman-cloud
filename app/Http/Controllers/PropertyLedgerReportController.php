--- a/app/Http/Controllers/PropertyLedgerReportController.php
 b/app/Http/Controllers/PropertyLedgerReportController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentSchedule;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PropertyLedgerReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'is_active' => ['nullable', 'in:all,active,inactive'],
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

        $selectedPropertyId = isset($validated['property_id'])
            ? (int) $validated['property_id']
            : null;

        $activeFilter = $validated['is_active'] ?? 'active';

        $propertiesForSelect = $selectedBookId !== null
            ? $this->getSelectableProperties($selectedBookId, $selectedPropertyId)
            : collect();

        $propertyRows = collect();

        if ($selectedBook !== null) {
            $propertyRows = $this->buildPropertyRows(
                (int) $selectedBook->id,
                $selectedPropertyId,
                $activeFilter
            );
        }

        return view('reports.property_ledgers.index', [
            'books' => $books,
            'propertiesForSelect' => $propertiesForSelect,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'selectedPropertyId' => $selectedPropertyId,
            'activeFilter' => $activeFilter,
            'propertyRows' => $propertyRows,
            'summary' => $this->buildSummary($propertyRows),
        ]);
    }

    private function buildPropertyRows(
        int $bookId,
        ?int $propertyId,
        string $activeFilter
    ): Collection {
        $query = Property::query()
            ->with([
                'book.businessOwner',
                'propertyCategory',
                'primaryOwner',
                'representativeOwner',
                'units' => function ($query): void {
                    $query
                        ->orderBy('sort_order')
                        ->orderBy('unit_no')
                        ->orderBy('id');
                },
                'rentalContracts' => function ($query): void {
                    $query
                        ->with(['contractTenant', 'propertyUnit'])
                        ->orderByRaw("CASE WHEN contract_status = 'active' THEN 0 WHEN contract_status = 'planned' THEN 1 ELSE 2 END")
                        ->orderBy('property_unit_id')
                        ->orderBy('contract_started_on')
                        ->orderBy('id');
                },
            ])
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id');

        if ($propertyId !== null) {
            $query->where('id', $propertyId);
        }

        if ($activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $properties = $query->get();

        if ($properties->isEmpty()) {
            return collect();
        }

        $paymentSummaries = $this->buildPaymentSummaries($bookId, $properties->pluck('id'));

        return $properties->map(function (Property $property) use ($paymentSummaries) {
            $paymentSummary = $paymentSummaries[$property->id] ?? [
                'expected_total' => 0.0,
                'received_total' => 0.0,
                'remaining_total' => 0.0,
                'schedules_count' => 0,
                'unpaid_count' => 0,
                'partial_count' => 0,
                'paid_count' => 0,
            ];

            $activeContracts = $property->rentalContracts
                ->where('contract_status', 'active')
                ->where('is_active', true);

            return (object) [
                'property' => $property,
                'units_count' => $property->units->count(),
                'active_units_count' => $property->units->where('is_active', true)->count(),
                'room_units_count' => $property->units->where('unit_type', 'room')->count(),
                'parking_units_count' => $property->units->where('unit_type', 'parking')->count(),
                'contracts_count' => $property->rentalContracts->count(),
                'active_contracts_count' => $activeContracts->count(),
                'payment_summary' => $paymentSummary,
            ];
        });
    }

    private function buildPaymentSummaries(int $bookId, Collection $propertyIds): array
    {
        if ($propertyIds->isEmpty()) {
            return [];
        }

        $paymentSchedules = PaymentSchedule::query()
            ->with(['rentalContract'])
            ->where('book_id', $bookId)
            ->whereHas('rentalContract', function ($query) use ($propertyIds): void {
                $query->whereIn('property_id', $propertyIds);
            })
            ->get();

        return $paymentSchedules
            ->groupBy(fn (PaymentSchedule $paymentSchedule) => (int) $paymentSchedule->rentalContract?->property_id)
            ->map(function (Collection $propertySchedules) {
                $expectedTotal = round($propertySchedules->sum(fn ($schedule) => (float) $schedule->expected_amount), 2);
                $receivedTotal = round($propertySchedules->sum(fn ($schedule) => (float) $schedule->received_amount), 2);

                return [
                    'expected_total' => $expectedTotal,
                    'received_total' => $receivedTotal,
                    'remaining_total' => round(max($expectedTotal - $receivedTotal, 0), 2),
                    'schedules_count' => $propertySchedules->count(),
                    'unpaid_count' => $propertySchedules->where('status', 'unpaid')->count(),
                    'partial_count' => $propertySchedules->where('status', 'partial')->count(),
                    'paid_count' => $propertySchedules->where('status', 'paid')->count(),
                ];
            })
            ->toArray();
    }

    private function buildSummary(Collection $propertyRows): array
    {
        $expectedTotal = round($propertyRows->sum(fn ($row) => (float) $row->payment_summary['expected_total']), 2);
        $receivedTotal = round($propertyRows->sum(fn ($row) => (float) $row->payment_summary['received_total']), 2);

        return [
            'properties_count' => $propertyRows->count(),
            'units_count' => $propertyRows->sum(fn ($row) => (int) $row->units_count),
            'active_units_count' => $propertyRows->sum(fn ($row) => (int) $row->active_units_count),
            'contracts_count' => $propertyRows->sum(fn ($row) => (int) $row->contracts_count),
            'active_contracts_count' => $propertyRows->sum(fn ($row) => (int) $row->active_contracts_count),
            'expected_total' => $expectedTotal,
            'received_total' => $receivedTotal,
            'remaining_total' => round(max($expectedTotal - $receivedTotal, 0), 2),
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