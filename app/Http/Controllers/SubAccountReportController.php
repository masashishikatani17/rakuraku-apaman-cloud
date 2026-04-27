<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubAccountReportController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:account_titles,id'],
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
                ->with('businessOwner')
                ->find($selectedBookId);
        }

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $selectedAccountTitleId = isset($validated['account_title_id'])
            ? (int) $validated['account_title_id']
            : null;

        $accountTitles = collect();
        $selectedAccountTitle = null;
        $subAccountRows = collect();

        if ($selectedBook !== null) {
            $accountTitles = $this->getAccountTitles((int) $selectedBook->id);

            if (
                $selectedAccountTitleId !== null
                && !$accountTitles->contains('id', $selectedAccountTitleId)
            ) {
                $selectedAccountTitleId = null;
            }

            $selectedAccountTitle = $selectedAccountTitleId !== null
                ? $accountTitles->firstWhere('id', $selectedAccountTitleId)
                : null;

            $subAccountRows = $this->buildSubAccountRows(
                (int) $selectedBook->id,
                $selectedAccountTitleId,
                $dateFrom,
                $dateTo
            );
        }

        return view('reports.sub_accounts.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'accountTitles' => $accountTitles,
            'selectedAccountTitle' => $selectedAccountTitle,
            'selectedAccountTitleId' => $selectedAccountTitleId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'subAccountRows' => $subAccountRows,
            'summary' => $this->buildSummary($subAccountRows),
        ]);
    }

    private function buildSubAccountRows(
        int $bookId,
        ?int $accountTitleId,
        ?string $dateFrom,
        ?string $dateTo
    ): Collection {
        $query = DB::table('sub_account_titles as sat')
            ->join('account_titles as at', 'at.id', '=', 'sat.account_title_id')
            ->leftJoin('journal_entry_lines as jel', 'jel.sub_account_title_id', '=', 'sat.id')
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
                'sat.id as sub_account_title_id',
                'sat.sub_account_code',
                'sat.name as sub_account_name',
                'sat.is_active as sub_account_is_active',
                'sat.sort_order as sub_account_sort_order',
                'at.id as account_title_id',
                'at.account_code',
                'at.name as account_name',
                'at.category',
                'at.normal_balance',
                'at.sort_order as account_sort_order',
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN je.id IS NOT NULL AND jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->groupBy(
                'sat.id',
                'sat.sub_account_code',
                'sat.name',
                'sat.is_active',
                'sat.sort_order',
                'at.id',
                'at.account_code',
                'at.name',
                'at.category',
                'at.normal_balance',
                'at.sort_order'
            )
            ->orderBy('at.sort_order')
            ->orderBy('at.account_code')
            ->orderBy('sat.sort_order')
            ->orderBy('sat.sub_account_code');

        if ($accountTitleId !== null) {
            $query->where('at.id', $accountTitleId);
        }

        return $query->get()->map(function ($row) {
            $debitTotal = round((float) $row->debit_total, 2);
            $creditTotal = round((float) $row->credit_total, 2);

            $rawBalance = $row->normal_balance === 'debit'
                ? $debitTotal - $creditTotal
                : $creditTotal - $debitTotal;

            [$endingBalance, $endingBalanceSide] = $this->normalizeBalance(
                $rawBalance,
                $row->normal_balance
            );

            return (object) [
                'sub_account_title_id' => (int) $row->sub_account_title_id,
                'sub_account_code' => $row->sub_account_code,
                'sub_account_name' => $row->sub_account_name,
                'sub_account_is_active' => (bool) $row->sub_account_is_active,
                'account_title_id' => (int) $row->account_title_id,
                'account_code' => $row->account_code,
                'account_name' => $row->account_name,
                'category' => $row->category,
                'normal_balance' => $row->normal_balance,
                'debit_total' => $debitTotal,
                'credit_total' => $creditTotal,
                'ending_balance' => $endingBalance,
                'ending_balance_side' => $endingBalanceSide,
            ];
        });
    }

    private function buildSummary(Collection $subAccountRows): array
    {
        $debitTotal = round($subAccountRows->sum(fn ($row) => (float) $row->debit_total), 2);
        $creditTotal = round($subAccountRows->sum(fn ($row) => (float) $row->credit_total), 2);

        return [
            'sub_accounts_count' => $subAccountRows->count(),
            'accounts_count' => $subAccountRows
                ->pluck('account_title_id')
                ->unique()
                ->count(),
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'difference' => round($debitTotal - $creditTotal, 2),
        ];
    }

    private function normalizeBalance(float $rawBalance, string $normalBalance): array
    {
        $balance = round(abs($rawBalance), 2);

        if ($balance < 0.005) {
            return [0.0, null];
        }

        if ($rawBalance > 0) {
            return [$balance, $normalBalance];
        }

        return [
            $balance,
            $normalBalance === 'debit' ? 'credit' : 'debit',
        ];
    }

    private function getAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->get();
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