--- a/app/Http/Controllers/JournalDiaryController.php
 b/app/Http/Controllers/JournalDiaryController.php
@@
<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\JournalEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class JournalDiaryController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,draft,posted'],
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

        $status = $validated['status'] ?? 'all';

        $journalEntries = collect();

        if ($selectedBook !== null) {
            $journalEntriesQuery = JournalEntry::query()
                ->with([
                    'book.businessOwner',
                    'journalDescription',
                    'lines' => function ($query): void {
                        $query
                            ->with(['accountTitle', 'subAccountTitle', 'department'])
                            ->orderBy('line_no');
                    },
                ])
                ->where('book_id', $selectedBook->id)
                ->orderBy('entry_date')
                ->orderByRaw("COALESCE(voucher_no, '')")
                ->orderBy('id');

            if (!empty($dateFrom)) {
                $journalEntriesQuery->whereDate('entry_date', '>=', $dateFrom);
            }

            if (!empty($dateTo)) {
                $journalEntriesQuery->whereDate('entry_date', '<=', $dateTo);
            }

            if ($status !== 'all') {
                $journalEntriesQuery->where('status', $status);
            }

            $journalEntries = $journalEntriesQuery->get();
        }

        return view('journal_diaries.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'journalEntries' => $journalEntries,
            'summary' => $this->buildSummary($journalEntries),
        ]);
    }

    private function buildSummary(Collection $journalEntries): array
    {
        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($journalEntries as $journalEntry) {
            foreach ($journalEntry->lines as $line) {
                if ($line->side === 'debit') {
                    $debitTotal += (float) $line->amount;
                }

                if ($line->side === 'credit') {
                    $creditTotal += (float) $line->amount;
                }
            }
        }

        $debitTotal = round($debitTotal, 2);
        $creditTotal = round($creditTotal, 2);

        return [
            'entries_count' => $journalEntries->count(),
            'debit_total' => $debitTotal,
            'credit_total' => $creditTotal,
            'difference' => round($debitTotal - $creditTotal, 2),
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