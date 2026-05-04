<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClosingNextYearRolloverController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'balancing_account_title_id' => ['nullable', 'integer', 'exists:account_titles,id'],
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

        $balanceRows = collect();
        $rolloverRows = collect();
        $profitLossSummary = $this->emptyProfitLossSummary();
        $accountTitles = collect();
        $selectedBalancingAccountTitleId = isset($validated['balancing_account_title_id'])
            ? (int) $validated['balancing_account_title_id']
            : null;
        $selectedBalancingAccountTitle = null;
        $nextPeriod = $this->buildNextPeriod($selectedBook, $dateTo);

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;

            $accountTitles = $this->getBalanceSheetAccountTitles($bookId);
            $selectedBalancingAccountTitle = $this->resolveBalancingAccountTitle(
                $accountTitles,
                $selectedBalancingAccountTitleId
            );
            $selectedBalancingAccountTitleId = $selectedBalancingAccountTitle?->id;

            $balanceRows = $this->buildBalanceRows($bookId, $dateTo, $display);
            $profitLossSummary = $this->buildProfitLossSummary($bookId, $dateFrom, $dateTo);
            $rolloverRows = $this->buildRolloverRows(
                $balanceRows,
                $profitLossSummary,
                $selectedBalancingAccountTitle
            );
        }

        return view('closing_next_year_rollovers.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'display' => $display,
            'accountTitles' => $accountTitles,
            'selectedBalancingAccountTitle' => $selectedBalancingAccountTitle,
            'selectedBalancingAccountTitleId' => $selectedBalancingAccountTitleId,
            'balanceRows' => $balanceRows,
            'rolloverRows' => $rolloverRows,
            'profitLossSummary' => $profitLossSummary,
            'nextPeriod' => $nextPeriod,
            'summary' => $this->buildSummary($balanceRows, $rolloverRows, $profitLossSummary),
        ]);
    }

    private function buildBalanceRows(int $bookId, ?string $dateTo, string $display): Collection
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
                $balanceAmount = $row->normal_balance === 'debit'
                    ? round($debitTotal - $creditTotal, 2)
                    : round($creditTotal - $debitTotal, 2);

                $openingSide = $balanceAmount >= 0
                    ? $row->normal_balance
                    : $this->oppositeSide((string) $row->normal_balance);

                return (object) [
                    'source_type' => 'balance',
                    'account_title_id' => (int) $row->account_title_id,
                    'account_code' => $row->account_code,
                    'account_name' => $row->account_name,
                    'category' => $row->category,
                    'normal_balance' => $row->normal_balance,
                    'debit_total' => $debitTotal,
                    'credit_total' => $creditTotal,
                    'balance_amount' => $balanceAmount,
                    'opening_side' => $openingSide,
                    'opening_amount' => round(abs($balanceAmount), 2),
                    'line_note' => '前期末残高の繰越',
                ];
            });

        if ($display === 'non_zero') {
            $rows = $rows
                ->filter(fn (object $row): bool => abs((float) $row->balance_amount) >= 0.005)
                ->values();
        }

        return $rows;
    }

    private function buildProfitLossSummary(int $bookId, ?string $dateFrom, ?string $dateTo): array
    {
        $query = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->join('account_titles as at', 'at.id', '=', 'jel.account_title_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->whereIn('at.category', ['revenue', 'expense'])
            ->select([
                'at.category',
                'at.normal_balance',
                'jel.side',
            ])
            ->selectRaw('COALESCE(SUM(jel.amount), 0) as amount_total')
            ->groupBy('at.category', 'at.normal_balance', 'jel.side');

        if (!empty($dateFrom)) {
            $query->whereDate('je.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('je.entry_date', '<=', $dateTo);
        }

        $revenueTotal = 0.0;
        $expenseTotal = 0.0;

        foreach ($query->get() as $row) {
            $signedAmount = $this->signedAmountByNormalBalance(
                (string) $row->normal_balance,
                (string) $row->side,
                (float) $row->amount_total
            );

            if ($row->category === 'revenue') {
                $revenueTotal += $signedAmount;
            }

            if ($row->category === 'expense') {
                $expenseTotal += $signedAmount;
            }
        }

        return [
            'revenue_total' => round($revenueTotal, 2),
            'expense_total' => round($expenseTotal, 2),
            'income_total' => round($revenueTotal - $expenseTotal, 2),
        ];
    }

    private function buildRolloverRows(
        Collection $balanceRows,
        array $profitLossSummary,
        ?AccountTitle $balancingAccountTitle
    ): Collection {
        $rows = $balanceRows
            ->filter(fn (object $row): bool => (float) $row->opening_amount > 0)
            ->map(fn (object $row): object => clone $row)
            ->values();

        $incomeTotal = round((float) ($profitLossSummary['income_total'] ?? 0), 2);

        if (abs($incomeTotal) >= 0.005 && $balancingAccountTitle !== null) {
            $rows->push((object) [
                'source_type' => 'current_income',
                'account_title_id' => (int) $balancingAccountTitle->id,
                'account_code' => $balancingAccountTitle->account_code,
                'account_name' => $balancingAccountTitle->name,
                'category' => $balancingAccountTitle->category,
                'normal_balance' => $balancingAccountTitle->normal_balance,
                'debit_total' => 0.0,
                'credit_total' => 0.0,
                'balance_amount' => $incomeTotal,
                'opening_side' => $incomeTotal >= 0 ? 'credit' : 'debit',
                'opening_amount' => round(abs($incomeTotal), 2),
                'line_note' => $incomeTotal >= 0 ? '当期所得の元入金繰入候補' : '当期損失の元入金調整候補',
            ]);
        }

        return $rows;
    }

    private function buildNextPeriod(?Book $book, ?string $dateTo): array
    {
        if (!empty($dateTo)) {
            $nextStart = CarbonImmutable::parse($dateTo)->addDay();
        } elseif ($book?->period_start_date !== null) {
            $nextStart = CarbonImmutable::parse($book->period_start_date)->addYear();
        } else {
            $nextStart = now()->addYear()->startOfYear();
        }

        $nextEnd = $nextStart->addYear()->subDay();

        return [
            'period_start_date' => $nextStart->format('Y-m-d'),
            'period_end_date' => $nextEnd->format('Y-m-d'),
            'book_code' => trim(($book?->book_code ?: 'BOOK') . '-' . $nextStart->format('Y')),
            'name' => trim(($book?->name ?: '翌期帳簿') . ' ' . $nextStart->format('Y') . '年度'),
        ];
    }

    private function buildSummary(Collection $balanceRows, Collection $rolloverRows, array $profitLossSummary): array
    {
        $assetTotal = round($balanceRows->where('category', 'asset')->sum(fn (object $row): float => (float) $row->balance_amount), 2);
        $liabilityTotal = round($balanceRows->where('category', 'liability')->sum(fn (object $row): float => (float) $row->balance_amount), 2);
        $equityTotal = round($balanceRows->where('category', 'equity')->sum(fn (object $row): float => (float) $row->balance_amount), 2);
        $incomeTotal = round((float) ($profitLossSummary['income_total'] ?? 0), 2);
        $balanceDifference = round($assetTotal - ($liabilityTotal + $equityTotal + $incomeTotal), 2);
        $rolloverDebitTotal = round($rolloverRows->where('opening_side', 'debit')->sum(fn (object $row): float => (float) $row->opening_amount), 2);
        $rolloverCreditTotal = round($rolloverRows->where('opening_side', 'credit')->sum(fn (object $row): float => (float) $row->opening_amount), 2);

        return [
            'balance_rows_count' => $balanceRows->count(),
            'rollover_rows_count' => $rolloverRows->count(),
            'asset_total' => $assetTotal,
            'liability_total' => $liabilityTotal,
            'equity_total' => $equityTotal,
            'income_total' => $incomeTotal,
            'balance_difference' => $balanceDifference,
            'rollover_debit_total' => $rolloverDebitTotal,
            'rollover_credit_total' => $rolloverCreditTotal,
            'rollover_difference' => round($rolloverDebitTotal - $rolloverCreditTotal, 2),
        ];
    }

    private function emptyProfitLossSummary(): array
    {
        return [
            'revenue_total' => 0.0,
            'expense_total' => 0.0,
            'income_total' => 0.0,
        ];
    }

    private function getBalanceSheetAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->whereIn('category', ['asset', 'liability', 'equity'])
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();
    }

    private function resolveBalancingAccountTitle(Collection $accountTitles, ?int $selectedAccountTitleId): ?AccountTitle
    {
        if ($selectedAccountTitleId !== null) {
            $selected = $accountTitles->firstWhere('id', $selectedAccountTitleId);

            if ($selected !== null) {
                return $selected;
            }
        }

        foreach (['元入', '元本', '事業主借', '事業主'] as $keyword) {
            $matched = $accountTitles->first(function (AccountTitle $accountTitle) use ($keyword): bool {
                return $accountTitle->category === 'equity'
                    && mb_strpos($accountTitle->name, $keyword) !== false;
            });

            if ($matched !== null) {
                return $matched;
            }
        }

        return $accountTitles->firstWhere('category', 'equity')
            ?? $accountTitles->firstWhere('normal_balance', 'credit')
            ?? $accountTitles->first();
    }

    private function signedAmountByNormalBalance(string $normalBalance, string $side, float $amount): float
    {
        if ($normalBalance === 'debit') {
            return $side === 'debit' ? $amount : -$amount;
        }

        return $side === 'credit' ? $amount : -$amount;
    }

    private function oppositeSide(string $side): string
    {
        return $side === 'debit' ? 'credit' : 'debit';
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