<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrialBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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
                ->with(['businessOwner', 'setting'])
                ->find($selectedBookId);
        }

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $trialBalanceRows = collect();

        $summary = [
            'accounts_count' => 0,
            'total_debit' => 0.0,
            'total_credit' => 0.0,
            'difference' => 0.0,
        ];

        if ($selectedBook !== null) {
            $trialBalanceRows = $this->buildTrialBalanceRows(
                (int) $selectedBook->id,
                $dateFrom,
                $dateTo
            );

            $summary = [
                'accounts_count' => $trialBalanceRows->count(),
                'total_debit' => round(
                    $trialBalanceRows->sum(fn ($row) => (float) $row->debit_total),
                    2
                ),
                'total_credit' => round(
                    $trialBalanceRows->sum(fn ($row) => (float) $row->credit_total),
                    2
                ),
                'difference' => round(
                    $trialBalanceRows->sum(fn ($row) => (float) $row->debit_total)
                    - $trialBalanceRows->sum(fn ($row) => (float) $row->credit_total),
                    2
                ),
            ];
        }

        return view('trial_balances.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'trialBalanceRows' => $trialBalanceRows,
            'summary' => $summary,
        ]);
    }

    private function getSelectableBooks(?int $selectedBookId = null): Collection
    {
        $books = Book::query()
            ->with(['businessOwner', 'setting'])
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        if ($selectedBookId !== null && !$books->contains('id', $selectedBookId)) {
            $selectedBook = Book::query()
                ->with(['businessOwner', 'setting'])
                ->find($selectedBookId);

            if ($selectedBook !== null) {
                $books = $books->prepend($selectedBook);
            }
        }

        return $books;
    }

    private function buildTrialBalanceRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo
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
            ->get();

        return $rows->map(function ($row) {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);

            $rawEndingBalance = $row->normal_balance === 'debit'
                ? $debitTotal - $creditTotal
                : $creditTotal - $debitTotal;

            $endingBalance = round(abs($rawEndingBalance), 2);

            $endingBalanceSide = null;

            if ($endingBalance > 0) {
                if ($rawEndingBalance > 0) {
                    $endingBalanceSide = $row->normal_balance;
                } else {
                    $endingBalanceSide = $row->normal_balance === 'debit'
                        ? 'credit'
                        : 'debit';
                }
            }

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
                'ending_balance' => $endingBalance,
                'ending_balance_side' => $endingBalanceSide,
            ];
        });
    }
}