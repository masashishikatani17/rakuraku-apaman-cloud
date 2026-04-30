<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\Department;
use App\Models\JournalDescription;
use App\Models\JournalEntry;
use App\Models\JournalEntryTemplate;
use App\Models\JournalEntryTemplateLine;
use App\Models\Property;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class JournalEntryTemplateController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $templatesQuery = JournalEntryTemplate::query()
            ->with(['book.businessOwner', 'debitLines.accountTitle', 'creditLines.accountTitle'])
            ->withCount('lines')
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('template_code');

        if ($selectedBookId !== null) {
            $templatesQuery->where('book_id', $selectedBookId);
        }

        return view('journal_entry_templates.index', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
            'templates' => $templatesQuery->get(),
        ]);
    }

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

        return view('journal_entry_templates.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'template' => null,
            'debitLines' => [],
            'creditLines' => [],
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
        $validated = $this->validateTemplatePayload($request, $bookId);

        $debitLines = $this->normalizeTemplateLines($validated['debit_lines'] ?? [], 'debit');
        $creditLines = $this->normalizeTemplateLines($validated['credit_lines'] ?? [], 'credit');

        $this->validateTemplateLines($debitLines, $creditLines, $bookId);

        DB::transaction(function () use ($validated, $bookId, $debitLines, $creditLines): void {
            $template = JournalEntryTemplate::query()->create([
                'book_id' => $bookId,
                'template_code' => $validated['template_code'],
                'name' => $validated['name'],
                'description_text' => $validated['description_text'],
                'note' => $validated['note'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);

            $template->lines()->createMany($this->buildTemplateLineCreateRows($debitLines, $creditLines));
        });

        return redirect()
            ->route('journal-entry-templates.index', ['book_id' => $bookId])
            ->with('status', '仕訳テンプレートを登録しました。');
    }

    public function edit(JournalEntryTemplate $journalEntryTemplate): View
    {
        $journalEntryTemplate->load(['book.businessOwner', 'lines']);

        $selectedBookId = (int) $journalEntryTemplate->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $formData = $this->loadFormMasterData($selectedBookId);

        $debitLines = $journalEntryTemplate->lines
            ->where('side', 'debit')
            ->values()
            ->map(fn (JournalEntryTemplateLine $line) => $this->templateLineToFormArray($line))
            ->all();

        $creditLines = $journalEntryTemplate->lines
            ->where('side', 'credit')
            ->values()
            ->map(fn (JournalEntryTemplateLine $line) => $this->templateLineToFormArray($line))
            ->all();

        return view('journal_entry_templates.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'template' => $journalEntryTemplate,
            'debitLines' => $debitLines,
            'creditLines' => $creditLines,
            'debitRowCount' => max(5, count($debitLines) + 2),
            'creditRowCount' => max(5, count($creditLines) + 2),
        ], $formData));
    }

    public function update(Request $request, JournalEntryTemplate $journalEntryTemplate): RedirectResponse
    {
        $bookId = (int) $journalEntryTemplate->book_id;
        $validated = $this->validateTemplatePayload($request, $bookId, $journalEntryTemplate);

        $debitLines = $this->normalizeTemplateLines($validated['debit_lines'] ?? [], 'debit');
        $creditLines = $this->normalizeTemplateLines($validated['credit_lines'] ?? [], 'credit');

        $this->validateTemplateLines($debitLines, $creditLines, $bookId);

        DB::transaction(function () use ($journalEntryTemplate, $validated, $debitLines, $creditLines): void {
            $journalEntryTemplate->fill([
                'template_code' => $validated['template_code'],
                'name' => $validated['name'],
                'description_text' => $validated['description_text'],
                'note' => $validated['note'] ?? null,
                'is_active' => (bool) ($validated['is_active'] ?? true),
                'sort_order' => (int) ($validated['sort_order'] ?? 0),
            ]);
            $journalEntryTemplate->save();

            $journalEntryTemplate->lines()->delete();
            $journalEntryTemplate->lines()->createMany($this->buildTemplateLineCreateRows($debitLines, $creditLines));
        });

        return redirect()
            ->route('journal-entry-templates.index', ['book_id' => $bookId])
            ->with('status', '仕訳テンプレートを更新しました。');
    }

    public function destroy(JournalEntryTemplate $journalEntryTemplate): RedirectResponse
    {
        $bookId = (int) $journalEntryTemplate->book_id;

        $journalEntryTemplate->delete();

        return redirect()
            ->route('journal-entry-templates.index', ['book_id' => $bookId])
            ->with('status', '仕訳テンプレートを削除しました。');
    }

    public function createJournal(Request $request, JournalEntryTemplate $journalEntryTemplate): View
    {
        $journalEntryTemplate->load([
            'book.businessOwner',
            'lines.accountTitle',
            'lines.subAccountTitle',
            'lines.department',
            'lines.property',
        ]);

        return view('journal_entry_templates.create_journal', [
            'template' => $journalEntryTemplate,
            'entryDate' => $request->filled('entry_date') ? (string) $request->input('entry_date') : now()->format('Y-m-d'),
        ]);
    }

    public function storeJournal(Request $request, JournalEntryTemplate $journalEntryTemplate): RedirectResponse
    {
        $journalEntryTemplate->load(['lines']);
        $bookId = (int) $journalEntryTemplate->book_id;

        $voucherNoRule = Rule::unique('journal_entries', 'voucher_no')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'voucher_no' => ['nullable', 'string', 'max:20', $voucherNoRule],
            'description_text' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'line_amounts' => ['nullable', 'array'],
            'line_notes' => ['nullable', 'array'],
            'continue_input' => ['nullable', 'boolean'],
        ]);

        $lineAmounts = $validated['line_amounts'] ?? [];
        $lineNotes = $validated['line_notes'] ?? [];

        $createLines = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        $lineNo = 1;

        foreach ($journalEntryTemplate->lines as $templateLine) {
            $amount = round((float) ($lineAmounts[$templateLine->id] ?? 0), 2);

            if ($amount <= 0) {
                continue;
            }

            if ($templateLine->side === 'debit') {
                $debitTotal += $amount;
            } else {
                $creditTotal += $amount;
            }

            $createLines[] = [
                'line_no' => $lineNo++,
                'side' => $templateLine->side,
                'account_title_id' => $templateLine->account_title_id,
                'sub_account_title_id' => $templateLine->sub_account_title_id,
                'department_id' => $templateLine->department_id,
                'property_id' => $templateLine->property_id,
                'amount' => $amount,
                'line_note' => trim((string) ($lineNotes[$templateLine->id] ?? $templateLine->line_note ?? '')) ?: null,
            ];
        }

        $errors = [];

        if ($debitTotal <= 0) {
            $errors['line_amounts'] = '借方金額を入力してください。';
        }

        if ($creditTotal <= 0) {
            $errors['line_amounts'] = '貸方金額を入力してください。';
        }

        if ((int) round($debitTotal * 100) !== (int) round($creditTotal * 100)) {
            $errors['line_amounts'] = '借方合計と貸方合計は同じ金額にしてください。';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($journalEntryTemplate, $validated, $bookId, $debitTotal, $createLines): void {
            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['entry_date'],
                'voucher_no' => $validated['voucher_no'] ?? null,
                'description_text' => $validated['description_text'],
                'note' => $validated['note'] ?? null,
                'total_amount' => round($debitTotal, 2),
                'entry_type' => 'manual',
                'status' => 'posted',
            ]);

            $journalEntry->lines()->createMany($createLines);
        });

        if ($request->boolean('continue_input')) {
            return redirect()
                ->route('journal-entry-templates.journal.create', [
                    'journalEntryTemplate' => $journalEntryTemplate,
                    'entry_date' => $validated['entry_date'],
                ])
                ->with('status', 'テンプレートから仕訳を登録しました。続けて入力できます。');
        }

        return redirect()
            ->route('journal-entries.index', ['book_id' => $bookId])
            ->with('status', 'テンプレートから仕訳を登録しました。');
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

    private function validateTemplatePayload(
        Request $request,
        int $bookId,
        ?JournalEntryTemplate $template = null
    ): array {
        $templateCodeRule = Rule::unique('journal_entry_templates', 'template_code')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        if ($template !== null) {
            $templateCodeRule = $templateCodeRule->ignore($template->id);
        }

        return $request->validate([
            'template_code' => ['required', 'string', 'max:30', $templateCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'description_text' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'is_active' => ['required', 'boolean'],
            'debit_lines' => ['nullable', 'array'],
            'credit_lines' => ['nullable', 'array'],
            'debit_lines.*.account_title_id' => ['nullable', 'integer'],
            'debit_lines.*.sub_account_title_id' => ['nullable', 'integer'],
            'debit_lines.*.department_id' => ['nullable', 'integer'],
            'debit_lines.*.property_id' => ['nullable', 'integer'],
            'debit_lines.*.default_amount' => ['nullable', 'numeric', 'min:0'],
            'debit_lines.*.line_note' => ['nullable', 'string', 'max:255'],
            'credit_lines.*.account_title_id' => ['nullable', 'integer'],
            'credit_lines.*.sub_account_title_id' => ['nullable', 'integer'],
            'credit_lines.*.department_id' => ['nullable', 'integer'],
            'credit_lines.*.property_id' => ['nullable', 'integer'],
            'credit_lines.*.default_amount' => ['nullable', 'numeric', 'min:0'],
            'credit_lines.*.line_note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function normalizeTemplateLines(array $inputLines, string $side): array
    {
        $lines = [];

        foreach ($inputLines as $line) {
            $accountTitleId = $line['account_title_id'] ?? null;

            if (empty($accountTitleId)) {
                continue;
            }

            $defaultAmount = $line['default_amount'] ?? null;

            $lines[] = [
                'side' => $side,
                'account_title_id' => (int) $accountTitleId,
                'sub_account_title_id' => !empty($line['sub_account_title_id']) ? (int) $line['sub_account_title_id'] : null,
                'department_id' => !empty($line['department_id']) ? (int) $line['department_id'] : null,
                'property_id' => !empty($line['property_id']) ? (int) $line['property_id'] : null,
                'default_amount' => $defaultAmount !== null && $defaultAmount !== '' ? round((float) $defaultAmount, 2) : null,
                'line_note' => trim((string) ($line['line_note'] ?? '')) ?: null,
            ];
        }

        return $lines;
    }

    private function validateTemplateLines(array $debitLines, array $creditLines, int $bookId): void
    {
        $errors = [];

        if ($debitLines === []) {
            $errors['debit_lines'] = '借方テンプレート明細を1行以上入力してください。';
        }

        if ($creditLines === []) {
            $errors['credit_lines'] = '貸方テンプレート明細を1行以上入力してください。';
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
            $label = ($line['side'] === 'debit' ? '借方' : '貸方') . 'テンプレート明細' . ($index + 1);

            if (empty($line['account_title_id']) || !$accountTitles->has($line['account_title_id'])) {
                $errors["{$line['side']}_lines"] = "{$label} の勘定科目が不正です。";
                continue;
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

    private function buildTemplateLineCreateRows(array $debitLines, array $creditLines): array
    {
        $lineNo = 1;
        $rows = [];

        foreach ($debitLines as $line) {
            $rows[] = $this->buildTemplateLineCreateRow($line, $lineNo++);
        }

        foreach ($creditLines as $line) {
            $rows[] = $this->buildTemplateLineCreateRow($line, $lineNo++);
        }

        return $rows;
    }

    private function buildTemplateLineCreateRow(array $line, int $lineNo): array
    {
        return [
            'line_no' => $lineNo,
            'side' => $line['side'],
            'account_title_id' => $line['account_title_id'],
            'sub_account_title_id' => $line['sub_account_title_id'],
            'department_id' => $line['department_id'],
            'property_id' => $line['property_id'],
            'default_amount' => $line['default_amount'],
            'line_note' => $line['line_note'],
        ];
    }

    private function templateLineToFormArray(JournalEntryTemplateLine $line): array
    {
        return [
            'account_title_id' => $line->account_title_id,
            'sub_account_title_id' => $line->sub_account_title_id,
            'department_id' => $line->department_id,
            'property_id' => $line->property_id,
            'default_amount' => $line->default_amount,
            'line_note' => $line->line_note,
        ];
    }
}