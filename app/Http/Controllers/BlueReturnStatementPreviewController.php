<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\RealEstateClosingAdjustment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BlueReturnStatementPreviewController extends Controller
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

        $profitLossAccountRows = collect();
        $profitLossCategoryRows = collect();
        $balanceSheetRows = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;

            $profitLossAccountRows = $this->buildProfitLossAccountRows($bookId, $dateFrom, $dateTo, $display);
            $profitLossCategoryRows = $this->buildProfitLossCategoryRows($profitLossAccountRows);
            $balanceSheetRows = $this->buildBalanceSheetRows($bookId, $dateTo, $display);
        }

        return view('reports.blue_return_statement_previews.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'profitLossAccountRows' => $profitLossAccountRows,
            'profitLossCategoryRows' => $profitLossCategoryRows,
            'balanceSheetRows' => $balanceSheetRows,
            'summary' => $this->buildSummary($profitLossCategoryRows, $balanceSheetRows),
        ]);
    }

    private function buildProfitLossAccountRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $display
    ): Collection {
        $closingAdjustments = $this->getClosingAdjustments($bookId, $dateFrom, $dateTo);

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
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.real_estate_statement_category',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function ($row) use ($closingAdjustments): object {
                $debitTotal = round((float) $row->debit_total, 2);
                $creditTotal = round((float) $row->credit_total, 2);

                $amount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category
                );

                $closingAdjustment = $closingAdjustments->get((int) $row->account_title_id);
                $adjustmentAmount = round((float) ($closingAdjustment?->adjustment_amount ?? 0), 2);
                $filingAmount = $statementCategory === 'none' ? 0.0 : round($amount + $adjustmentAmount, 2);

                return (object) [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'normal_balance' => $row->normal_balance,
                    'statement_category' => $statementCategory,
                    'statement_category_label' => AccountTitle::REAL_ESTATE_STATEMENT_CATEGORIES[$statementCategory] ?? $statementCategory,
                    'accounting_amount' => $amount,
                    'adjustment_amount' => $adjustmentAmount,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $filingAmount,
                    'sort_order' => (int) $row->sort_order,
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildProfitLossCategoryRows(Collection $accountRows): Collection
    {
        $categoryLabels = AccountTitle::REAL_ESTATE_STATEMENT_CATEGORIES;
        $orderedKeys = collect(array_keys($categoryLabels))
            ->filter(fn (string $key): bool => ! in_array($key, ['auto', 'none'], true))
            ->values();

        $rows = $orderedKeys->map(function (string $key) use ($accountRows, $categoryLabels): object {
            $accounts = $accountRows
                ->where('statement_category', $key)
                ->values();

            $category = str_starts_with($key, 'revenue_') ? 'revenue' : 'expense';

            return (object) [
                'statement_category' => $key,
                'statement_category_label' => $categoryLabels[$key] ?? $key,
                'category' => $category,
                'accounts_count' => $accounts->count(),
                'amount' => round($accounts->sum(fn (object $row) => (float) $row->amount), 2),
                'accounts' => $accounts,
            ];
        });

        return $rows
            ->filter(fn (object $row): bool => $row->accounts_count > 0 || abs((float) $row->amount) >= 0.005)
            ->values();
    }

    private function buildBalanceSheetRows(int $bookId, ?string $dateTo, string $display): Collection
    {
        $rows = DB::table('account_titles as at')
            ->leftJoin('journal_entry_lines as jel', 'jel.account_title_id', '=', 'at.id')
            ->leftJoin('journal_entries as je', function ($join) use ($bookId, $dateTo): void {
                $join->on('je.id', '=', 'jel.journal_entry_id')
                    ->where('je.book_id', '=', $bookId)
                    ->where('je.status', '=', 'posted');

                if (!empty($dateTo)) {
                    $join->whereDate('je.entry_date', '<=', $dateTo);
                }
            })
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['asset', 'liability', 'equity'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.sort_order',
            ])
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total")
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.sort_order'
            )
            ->orderBy('at.category')
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->get()
            ->map(function ($row): object {
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
                    'normal_balance' => $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'amount' => $amount,
                    'sort_order' => (int) $row->sort_order,
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildSummary(Collection $profitLossCategoryRows, Collection $balanceSheetRows): array
    {
        $revenueTotal = round(
            $profitLossCategoryRows
                ->where('category', 'revenue')
                ->sum(fn (object $row) => (float) $row->amount),
            2
        );

        $expenseTotal = round(
            $profitLossCategoryRows
                ->where('category', 'expense')
                ->sum(fn (object $row) => (float) $row->amount),
            2
        );

        $assetTotal = round(
            $balanceSheetRows
                ->where('category', 'asset')
                ->sum(fn (object $row) => (float) $row->amount),
            2
        );

        $liabilityTotal = round(
            $balanceSheetRows
                ->where('category', 'liability')
                ->sum(fn (object $row) => (float) $row->amount),
            2
        );

        $equityTotal = round(
            $balanceSheetRows
                ->where('category', 'equity')
                ->sum(fn (object $row) => (float) $row->amount),
            2
        );

        $incomeTotal = round($revenueTotal - $expenseTotal, 2);
        $liabilityEquityIncomeTotal = round($liabilityTotal + $equityTotal + $incomeTotal, 2);

        return [
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'income_total' => $incomeTotal,
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'liability_equity_income_total' => $liabilityEquityIncomeTotal,
            'balance_difference' => round($assetTotal - $liabilityEquityIncomeTotal, 2),
            'pl_category_count' => $profitLossCategoryRows->count(),
            'bs_account_count' => $balanceSheetRows->count(),
        ];
    }

    private function resolveRealEstateStatementCategory(string $category, string $accountName, ?string $configuredCategory): string
    {
        $configuredCategory = $configuredCategory ?: 'auto';

        if ($configuredCategory !== 'auto') {
            return $configuredCategory;
        }

        if ($category === 'revenue') {
            if ($this->containsAny($accountName, ['家賃', '賃料', '地代'])) {
                return 'revenue_rent';
            }

            if ($this->containsAny($accountName, ['共益', '管理費収入'])) {
                return 'revenue_common_service';
            }

            if ($this->containsAny($accountName, ['駐車', '車庫'])) {
                return 'revenue_parking';
            }

            if ($this->containsAny($accountName, ['礼金', '権利金', '更新料'])) {
                return 'revenue_key_money';
            }

            return 'revenue_other';
        }

        if ($category === 'expense') {
            if ($this->containsAny($accountName, ['租税', '固定資産税', '都市計画税', '印紙'])) {
                return 'expense_tax_dues';
            }

            if ($this->containsAny($accountName, ['保険'])) {
                return 'expense_insurance';
            }

            if ($this->containsAny($accountName, ['修繕', '修理'])) {
                return 'expense_repair';
            }

            if ($this->containsAny($accountName, ['減価償却'])) {
                return 'expense_depreciation';
            }

            if ($this->containsAny($accountName, ['支払利息', '借入金利子', '利息'])) {
                return 'expense_interest';
            }

            if ($this->containsAny($accountName, ['管理費', '管理委託'])) {
                return 'expense_management_fee';
            }

            if ($this->containsAny($accountName, ['手数料'])) {
                return 'expense_commission';
            }

            if ($this->containsAny($accountName, ['給料', '給与', '賃金'])) {
                return 'expense_salary';
            }

            if ($this->containsAny($accountName, ['水道', '光熱', '電気', 'ガス'])) {
                return 'expense_utilities';
            }

            return 'expense_other';
        }

        return 'none';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getClosingAdjustments(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = RealEstateClosingAdjustment::query()
            ->where('book_id', $bookId);

        empty($dateFrom)
            ? $query->whereNull('date_from')
            : $query->whereDate('date_from', $dateFrom);

        empty($dateTo)
            ? $query->whereNull('date_to')
            : $query->whereDate('date_to', $dateTo);

        return $query
            ->get()
            ->keyBy('account_title_id');
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