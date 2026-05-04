<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\ContractTenant;
use App\Models\PaymentAccount;
use App\Models\PaymentItem;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use App\Models\PropertyUnit;
use App\Models\RentalContract;
use App\Models\RentalContractTerm;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingNextYearRentalCarryoverController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'source_book_id' => ['nullable', 'integer', 'exists:books,id'],
            'target_book_id' => ['nullable', 'integer', 'exists:books,id'],
            'copy_only_active' => ['nullable', 'boolean'],
        ]);

        $books = $this->getSelectableBooks();

        $sourceBookId = isset($validated['source_book_id'])
            ? (int) $validated['source_book_id']
            : ($books->first()?->id);

        $sourceBook = $sourceBookId !== null
            ? $books->firstWhere('id', $sourceBookId)
            : null;

        if ($sourceBook === null && $sourceBookId !== null) {
            $sourceBook = Book::query()
                ->with('businessOwner')
                ->find($sourceBookId);
        }

        $targetBookId = isset($validated['target_book_id'])
            ? (int) $validated['target_book_id']
            : $this->guessTargetBookId($books, $sourceBook);

        $targetBook = $targetBookId !== null
            ? $books->firstWhere('id', $targetBookId)
            : null;

        if ($targetBook === null && $targetBookId !== null) {
            $targetBook = Book::query()
                ->with('businessOwner')
                ->find($targetBookId);
        }

        $copyOnlyActive = array_key_exists('copy_only_active', $validated)
            ? (bool) $validated['copy_only_active']
            : true;

        $sourceSummary = $sourceBook !== null
            ? $this->buildSourceSummary((int) $sourceBook->id, $copyOnlyActive)
            : $this->emptySummary();

        $targetSummary = $targetBook !== null
            ? $this->buildTargetSummary((int) $targetBook->id)
            : $this->emptySummary();

        return view('closing_next_year_rental_carryovers.index', [
            'books' => $books,
            'sourceBook' => $sourceBook,
            'targetBook' => $targetBook,
            'sourceBookId' => $sourceBookId,
            'targetBookId' => $targetBookId,
            'copyOnlyActive' => $copyOnlyActive,
            'sourceSummary' => $sourceSummary,
            'targetSummary' => $targetSummary,
            'canCopy' => $sourceBook !== null
                && $targetBook !== null
                && (int) $sourceBook->id !== (int) $targetBook->id
                && !$this->targetHasRentalData((int) $targetBook->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'source_book_id' => ['required', 'integer', 'exists:books,id'],
            'target_book_id' => ['required', 'integer', 'exists:books,id'],
            'copy_only_active' => ['nullable', 'boolean'],
        ]);

        $sourceBook = Book::query()
            ->with('businessOwner')
            ->findOrFail((int) $validated['source_book_id']);

        $targetBook = Book::query()
            ->with('businessOwner')
            ->findOrFail((int) $validated['target_book_id']);

        if ((int) $sourceBook->id === (int) $targetBook->id) {
            throw ValidationException::withMessages([
                'target_book_id' => '移行元帳簿と移行先帳簿は別の帳簿を選択してください。',
            ]);
        }

        if ((int) $sourceBook->business_owner_id !== (int) $targetBook->business_owner_id) {
            throw ValidationException::withMessages([
                'target_book_id' => '移行元帳簿と移行先帳簿の事業主が異なります。',
            ]);
        }

        if ($this->targetHasRentalData((int) $targetBook->id)) {
            throw ValidationException::withMessages([
                'target_book_id' => '移行先帳簿には既に賃貸管理データがあります。重複防止のためコピーできません。',
            ]);
        }

        $copyOnlyActive = (bool) ($validated['copy_only_active'] ?? true);

        $result = DB::transaction(function () use ($sourceBook, $targetBook, $copyOnlyActive): array {
            $accountTitleMap = $this->buildAccountTitleMap((int) $sourceBook->id, (int) $targetBook->id);
            $subAccountTitleMap = $this->buildSubAccountTitleMap((int) $sourceBook->id, (int) $targetBook->id, $accountTitleMap);

            $ownerMap = $this->copyPropertyOwners($sourceBook, $targetBook, $copyOnlyActive);
            $categoryMap = $this->copyPropertyCategories($sourceBook, $targetBook, $copyOnlyActive);
            $propertyMap = $this->copyProperties($sourceBook, $targetBook, $ownerMap, $categoryMap, $copyOnlyActive);
            $unitMap = $this->copyPropertyUnits($propertyMap, $copyOnlyActive);
            $tenantMap = $this->copyContractTenants($sourceBook, $targetBook, $copyOnlyActive);
            $paymentItemMap = $this->copyPaymentItems($sourceBook, $targetBook, $accountTitleMap, $subAccountTitleMap, $copyOnlyActive);
            $paymentAccountMap = $this->copyPaymentAccounts($sourceBook, $targetBook, $accountTitleMap, $subAccountTitleMap, $copyOnlyActive);
            $contractMap = $this->copyRentalContracts($sourceBook, $targetBook, $tenantMap, $propertyMap, $unitMap, $copyOnlyActive);
            $termsCount = $this->copyRentalContractTerms($sourceBook, $targetBook, $contractMap);

            return [
                'owners' => count($ownerMap),
                'categories' => count($categoryMap),
                'properties' => count($propertyMap),
                'units' => count($unitMap),
                'tenants' => count($tenantMap),
                'payment_items' => count($paymentItemMap),
                'payment_accounts' => count($paymentAccountMap),
                'contracts' => count($contractMap),
                'terms' => $termsCount,
            ];
        });

        $message = sprintf(
            '賃貸管理データを引き継ぎました。所有者%d件、物件%d件、部屋%d件、契約者%d件、賃貸条件%d件、入金項目%d件、入金口座%d件。',
            $result['owners'],
            $result['properties'],
            $result['units'],
            $result['tenants'],
            $result['contracts'],
            $result['payment_items'],
            $result['payment_accounts']
        );

        return redirect()
            ->route('closing.next-year-rental-carryovers.index', [
                'source_book_id' => $sourceBook->id,
                'target_book_id' => $targetBook->id,
                'copy_only_active' => $copyOnlyActive ? 1 : 0,
            ])
            ->with('status', $message);
    }

    private function copyPropertyOwners(Book $sourceBook, Book $targetBook, bool $copyOnlyActive): array
    {
        $map = [];

        $query = PropertyOwner::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('owner_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (PropertyOwner $source) use ($targetBook, &$map): void {
            $new = PropertyOwner::query()->create([
                'book_id' => $targetBook->id,
                'owner_code' => $source->owner_code,
                'classification_code' => $source->classification_code,
                'name' => $source->name,
                'short_name' => $source->short_name,
                'blue_return_deduction_code' => $source->blue_return_deduction_code,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyPropertyCategories(Book $sourceBook, Book $targetBook, bool $copyOnlyActive): array
    {
        $map = [];

        $query = PropertyCategory::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('category_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (PropertyCategory $source) use ($targetBook, &$map): void {
            $new = PropertyCategory::query()->create([
                'book_id' => $targetBook->id,
                'category_code' => $source->category_code,
                'name' => $source->name,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyProperties(
        Book $sourceBook,
        Book $targetBook,
        array $ownerMap,
        array $categoryMap,
        bool $copyOnlyActive
    ): array {
        $map = [];

        $query = Property::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (Property $source) use ($targetBook, $ownerMap, $categoryMap, &$map): void {
            $new = Property::query()->create([
                'book_id' => $targetBook->id,
                'property_category_id' => $categoryMap[(int) $source->property_category_id] ?? null,
                'property_code' => $source->property_code,
                'name' => $source->name,
                'short_name' => $source->short_name,
                'name_reading' => $source->name_reading,
                'postal_code_1' => $source->postal_code_1,
                'postal_code_2' => $source->postal_code_2,
                'address' => $source->address,
                'ownership_form' => $source->ownership_form,
                'primary_owner_id' => $ownerMap[(int) $source->primary_owner_id] ?? null,
                'representative_owner_id' => $ownerMap[(int) $source->representative_owner_id] ?? null,
                'right_form' => $source->right_form,
                'land_area_sqm' => $source->land_area_sqm,
                'building_area_sqm' => $source->building_area_sqm,
                'residential_floor_area' => $source->residential_floor_area,
                'business_floor_area' => $source->business_floor_area,
                'parking_monthly_indoor' => $source->parking_monthly_indoor,
                'parking_monthly_outdoor' => $source->parking_monthly_outdoor,
                'parking_hourly' => $source->parking_hourly,
                'parking_total' => $source->parking_total,
                'built_at' => $source->built_at,
                'structure' => $source->structure,
                'floors' => $source->floors,
                'layout_summary' => $source->layout_summary,
                'note' => $source->note,
                'note2' => $source->note2,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyPropertyUnits(array $propertyMap, bool $copyOnlyActive): array
    {
        $map = [];

        if (empty($propertyMap)) {
            return $map;
        }

        $query = PropertyUnit::query()
            ->whereIn('property_id', array_keys($propertyMap))
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->orderBy('unit_no')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (PropertyUnit $source) use ($propertyMap, &$map): void {
            $newPropertyId = $propertyMap[(int) $source->property_id] ?? null;

            if ($newPropertyId === null) {
                return;
            }

            $new = PropertyUnit::query()->create([
                'property_id' => $newPropertyId,
                'unit_no' => $source->unit_no,
                'unit_type' => $source->unit_type,
                'area_sqm' => $source->area_sqm,
                'layout_code' => $source->layout_code,
                'parking_category_code' => $source->parking_category_code,
                'ended_at' => $source->ended_at,
                'is_new_registration' => $source->is_new_registration,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyContractTenants(Book $sourceBook, Book $targetBook, bool $copyOnlyActive): array
    {
        $map = [];

        $query = ContractTenant::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('tenant_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (ContractTenant $source) use ($targetBook, &$map): void {
            $new = ContractTenant::query()->create([
                'book_id' => $targetBook->id,
                'tenant_code' => $source->tenant_code,
                'name' => $source->name,
                'short_name' => $source->short_name,
                'name_kana' => $source->name_kana,
                'status' => $source->status,
                'phone' => $source->phone,
                'mobile' => $source->mobile,
                'email' => $source->email,
                'postal_code_1' => $source->postal_code_1,
                'postal_code_2' => $source->postal_code_2,
                'address' => $source->address,
                'emergency_contact_name' => $source->emergency_contact_name,
                'emergency_contact_phone' => $source->emergency_contact_phone,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyPaymentItems(
        Book $sourceBook,
        Book $targetBook,
        array $accountTitleMap,
        array $subAccountTitleMap,
        bool $copyOnlyActive
    ): array {
        $map = [];

        $query = PaymentItem::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('item_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (PaymentItem $source) use ($targetBook, $accountTitleMap, $subAccountTitleMap, &$map): void {
            $new = PaymentItem::query()->create([
                'book_id' => $targetBook->id,
                'item_code' => $source->item_code,
                'name' => $source->name,
                'item_type' => $source->item_type,
                'default_amount' => $source->default_amount,
                'account_title_id' => $accountTitleMap[(int) $source->account_title_id] ?? null,
                'sub_account_title_id' => $subAccountTitleMap[(int) $source->sub_account_title_id] ?? null,
                'is_monthly' => $source->is_monthly,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyPaymentAccounts(
        Book $sourceBook,
        Book $targetBook,
        array $accountTitleMap,
        array $subAccountTitleMap,
        bool $copyOnlyActive
    ): array {
        $map = [];

        $query = PaymentAccount::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('is_active', true);
        }

        $query->get()->each(function (PaymentAccount $source) use ($targetBook, $accountTitleMap, $subAccountTitleMap, &$map): void {
            $new = PaymentAccount::query()->create([
                'book_id' => $targetBook->id,
                'account_code' => $source->account_code,
                'name' => $source->name,
                'bank_name' => $source->bank_name,
                'branch_name' => $source->branch_name,
                'account_type' => $source->account_type,
                'account_number' => $source->account_number,
                'account_holder' => $source->account_holder,
                'account_title_id' => $accountTitleMap[(int) $source->account_title_id] ?? null,
                'sub_account_title_id' => $subAccountTitleMap[(int) $source->sub_account_title_id] ?? null,
                'is_active' => $source->is_active,
                'sort_order' => $source->sort_order,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyRentalContracts(
        Book $sourceBook,
        Book $targetBook,
        array $tenantMap,
        array $propertyMap,
        array $unitMap,
        bool $copyOnlyActive
    ): array {
        $map = [];

        $query = RentalContract::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('property_id')
            ->orderBy('property_unit_id')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query
                ->where('is_active', true)
                ->whereIn('contract_status', ['active', 'planned']);
        }

        $query->get()->each(function (RentalContract $source) use ($targetBook, $tenantMap, $propertyMap, $unitMap, &$map): void {
            $newTenantId = $tenantMap[(int) $source->contract_tenant_id] ?? null;
            $newPropertyId = $propertyMap[(int) $source->property_id] ?? null;
            $newUnitId = $source->property_unit_id !== null
                ? ($unitMap[(int) $source->property_unit_id] ?? null)
                : null;

            if ($newTenantId === null || $newPropertyId === null) {
                return;
            }

            $new = RentalContract::query()->create([
                'book_id' => $targetBook->id,
                'contract_tenant_id' => $newTenantId,
                'property_id' => $newPropertyId,
                'property_unit_id' => $newUnitId,
                'contract_no' => $source->contract_no,
                'contract_status' => $source->contract_status,
                'contract_started_on' => $source->contract_started_on,
                'contract_ended_on' => $source->contract_ended_on,
                'move_in_on' => $source->move_in_on,
                'move_out_on' => $source->move_out_on,
                'rent_amount' => $source->rent_amount,
                'common_service_fee' => $source->common_service_fee,
                'parking_fee' => $source->parking_fee,
                'other_monthly_fee' => $source->other_monthly_fee,
                'deposit_amount' => $source->deposit_amount,
                'key_money_amount' => $source->key_money_amount,
                'guarantee_deposit_amount' => $source->guarantee_deposit_amount,
                'payment_due_day' => $source->payment_due_day,
                'payment_method' => $source->payment_method,
                'is_active' => $source->is_active,
                'note' => $source->note,
            ]);

            $map[(int) $source->id] = (int) $new->id;
        });

        return $map;
    }

    private function copyRentalContractTerms(Book $sourceBook, Book $targetBook, array $contractMap): int
    {
        $count = 0;

        if (empty($contractMap)) {
            return $count;
        }

        RentalContractTerm::query()
            ->where('book_id', $sourceBook->id)
            ->whereIn('rental_contract_id', array_keys($contractMap))
            ->orderBy('rental_contract_id')
            ->orderBy('effective_from_year_month')
            ->orderBy('id')
            ->get()
            ->each(function (RentalContractTerm $source) use ($targetBook, $contractMap, &$count): void {
                $newContractId = $contractMap[(int) $source->rental_contract_id] ?? null;

                if ($newContractId === null) {
                    return;
                }

                RentalContractTerm::query()->create([
                    'book_id' => $targetBook->id,
                    'rental_contract_id' => $newContractId,
                    'effective_from_year_month' => $source->effective_from_year_month,
                    'rent_amount' => $source->rent_amount,
                    'common_service_fee' => $source->common_service_fee,
                    'parking_fee' => $source->parking_fee,
                    'other_monthly_fee' => $source->other_monthly_fee,
                    'payment_due_day' => $source->payment_due_day,
                    'note' => $source->note,
                ]);

                $count++;
            });

        return $count;
    }

    private function buildAccountTitleMap(int $sourceBookId, int $targetBookId): array
    {
        $targetByCode = AccountTitle::query()
            ->where('book_id', $targetBookId)
            ->get()
            ->keyBy('account_code');

        $map = [];

        AccountTitle::query()
            ->where('book_id', $sourceBookId)
            ->get()
            ->each(function (AccountTitle $source) use ($targetByCode, &$map): void {
                $target = $targetByCode->get($source->account_code);

                if ($target !== null) {
                    $map[(int) $source->id] = (int) $target->id;
                }
            });

        return $map;
    }

    private function buildSubAccountTitleMap(int $sourceBookId, int $targetBookId, array $accountTitleMap): array
    {
        $targetSubAccounts = SubAccountTitle::query()
            ->whereHas('accountTitle', fn ($query) => $query->where('book_id', $targetBookId))
            ->with('accountTitle')
            ->get()
            ->groupBy(fn (SubAccountTitle $subAccountTitle): string => $subAccountTitle->accountTitle?->account_code . '|' . $subAccountTitle->sub_account_code);

        $map = [];

        SubAccountTitle::query()
            ->whereHas('accountTitle', fn ($query) => $query->where('book_id', $sourceBookId))
            ->with('accountTitle')
            ->get()
            ->each(function (SubAccountTitle $source) use ($targetSubAccounts, &$map): void {
                $key = $source->accountTitle?->account_code . '|' . $source->sub_account_code;
                $target = $targetSubAccounts->get($key)?->first();

                if ($target !== null) {
                    $map[(int) $source->id] = (int) $target->id;
                }
            });

        return $map;
    }

    private function targetHasRentalData(int $bookId): bool
    {
        if (PropertyOwner::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        if (PropertyCategory::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        if (Property::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        if (ContractTenant::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        if (PaymentItem::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        if (PaymentAccount::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        return RentalContract::query()->where('book_id', $bookId)->exists();
    }

    private function buildSourceSummary(int $bookId, bool $copyOnlyActive): array
    {
        $propertyIds = Property::query()
            ->where('book_id', $bookId)
            ->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))
            ->pluck('id');

        return [
            'owners' => PropertyOwner::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'categories' => PropertyCategory::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'properties' => $propertyIds->count(),
            'units' => PropertyUnit::query()->whereIn('property_id', $propertyIds)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'tenants' => ContractTenant::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'payment_items' => PaymentItem::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'payment_accounts' => PaymentAccount::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true))->count(),
            'contracts' => RentalContract::query()->where('book_id', $bookId)->when($copyOnlyActive, fn ($query) => $query->where('is_active', true)->whereIn('contract_status', ['active', 'planned']))->count(),
        ];
    }

    private function buildTargetSummary(int $bookId): array
    {
        $propertyIds = Property::query()
            ->where('book_id', $bookId)
            ->pluck('id');

        return [
            'owners' => PropertyOwner::query()->where('book_id', $bookId)->count(),
            'categories' => PropertyCategory::query()->where('book_id', $bookId)->count(),
            'properties' => $propertyIds->count(),
            'units' => PropertyUnit::query()->whereIn('property_id', $propertyIds)->count(),
            'tenants' => ContractTenant::query()->where('book_id', $bookId)->count(),
            'payment_items' => PaymentItem::query()->where('book_id', $bookId)->count(),
            'payment_accounts' => PaymentAccount::query()->where('book_id', $bookId)->count(),
            'contracts' => RentalContract::query()->where('book_id', $bookId)->count(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'owners' => 0,
            'categories' => 0,
            'properties' => 0,
            'units' => 0,
            'tenants' => 0,
            'payment_items' => 0,
            'payment_accounts' => 0,
            'contracts' => 0,
        ];
    }

    private function guessTargetBookId(Collection $books, ?Book $sourceBook): ?int
    {
        if ($sourceBook === null) {
            return null;
        }

        return $books
            ->where('business_owner_id', $sourceBook->business_owner_id)
            ->where('id', '!=', $sourceBook->id)
            ->sortByDesc('period_start_date')
            ->first()?->id;
    }

    private function getSelectableBooks(): Collection
    {
        return Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('period_start_date')
            ->orderBy('name')
            ->get();
    }
}