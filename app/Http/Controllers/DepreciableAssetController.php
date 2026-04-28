<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\DepreciableAsset;
use App\Models\Department;
use App\Models\JournalEntry;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DepreciableAssetController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,active,disposed'],
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

        $status = $validated['status'] ?? 'active';

        $assetRows = collect();

        if ($selectedBook !== null) {
            $assetsQuery = DepreciableAsset::query()
                ->with([
                    'book.businessOwner',
                    'property',
                    'assetAccountTitle',
                    'accumulatedDepreciationAccountTitle',
                    'depreciationExpenseAccountTitle',
                    'department',
                ])
                ->where('book_id', $selectedBook->id)
                ->orderBy('asset_code')
                ->orderBy('id');

            if ($status !== 'all') {
                $assetsQuery->where('status', $status);
            }

            $assetRows = $assetsQuery
                ->get()
                ->map(fn (DepreciableAsset $asset) => $this->buildAssetRow($asset, $dateFrom, $dateTo));
        }

        return view('depreciable_assets.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'assetRows' => $assetRows,
            'summary' => $this->buildSummary($assetRows),
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
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

        $formData = $selectedBookId !== null
            ? $this->loadFormMasterData($selectedBookId)
            : $this->emptyFormMasterData();

        return view('depreciable_assets.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'depreciableAsset' => null,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');
        $validated = $this->validateAssetPayload($request, $bookId);

        DepreciableAsset::query()->create(array_merge($validated, [
            'book_id' => $bookId,
        ]));

        return redirect()
            ->route('depreciable-assets.index', ['book_id' => $bookId])
            ->with('status', '固定資産を登録しました。');
    }

    public function edit(DepreciableAsset $depreciableAsset): View
    {
        $depreciableAsset->load([
            'book.businessOwner',
            'property',
            'assetAccountTitle',
            'accumulatedDepreciationAccountTitle',
            'depreciationExpenseAccountTitle',
            'department',
        ]);

        $selectedBookId = (int) $depreciableAsset->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $formData = $this->loadFormMasterData($selectedBookId);

        return view('depreciable_assets.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'depreciableAsset' => $depreciableAsset,
        ], $formData));
    }

    public function update(Request $request, DepreciableAsset $depreciableAsset): RedirectResponse
    {
        $bookId = (int) $depreciableAsset->book_id;
        $validated = $this->validateAssetPayload($request, $bookId, $depreciableAsset);

        $depreciableAsset->fill($validated);
        $depreciableAsset->save();

        return redirect()
            ->route('depreciable-assets.index', ['book_id' => $bookId])
            ->with('status', '固定資産を更新しました。');
    }

    public function destroy(DepreciableAsset $depreciableAsset): RedirectResponse
    {
        $bookId = (int) $depreciableAsset->book_id;

        $depreciableAsset->delete();

        return redirect()
            ->route('depreciable-assets.index', ['book_id' => $bookId])
            ->with('status', '固定資産を削除しました。作成済みの減価償却仕訳は必要に応じて仕訳一覧から確認してください。');
    }

    public function storeDepreciationJournals(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'entry_date' => ['required', 'date'],
        ]);

        $bookId = (int) $validated['book_id'];
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];
        $entryDate = $validated['entry_date'];

        $assets = DepreciableAsset::query()
            ->with([
                'assetAccountTitle',
                'accumulatedDepreciationAccountTitle',
                'depreciationExpenseAccountTitle',
                'department',
            ])
            ->where('book_id', $bookId)
            ->where('status', 'active')
            ->orderBy('asset_code')
            ->orderBy('id')
            ->get();

        $createdOrUpdatedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($assets, $bookId, $dateFrom, $dateTo, $entryDate, &$createdOrUpdatedCount, &$skippedCount): void {
            foreach ($assets as $asset) {
                $depreciation = $this->calculateDepreciation($asset, $dateFrom, $dateTo);
                $amount = (float) $depreciation['period_depreciation_amount'];

                if ($amount <= 0) {
                    $skippedCount++;
                    continue;
                }

                $this->saveDepreciationJournal($asset, $bookId, $entryDate, $dateFrom, $dateTo, $amount);
                $createdOrUpdatedCount++;
            }
        });

        return redirect()
            ->route('depreciable-assets.index', [
                'book_id' => $bookId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => 'active',
            ])
            ->with('status', "減価償却仕訳を {$createdOrUpdatedCount} 件作成・更新しました。対象外 {$skippedCount} 件。");
    }

    private function validateAssetPayload(
        Request $request,
        int $bookId,
        ?DepreciableAsset $depreciableAsset = null
    ): array {
        $assetCodeRule = Rule::unique('depreciable_assets', 'asset_code')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        if ($depreciableAsset !== null) {
            $assetCodeRule = $assetCodeRule->ignore($depreciableAsset->id);
        }

        $validated = $request->validate([
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'asset_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('category', 'asset')
                        ->where('is_active', true)
                ),
            ],
            'accumulated_depreciation_account_title_id' => [
                'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'depreciation_expense_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('category', 'expense')
                        ->where('is_active', true)
                ),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'asset_code' => ['required', 'string', 'max:30', $assetCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'acquisition_date' => ['required', 'date'],
            'depreciation_start_date' => ['nullable', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'depreciation_method' => ['required', 'in:straight_line'],
            'business_use_ratio' => ['required', 'numeric', 'min:0', 'max:100'],
            'status' => ['required', 'in:active,disposed'],
            'note' => ['nullable', 'string'],
        ]);

        $acquisitionCost = (float) $validated['acquisition_cost'];
        $salvageValue = (float) ($validated['salvage_value'] ?? 0);

        if ($salvageValue >= $acquisitionCost) {
            throw ValidationException::withMessages([
                'salvage_value' => '残存価額は取得価額より小さい金額にしてください。',
            ]);
        }

        $validated['salvage_value'] = $salvageValue;
        $validated['depreciation_start_date'] = $validated['depreciation_start_date'] ?: $validated['acquisition_date'];

        return $validated;
    }

    private function getSelectableBooks(?int $currentBookId = null): Collection
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        if ($currentBookId !== null && !$books->contains('id', $currentBookId)) {
            $currentBook = Book::query()
                ->with('businessOwner')
                ->find($currentBookId);

            if ($currentBook !== null) {
                $books = $books->prepend($currentBook);
            }
        }

        return $books;
    }

    private function loadFormMasterData(int $bookId): array
    {
        $assetAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'asset')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $expenseAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'expense')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $allAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $properties = Property::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->get();

        $departments = Department::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->get();

        return [
            'assetAccountTitles' => $assetAccountTitles,
            'expenseAccountTitles' => $expenseAccountTitles,
            'allAccountTitles' => $allAccountTitles,
            'properties' => $properties,
            'departments' => $departments,
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'assetAccountTitles' => collect(),
            'expenseAccountTitles' => collect(),
            'allAccountTitles' => collect(),
            'properties' => collect(),
            'departments' => collect(),
        ];
    }

    private function buildAssetRow(DepreciableAsset $asset, ?string $dateFrom, ?string $dateTo): object
    {
        $depreciation = $this->calculateDepreciation($asset, $dateFrom, $dateTo);
        $voucherNo = null;
        $journalEntry = null;

        if (!empty($dateTo)) {
            $voucherNo = $this->buildVoucherNo($asset, $dateTo);
            $journalEntry = JournalEntry::query()
                ->where('book_id', $asset->book_id)
                ->where('entry_type', 'depreciation')
                ->where('voucher_no', $voucherNo)
                ->first();
        }

        return (object) [
            'asset' => $asset,
            'depreciation' => $depreciation,
            'voucher_no' => $voucherNo,
            'journal_entry' => $journalEntry,
        ];
    }

    private function calculateDepreciation(DepreciableAsset $asset, ?string $dateFrom, ?string $dateTo): array
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return $this->emptyDepreciation();
        }

        $periodStart = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $periodEnd = CarbonImmutable::parse($dateTo)->startOfMonth();

        if ($periodStart->greaterThan($periodEnd)) {
            return $this->emptyDepreciation();
        }

        $depreciationStartDate = $asset->depreciation_start_date ?? $asset->acquisition_date;

        if ($depreciationStartDate === null) {
            return $this->emptyDepreciation();
        }

        $depreciationStart = CarbonImmutable::parse($depreciationStartDate)->startOfMonth();
        $usableStart = $periodStart->greaterThan($depreciationStart) ? $periodStart : $depreciationStart;
        $usableEnd = $periodEnd;

        if ($usableStart->greaterThan($usableEnd)) {
            return $this->emptyDepreciation();
        }

        $acquisitionCost = (float) $asset->acquisition_cost;
        $salvageValue = (float) $asset->salvage_value;
        $businessUseRatio = (float) $asset->business_use_ratio / 100;
        $usefulLifeYears = max((int) $asset->useful_life_years, 1);
        $depreciableBase = max($acquisitionCost - $salvageValue, 0);

        if ($depreciableBase <= 0 || $businessUseRatio <= 0) {
            return $this->emptyDepreciation();
        }

        $annualDepreciation = round($depreciableBase / $usefulLifeYears, 2);
        $periodMonths = (int) $usableStart->diffInMonths($usableEnd) + 1;
        $monthsToPeriodStart = (int) $depreciationStart->diffInMonths($usableStart);
        $monthsToPeriodEnd = (int) $depreciationStart->diffInMonths($usableEnd) + 1;
        $maximumDepreciation = round($depreciableBase * $businessUseRatio, 2);

        $depreciationBeforePeriod = min(
            round($annualDepreciation * ($monthsToPeriodStart / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        $depreciationThroughPeriodEnd = min(
            round($annualDepreciation * ($monthsToPeriodEnd / 12) * $businessUseRatio, 2),
            $maximumDepreciation
        );

        $periodDepreciation = max(round($depreciationThroughPeriodEnd - $depreciationBeforePeriod, 2), 0);
        $bookValueAfterPeriod = max(round($acquisitionCost - $depreciationThroughPeriodEnd, 2), 0);

        return [
            'depreciable_base' => round($depreciableBase, 2),
            'annual_depreciation_amount' => $annualDepreciation,
            'period_months' => $periodMonths,
            'period_depreciation_amount' => $periodDepreciation,
            'accumulated_depreciation_amount' => round($depreciationThroughPeriodEnd, 2),
            'book_value_after_period' => $bookValueAfterPeriod,
        ];
    }

    private function emptyDepreciation(): array
    {
        return [
            'depreciable_base' => 0.0,
            'annual_depreciation_amount' => 0.0,
            'period_months' => 0,
            'period_depreciation_amount' => 0.0,
            'accumulated_depreciation_amount' => 0.0,
            'book_value_after_period' => 0.0,
        ];
    }

    private function saveDepreciationJournal(
        DepreciableAsset $asset,
        int $bookId,
        string $entryDate,
        string $dateFrom,
        string $dateTo,
        float $amount
    ): JournalEntry {
        $voucherNo = $this->buildVoucherNo($asset, $dateTo);

        $journalEntry = JournalEntry::query()
            ->where('book_id', $bookId)
            ->where('entry_type', 'depreciation')
            ->where('voucher_no', $voucherNo)
            ->first() ?? new JournalEntry();

        $creditAccountTitleId = $asset->accumulated_depreciation_account_title_id
            ?: $asset->asset_account_title_id;

        $journalEntry->fill([
            'book_id' => $bookId,
            'journal_description_id' => null,
            'entry_date' => $entryDate,
            'voucher_no' => $voucherNo,
            'description_text' => '減価償却費 ' . $asset->name,
            'note' => '固定資産コード: ' . $asset->asset_code . ' / 対象期間: ' . $dateFrom . '〜' . $dateTo,
            'total_amount' => $amount,
            'entry_type' => 'depreciation',
            'status' => 'posted',
        ]);

        $journalEntry->save();
        $journalEntry->lines()->delete();

        $lineNote = '減価償却: ' . $asset->asset_code . ' ' . $asset->name;

        $journalEntry->lines()->createMany([
            [
                'line_no' => 1,
                'side' => 'debit',
                'account_title_id' => $asset->depreciation_expense_account_title_id,
                'sub_account_title_id' => null,
                'department_id' => $asset->department_id,
                'amount' => $amount,
                'line_note' => $lineNote,
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_title_id' => $creditAccountTitleId,
                'sub_account_title_id' => null,
                'department_id' => null,
                'amount' => $amount,
                'line_note' => $lineNote,
            ],
        ]);

        return $journalEntry;
    }

    private function buildVoucherNo(DepreciableAsset $asset, string $dateTo): string
    {
        $periodKey = CarbonImmutable::parse($dateTo)->format('ymd');

        return 'DEP' . $periodKey . '-' . $asset->id;
    }

    private function buildSummary(Collection $assetRows): array
    {
        return [
            'assets_count' => $assetRows->count(),
            'acquisition_cost_total' => round(
                $assetRows->sum(fn ($row) => (float) $row->asset->acquisition_cost),
                2
            ),
            'period_depreciation_total' => round(
                $assetRows->sum(fn ($row) => (float) $row->depreciation['period_depreciation_amount']),
                2
            ),
            'journal_count' => $assetRows->filter(fn ($row) => $row->journal_entry !== null)->count(),
        ];
    }
}