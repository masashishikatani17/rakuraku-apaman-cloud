<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceSheetReportController extends Controller
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

        $balanceSheetRows = collect();
        $profitLossSummary = $this->emptyProfitLossSummary();

        if ($selectedBook !== null) {
            $balanceSheetRows = $this->buildBalanceSheetRows(
                (int) $selectedBook->id,
                $dateTo,
                $display
            );

            $profitLossSummary = $this->buildProfitLossSummary(
                (int) $selectedBook->id,
                $dateFrom,
                $dateTo
            );
        }

        $summary = $this->buildSummary($balanceSheetRows, $profitLossSummary);

        return view('reports.balance_sheets.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'balanceSheetRows' => $balanceSheetRows,
            'assetRows' => $balanceSheetRows->where('category', 'asset')->values(),
            'liabilityRows' => $balanceSheetRows->where('category', 'liability')->values(),
            'equityRows' => $balanceSheetRows->where('category', 'equity')->values(),
            'profitLossSummary' => $profitLossSummary,
            'summary' => $summary,
        ]);
    }

    private function buildBalanceSheetRows(
        int $bookId,
        ?string $dateTo,
        string $display
    ): Collection {
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
                ->filter(function ($row): bool {
                    return abs((float) $row->debit_total) >= 0.005
                        || abs((float) $row->credit_total) >= 0.005
                        || abs((float) $row->amount) >= 0.005;
                })
                ->values();
        }

        return $rows;
    }

    private function buildProfitLossSummary(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo
    ): array {
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
                'at.category',
                'at.normal_balance',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->groupBy(
                'at.id',
                'at.category',
                'at.normal_balance'
            )
            ->get();

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($rows as $row) {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);

            $amount = $row->normal_balance === 'debit'
                ? round($debitTotal - $creditTotal, 2)
                : round($creditTotal - $debitTotal, 2);

            if ($row->category === 'revenue') {
                $revenueTotal += $amount;
            }

            if ($row->category === 'expense') {
                $expenseTotal += $amount;
            }
        }

        $revenueTotal = round($revenueTotal, 2);
        $expenseTotal = round($expenseTotal, 2);

        return [
            'revenue_total' => $revenueTotal,
            'expense_total' => $expenseTotal,
            'profit_loss_total' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    private function buildSummary(Collection $balanceSheetRows, array $profitLossSummary): array
    {
        $assetTotal = round(
            $balanceSheetRows
                ->where('category', 'asset')
                ->sum(fn ($row) => (float) $row->amount),
            2
        );

        $liabilityTotal = round(
            $balanceSheetRows
                ->where('category', 'liability')
                ->sum(fn ($row) => (float) $row->amount),
            2
        );

        $equityTotal = round(
            $balanceSheetRows
                ->where('category', 'equity')
                ->sum(fn ($row) => (float) $row->amount),
            2
        );

        $currentProfitLoss = round((float) $profitLossSummary['profit_loss_total'], 2);
        $netAssetsTotal = round($equityTotal + $currentProfitLoss, 2);
        $liabilityEquityTotal = round($liabilityTotal + $netAssetsTotal, 2);

        return [
            'rows_count' => $balanceSheetRows->count(),
            'asset_accounts_count' => $balanceSheetRows->where('category', 'asset')->count(),
            'liability_accounts_count' => $balanceSheetRows->where('category', 'liability')->count(),
            'equity_accounts_count' => $balanceSheetRows->where('category', 'equity')->count(),
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'current_profit_loss' => $currentProfitLoss,
            'net_assets_total' => $netAssetsTotal,
            'liability_equity_total' => $liabilityEquityTotal,
            'balance_difference' => round($assetTotal - $liabilityEquityTotal, 2),
        ];
    }

    private function emptyProfitLossSummary(): array
    {
        return [
            'revenue_total' => 0.0,
            'expense_total' => 0.0,
            'profit_loss_total' => 0.0,
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