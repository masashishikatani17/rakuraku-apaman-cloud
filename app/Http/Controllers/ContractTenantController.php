<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ContractTenant;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\RentalContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContractTenantController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $contractTenantsQuery = ContractTenant::query()
            ->with([
                'book.businessOwner',
                'latestRentalContract.property',
                'latestRentalContract.propertyUnit',
            ])
            ->withCount('rentalContracts')
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('tenant_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $contractTenantsQuery->where('book_id', $selectedBookId);
        }

        $contractTenants = $contractTenantsQuery->get();

        return view('contract_tenants.index', [
            'books' => $books,
            'contractTenants' => $contractTenants,
            'selectedBookId' => $selectedBookId,
        ]);
    }

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

        $formData = $selectedBookId !== null
            ? $this->loadContractMasterData($selectedBookId)
            : $this->emptyContractMasterData();

        return view('contract_tenants.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);
        $this->prepareBooleanAndDefaultValues($validated, $request);

        DB::transaction(function () use ($validated, $bookId): void {
            $contractTenant = ContractTenant::create($this->tenantValues($validated, $bookId));

            $rentalContractValues = $this->rentalContractValues(
                $validated,
                $bookId,
                (int) $contractTenant->id
            );

            RentalContract::create($rentalContractValues);
        });

        return redirect()
            ->route('contract-tenants.index', ['book_id' => $bookId])
            ->with('status', '契約者を登録しました。');
    }

    public function edit(ContractTenant $contractTenant): View
    {
        $contractTenant->load([
            'book.businessOwner',
            'latestRentalContract.property',
            'latestRentalContract.propertyUnit',
        ]);

        $selectedBookId = (int) $contractTenant->book_id;

        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);

        $formData = $this->loadContractMasterData($selectedBookId);

        return view('contract_tenants.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'contractTenant' => $contractTenant,
            'rentalContract' => $contractTenant->latestRentalContract,
        ], $formData));
    }

    public function update(Request $request, ContractTenant $contractTenant): RedirectResponse
    {
        $bookId = (int) $contractTenant->book_id;

        $validated = $this->validatePayload($request, $bookId, $contractTenant);
        $this->prepareBooleanAndDefaultValues($validated, $request);

        DB::transaction(function () use ($contractTenant, $validated, $bookId): void {
            $contractTenant->update($this->tenantValues($validated, $bookId));

            $rentalContractValues = $this->rentalContractValues(
                $validated,
                $bookId,
                (int) $contractTenant->id
            );

            $rentalContract = $contractTenant->latestRentalContract;

            if ($rentalContract) {
                $rentalContract->update($rentalContractValues);
            } else {
                RentalContract::create($rentalContractValues);
            }
        });

        return redirect()
            ->route('contract-tenants.index', ['book_id' => $bookId])
            ->with('status', '契約者を更新しました。');
    }

    public function destroy(ContractTenant $contractTenant): RedirectResponse
    {
        $bookId = (int) $contractTenant->book_id;

        DB::transaction(function () use ($contractTenant): void {
            $contractTenant->delete();
        });

        return redirect()
            ->route('contract-tenants.index', ['book_id' => $bookId])
            ->with('status', '契約者を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?ContractTenant $contractTenant = null
    ): array {
        $tenantCodeRule = Rule::unique('contract_tenants', 'tenant_code')->where(
            fn ($query) => $query->where('book_id', $bookId)
        );

        if ($contractTenant !== null) {
            $tenantCodeRule = $tenantCodeRule->ignore($contractTenant->id);
        }

        $contractNoRule = Rule::unique('rental_contracts', 'contract_no')->where(
            fn ($query) => $query->where('book_id', $bookId)
        );

        if ($contractTenant?->latestRentalContract) {
            $contractNoRule = $contractNoRule->ignore($contractTenant->latestRentalContract->id);
        }

        return $request->validate([
            'tenant_code' => ['required', 'string', 'max:20', $tenantCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'name_kana' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:active,planned,ended'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'postal_code_1' => ['nullable', 'string', 'max:3'],
            'postal_code_2' => ['nullable', 'string', 'max:4'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'tenant_note' => ['nullable', 'string'],
            'tenant_is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],

            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'property_unit_id' => [
                'nullable',
                'integer',
                Rule::exists('property_units', 'id')->where(
                    fn ($query) => $query->where('property_id', $request->input('property_id'))
                ),
            ],
            'contract_no' => ['nullable', 'string', 'max:30', $contractNoRule],
            'contract_status' => ['required', 'in:active,planned,ended'],
            'contract_started_on' => ['nullable', 'date'],
            'contract_ended_on' => ['nullable', 'date', 'after_or_equal:contract_started_on'],
            'move_in_on' => ['nullable', 'date'],
            'move_out_on' => ['nullable', 'date', 'after_or_equal:move_in_on'],
            'rent_amount' => ['nullable', 'numeric', 'min:0'],
            'common_service_fee' => ['nullable', 'numeric', 'min:0'],
            'parking_fee' => ['nullable', 'numeric', 'min:0'],
            'other_monthly_fee' => ['nullable', 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'key_money_amount' => ['nullable', 'numeric', 'min:0'],
            'guarantee_deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_due_day' => ['nullable', 'integer', 'between:1,31'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'contract_is_active' => ['required', 'boolean'],
            'contract_note' => ['nullable', 'string'],
        ]);
    }

    private function prepareBooleanAndDefaultValues(array &$validated, Request $request): void
    {
        $validated['tenant_is_active'] = $request->boolean('tenant_is_active');
        $validated['contract_is_active'] = $request->boolean('contract_is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        foreach ([
            'rent_amount',
            'common_service_fee',
            'parking_fee',
            'other_monthly_fee',
            'deposit_amount',
            'key_money_amount',
            'guarantee_deposit_amount',
        ] as $amountField) {
            $validated[$amountField] = $validated[$amountField] ?? 0;
        }
    }

    private function tenantValues(array $validated, int $bookId): array
    {
        return [
            'book_id' => $bookId,
            'tenant_code' => $validated['tenant_code'],
            'name' => $validated['name'],
            'short_name' => $validated['short_name'] ?? null,
            'name_kana' => $validated['name_kana'] ?? null,
            'status' => $validated['status'],
            'phone' => $validated['phone'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'postal_code_1' => $validated['postal_code_1'] ?? null,
            'postal_code_2' => $validated['postal_code_2'] ?? null,
            'address' => $validated['address'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            'is_active' => $validated['tenant_is_active'],
            'sort_order' => $validated['sort_order'],
            'note' => $validated['tenant_note'] ?? null,
        ];
    }

    private function rentalContractValues(
        array $validated,
        int $bookId,
        int $contractTenantId
    ): array {
        return [
            'book_id' => $bookId,
            'contract_tenant_id' => $contractTenantId,
            'property_id' => $validated['property_id'],
            'property_unit_id' => $validated['property_unit_id'] ?? null,
            'contract_no' => $validated['contract_no'] ?? null,
            'contract_status' => $validated['contract_status'],
            'contract_started_on' => $validated['contract_started_on'] ?? null,
            'contract_ended_on' => $validated['contract_ended_on'] ?? null,
            'move_in_on' => $validated['move_in_on'] ?? null,
            'move_out_on' => $validated['move_out_on'] ?? null,
            'rent_amount' => $validated['rent_amount'],
            'common_service_fee' => $validated['common_service_fee'],
            'parking_fee' => $validated['parking_fee'],
            'other_monthly_fee' => $validated['other_monthly_fee'],
            'deposit_amount' => $validated['deposit_amount'],
            'key_money_amount' => $validated['key_money_amount'],
            'guarantee_deposit_amount' => $validated['guarantee_deposit_amount'],
            'payment_due_day' => $validated['payment_due_day'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'is_active' => $validated['contract_is_active'],
            'note' => $validated['contract_note'] ?? null,
        ];
    }

    private function loadContractMasterData(int $bookId): array
    {
        $properties = Property::query()
            ->with(['propertyCategory', 'primaryOwner'])
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->get();

        $propertyUnits = PropertyUnit::query()
            ->with('property')
            ->whereHas('property', fn ($query) => $query->where('book_id', $bookId))
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->orderBy('unit_no')
            ->get();

        return [
            'properties' => $properties,
            'propertyUnits' => $propertyUnits,
        ];
    }

    private function emptyContractMasterData(): array
    {
        return [
            'properties' => collect(),
            'propertyUnits' => collect(),
        ];
    }

    private function getSelectableBooks(?int $selectedBookId = null)
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