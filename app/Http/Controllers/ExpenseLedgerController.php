<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\Department;
use App\Models\JournalEntryLine;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExpenseLedgerController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'account_title_id' => ['nullable', 'integer', 'exists:account_titles,id'],
            'sub_account_title_id' => ['nullable', 'integer', 'exists:sub_account_titles,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
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

        $selectedDepartmentId = isset($validated['department_id'])
            ? (int) $validated['department_id']
            : null;

        $expenseAccountTitles = collect();
        $subAccountTitles = collect();
        $departments = collect();
        $ledgerRows = collect();

        $selectedAccountTitle = null;
        $selectedSubAccountTitle = null;
        $selectedDepartment = null;

        if ($selectedBook !== null) {
            $expenseAccountTitles = $this->getExpenseAccountTitles((int) $selectedBook->id);
            $departments = $this->getDepartments((int) $selectedBook->id);

            if (
                $selectedAccountTitleId !== null
                && !$expenseAccountTitles->contains('id', $selectedAccountTitleId)
            ) {
                $selectedAccountTitleId = null;
            }

            $selectedAccountTitle = $selectedAccountTitleId !== null
                ? $expenseAccountTitles->firstWhere('id', $selectedAccountTitleId)
                : null;

            if ($selectedAccountTitle !== null) {
                $subAccountTitles = $this->getSubAccountTitles((int) $selectedAccountTitle->id);

                if (
                    $selectedSubAccountTitleId !== null
                    && !$subAccountTitles->contains('id', $selectedSubAccountTitleId)
                ) {
                    $selectedSubAccountTitleId = null;
                }

                $selectedSubAccountTitle = $selectedSubAccountTitleId !== null
                    ? $subAccountTitles->firstWhere('id', $selectedSubAccountTitleId)
                    : null;
            } else {
                $selectedSubAccountTitleId = null;
            }

            if (
                $selectedDepartmentId !== null
                && !$departments->contains('id', $selectedDepartmentId)
            ) {
                $selectedDepartmentId = null;
            }

            $selectedDepartment = $selectedDepartmentId !== null
                ? $departments->firstWhere('id', $selectedDepartmentId)
                : null;

            $ledgerRows = $this->buildLedgerRows(
                (int) $selectedBook->id,
                $selectedAccountTitleId,
                $selectedSubAccountTitleId,
                $selectedDepartmentId,
                $dateFrom,
                $dateTo
            );
        }

        return view('expense_ledgers.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'expenseAccountTitles' => $expenseAccountTitles,
            'selectedAccountTitle' => $selectedAccountTitle,
            'selectedAccountTitleId' => $selectedAccountTitleId,
            'subAccountTitles' => $subAccountTitles,
            'selectedSubAccountTitle' => $selectedSubAccountTitle,
            'selectedSubAccountTitleId' => $selectedSubAccountTitleId,
            'departments' => $departments,
            'selectedDepartment' => $selectedDepartment,
            'selectedDepartmentId' => $selectedDepartmentId,
            'ledgerRows' => $ledgerRows,
            'accountSummaries' => $this->buildAccountSummaries($ledgerRows),
            'summary' => $this->buildSummary($ledgerRows),
        ]);
    }

    private function buildLedgerRows(
        int $bookId,
        ?int $accountTitleId,
        ?int $subAccountTitleId,
        ?int $departmentId,
        ?string $dateFrom,
        ?string $dateTo
    ): Collection {
        $query = JournalEntryLine::query()
            ->select('journal_entry_lines.*')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('account_titles', 'account_titles.id', '=', 'journal_entry_lines.account_title_id')
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
            ->where('account_titles.book_id', $bookId)
            ->where('account_titles.category', 'expense');

        if ($accountTitleId !== null) {
            $query->where('journal_entry_lines.account_title_id', $accountTitleId);
        }

        if ($subAccountTitleId !== null) {
            $query->where('journal_entry_lines.sub_account_title_id', $subAccountTitleId);
        }

        if ($departmentId !== null) {
            $query->where('journal_entry_lines.department_id', $departmentId);
        }

        if (!empty($dateFrom)) {
            $query->whereDate('journal_entries.entry_date', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('journal_entries.entry_date', '<=', $dateTo);
        }

        $lines = $query
            ->orderBy('account_titles.sort_order')
            ->orderBy('account_titles.account_code')
            ->orderBy('journal_entries.entry_date')
            ->orderByRaw("COALESCE(journal_entries.voucher_no, '')")
            ->orderBy('journal_entries.id')
            ->orderBy('journal_entry_lines.line_no')
            ->get();

        return $lines->map(function (JournalEntryLine $line) {
            $amount = round((float) $line->amount, 2);

            $expenseAmount = $line->side === 'debit' ? $amount : 0.0;
            $reversalAmount = $line->side === 'credit' ? $amount : 0.0;
            $netAmount = round($expenseAmount - $reversalAmount, 2);

            $counterpartLines = $line->journalEntry?->lines
                ?->filter(fn (JournalEntryLine $relatedLine) => (int) $relatedLine->id !== (int) $line->id)
                ->values()
                ?? collect();

            $counterpartLabels = $counterpartLines->map(function (JournalEntryLine $counterpartLine): string {
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
            });

            return (object) [
                'journal_entry_id' => (int) $line->journal_entry_id,
                'journal_entry_line_id' => (int) $line->id,
                'entry_date' => $line->journalEntry?->entry_date?->format('Y-m-d'),
                'voucher_no' => $line->journalEntry?->voucher_no,
                'entry_type' => $line->journalEntry?->entry_type,
                'description_text' => $line->journalEntry?->description_text,
                'account_title_id' => (int) $line->account_title_id,
                'account_code' => $line->accountTitle?->account_code,
                'account_name' => $line->accountTitle?->name,
                'sub_account_code' => $line->subAccountTitle?->sub_account_code,
                'sub_account_name' => $line->subAccountTitle?->name,
                'department_code' => $line->department?->department_code,
                'department_name' => $line->department?->name,
                'counterpart_labels' => $counterpartLabels,
                'side' => $line->side,
                'expense_amount' => $expenseAmount,
                'reversal_amount' => $reversalAmount,
                'net_amount' => $netAmount,
                'line_note' => $line->line_note,
            ];
        });
    }

    private function buildSummary(Collection $ledgerRows): array
    {
        $expenseTotal = round($ledgerRows->sum(fn ($row) => (float) $row->expense_amount), 2);
        $reversalTotal = round($ledgerRows->sum(fn ($row) => (float) $row->reversal_amount), 2);

        return [
            'rows_count' => $ledgerRows->count(),
            'accounts_count' => $ledgerRows
                ->pluck('account_title_id')
                ->unique()
                ->count(),
            'expense_total' => $expenseTotal,
            'reversal_total' => $reversalTotal,
            'net_expense_total' => round($expenseTotal - $reversalTotal, 2),
        ];
    }

    private function buildAccountSummaries(Collection $ledgerRows): Collection
    {
        return $ledgerRows
            ->groupBy('account_title_id')
            ->map(function (Collection $rows) {
                $firstRow = $rows->first();
                $expenseTotal = round($rows->sum(fn ($row) => (float) $row->expense_amount), 2);
                $reversalTotal = round($rows->sum(fn ($row) => (float) $row->reversal_amount), 2);

                return (object) [
                    'account_title_id' => $firstRow->account_title_id,
                    'account_code' => $firstRow->account_code,
                    'account_name' => $firstRow->account_name,
                    'rows_count' => $rows->count(),
                    'expense_total' => $expenseTotal,
                    'reversal_total' => $reversalTotal,
                    'net_expense_total' => round($expenseTotal - $reversalTotal, 2),
                ];
            })
            ->sortBy(fn ($row) => ($row->account_code ?? '') . '|' . ($row->account_name ?? ''))
            ->values();
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

    private function getExpenseAccountTitles(int $bookId): Collection
    {
        return AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'expense')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id')
            ->get();
    }

    private function getSubAccountTitles(int $accountTitleId): Collection
    {
        return SubAccountTitle::query()
            ->where('account_title_id', $accountTitleId)
            ->orderBy('sort_order')
            ->orderBy('sub_account_code')
            ->orderBy('id')
            ->get();
    }

    private function getDepartments(int $bookId): Collection
    {
        return Department::query()
            ->where('book_id', $bookId)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->orderBy('id')
            ->get();
    }
}