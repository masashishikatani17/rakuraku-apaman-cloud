<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MonthlyTrendReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'category' => ['nullable', 'in:all,revenue,expense'],
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

        $category = $validated['category'] ?? 'all';

        $months = $this->buildMonths($dateFrom, $dateTo);
        $accountMonthlyRows = collect();

        if ($selectedBook !== null && $months->isNotEmpty()) {
            $accountMonthlyRows = $this->buildAccountMonthlyRows(
                (int) $selectedBook->id,
                $dateFrom,
                $dateTo,
                $category
            );
        }

        $monthlySummaries = $this->buildMonthlySummaries($accountMonthlyRows, $months);

        return view('reports.monthly_trends.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'category' => $category,
            'months' => $months,
            'monthlySummaries' => $monthlySummaries,
            'accountMonthlyRows' => $accountMonthlyRows,
            'summary' => $this->buildSummary($accountMonthlyRows, $monthlySummaries),
        ]);
    }

    private function buildMonths(?string $dateFrom, ?string $dateTo): Collection
    {
        if (empty($dateFrom) || empty($dateTo)) {
            return collect();
        }

        $start = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $end = CarbonImmutable::parse($dateTo)->startOfMonth();

        if ($start->greaterThan($end)) {
            return collect();
        }

        $months = collect();
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            $months->push((object) [
                'year_month' => $cursor->format('Y-m'),
                'label' => $cursor->format('Y年n月'),
            ]);

            $cursor = $cursor->addMonth();
        }

        return $months;
    }

    private function buildAccountMonthlyRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $category
    ): Collection {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('at.book_id', $bookId)
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
            ])
            ->selectRaw("DATE_FORMAT(je.entry_date, '%Y-%m') as ym")
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->groupBy(
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.is_active',
                'at.sort_order',
                DB::raw("DATE_FORMAT(je.entry_date, '%Y-%m')")
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderByRaw("DATE_FORMAT(je.entry_date, '%Y-%m')");

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        if ($category !== 'all') {
            $query->where('at.category', $category);
        }

        return $query
            ->get()
            ->groupBy('account_title_id')
            ->map(function (Collection $rows) {
                $firstRow = $rows->first();

                $monthly = [];

                foreach ($rows as $row) {
                    $debitTotal = round((float) $row->debit_total, 2);
                    $creditTotal = round((float) $row->credit_total, 2);

                    $amount = $row->normal_balance === 'debit'
                        ? round($debitTotal - $creditTotal, 2)
                        : round($creditTotal - $debitTotal, 2);

                    $monthly[$row->ym] = [
                        'debit_total' => $debitTotal,
                        'credit_total' => $creditTotal,
                        'amount' => $amount,
                    ];
                }

                return (object) [
                    'account_title_id' => (int) $firstRow->account_title_id,
                    'account_code' => $firstRow->account_code,
                    'account_name' => $firstRow->account_name,
                    'category' => $firstRow->category,
                    'normal_balance' => $firstRow->normal_balance,
                    'is_active' => (bool) $firstRow->is_active,
                    'sort_order' => (int) $firstRow->sort_order,
                    'monthly' => $monthly,
                    'total_amount' => round(
                        collect($monthly)->sum(fn (array $month) => (float) $month['amount']),
                        2
                    ),
                ];
            })
            ->sortBy(fn ($row) => str_pad((string) $row->sort_order, 10, '0', STR_PAD_LEFT) . '|' . $row->account_code)
            ->values();
    }

    private function buildMonthlySummaries(Collection $accountMonthlyRows, Collection $months): Collection
    {
        return $months->map(function ($month) use ($accountMonthlyRows) {
            $revenueTotal = round(
                $accountMonthlyRows
                    ->where('category', 'revenue')
                    ->sum(fn ($row) => (float) ($row->monthly[$month->year_month]['amount'] ?? 0)),
                2
            );

            $expenseTotal = round(
                $accountMonthlyRows
                    ->where('category', 'expense')
                    ->sum(fn ($row) => (float) ($row->monthly[$month->year_month]['amount'] ?? 0)),
                2
            );

            return (object) [
                'year_month' => $month->year_month,
                'label' => $month->label,
                'revenue_total' => $revenueTotal,
                'expense_total' => $expenseTotal,
                'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
            ];
        });
    }

    private function buildSummary(Collection $accountMonthlyRows, Collection $monthlySummaries): array
    {
        $revenueTotal = round(
            $monthlySummaries->sum(fn ($summary) => (float) $summary->revenue_total),
            2
        );

        $expenseTotal = round(
            $monthlySummaries->sum(fn ($summary) => (float) $summary->expense_total),
            2
        );

        return [
            'months_count' => $monthlySummaries->count(),
            'accounts_count' => $accountMonthlyRows->count(),
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
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