<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\BorrowingLoan;
use App\Models\BorrowingRepayment;
use App\Models\Department;
use App\Models\DepreciableAsset;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClosingNextYearAssetLoanCarryoverController extends Controller
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
            ? $this->buildSourceSummary((int) $sourceBook->id, $targetBook, $copyOnlyActive)
            : $this->emptySummary();

        $targetSummary = $targetBook !== null
            ? $this->buildTargetSummary((int) $targetBook->id)
            : $this->emptySummary();

        return view('closing_next_year_asset_loan_carryovers.index', [
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
                && (int) $sourceBook->business_owner_id === (int) $targetBook->business_owner_id
                && !$this->targetHasAssetLoanData((int) $targetBook->id),
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

        if ($this->targetHasAssetLoanData((int) $targetBook->id)) {
            throw ValidationException::withMessages([
                'target_book_id' => '移行先帳簿には既に固定資産または借入金があります。重複防止のためコピーできません。',
            ]);
        }

        $copyOnlyActive = (bool) ($validated['copy_only_active'] ?? true);

        $result = DB::transaction(function () use ($sourceBook, $targetBook, $copyOnlyActive): array {
            $accountTitleMap = $this->buildAccountTitleMap((int) $sourceBook->id, (int) $targetBook->id);
            $departmentMap = $this->buildDepartmentMap((int) $sourceBook->id, (int) $targetBook->id);
            $propertyMap = $this->buildPropertyMap((int) $sourceBook->id, (int) $targetBook->id);

            $assetResult = $this->copyDepreciableAssets($sourceBook, $targetBook, $accountTitleMap, $departmentMap, $propertyMap, $copyOnlyActive);
            $loanResult = $this->copyBorrowingLoans($sourceBook, $targetBook, $accountTitleMap, $departmentMap, $propertyMap, $copyOnlyActive);

            return [
                'assets' => $assetResult['created_count'],
                'asset_skipped' => $assetResult['skipped_count'],
                'loans' => $loanResult['created_count'],
                'loan_skipped' => $loanResult['skipped_count'],
                'repayments' => $loanResult['repayments_count'],
            ];
        });

        $message = sprintf(
            '固定資産・借入金台帳を引き継ぎました。固定資産%d件、借入金%d件、返済予定%d件。スキップ: 固定資産%d件、借入金%d件。',
            $result['assets'],
            $result['loans'],
            $result['repayments'],
            $result['asset_skipped'],
            $result['loan_skipped']
        );

        return redirect()
            ->route('closing.next-year-asset-loan-carryovers.index', [
                'source_book_id' => $sourceBook->id,
                'target_book_id' => $targetBook->id,
                'copy_only_active' => $copyOnlyActive ? 1 : 0,
            ])
            ->with('status', $message);
    }

    private function copyDepreciableAssets(
        Book $sourceBook,
        Book $targetBook,
        array $accountTitleMap,
        array $departmentMap,
        array $propertyMap,
        bool $copyOnlyActive
    ): array {
        $createdCount = 0;
        $skippedCount = 0;

        $query = DepreciableAsset::query()
            ->where('book_id', $sourceBook->id)
            ->orderBy('asset_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('status', 'active');
        }

        $query->get()->each(function (DepreciableAsset $source) use (
            $targetBook,
            $accountTitleMap,
            $departmentMap,
            $propertyMap,
            &$createdCount,
            &$skippedCount
        ): void {
            $assetAccountTitleId = $accountTitleMap[(int) $source->asset_account_title_id] ?? null;
            $expenseAccountTitleId = $accountTitleMap[(int) $source->depreciation_expense_account_title_id] ?? null;
            $accumulatedAccountTitleId = $source->accumulated_depreciation_account_title_id !== null
                ? ($accountTitleMap[(int) $source->accumulated_depreciation_account_title_id] ?? null)
                : null;

            if ($assetAccountTitleId === null || $expenseAccountTitleId === null) {
                $skippedCount++;
                return;
            }

            if ($source->accumulated_depreciation_account_title_id !== null && $accumulatedAccountTitleId === null) {
                $skippedCount++;
                return;
            }

            DepreciableAsset::query()->create([
                'book_id' => $targetBook->id,
                'property_id' => $source->property_id !== null ? ($propertyMap[(int) $source->property_id] ?? null) : null,
                'asset_account_title_id' => $assetAccountTitleId,
                'accumulated_depreciation_account_title_id' => $accumulatedAccountTitleId,
                'depreciation_expense_account_title_id' => $expenseAccountTitleId,
                'department_id' => $source->department_id !== null ? ($departmentMap[(int) $source->department_id] ?? null) : null,
                'asset_code' => $source->asset_code,
                'name' => $source->name,
                'acquisition_date' => $source->acquisition_date,
                'depreciation_start_date' => $source->depreciation_start_date,
                'acquisition_cost' => $source->acquisition_cost,
                'salvage_value' => $source->salvage_value,
                'useful_life_years' => $source->useful_life_years,
                'depreciation_method' => $source->depreciation_method,
                'business_use_ratio' => $source->business_use_ratio,
                'status' => $source->status,
                'note' => trim((string) ($source->note ?? '') . "\n前期固定資産ID: " . $source->id . ' から年度繰越で作成'),
            ]);

            $createdCount++;
        });

        return [
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
        ];
    }

    private function copyBorrowingLoans(
        Book $sourceBook,
        Book $targetBook,
        array $accountTitleMap,
        array $departmentMap,
        array $propertyMap,
        bool $copyOnlyActive
    ): array {
        $createdCount = 0;
        $skippedCount = 0;
        $repaymentsCount = 0;
        $targetStartDate = $targetBook->period_start_date?->format('Y-m-d');

        $query = BorrowingLoan::query()
            ->with('repayments')
            ->where('book_id', $sourceBook->id)
            ->orderBy('loan_code')
            ->orderBy('id');

        if ($copyOnlyActive) {
            $query->where('status', 'active');
        }

        $query->get()->each(function (BorrowingLoan $source) use (
            $targetBook,
            $accountTitleMap,
            $departmentMap,
            $propertyMap,
            $targetStartDate,
            &$createdCount,
            &$skippedCount,
            &$repaymentsCount
        ): void {
            $principalAccountTitleId = $accountTitleMap[(int) $source->principal_account_title_id] ?? null;
            $interestExpenseAccountTitleId = $accountTitleMap[(int) $source->interest_expense_account_title_id] ?? null;
            $paymentAccountTitleId = $accountTitleMap[(int) $source->payment_account_title_id] ?? null;

            if ($principalAccountTitleId === null || $interestExpenseAccountTitleId === null || $paymentAccountTitleId === null) {
                $skippedCount++;
                return;
            }

            $futureRepayments = $source->repayments
                ->filter(function (BorrowingRepayment $repayment) use ($targetStartDate): bool {
                    if ($repayment->journal_entry_id !== null || $repayment->status === 'journaled') {
                        return false;
                    }

                    if ($targetStartDate === null) {
                        return true;
                    }

                    return $repayment->due_on?->format('Y-m-d') >= $targetStartDate;
                })
                ->sortBy(fn (BorrowingRepayment $repayment): string => $repayment->due_on?->format('Y-m-d') . '|' . str_pad((string) $repayment->period_no, 6, '0', STR_PAD_LEFT))
                ->values();

            if ($futureRepayments->isEmpty() && $source->status === 'paid_off') {
                $skippedCount++;
                return;
            }

            $new = BorrowingLoan::query()->create([
                'book_id' => $targetBook->id,
                'property_id' => $source->property_id !== null ? ($propertyMap[(int) $source->property_id] ?? null) : null,
                'department_id' => $source->department_id !== null ? ($departmentMap[(int) $source->department_id] ?? null) : null,
                'principal_account_title_id' => $principalAccountTitleId,
                'interest_expense_account_title_id' => $interestExpenseAccountTitleId,
                'payment_account_title_id' => $paymentAccountTitleId,
                'loan_code' => $source->loan_code,
                'name' => $source->name,
                'lender_name' => $source->lender_name,
                'borrowed_on' => $source->borrowed_on,
                'principal_amount' => $this->calculateRemainingPrincipalAtStart($source, $targetStartDate),
                'annual_interest_rate' => $source->annual_interest_rate,
                'term_months' => max($futureRepayments->count(), 1),
                'repayment_start_date' => $futureRepayments->first()?->due_on ?? $source->repayment_start_date,
                'monthly_repayment_day' => $source->monthly_repayment_day,
                'repayment_method' => $source->repayment_method,
                'status' => $source->status,
                'note' => trim((string) ($source->note ?? '') . "\n前期借入金ID: " . $source->id . ' から年度繰越で作成'),
            ]);

            $futureRepayments->each(function (BorrowingRepayment $sourceRepayment) use ($new, &$repaymentsCount): void {
                BorrowingRepayment::query()->create([
                    'borrowing_loan_id' => $new->id,
                    'journal_entry_id' => null,
                    'period_no' => $sourceRepayment->period_no,
                    'due_on' => $sourceRepayment->due_on,
                    'principal_amount' => $sourceRepayment->principal_amount,
                    'interest_amount' => $sourceRepayment->interest_amount,
                    'total_amount' => $sourceRepayment->total_amount,
                    'remaining_principal_after' => $sourceRepayment->remaining_principal_after,
                    'status' => 'scheduled',
                    'note' => trim((string) ($sourceRepayment->note ?? '') . "\n前期返済予定ID: " . $sourceRepayment->id . ' から年度繰越で作成'),
                ]);

                $repaymentsCount++;
            });

            $createdCount++;
        });

        return [
            'created_count' => $createdCount,
            'skipped_count' => $skippedCount,
            'repayments_count' => $repaymentsCount,
        ];
    }

    private function calculateRemainingPrincipalAtStart(BorrowingLoan $loan, ?string $targetStartDate): float
    {
        if ($targetStartDate === null) {
            return round((float) $loan->principal_amount, 2);
        }

        $previousRepayment = $loan->repayments
            ->filter(fn (BorrowingRepayment $repayment): bool => $repayment->due_on?->format('Y-m-d') < $targetStartDate)
            ->sortBy(fn (BorrowingRepayment $repayment): string => $repayment->due_on?->format('Y-m-d') . '|' . str_pad((string) $repayment->period_no, 6, '0', STR_PAD_LEFT))
            ->last();

        if ($previousRepayment === null) {
            return round((float) $loan->principal_amount, 2);
        }

        return round((float) $previousRepayment->remaining_principal_after, 2);
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

    private function buildDepartmentMap(int $sourceBookId, int $targetBookId): array
    {
        $targetByCode = Department::query()
            ->where('book_id', $targetBookId)
            ->get()
            ->keyBy('department_code');

        $map = [];

        Department::query()
            ->where('book_id', $sourceBookId)
            ->get()
            ->each(function (Department $source) use ($targetByCode, &$map): void {
                $target = $targetByCode->get($source->department_code);

                if ($target !== null) {
                    $map[(int) $source->id] = (int) $target->id;
                }
            });

        return $map;
    }

    private function buildPropertyMap(int $sourceBookId, int $targetBookId): array
    {
        $targetByCode = Property::query()
            ->where('book_id', $targetBookId)
            ->get()
            ->keyBy('property_code');

        $map = [];

        Property::query()
            ->where('book_id', $sourceBookId)
            ->get()
            ->each(function (Property $source) use ($targetByCode, &$map): void {
                $target = $targetByCode->get($source->property_code);

                if ($target !== null) {
                    $map[(int) $source->id] = (int) $target->id;
                }
            });

        return $map;
    }

    private function targetHasAssetLoanData(int $bookId): bool
    {
        if (DepreciableAsset::query()->where('book_id', $bookId)->exists()) {
            return true;
        }

        return BorrowingLoan::query()->where('book_id', $bookId)->exists();
    }

    private function buildSourceSummary(int $bookId, ?Book $targetBook, bool $copyOnlyActive): array
    {
        $assetsQuery = DepreciableAsset::query()->where('book_id', $bookId);
        $loansQuery = BorrowingLoan::query()->where('book_id', $bookId);

        if ($copyOnlyActive) {
            $assetsQuery->where('status', 'active');
            $loansQuery->where('status', 'active');
        }

        $futureRepaymentsCount = 0;

        if ($targetBook !== null) {
            $targetStartDate = $targetBook->period_start_date?->format('Y-m-d');
            $sourceLoanIds = (clone $loansQuery)->pluck('id');

            if ($sourceLoanIds->isNotEmpty()) {
                $futureRepaymentsQuery = BorrowingRepayment::query()
                    ->whereIn('borrowing_loan_id', $sourceLoanIds)
                    ->whereNull('journal_entry_id')
                    ->where('status', '<>', 'journaled');

                if ($targetStartDate !== null) {
                    $futureRepaymentsQuery->whereDate('due_on', '>=', $targetStartDate);
                }

                $futureRepaymentsCount = $futureRepaymentsQuery->count();
            }
        }

        return [
            'assets' => $assetsQuery->count(),
            'loans' => $loansQuery->count(),
            'repayments' => $futureRepaymentsCount,
        ];
    }

    private function buildTargetSummary(int $bookId): array
    {
        return [
            'assets' => DepreciableAsset::query()->where('book_id', $bookId)->count(),
            'loans' => BorrowingLoan::query()->where('book_id', $bookId)->count(),
            'repayments' => BorrowingRepayment::query()
                ->whereHas('borrowingLoan', fn ($query) => $query->where('book_id', $bookId))
                ->count(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'assets' => 0,
            'loans' => 0,
            'repayments' => 0,
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