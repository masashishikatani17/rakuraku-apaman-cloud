--- a/app/Http/Controllers/RentalContractReportController.php
 b/app/Http/Controllers/RentalContractReportController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ContractTenant;
use App\Models\Property;
use App\Models\RentalContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RentalContractReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'contract_tenant_id' => ['nullable', 'integer', 'exists:contract_tenants,id'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'contract_status' => ['nullable', 'in:all,active,planned,ended'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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

        $selectedContractTenantId = isset($validated['contract_tenant_id'])
            ? (int) $validated['contract_tenant_id']
            : null;

        $selectedPropertyId = isset($validated['property_id'])
            ? (int) $validated['property_id']
            : null;

        $contractStatus = $validated['contract_status'] ?? 'all';
        $activeFilter = $validated['is_active'] ?? 'active';

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $contractTenants = $selectedBookId !== null
            ? $this->getSelectableContractTenants($selectedBookId, $selectedContractTenantId)
            : collect();

        $properties = $selectedBookId !== null
            ? $this->getSelectableProperties($selectedBookId, $selectedPropertyId)
            : collect();

        $rentalContracts = collect();

        if ($selectedBook !== null) {
            $rentalContracts = $this->buildRentalContractRows(
                (int) $selectedBook->id,
                $selectedContractTenantId,
                $selectedPropertyId,
                $contractStatus,
                $activeFilter,
                $dateFrom,
                $dateTo
            );
        }

        return view('reports.rental_contracts.index', [
            'books' => $books,
            'contractTenants' => $contractTenants,
            'properties' => $properties,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'selectedContractTenantId' => $selectedContractTenantId,
            'selectedPropertyId' => $selectedPropertyId,
            'contractStatus' => $contractStatus,
            'activeFilter' => $activeFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rentalContracts' => $rentalContracts,
            'summary' => $this->buildSummary($rentalContracts),
        ]);
    }

    private function buildRentalContractRows(
        int $bookId,
        ?int $contractTenantId,
        ?int $propertyId,
        string $contractStatus,
        string $activeFilter,
        ?string $dateFrom,
        ?string $dateTo
    ): Collection {
        $query = RentalContract::query()
            ->with([
                'book.businessOwner',
                'contractTenant',
                'property.propertyCategory',
                'property.primaryOwner',
                'propertyUnit',
            ])
            ->where('book_id', $bookId)
            ->orderByRaw("CASE WHEN contract_status = 'active' THEN 0 WHEN contract_status = 'planned' THEN 1 ELSE 2 END")
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('contract_started_on')
            ->orderBy('id');

        if ($contractTenantId !== null) {
            $query->where('contract_tenant_id', $contractTenantId);
        }

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        if ($contractStatus !== 'all') {
            $query->where('contract_status', $contractStatus);
        }

        if ($activeFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($activeFilter === 'inactive') {
            $query->where('is_active', false);
        }

        if (!empty($dateFrom)) {
            $query->where(function ($query) use ($dateFrom): void {
                $query->whereNull('contract_ended_on')
                    ->orWhereDate('contract_ended_on', '>=', $dateFrom);
            });
        }

        if (!empty($dateTo)) {
            $query->where(function ($query) use ($dateTo): void {
                $query->whereNull('contract_started_on')
                    ->orWhereDate('contract_started_on', '<=', $dateTo);
            });
        }

        return $query->get();
    }

    private function buildSummary(Collection $rentalContracts): array
    {
        $monthlyTotal = round($rentalContracts->sum(function (RentalContract $contract): float {
            return (float) $contract->rent_amount
                + (float) $contract->common_service_fee
                + (float) $contract->parking_fee
                + (float) $contract->other_monthly_fee;
        }), 2);

        return [
            'contracts_count' => $rentalContracts->count(),
            'active_count' => $rentalContracts->where('contract_status', 'active')->count(),
            'planned_count' => $rentalContracts->where('contract_status', 'planned')->count(),
            'ended_count' => $rentalContracts->where('contract_status', 'ended')->count(),
            'tenant_count' => $rentalContracts
                ->pluck('contract_tenant_id')
                ->filter()
                ->unique()
                ->count(),
            'property_count' => $rentalContracts
                ->pluck('property_id')
                ->filter()
                ->unique()
                ->count(),
            'rent_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->rent_amount), 2),
            'common_service_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->common_service_fee), 2),
            'parking_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->parking_fee), 2),
            'other_monthly_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->other_monthly_fee), 2),
            'monthly_total' => $monthlyTotal,
            'deposit_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->deposit_amount), 2),
            'key_money_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->key_money_amount), 2),
            'guarantee_deposit_total' => round($rentalContracts->sum(fn ($contract) => (float) $contract->guarantee_deposit_amount), 2),
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