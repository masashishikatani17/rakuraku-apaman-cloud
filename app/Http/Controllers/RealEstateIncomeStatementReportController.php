<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\DepreciableAsset;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RealEstateIncomeStatementReportController extends Controller
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

        $accountingRows = collect();
        $paymentItemRows = collect();
        $propertyIncomeRows = collect();
        $depreciableAssetRows = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;

            $accountingRows = $this->buildAccountingRows($bookId, $dateFrom, $dateTo, $display);
            $paymentItemRows = $this->buildPaymentItemRows($bookId, $dateFrom, $dateTo);
            $propertyIncomeRows = $this->buildPropertyIncomeRows($bookId, $dateFrom, $dateTo);
            $depreciableAssetRows = $this->buildDepreciableAssetRows($bookId, $dateFrom, $dateTo);
        }

        return view('reports.real_estate_income_statements.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'accountingRows' => $accountingRows,
            'revenueRows' => $accountingRows->where('category', 'revenue')->values(),
            'expenseRows' => $accountingRows->where('category', 'expense')->values(),
            'paymentItemRows' => $paymentItemRows,
            'propertyIncomeRows' => $propertyIncomeRows,
            'depreciableAssetRows' => $depreciableAssetRows,
            'summary' => $this->buildSummary(
                $accountingRows,
                $paymentItemRows,
                $propertyIncomeRows,
                $depreciableAssetRows
            ),
        ]);
    }

    private function buildAccountingRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $display
    ): Collection {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $dateFrom, $dateTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted');

                if (!empty($dateFrom)) {
                    $join->whereDate('je.entry_date', '>=', $dateFrom);
                }

                if (!empty($dateTo)) {
                    $join->whereDate('je.entry_date', '<=', $dateTo);
                }
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.real_estate_statement_category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.real_estate_statement_category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function ($row) {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);

                $amount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                return (object) [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'real_estate_statement_category' => $row->real_estate_statement_category ?: 'auto',
                    'normal_balance' => $row->normal_balance,
                    'is_active' => (bool) $row->is_active,
                    'sort_order' => (int) $row->sort_order,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn ($row) => abs((float) $row->amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildPaymentItemRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('payment_schedules as ps')
            ->join('payment_items as pi', 'pi.id', '=', 'ps.payment_item_id')
            ->leftJoin('account_titles as at', 'at.id', '=', 'pi.account_title_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->select([
                'pi.id as payment_item_id',
                'pi.item_code',
                'pi.name as payment_item_name',
                'pi.item_type',
                'pi.sort_order',
                'at.account_code',
                'at.name as account_name',
            ])
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->selectRaw('COALESCE(SUM(GREATEST(ps.expected_amount - ps.received_amount, 0)), 0) as remaining_total')
            ->groupBy(
                'pi.id',
                'pi.item_code',
                'pi.name',
                'pi.item_type',
                'pi.sort_order',
                'at.account_code',
                'at.name'
            )
            ->orderBy('pi.sort_order')
            ->orderBy('pi.item_code');

        if (!empty($dateFrom)) {
            $query->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('ps.due_on', '<=', $dateTo);
        }

        return $query
            ->get()
            ->map(fn ($row) => (object) [
                'payment_item_id' => (int) $row->payment_item_id,
                'item_code' => $row->item_code,
                'payment_item_name' => $row->payment_item_name,
                'item_type' => $row->item_type,
                'sort_order' => (int) $row->sort_order,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'schedules_count' => (int) $row->schedules_count,
                'expected_total' => round((float) $row->expected_total, 2),
                'received_total' => round((float) $row->received_total, 2),
                'remaining_total' => round((float) $row->remaining_total, 2),
            ]);
    }

    private function buildPropertyIncomeRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = DB::table('payment_schedules as ps')
            ->join('rental_contracts as rc', 'rc.id', '=', 'ps.rental_contract_id')
            ->leftJoin('properties as p', 'p.id', '=', 'rc.property_id')
            ->where('ps.book_id', $bookId)
            ->where('ps.status', '<>', 'cancelled')
            ->select([
                'p.id as property_id',
                'p.property_code',
                'p.name as property_name',
            ])
            ->selectRaw('COUNT(DISTINCT rc.id) as contracts_count')
            ->selectRaw('COUNT(ps.id) as schedules_count')
            ->selectRaw('COALESCE(SUM(ps.expected_amount), 0) as expected_total')
            ->selectRaw('COALESCE(SUM(ps.received_amount), 0) as received_total')
            ->selectRaw('COALESCE(SUM(GREATEST(ps.expected_amount - ps.received_amount, 0)), 0) as remaining_total')
            ->groupBy('p.id', 'p.property_code', 'p.name')
            ->orderBy('p.property_code')
            ->orderBy('p.id');

        if (!empty($dateFrom)) {
            $query->whereDate('ps.due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('ps.due_on', '<=', $dateTo);
        }

        return $query
            ->get()
            ->map(fn ($row) => (object) [
                'property_id' => $row->property_id !== null ? (int) $row->property_id : null,
                'property_code' => $row->property_code,
                'property_name' => $row->property_name,
                'contracts_count' => (int) $row->contracts_count,
                'schedules_count' => (int) $row->schedules_count,
                'expected_total' => round((float) $row->expected_total, 2),
                'received_total' => round((float) $row->received_total, 2),
                'remaining_total' => round((float) $row->remaining_total, 2),
            ]);
    }

    private function buildDepreciableAssetRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return DepreciableAsset::query()
            ->with([
                'property',
                'assetAccountTitle',
                'accumulatedDepreciationAccountTitle',
                'depreciationExpenseAccountTitle',
            ])
            ->where('book_id', $bookId)
            ->where('status', 'active')
            ->orderBy('asset_code')
            ->orderBy('id')
            ->get()
            ->map(function (DepreciableAsset $asset) use ($dateFrom, $dateTo) {
                return (object) [
                    'asset' => $asset,
                    'depreciation' => $this->calculateDepreciation($asset, $dateFrom, $dateTo),
                ];
            });
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

    private function buildSummary(
        Collection $accountingRows,
        Collection $paymentItemRows,
        Collection $propertyIncomeRows,
        Collection $depreciableAssetRows
    ): array {
        $revenueTotal = round(
            $accountingRows
                ->where('category', 'revenue')
                ->sum(fn ($row) => (float) $row->amount),
            2
        );

        $expenseTotal = round(
            $accountingRows
                ->where('category', 'expense')
                ->sum(fn ($row) => (float) $row->amount),
            2
        );

        $rentalExpectedTotal = round(
            $paymentItemRows->sum(fn ($row) => (float) $row->expected_total),
            2
        );

        $rentalReceivedTotal = round(
            $paymentItemRows->sum(fn ($row) => (float) $row->received_total),
            2
        );

        $rentalRemainingTotal = round(
            $paymentItemRows->sum(fn ($row) => (float) $row->remaining_total),
            2
        );

        $depreciationTotal = round(
            $depreciableAssetRows->sum(fn ($row) => (float) $row->depreciation['period_depreciation_amount']),
            2
        );

        return [
            'accounting_rows_count' => $accountingRows->count(),
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'real_estate_income_total' => round($revenueTotal - $expenseTotal, 2),
            'rental_expected_total' => $rentalExpectedTotal,
            'rental_received_total' => $rentalReceivedTotal,
            'rental_remaining_total' => $rentalRemainingTotal,
            'property_rows_count' => $propertyIncomeRows->count(),
            'payment_item_rows_count' => $paymentItemRows->count(),
            'depreciable_assets_count' => $depreciableAssetRows->count(),
            'depreciation_total' => $depreciationTotal,
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