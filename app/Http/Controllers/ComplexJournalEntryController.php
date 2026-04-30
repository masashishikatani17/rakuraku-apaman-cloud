<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\Department;
use App\Models\JournalDescription;
use App\Models\JournalEntry;
use App\Models\Property;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ComplexJournalEntryController extends Controller
{
    public function create(Request $request): View
    {
        $books = $this->getSelectableBooks();

        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : ($books->first()?->id);

        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        if ($selectedBook === null && $books->isNotEmpty()) {
            $selectedBook = $books->first();
            $selectedBookId = (int) $selectedBook->id;
        }

        $formData = $selectedBookId !== null
            ? $this->loadFormMasterData($selectedBookId)
            : $this->emptyFormMasterData();

        return view('journal_entries.complex_create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'defaultEntryDate' => $request->filled('entry_date')
                ? (string) $request->input('entry_date')
                : now()->format('Y-m-d'),
            'debitRowCount' => 5,
            'creditRowCount' => 5,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');
        $validated = $this->validatePayload($request, $bookId);
        $this->resolveDescriptionText($validated, $bookId);

        $debitLines = $this->normalizeLines($validated['debit_lines'] ?? [], 'debit');
        $creditLines = $this->normalizeLines($validated['credit_lines'] ?? [], 'credit');

        $this->validateLines($debitLines, $creditLines, $bookId);

        $journalEntry = null;

        DB::transaction(function () use ($validated, $bookId, $debitLines, $creditLines, &$journalEntry): void {
            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => $validated['journal_description_id'] ?? null,
                'entry_date' => $validated['entry_date'],
                'voucher_no' => $validated['voucher_no'] ?? null,
                'description_text' => $validated['description_text'],
                'note' => $validated['note'] ?? null,
                'total_amount' => round(collect($debitLines)->sum(fn (array $line) => (float) $line['amount']), 2),
                'entry_type' => 'manual',
                'status' => 'posted',
            ]);

            $lineNo = 1;
            $createRows = [];

            foreach ($debitLines as $line) {
                $createRows[] = $this->buildLineCreateRow($line, $lineNo++);
            }

            foreach ($creditLines as $line) {
                $createRows[] = $this->buildLineCreateRow($line, $lineNo++);
            }

            $journalEntry->lines()->createMany($createRows);
        });

        if ($request->boolean('continue_input')) {
            return redirect()
                ->route('journal-entries.complex.create', [
                    'book_id' => $bookId,
                    'entry_date' => $validated['entry_date'],
                ])
                ->with('status', '複合仕訳を登録しました。続けて入力できます。');
        }

        return redirect()
            ->route('journal-entries.index', ['book_id' => $bookId])
            ->with('status', '複合仕訳を登録しました。');
    }

    public function edit(JournalEntry $journalEntry): View
    {
        abort_if($journalEntry->entry_type !== 'manual', 404);

        $journalEntry->load([
            'book.businessOwner',
            'journalDescription',
            'lines' => function ($query): void {
                $query->orderBy('line_no');
            },
        ]);

        $selectedBookId = (int) $journalEntry->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $formData = $this->loadFormMasterData($selectedBookId);

        $debitLines = $journalEntry->lines
            ->where('side', 'debit')
            ->values()
            ->map(fn ($line) => $this->lineToFormArray($line))
            ->all();

        $creditLines = $journalEntry->lines
            ->where('side', 'credit')
            ->values()
            ->map(fn ($line) => $this->lineToFormArray($line))
            ->all();

        return view('journal_entries.complex_edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'journalEntry' => $journalEntry,
            'debitLines' => $debitLines,
            'creditLines' => $creditLines,
            'debitRowCount' => max(5, count($debitLines) + 2),
            'creditRowCount' => max(5, count($creditLines) + 2),
        ], $formData));
    }

    public function update(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        abort_if($journalEntry->entry_type !== 'manual', 404);

        $bookId = (int) $journalEntry->book_id;
        $validated = $this->validatePayload($request, $bookId, $journalEntry);
        $this->resolveDescriptionText($validated, $bookId);

        $debitLines = $this->normalizeLines($validated['debit_lines'] ?? [], 'debit');
        $creditLines = $this->normalizeLines($validated['credit_lines'] ?? [], 'credit');

        $this->validateLines($debitLines, $creditLines, $bookId);

        DB::transaction(function () use ($journalEntry, $validated, $debitLines, $creditLines): void {
            $journalEntry->fill([
                'journal_description_id' => $validated['journal_description_id'] ?? null,
                'entry_date' => $validated['entry_date'],
                'voucher_no' => $validated['voucher_no'] ?? null,
                'description_text' => $validated['description_text'],
                'note' => $validated['note'] ?? null,
                'total_amount' => round(collect($debitLines)->sum(fn (array $line) => (float) $line['amount']), 2),
                'status' => 'posted',
            ]);

            $journalEntry->save();
            $journalEntry->lines()->delete();

            $lineNo = 1;
            $createRows = [];

            foreach ($debitLines as $line) {
                $createRows[] = $this->buildLineCreateRow($line, $lineNo++);
            }

            foreach ($creditLines as $line) {
                $createRows[] = $this->buildLineCreateRow($line, $lineNo++);
            }

            $journalEntry->lines()->createMany($createRows);
        });

        return redirect()
            ->route('journal-entries.index', ['book_id' => $bookId])
            ->with('status', '複合仕訳を更新しました。');
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

        $properties = Property::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id')
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
            'properties' => $properties,
            'journalDescriptions' => $journalDescriptions,
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'accountTitles' => collect(),
            'subAccountTitles' => collect(),
            'departments' => collect(),
            'properties' => collect(),
            'journalDescriptions' => collect(),
        ];
    }

    private function validatePayload(Request $request, int $bookId, ?JournalEntry $journalEntry = null): array
    {
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
            'debit_lines' => ['nullable', 'array'],
            'credit_lines' => ['nullable', 'array'],
            'debit_lines.*.account_title_id' => ['nullable', 'integer'],
            'debit_lines.*.sub_account_title_id' => ['nullable', 'integer'],
            'debit_lines.*.department_id' => ['nullable', 'integer'],
            'debit_lines.*.property_id' => ['nullable', 'integer'],
            'debit_lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'debit_lines.*.line_note' => ['nullable', 'string', 'max:255'],
            'credit_lines.*.account_title_id' => ['nullable', 'integer'],
            'credit_lines.*.sub_account_title_id' => ['nullable', 'integer'],
            'credit_lines.*.department_id' => ['nullable', 'integer'],
            'credit_lines.*.property_id' => ['nullable', 'integer'],
            'credit_lines.*.amount' => ['nullable', 'numeric', 'min:0'],
            'credit_lines.*.line_note' => ['nullable', 'string', 'max:255'],
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
            throw ValidationException::withMessages([
                'description_text' => '摘要文を入力するか、登録済摘要を選択してください。',
            ]);
        }

        $validated['description_text'] = $descriptionText;
    }

    private function normalizeLines(array $inputLines, string $side): array
    {
        $lines = [];

        foreach ($inputLines as $line) {
            $accountTitleId = $line['account_title_id'] ?? null;
            $amount = (float) ($line['amount'] ?? 0);

            if (empty($accountTitleId) && $amount <= 0) {
                continue;
            }

            $lines[] = [
                'side' => $side,
                'account_title_id' => $accountTitleId !== null && $accountTitleId !== '' ? (int) $accountTitleId : null,
                'sub_account_title_id' => !empty($line['sub_account_title_id']) ? (int) $line['sub_account_title_id'] : null,
                'department_id' => !empty($line['department_id']) ? (int) $line['department_id'] : null,
                'property_id' => !empty($line['property_id']) ? (int) $line['property_id'] : null,
                'amount' => round($amount, 2),
                'line_note' => trim((string) ($line['line_note'] ?? '')) ?: null,
            ];
        }

        return $lines;
    }

    private function validateLines(array $debitLines, array $creditLines, int $bookId): void
    {
        $errors = [];

        if ($debitLines === []) {
            $errors['debit_lines'] = '借方明細を1行以上入力してください。';
        }

        if ($creditLines === []) {
            $errors['credit_lines'] = '貸方明細を1行以上入力してください。';
        }

        $debitTotal = round(collect($debitLines)->sum(fn (array $line) => (float) $line['amount']), 2);
        $creditTotal = round(collect($creditLines)->sum(fn (array $line) => (float) $line['amount']), 2);

        if ($debitTotal <= 0) {
            $errors['debit_lines'] = '借方金額を入力してください。';
        }

        if ($creditTotal <= 0) {
            $errors['credit_lines'] = '貸方金額を入力してください。';
        }

        if ((int) round($debitTotal * 100) !== (int) round($creditTotal * 100)) {
            $errors['credit_lines'] = '借方合計と貸方合計は同じ金額にしてください。';
        }

        $allLines = array_merge($debitLines, $creditLines);

        $accountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->whereIn('id', collect($allLines)->pluck('account_title_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $subAccountTitles = SubAccountTitle::query()
            ->whereIn('id', collect($allLines)->pluck('sub_account_title_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $departmentIds = Department::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->whereIn('id', collect($allLines)->pluck('department_id')->filter()->unique())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $propertyIds = Property::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->whereIn('id', collect($allLines)->pluck('property_id')->filter()->unique())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($allLines as $index => $line) {
            $label = ($line['side'] === 'debit' ? '借方' : '貸方') . '明細' . ($index + 1);

            if (empty($line['account_title_id']) || !$accountTitles->has($line['account_title_id'])) {
                $errors["{$line['side']}_lines"] = "{$label} の勘定科目が不正です。";
                continue;
            }

            if ((float) $line['amount'] <= 0) {
                $errors["{$line['side']}_lines"] = "{$label} の金額を入力してください。";
            }

            $accountTitle = $accountTitles->get($line['account_title_id']);

            if (!empty($line['sub_account_title_id'])) {
                $subAccountTitle = $subAccountTitles->get($line['sub_account_title_id']);

                if (
                    $subAccountTitle === null ||
                    !$subAccountTitle->is_active ||
                    (int) $subAccountTitle->account_title_id !== (int) $accountTitle->id ||
                    !$accountTitle->allows_sub_account
                ) {
                    $errors["{$line['side']}_lines"] = "{$label} の補助科目が勘定科目と一致していません。";
                }
            }

            if (!empty($line['department_id']) && !in_array((int) $line['department_id'], $departmentIds, true)) {
                $errors["{$line['side']}_lines"] = "{$label} の部門が不正です。";
            }

            if (!empty($line['property_id']) && !in_array((int) $line['property_id'], $propertyIds, true)) {
                $errors["{$line['side']}_lines"] = "{$label} の物件が不正です。";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function buildLineCreateRow(array $line, int $lineNo): array
    {
        return [
            'line_no' => $lineNo,
            'side' => $line['side'],
            'account_title_id' => $line['account_title_id'],
            'sub_account_title_id' => $line['sub_account_title_id'],
            'department_id' => $line['department_id'],
            'property_id' => $line['property_id'],
            'amount' => $line['amount'],
            'line_note' => $line['line_note'],
        ];
    }

    private function lineToFormArray($line): array
    {
        return [
            'account_title_id' => $line->account_title_id,
            'sub_account_title_id' => $line->sub_account_title_id,
            'department_id' => $line->department_id,
            'property_id' => $line->property_id,
            'amount' => $line->amount,
            'line_note' => $line->line_note,
        ];
    }
}