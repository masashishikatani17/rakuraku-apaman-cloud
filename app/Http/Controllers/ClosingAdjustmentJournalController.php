<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\Department;
use App\Models\JournalDescription;
use App\Models\JournalEntry;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClosingAdjustmentJournalController extends Controller
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
                ->with('businessOwner')
                ->find($selectedBookId);
        }

        $dateFrom = $validated['date_from']
            ?? $selectedBook?->period_start_date?->format('Y-m-d');

        $dateTo = $validated['date_to']
            ?? $selectedBook?->period_end_date?->format('Y-m-d');

        $closingAdjustmentJournals = collect();

        if ($selectedBook !== null) {
            $closingAdjustmentJournalsQuery = JournalEntry::query()
                ->with([
                    'book.businessOwner',
                    'journalDescription',
                    'debitLines.accountTitle',
                    'debitLines.subAccountTitle',
                    'debitLines.department',
                    'creditLines.accountTitle',
                    'creditLines.subAccountTitle',
                    'creditLines.department',
                ])
                ->where('book_id', $selectedBook->id)
                ->where('entry_type', 'closing')
                ->orderByDesc('entry_date')
                ->orderByDesc('id');

            if (!empty($dateFrom)) {
                $closingAdjustmentJournalsQuery->whereDate('entry_date', '>=', $dateFrom);
            }

            if (!empty($dateTo)) {
                $closingAdjustmentJournalsQuery->whereDate('entry_date', '<=', $dateTo);
            }

            $closingAdjustmentJournals = $closingAdjustmentJournalsQuery->get();
        }

        return view('closing_adjustment_journals.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'closingAdjustmentJournals' => $closingAdjustmentJournals,
            'summary' => $this->buildSummary($closingAdjustmentJournals),
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
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

        $formData = $selectedBookId !== null
            ? $this->loadFormMasterData($selectedBookId)
            : $this->emptyFormMasterData();

        return view('closing_adjustment_journals.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'journalEntry' => null,
            'debitLine' => null,
            'creditLine' => null,
            'defaultEntryDate' => $selectedBook?->period_end_date?->format('Y-m-d'),
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validateJournalEntryPayload($request, $bookId);
        $this->resolveDescriptionText($validated, $bookId);
        $this->validateLineConsistency($validated, $bookId);

        DB::transaction(function () use ($validated, $bookId): void {
            $this->saveClosingAdjustmentJournal(new JournalEntry(), $validated, $bookId);
        });

        return redirect()
            ->route('closing-adjustment-journals.index', [
                'book_id' => $bookId,
                'date_from' => $validated['entry_date'],
                'date_to' => $validated['entry_date'],
            ])
            ->with('status', '決算整理仕訳を登録しました。');
    }

    public function edit(JournalEntry $journalEntry): View
    {
        $this->ensureClosingAdjustmentJournal($journalEntry);

        $journalEntry->load(['book.businessOwner', 'journalDescription', 'lines']);

        $selectedBookId = (int) $journalEntry->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $formData = $this->loadFormMasterData($selectedBookId);

        $debitLine = $journalEntry->lines->firstWhere('side', 'debit');
        $creditLine = $journalEntry->lines->firstWhere('side', 'credit');

        return view('closing_adjustment_journals.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'journalEntry' => $journalEntry,
            'debitLine' => $debitLine,
            'creditLine' => $creditLine,
            'defaultEntryDate' => $journalEntry->entry_date?->format('Y-m-d'),
        ], $formData));
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $this->ensureClosingAdjustmentJournal($journalEntry);

        $bookId = (int) $journalEntry->book_id;

        $validated = $this->validateJournalEntryPayload($request, $bookId, $journalEntry);
        $this->resolveDescriptionText($validated, $bookId);
        $this->validateLineConsistency($validated, $bookId);

        DB::transaction(function () use ($journalEntry, $validated, $bookId): void {
            $this->saveClosingAdjustmentJournal($journalEntry, $validated, $bookId);
        });

        return redirect()
            ->route('closing-adjustment-journals.index', [
                'book_id' => $bookId,
                'date_from' => $validated['entry_date'],
                'date_to' => $validated['entry_date'],
            ])
            ->with('status', '決算整理仕訳を更新しました。');
    }

    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        $this->ensureClosingAdjustmentJournal($journalEntry);

        $bookId = (int) $journalEntry->book_id;

        DB::transaction(function () use ($journalEntry): void {
            $journalEntry->delete();
        });

        return redirect()
            ->route('closing-adjustment-journals.index', ['book_id' => $bookId])
            ->with('status', '決算整理仕訳を削除しました。');
    }

    private function ensureClosingAdjustmentJournal(JournalEntry $journalEntry): void
    {
        abort_if($journalEntry->entry_type !== 'closing', 404);
    }

    private function getSelectableBooks(?int $currentBookId = null): Collection
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        if ($currentBookId !== null && !$books->contains('id', $currentBookId)) {
            $currentBook = Book::query()
                ->with('businessOwner')
                ->find($currentBookId);

            if ($currentBook !== null) {
                $books = $books->prepend($currentBook);
            }
        }

        return $books;
    }

    private function loadFormMasterData(int $bookId): array
    {
        $accountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $subAccountTitles = SubAccountTitle::query()
            ->with('accountTitle')
            ->where('is_active', true)
            ->whereHas('accountTitle', function ($query) use ($bookId): void {
                $query->where('book_id', $bookId)
                    ->where('allows_sub_account', true)
                    ->where('is_active', true);
            })
            ->orderBy('sort_order')
            ->orderBy('sub_account_code')
            ->get();

        $departments = Department::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->get();

        $journalDescriptions = JournalDescription::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('description_code')
            ->orderBy('id')
            ->get();

        return [
            'accountTitles' => $accountTitles,
            'subAccountTitles' => $subAccountTitles,
            'departments' => $departments,
            'journalDescriptions' => $journalDescriptions,
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'accountTitles' => collect(),
            'subAccountTitles' => collect(),
            'departments' => collect(),
            'journalDescriptions' => collect(),
        ];
    }

    private function validateJournalEntryPayload(
        Request $request,
        int $bookId,
        ?JournalEntry $journalEntry = null
    ): array {
        $voucherNoRule = Rule::unique('journal_entries', 'voucher_no')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        if ($journalEntry !== null) {
            $voucherNoRule = $voucherNoRule->ignore($journalEntry->id);
        }

        return $request->validate([
            'entry_date' => ['required', 'date'],
            'voucher_no' => ['nullable', 'string', 'max:20', $voucherNoRule],
            'journal_description_id' => [
                'nullable',
                'integer',
                Rule::exists('journal_descriptions', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'description_text' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],

            'debit_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'debit_sub_account_title_id' => ['nullable', 'integer', 'exists:sub_account_titles,id'],
            'debit_department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'debit_amount' => ['required', 'numeric', 'gt:0'],
            'debit_line_note' => ['nullable', 'string', 'max:255'],

            'credit_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'credit_sub_account_title_id' => ['nullable', 'integer', 'exists:sub_account_titles,id'],
            'credit_department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'credit_amount' => ['required', 'numeric', 'gt:0'],
            'credit_line_note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function resolveDescriptionText(array &$validated, int $bookId): void
    {
        $descriptionText = trim((string) ($validated['description_text'] ?? ''));

        if ($descriptionText === '' && !empty($validated['journal_description_id'])) {
            $descriptionText = trim((string) JournalDescription::query()
                ->where('book_id', $bookId)
                ->where('is_active', true)
                ->findOrFail($validated['journal_description_id'])
                ->description_text);
        }

        if ($descriptionText === '') {
            $descriptionText = '決算整理仕訳';
        }

        $validated['description_text'] = $descriptionText;
    }

    private function validateLineConsistency(array $validated, int $bookId): void
    {
        $debitAccountTitle = AccountTitle::query()
            ->select(['id', 'book_id', 'allows_sub_account'])
            ->findOrFail($validated['debit_account_title_id']);

        $creditAccountTitle = AccountTitle::query()
            ->select(['id', 'book_id', 'allows_sub_account'])
            ->findOrFail($validated['credit_account_title_id']);

        $errors = [];

        $debitAmount = (int) round(((float) $validated['debit_amount']) * 100);
        $creditAmount = (int) round(((float) $validated['credit_amount']) * 100);

        if ($debitAmount !== $creditAmount) {
            $errors['credit_amount'] = '借方金額と貸方金額は同じ金額にしてください。';
        }

        if ((int) $debitAccountTitle->book_id !== $bookId) {
            $errors['debit_account_title_id'] = '借方勘定科目の帳簿が一致していません。';
        }

        if ((int) $creditAccountTitle->book_id !== $bookId) {
            $errors['credit_account_title_id'] = '貸方勘定科目の帳簿が一致していません。';
        }

        if (!empty($validated['debit_sub_account_title_id'])) {
            $debitSubAccountTitle = SubAccountTitle::query()
                ->select(['id', 'account_title_id', 'is_active'])
                ->find($validated['debit_sub_account_title_id']);

            if (
                $debitSubAccountTitle === null ||
                !$debitSubAccountTitle->is_active ||
                (int) $debitSubAccountTitle->account_title_id !== (int) $debitAccountTitle->id
            ) {
                $errors['debit_sub_account_title_id'] = '借方補助科目が借方勘定科目と一致していません。';
            }

            if (!$debitAccountTitle->allows_sub_account) {
                $errors['debit_sub_account_title_id'] = '借方勘定科目では補助科目を使用できません。';
            }
        }

        if (!empty($validated['credit_sub_account_title_id'])) {
            $creditSubAccountTitle = SubAccountTitle::query()
                ->select(['id', 'account_title_id', 'is_active'])
                ->find($validated['credit_sub_account_title_id']);

            if (
                $creditSubAccountTitle === null ||
                !$creditSubAccountTitle->is_active ||
                (int) $creditSubAccountTitle->account_title_id !== (int) $creditAccountTitle->id
            ) {
                $errors['credit_sub_account_title_id'] = '貸方補助科目が貸方勘定科目と一致していません。';
            }

            if (!$creditAccountTitle->allows_sub_account) {
                $errors['credit_sub_account_title_id'] = '貸方勘定科目では補助科目を使用できません。';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function saveClosingAdjustmentJournal(
        JournalEntry $journalEntry,
        array $validated,
        int $bookId
    ): JournalEntry {
        $journalEntry->fill([
            'book_id' => $bookId,
            'journal_description_id' => $validated['journal_description_id'] ?? null,
            'entry_date' => $validated['entry_date'],
            'voucher_no' => $validated['voucher_no'] ?? null,
            'description_text' => $validated['description_text'],
            'note' => $validated['note'] ?? null,
            'total_amount' => $validated['debit_amount'],
            'entry_type' => 'closing',
            'status' => 'posted',
        ]);

        $journalEntry->save();

        $journalEntry->lines()->delete();

        $journalEntry->lines()->createMany([
            [
                'line_no' => 1,
                'side' => 'debit',
                'account_title_id' => $validated['debit_account_title_id'],
                'sub_account_title_id' => $validated['debit_sub_account_title_id'] ?? null,
                'department_id' => $validated['debit_department_id'] ?? null,
                'amount' => $validated['debit_amount'],
                'line_note' => $validated['debit_line_note'] ?? null,
            ],
            [
                'line_no' => 2,
                'side' => 'credit',
                'account_title_id' => $validated['credit_account_title_id'],
                'sub_account_title_id' => $validated['credit_sub_account_title_id'] ?? null,
                'department_id' => $validated['credit_department_id'] ?? null,
                'amount' => $validated['credit_amount'],
                'line_note' => $validated['credit_line_note'] ?? null,
            ],
        ]);

        return $journalEntry;
    }

    private function buildSummary(Collection $closingAdjustmentJournals): array
    {
        return [
            'entries_count' => $closingAdjustmentJournals->count(),
            'total_amount' => round(
                $closingAdjustmentJournals->sum(fn (JournalEntry $journalEntry) => (float) $journalEntry->total_amount),
                2
            ),
        ];
    }
}