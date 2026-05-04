<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\RealEstateClosingAdjustment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WhiteReturnStatementPreviewController extends Controller
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

        $accountRows = collect();
        $categoryRows = collect();
        $incomeRows = collect();
        $expenseRows = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;

            $accountRows = $this->buildAccountRows($bookId, $dateFrom, $dateTo, $display);
            $categoryRows = $this->buildCategoryRows($accountRows);
            $incomeRows = $categoryRows->where('category', 'revenue')->values();
            $expenseRows = $categoryRows->where('category', 'expense')->values();
        }

        return view('reports.white_return_statement_previews.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'accountRows' => $accountRows,
            'categoryRows' => $categoryRows,
            'incomeRows' => $incomeRows,
            'expenseRows' => $expenseRows,
            'summary' => $this->buildSummary($categoryRows, $accountRows),
        ]);
    }

    private function buildAccountRows(int $bookId, ?string $dateFrom, ?string $dateTo, string $display): Collection
    {
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
                $accountingAmount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $statementCategory = $this->resolveRealEstateStatementCategory(
                    (string) $row->category,
                    (string) $row->account_name,
                    $row->real_estate_statement_category
                );

                $closingAdjustment = $closingAdjustments->get((int) $row->account_title_id);
                $adjustmentAmount = round((float) ($closingAdjustment?->adjustment_amount ?? 0), 2);
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round($accountingAmount + $adjustmentAmount, 2);

                return (object) [
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'normal_balance' => $row->normal_balance,
                    'statement_category' => $statementCategory,
                    'statement_category_label' => AccountTitle::REAL_ESTATE_STATEMENT_CATEGORIES[$statementCategory] ?? $statementCategory,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'adjustment_reason' => $closingAdjustment?->reason,
                    'filing_amount' => $filingAmount,
                    'needs_review' => $statementCategory === 'none' && abs($accountingAmount) >= 0.005,
                    'sort_order' => (int) $row->sort_order,
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->accounting_amount) >= 0.005 || abs((float) $row->adjustment_amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildCategoryRows(Collection $accountRows): Collection
    {
        return $accountRows
            ->groupBy('statement_category')
            ->map(function (Collection $rows, string $statementCategory): object {
                $first = $rows->first();
                $category = (string) ($first->category ?? '');
                $accountingAmount = round($rows->sum(fn (object $row) => (float) $row->accounting_amount), 2);
                $adjustmentAmount = round($rows->sum(fn (object $row) => (float) $row->adjustment_amount), 2);
                $filingAmount = $statementCategory === 'none'
                    ? 0.0
                    : round($rows->sum(fn (object $row) => (float) $row->filing_amount), 2);

                return (object) [
                    'statement_category' => $statementCategory,
                    'statement_category_label' => AccountTitle::REAL_ESTATE_STATEMENT_CATEGORIES[$statementCategory] ?? $statementCategory,
                    'category' => $category,
                    'accounts_count' => $rows->count(),
                    'accounting_amount' => $accountingAmount,
                    'adjustment_amount' => $adjustmentAmount,
                    'filing_amount' => $filingAmount,
                    'needs_review_count' => $rows->where('needs_review', true)->count(),
                    'accounts' => $rows->values(),
                ];
            })
            ->sortBy(function (object $row): string {
                $categoryOrder = $row->category === 'revenue' ? '1' : ($row->category === 'expense' ? '2' : '9');
                $statementOrder = array_search($row->statement_category, array_keys(AccountTitle::REAL_ESTATE_STATEMENT_CATEGORIES), true);

                return $categoryOrder . '|' . str_pad((string) ($statementOrder === false ? 999 : $statementOrder), 3, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    private function buildSummary(Collection $categoryRows, Collection $accountRows): array
    {
        $incomeTotal = round(
            $categoryRows
                ->where('category', 'revenue')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (object $row): float => (float) $row->filing_amount),
            2
        );

        $expenseTotal = round(
            $categoryRows
                ->where('category', 'expense')
                ->where('statement_category', '!=', 'none')
                ->sum(fn (object $row): float => (float) $row->filing_amount),
            2
        );

        return [
            'income_total' => $incomeTotal,
            'expense_total' => $expenseTotal,
            'profit_total' => round($incomeTotal - $expenseTotal, 2),
            'adjustment_total' => round($categoryRows->sum(fn (object $row): float => (float) $row->adjustment_amount), 2),
            'review_count' => $accountRows->where('needs_review', true)->count(),
            'category_count' => $categoryRows->count(),
            'account_count' => $accountRows->count(),
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