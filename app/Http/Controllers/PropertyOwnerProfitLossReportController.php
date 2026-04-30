<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\DepreciableAsset;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PropertyOwnerProfitLossReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'display' => ['nullable', 'in:non_zero,all'],
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

        $display = $validated['display'] ?? 'non_zero';

        $propertyRows = collect();
        $ownerRows = collect();

        if ($selectedBook !== null) {
            $propertyRows = $this->buildPropertyRows((int) $selectedBook->id, $dateFrom, $dateTo, $display);
            $ownerRows = $this->buildOwnerRows($propertyRows);
        }

        return view('reports.property_owner_profit_losses.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'propertyRows' => $propertyRows,
            'ownerRows' => $ownerRows,
            'summary' => $this->buildSummary($propertyRows, $ownerRows),
        ]);
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

    private function buildPropertyRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $display
    ): Collection {
        $rows = [];

        $properties = DB::table('properties as p')
            ->leftJoin('property_categories as pc', 'pc.id', '=', 'p.property_category_id')
            ->leftJoin('property_owners as po', 'po.id', '=', 'p.primary_owner_id')
            ->where('p.book_id', $bookId)
            ->select([
                'p.id as property_id',
                'p.property_code',
                'p.name as property_name',
                'p.sort_order',
                'pc.name as property_category_name',
                'po.id as owner_id',
                'po.owner_code',
                'po.name as owner_name',
            ])
            ->orderBy('p.sort_order')
            ->orderBy('p.property_code')
            ->orderBy('p.id')
            ->get();

        foreach ($properties as $property) {
            $rows[(string) $property->property_id] = $this->emptyPropertyRow(
                $property->property_id !== null ? (int) $property->property_id : null,
                $property->property_code,
                $property->property_name,
                $property->property_category_name,
                $property->owner_id !== null ? (int) $property->owner_id : null,
                $property->owner_code,
                $property->owner_name,
                (int) $property->sort_order
            );
        }

        $incomeRows = $this->buildRentalIncomeRows($bookId, $dateFrom, $dateTo);
        foreach ($incomeRows as $incomeRow) {
            $key = $incomeRow->property_id !== null ? (string) $incomeRow->property_id : 'none';
            $this->ensurePropertyRow($rows, $key);

            $rows[$key]['rental_schedules_count'] = (int) $incomeRow->schedules_count;
            $rows[$key]['rental_expected_total'] = round((float) $incomeRow->expected_total, 2);
            $rows[$key]['rental_received_total'] = round((float) $incomeRow->received_total, 2);
            $rows[$key]['rental_remaining_total'] = round((float) $incomeRow->remaining_total, 2);
        }

        $depreciationRows = $this->buildDepreciationRows($bookId, $dateFrom, $dateTo);
        foreach ($depreciationRows as $depreciationRow) {
            $key = $depreciationRow->property_id !== null ? (string) $depreciationRow->property_id : 'none';
            $this->ensurePropertyRow($rows, $key);

            $rows[$key]['depreciable_assets_count'] = (int) $depreciationRow->assets_count;
            $rows[$key]['depreciation_total'] = round((float) $depreciationRow->depreciation_total, 2);
        }

        $loanRows = $this->buildLoanRepaymentRows($bookId, $dateFrom, $dateTo);
        foreach ($loanRows as $loanRow) {
            $key = $loanRow->property_id !== null ? (string) $loanRow->property_id : 'none';
            $this->ensurePropertyRow($rows, $key);

            $rows[$key]['loan_repayments_count'] = (int) $loanRow->repayments_count;
            $rows[$key]['loan_principal_total'] = round((float) $loanRow->principal_total, 2);
            $rows[$key]['loan_interest_total'] = round((float) $loanRow->interest_total, 2);
            $rows[$key]['loan_payment_total'] = round((float) $loanRow->payment_total, 2);
        }

        $journalRows = $this->buildPropertyJournalRows($bookId, $dateFrom, $dateTo);
        foreach ($journalRows as $journalRow) {
            $key = $journalRow->property_id !== null ? (string) $journalRow->property_id : 'none';
            $this->ensurePropertyRow($rows, $key);

            $rows[$key]['journal_lines_count'] = (int) $journalRow->lines_count;
            $rows[$key]['journal_revenue_total'] = round((float) $journalRow->revenue_total, 2);
            $rows[$key]['journal_expense_total'] = round((float) $journalRow->expense_total, 2);
            $rows[$key]['journal_profit_loss_total'] = round((float) $journalRow->revenue_total - (float) $journalRow->expense_total, 2);
        }

        return collect($rows)
            ->map(function (array $row): object {
                $row['estimated_income_by_expected'] = round(
                    (float) $row['rental_expected_total']
                    - (float) $row['depreciation_total']
                    - (float) $row['loan_interest_total'],
                    2
                );

                $row['estimated_income_by_received'] = round(
                    (float) $row['rental_received_total']
                    - (float) $row['depreciation_total']
                    - (float) $row['loan_interest_total'],
                    2
                );
                $row['estimated_income_with_journal_by_expected'] = round((float) $row['estimated_income_by_expected'] + (float) $row['journal_profit_loss_total'], 2);
                $row['estimated_income_with_journal_by_received'] = round((float) $row['estimated_income_by_received'] + (float) $row['journal_profit_loss_total'], 2);
                return (object) $row;
            })
            ->filter(function (object $row) use ($display): bool {
                if ($display === 'all') {
                    return true;
                }

                return abs((float) $row->rental_expected_total) >= 0.005
                    || abs((float) $row->rental_received_total) >= 0.005
                    || abs((float) $row->depreciation_total) >= 0.005
                    || abs((float) $row->loan_interest_total) >= 0.005
                    || abs((float) $row->journal_profit_loss_total) >= 0.005
                    || abs((float) $row->estimated_income_by_expected) >= 0.005
                    || abs((float) $row->estimated_income_by_received) >= 0.005;
            })
            ->sortBy(fn (object $row): string => str_pad((string) $row->sort_order, 10, '0', STR_PAD_LEFT) . '|' . (string) $row->property_code)
            ->values();
    }

    private function emptyPropertyRow(
        ?int $propertyId = null,
        ?string $propertyCode = null,
        ?string $propertyName = null,
        ?string $propertyCategoryName = null,
        ?int $ownerId = null,
        mixed $ownerCode = null,
        ?string $ownerName = null,
        int $sortOrder = 999999
    ): array {
        return [
            'property_id' => $propertyId,
            'property_code' => $propertyCode ?? '—',
            'property_name' => $propertyName ?? '物件未設定',
            'property_category_name' => $propertyCategoryName ?? '—',
            'owner_id' => $ownerId,
            'owner_code' => $ownerCode,
            'owner_name' => $ownerName ?? '所有者未設定',
            'sort_order' => $sortOrder,
            'rental_schedules_count' => 0,
            'rental_expected_total' => 0.0,
            'rental_received_total' => 0.0,
            'rental_remaining_total' => 0.0,
            'depreciable_assets_count' => 0,
            'depreciation_total' => 0.0,
            'loan_repayments_count' => 0,
            'loan_principal_total' => 0.0,
            'loan_interest_total' => 0.0,
            'loan_payment_total' => 0.0,
            'journal_lines_count' => 0,
            'journal_revenue_total' => 0.0,
            'journal_expense_total' => 0.0,
            'journal_profit_loss_total' => 0.0,
            'estimated_income_by_expected' => 0.0,
            'estimated_income_by_received' => 0.0,
            'estimated_income_with_journal_by_expected' => 0.0,
            'estimated_income_with_journal_by_received' => 0.0,
        ];
    }

    private function ensurePropertyRow(array &$rows, string $key): void
    {
        if (! array_key_exists($key, $rows)) {
            $rows[$key] = $this->emptyPropertyRow();
        }
    }

    private function buildRentalIncomeRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('payment_schedules as ps')
            ->join('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->select([
                'rc.property_id',
                'p.property_code',
                'p.name as property_name',
            ])
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->selectRaw('COALESCE(SUM(GREATEST(ps.expected_amount - ps.received_amount, 0)), 0) as remaining_total')
            ->groupBy('rc.property_id', 'p.property_code', 'p.name');

        if (!empty($dateFrom)) {
            $query->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('ps.due_on', '<=', $dateTo);
        }

        return $query->get();
    }

    private function buildDepreciationRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $assets = DepreciableAsset::query()
            ->where('book_id', $bookId)
            ->where('status', 'active')
            ->get();

        return $assets
            ->groupBy(fn (DepreciableAsset $asset) => $asset->property_id !== null ? (string) $asset->property_id : 'none')
            ->map(function (Collection $assets, string $key) use ($dateFrom, $dateTo): object {
                return (object) [
                    'property_id' => $key === 'none' ? null : (int) $key,
                    'assets_count' => $assets->count(),
                    'depreciation_total' => round(
                        $assets->sum(fn (DepreciableAsset $asset) => (float) $this->calculateDepreciation($asset, $dateFrom, $dateTo)['period_depreciation_amount']),
                        2
                    ),
                ];
            })
            ->values();
    }

    private function buildLoanRepaymentRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('borrowing_repayments as br')
            ->join('borrowing_loans as bl', 'bl.id', '=', 'br.borrowing_loan_id')
            ->where('bl.book_id', $bookId)
            ->where('bl.status', 'active')
            ->select([
                'bl.property_id',
            ])
            ->selectRaw('COUNT(br.id) as repayments_count')
            ->selectRaw('COALESCE(SUM(br.principal_amount), 0) as principal_total')
            ->selectRaw('COALESCE(SUM(br.interest_amount), 0) as interest_total')
            ->selectRaw('COALESCE(SUM(br.total_amount), 0) as payment_total')
            ->groupBy('bl.property_id');

        if (!empty($dateFrom)) {
            $query->whereDate('br.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('br.due_on', '<=', $dateTo);
        }

        return $query->get();
    }

    private function buildPropertyJournalRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereNotNull('jel.property_id')
            ->whereIn('at.category', ['revenue', 'expense'])
            ->whereNotIn('je.entry_type', ['rental_payment', 'depreciation', 'loan_repayment'])
            ->select([
                'jel.property_id',
            ])
            ->selectRaw('COUNT(jel.id) as lines_count')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN at.category = 'revenue' AND at.normal_balance = jel.side THEN jel.amount
                        WHEN at.category = 'revenue' AND at.normal_balance <> jel.side THEN -jel.amount
                        ELSE 0
                    END
                ), 0) as revenue_total
            ")
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN at.category = 'expense' AND at.normal_balance = jel.side THEN jel.amount
                        WHEN at.category = 'expense' AND at.normal_balance <> jel.side THEN -jel.amount
                        ELSE 0
                    END
                ), 0) as expense_total
            ")
            ->groupBy('jel.property_id');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        return $query->get();
    }

    private function buildOwnerRows(Collection $propertyRows): Collection
    {
        return $propertyRows
            ->groupBy(fn (object $row): string => $row->owner_id !== null ? (string) $row->owner_id : 'none')
            ->map(function (Collection $rows, string $key): object {
                $first = $rows->first();

                return (object) [
                    'owner_id' => $key === 'none' ? null : (int) $key,
                    'owner_code' => $first?->owner_code,
                    'owner_name' => $first?->owner_name ?? '所有者未設定',
                    'properties_count' => $rows->count(),
                    'rental_expected_total' => round($rows->sum(fn (object $row) => (float) $row->rental_expected_total), 2),
                    'rental_received_total' => round($rows->sum(fn (object $row) => (float) $row->rental_received_total), 2),
                    'rental_remaining_total' => round($rows->sum(fn (object $row) => (float) $row->rental_remaining_total), 2),
                    'depreciation_total' => round($rows->sum(fn (object $row) => (float) $row->depreciation_total), 2),
                    'loan_interest_total' => round($rows->sum(fn (object $row) => (float) $row->loan_interest_total), 2),
                    'journal_profit_loss_total' => round($rows->sum(fn (object $row) => (float) $row->journal_profit_loss_total), 2),
                    'estimated_income_by_expected' => round($rows->sum(fn (object $row) => (float) $row->estimated_income_by_expected), 2),
                    'estimated_income_by_received' => round($rows->sum(fn (object $row) => (float) $row->estimated_income_by_received), 2),
                    'estimated_income_with_journal_by_expected' => round($rows->sum(fn (object $row) => (float) $row->estimated_income_with_journal_by_expected), 2),
                    'estimated_income_with_journal_by_received' => round($rows->sum(fn (object $row) => (float) $row->estimated_income_with_journal_by_received), 2),
                ];
            })
            ->sortBy(fn (object $row): string => str_pad((string) ($row->owner_code ?? 999999), 10, '0', STR_PAD_LEFT) . '|' . $row->owner_name)
            ->values();
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

    private function buildSummary(Collection $propertyRows, Collection $ownerRows): array
    {
        return [
            'property_rows_count' => $propertyRows->count(),
            'owner_rows_count' => $ownerRows->count(),
            'rental_expected_total' => round($propertyRows->sum(fn (object $row) => (float) $row->rental_expected_total), 2),
            'rental_received_total' => round($propertyRows->sum(fn (object $row) => (float) $row->rental_received_total), 2),
            'rental_remaining_total' => round($propertyRows->sum(fn (object $row) => (float) $row->rental_remaining_total), 2),
            'depreciation_total' => round($propertyRows->sum(fn (object $row) => (float) $row->depreciation_total), 2),
            'loan_interest_total' => round($propertyRows->sum(fn (object $row) => (float) $row->loan_interest_total), 2),
            'journal_profit_loss_total' => round($propertyRows->sum(fn (object $row) => (float) $row->journal_profit_loss_total), 2),
            'estimated_income_by_expected_total' => round($propertyRows->sum(fn (object $row) => (float) $row->estimated_income_by_expected), 2),
            'estimated_income_by_received_total' => round($propertyRows->sum(fn (object $row) => (float) $row->estimated_income_by_received), 2),
            'estimated_income_with_journal_by_expected_total' => round($propertyRows->sum(fn (object $row) => (float) $row->estimated_income_with_journal_by_expected), 2),
            'estimated_income_with_journal_by_received_total' => round($propertyRows->sum(fn (object $row) => (float) $row->estimated_income_with_journal_by_received), 2),
        ];
    }
}