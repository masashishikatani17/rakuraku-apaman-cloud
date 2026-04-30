<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\JournalEntryLine;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PropertyJournalAllocationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'property_status' => ['nullable', 'in:unassigned,assigned,all'],
            'category' => ['nullable', 'in:all,revenue,expense'],
            'property_id' => ['nullable', 'integer', 'exists:properties,id'],
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

        $propertyStatus = $validated['property_status'] ?? 'unassigned';
        $category = $validated['category'] ?? 'all';
        $selectedPropertyId = isset($validated['property_id'])
            ? (int) $validated['property_id']
            : null;

        $isReady = Schema::hasColumn('journal_entry_lines', 'property_id');
        $properties = collect();
        $journalLineRows = collect();

        if ($selectedBook !== null) {
            $properties = Property::query()
                ->where('book_id', $selectedBook->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('property_code')
                ->get();

            if ($isReady) {
                $journalLineRows = $this->buildJournalLineRows(
                    (int) $selectedBook->id,
                    $dateFrom,
                    $dateTo,
                    $propertyStatus,
                    $category,
                    $selectedPropertyId
                );
            }
        }

        return view('property_journal_allocations.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'propertyStatus' => $propertyStatus,
            'category' => $category,
            'selectedPropertyId' => $selectedPropertyId,
            'properties' => $properties,
            'journalLineRows' => $journalLineRows,
            'summary' => $this->buildSummary($journalLineRows),
            'isReady' => $isReady,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'property_status' => ['nullable', 'in:unassigned,assigned,all'],
            'category' => ['nullable', 'in:all,revenue,expense'],
            'filter_property_id' => ['nullable', 'integer', 'exists:properties,id'],
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('is_active', true)
                ),
            ],
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['required', 'integer', 'exists:journal_entry_lines,id'],
        ]);

        if (! Schema::hasColumn('journal_entry_lines', 'property_id')) {
            return redirect()
                ->route('property-journal-allocations.index', $this->redirectParams($validated))
                ->with('error', 'journal_entry_lines.property_id がまだありません。先にmigrationを実行してください。');
        }

        $bookId = (int) $validated['book_id'];
        $propertyId = isset($validated['property_id'])
            ? (int) $validated['property_id']
            : null;

        $targetLines = JournalEntryLine::query()
            ->whereIn('id', $validated['line_ids'])
            ->whereHas('journalEntry', function ($query) use ($bookId): void {
                $query
                    ->where('book_id', $bookId)
                    ->where('status', 'posted')
                    ->whereNotIn('entry_type', ['rental_payment', 'depreciation', 'loan_repayment']);
            })
            ->whereHas('accountTitle', function ($query) use ($bookId): void {
                $query
                    ->where('book_id', $bookId)
                    ->whereIn('category', ['revenue', 'expense']);
            })
            ->get();

        $targetLines->each(function (JournalEntryLine $line) use ($propertyId): void {
            $line->forceFill([
                'property_id' => $propertyId,
            ])->save();
        });

        $message = $propertyId === null
            ? $targetLines->count() . ' 行の物件紐づけを解除しました。'
            : $targetLines->count() . ' 行へ物件を紐づけました。';

        return redirect()
            ->route('property-journal-allocations.index', $this->redirectParams($validated))
            ->with('status', $message);
    }

    private function redirectParams(array $validated): array
    {
        return array_filter([
            'book_id' => $validated['book_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'property_status' => $validated['property_status'] ?? null,
            'category' => $validated['category'] ?? null,
            'property_id' => $validated['filter_property_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
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

    private function buildJournalLineRows(
        int $bookId,
        ?string $dateFrom,
        ?string $dateTo,
        string $propertyStatus,
        string $category,
        ?int $propertyId
    ): Collection {
        $query = JournalEntryLine::query()
            ->with([
                'journalEntry',
                'accountTitle',
                'subAccountTitle',
                'department',
                'property',
            ])
            ->whereHas('journalEntry', function ($query) use ($bookId, $dateFrom, $dateTo): void {
                $query
                    ->where('book_id', $bookId)
                    ->where('status', 'posted')
                    ->whereNotIn('entry_type', ['rental_payment', 'depreciation', 'loan_repayment']);

                if (!empty($dateFrom)) {
                    $query->whereDate('entry_date', '>=', $dateFrom);
                }

                if (!empty($dateTo)) {
                    $query->whereDate('entry_date', '<=', $dateTo);
                }
            })
            ->whereHas('accountTitle', function ($query) use ($bookId, $category): void {
                $query
                    ->where('book_id', $bookId)
                    ->whereIn('category', ['revenue', 'expense']);

                if ($category !== 'all') {
                    $query->where('category', $category);
                }
            });

        if ($propertyStatus === 'unassigned') {
            $query->whereNull('property_id');
        }

        if ($propertyStatus === 'assigned') {
            $query->whereNotNull('property_id');
        }

        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        return $query
            ->get()
            ->sortBy(function (JournalEntryLine $line): string {
                return ($line->journalEntry?->entry_date?->format('Y-m-d') ?? '')
                    . '|'
                    . str_pad((string) ($line->journalEntry?->id ?? 0), 10, '0', STR_PAD_LEFT)
                    . '|'
                    . str_pad((string) $line->line_no, 4, '0', STR_PAD_LEFT);
            })
            ->values()
            ->map(function (JournalEntryLine $line): object {
                $accountTitle = $line->accountTitle;
                $journalEntry = $line->journalEntry;
                $amount = round((float) $line->amount, 2);

                $profitLossAmount = 0.0;
                if ($accountTitle?->category === 'revenue') {
                    $profitLossAmount = $line->side === 'credit' ? $amount : -$amount;
                }

                if ($accountTitle?->category === 'expense') {
                    $profitLossAmount = $line->side === 'debit' ? -$amount : $amount;
                }

                return (object) [
                    'line_id' => (int) $line->id,
                    'journal_entry_id' => (int) $line->journal_entry_id,
                    'entry_date' => $journalEntry?->entry_date?->format('Y-m-d'),
                    'voucher_no' => $journalEntry?->voucher_no,
                    'description_text' => $journalEntry?->description_text,
                    'entry_type' => $journalEntry?->entry_type,
                    'line_no' => (int) $line->line_no,
                    'side' => $line->side,
                    'account_code' => $accountTitle?->account_code,
                    'account_name' => $accountTitle?->name,
                    'category' => $accountTitle?->category,
                    'sub_account_code' => $line->subAccountTitle?->sub_account_code,
                    'sub_account_name' => $line->subAccountTitle?->name,
                    'department_code' => $line->department?->department_code,
                    'department_name' => $line->department?->name,
                    'property_id' => $line->property_id,
                    'property_code' => $line->property?->property_code,
                    'property_name' => $line->property?->name,
                    'amount' => $amount,
                    'profit_loss_amount' => round($profitLossAmount, 2),
                    'line_note' => $line->line_note,
                ];
            });
    }

    private function buildSummary(Collection $journalLineRows): array
    {
        $revenueRows = $journalLineRows->where('category', 'revenue');
        $expenseRows = $journalLineRows->where('category', 'expense');
        $assignedRows = $journalLineRows->filter(fn ($row) => $row->property_id !== null);
        $unassignedRows = $journalLineRows->filter(fn ($row) => $row->property_id === null);

        return [
            'rows_count' => $journalLineRows->count(),
            'assigned_count' => $assignedRows->count(),
            'unassigned_count' => $unassignedRows->count(),
            'revenue_amount_total' => round($revenueRows->sum(fn ($row) => (float) $row->amount), 2),
            'expense_amount_total' => round($expenseRows->sum(fn ($row) => (float) $row->amount), 2),
            'profit_loss_amount_total' => round($journalLineRows->sum(fn ($row) => (float) $row->profit_loss_amount), 2),
        ];
    }
}