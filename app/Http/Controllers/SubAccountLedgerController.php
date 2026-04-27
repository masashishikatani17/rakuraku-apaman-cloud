--- a/app/Http/Controllers/SubAccountLedgerController.php
 b/app/Http/Controllers/SubAccountLedgerController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\JournalEntryLine;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SubAccountLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:account_titles,id'],
            'sub_account_title_id' => ['nullable', 'integer', 'exists:sub_account_titles,id'],
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

        $selectedSubAccountTitleId = isset($validated['sub_account_title_id'])
            ? (int) $validated['sub_account_title_id']
            : null;

        $accountTitles = collect();
        $subAccountTitles = collect();
        $selectedAccountTitle = null;
        $selectedSubAccountTitle = null;
        $ledgerRows = collect();
        $summary = $this->emptySummary();

        if ($selectedBook !== null) {
            $accountTitles = $this->getAccountTitles((int) $selectedBook->id);

            if (
                $selectedAccountTitleId !== null
                && !$accountTitles->contains('id', $selectedAccountTitleId)
            ) {
                $selectedAccountTitleId = null;
            }

            $subAccountTitles = $this->getSubAccountTitles(
                (int) $selectedBook->id,
                $selectedAccountTitleId,
                $selectedSubAccountTitleId
            );

            if (
                $selectedSubAccountTitleId === null
                || !$subAccountTitles->contains('id', $selectedSubAccountTitleId)
            ) {
                $selectedSubAccountTitle = $subAccountTitles->first();
                $selectedSubAccountTitleId = $selectedSubAccountTitle?->id;
            } else {
                $selectedSubAccountTitle = $subAccountTitles->firstWhere('id', $selectedSubAccountTitleId);
            }

            if ($selectedSubAccountTitle !== null) {
                $selectedSubAccountTitle->load('accountTitle');
                $selectedAccountTitle = $selectedSubAccountTitle->accountTitle;
                $selectedAccountTitleId = (int) $selectedAccountTitle->id;

                $opening = $this->calculateOpeningBalance(
                    (int) $selectedBook->id,
                    (int) $selectedSubAccountTitle->id,
                    $dateFrom,
                    $selectedAccountTitle->normal_balance
                );

                $ledgerRows = $this->buildLedgerRows(
                    (int) $selectedBook->id,
                    (int) $selectedSubAccountTitle->id,
                    $dateFrom,
                    $dateTo,
                    $selectedAccountTitle->normal_balance,
                    (float) $opening['raw_balance']
                );

                $periodDebitTotal = round(
                    $ledgerRows->sum(fn ($row) => (float) $row->debit_amount),
                    2
                );

                $periodCreditTotal = round(
                    $ledgerRows->sum(fn ($row) => (float) $row->credit_amount),
                    2
                );

                $endingRawBalance = round(
                    (float) $opening['raw_balance']
                    + $ledgerRows->sum(fn ($row) => (float) $row->balance_delta_raw),
                    2
                );

                [$endingBalance, $endingBalanceSide] = $this->normalizeBalance(
                    $endingRawBalance,
                    $selectedAccountTitle->normal_balance
                );

                $summary = [
                    'entries_count' => $ledgerRows->count(),
                    'opening_balance' => $opening['balance'],
                    'opening_balance_side' => $opening['side'],
                    'period_debit_total' => $periodDebitTotal,
                    'period_credit_total' => $periodCreditTotal,
                    'ending_balance' => $endingBalance,
                    'ending_balance_side' => $endingBalanceSide,
                ];
            }
        }

        return view('sub_account_ledgers.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'accountTitles' => $accountTitles,
            'selectedAccountTitle' => $selectedAccountTitle,
            'selectedAccountTitleId' => $selectedAccountTitleId,
            'subAccountTitles' => $subAccountTitles,
            'selectedSubAccountTitle' => $selectedSubAccountTitle,
            'selectedSubAccountTitleId' => $selectedSubAccountTitleId,
            'ledgerRows' => $ledgerRows,
            'summary' => $summary,
        ]);
    }

    private function calculateOpeningBalance(
        int $bookId,
        int $subAccountTitleId,
        ?string $dateFrom,
        string $normalBalance
    ): array {
        if (empty($dateFrom)) {
            return [
                'debit_total' => 0.0,
                'credit_total' => 0.0,
                'balance' => 0.0,
                'side' => null,
                'raw_balance' => 0.0,
            ];
        }

        $opening = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.book_id', $bookId)
            ->where('je.status', 'posted')
            ->where('jel.sub_account_title_id', $subAccountTitleId)
            ->whereDate('je.entry_date', '<', $dateFrom)
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'debit' THEN jel.amount ELSE 0 END), 0) as debit_total"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jel.side = 'credit' THEN jel.amount ELSE 0 END), 0) as credit_total"
            )
            ->first();

        $debitTotal = round((float) ($opening->debit_total ?? 0), 2);
        $creditTotal = round((float) ($opening->credit_total ?? 0), 2);

        $rawBalance = $normalBalance === 'debit'
            ? $debitTotal - $creditTotal
            : $creditTotal - $debitTotal;

        [$balance, $side] = $this->normalizeBalance($rawBalance, $normalBalance);

        return [
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'balance' => $balance,
            'side' => $side,
            'raw_balance' => round($rawBalance, 2),
        ];
    }

    private function buildLedgerRows(
        int $bookId,
        int $subAccountTitleId,
        ?string $dateFrom,
        ?string $dateTo,
        string $normalBalance,
        float $openingRawBalance
    ): Collection {
        $linesQuery = JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->with([
                'journalEntry',
                'journalEntry.lines.accountTitle',
                'journalEntry.lines.subAccountTitle',
                'accountTitle',
                'subAccountTitle',
                'department',
            ])
            ->where('journal_entries.book_id', $bookId)
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.sub_account_title_id', $subAccountTitleId);

        if (!empty($dateFrom)) {
            $linesQuery->whereDate('journal_entries.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $linesQuery->whereDate('journal_entries.entry_date', '<=', $dateTo);
        }

        $lines = $linesQuery
            ->orderBy('journal_entries.entry_date')
            ->orderByRaw("COALESCE(journal_entries.voucher_no, '')")
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_no')
            ->get();

        $runningRawBalance = round($openingRawBalance, 2);

        return $lines->map(function (JournalEntryLine $line) use (&$runningRawBalance, $normalBalance) {
            $amount = round((float) $line->amount, 2);

            $balanceDeltaRaw = $line->side === $normalBalance
                ? $amount
                : -$amount;

            $runningRawBalance = round($runningRawBalance + $balanceDeltaRaw, 2);

            [$runningBalance, $runningBalanceSide] = $this->normalizeBalance(
                $runningRawBalance,
                $normalBalance
            );

            $counterpartLabels = $line->journalEntry?->lines
                ?->filter(fn (JournalEntryLine $relatedLine) => (int) $relatedLine->id !== (int) $line->id)
                ->map(function (JournalEntryLine $counterpartLine): string {
                    $label = trim(
                        ($counterpartLine->accountTitle?->account_code ?? '')
                        . ' '
                        . ($counterpartLine->accountTitle?->name ?? '科目不明')
                    );

                    if ($counterpartLine->subAccountTitle) {
                        $label .= ' / 補助: '
                            . $counterpartLine->subAccountTitle->sub_account_code
                            . ' '
                            . $counterpartLine->subAccountTitle->name;
                    }

                    return $label;
                })
                ->values()
                ?? collect();

            return (object) [
                'journal_entry_id' => (int) $line->journal_entry_id,
                'entry_date' => $line->journalEntry?->entry_date?->format('Y-m-d'),
                'voucher_no' => $line->journalEntry?->voucher_no,
                'entry_type' => $line->journalEntry?->entry_type,
                'description_text' => $line->journalEntry?->description_text,
                'account_code' => $line->accountTitle?->account_code,
                'account_name' => $line->accountTitle?->name,
                'department_code' => $line->department?->department_code,
                'department_name' => $line->department?->name,
                'counterpart_labels' => $counterpartLabels,
                'side' => $line->side,
                'debit_amount' => $line->side === 'debit' ? $amount : 0.0,
                'credit_amount' => $line->side === 'credit' ? $amount : 0.0,
                'amount' => $amount,
                'line_note' => $line->line_note,
                'balance_delta_raw' => $balanceDeltaRaw,
                'running_balance' => $runningBalance,
                'running_balance_side' => $runningBalanceSide,
            ];
        });
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

    private function emptySummary(): array
    {
        return [
            'entries_count' => 0,
            'opening_balance' => 0.0,
            'opening_balance_side' => null,
            'period_debit_total' => 0.0,
            'period_credit_total' => 0.0,
            'ending_balance' => 0.0,
            'ending_balance_side' => null,
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

    private function getSubAccountTitles(
        int $bookId,
        ?int $accountTitleId,
        ?int $selectedSubAccountTitleId = null
    ): Collection {
        $query = SubAccountTitle::query()
            ->with('accountTitle')
            ->whereHas('accountTitle', function ($query) use ($bookId): void {
                $query->where('book_id', $bookId);
            })
            ->orderBy('account_title_id')
            ->orderBy('sort_order')
            ->orderBy('sub_account_code')
            ->orderBy('id');

        if ($accountTitleId !== null) {
            $query->where('account_title_id', $accountTitleId);
        }

        $subAccountTitles = $query->get();

        if (
            $selectedSubAccountTitleId !== null
            && !$subAccountTitles->contains('id', $selectedSubAccountTitleId)
        ) {
            $selectedSubAccountTitle = SubAccountTitle::query()
                ->with('accountTitle')
                ->whereHas('accountTitle', function ($query) use ($bookId): void {
                    $query->where('book_id', $bookId);
                })
                ->find($selectedSubAccountTitleId);

            if ($selectedSubAccountTitle !== null) {
                $subAccountTitles = $subAccountTitles->prepend($selectedSubAccountTitle);
            }
        }

        return $subAccountTitles;
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