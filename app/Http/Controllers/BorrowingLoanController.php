<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\BorrowingLoan;
use App\Models\BorrowingRepayment;
use App\Models\Department;
use App\Models\JournalEntry;
use App\Models\Property;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BorrowingLoanController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'book_id' => ['nullable', 'integer', 'exists:books,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', 'in:all,active,paid_off'],
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

        $status = $validated['status'] ?? 'active';
        $loanRows = collect();
        $repaymentRows = collect();

        if ($selectedBook !== null) {
            $loansQuery = BorrowingLoan::query()
                ->with([
                    'book.businessOwner',
                    'property',
                    'department',
                    'principalAccountTitle',
                    'interestExpenseAccountTitle',
                    'paymentAccountTitle',
                    'repayments' => function ($query): void {
                        $query
                            ->with('journalEntry')
                            ->orderBy('due_on')
                            ->orderBy('period_no');
                    },
                ])
                ->where('book_id', $selectedBook->id)
                ->orderBy('loan_code')
                ->orderBy('id');

            if ($status !== 'all') {
                $loansQuery->where('status', $status);
            }

            $loanRows = $loansQuery
                ->get()
                ->map(fn (BorrowingLoan $loan) => $this->buildLoanRow($loan, $dateFrom, $dateTo));

            $repaymentRows = $loanRows
                ->flatMap(fn ($row) => $row->period_repayments->map(fn (BorrowingRepayment $repayment) => (object) [
                    'loan' => $row->loan,
                    'repayment' => $repayment,
                ]))
                ->sortBy(fn ($row) => $row->repayment->due_on?->format('Y-m-d') . '|' . $row->loan->loan_code . '|' . str_pad((string) $row->repayment->period_no, 6, '0', STR_PAD_LEFT))
                ->values();
        }

        return view('borrowing_loans.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'status' => $status,
            'loanRows' => $loanRows,
            'repaymentRows' => $repaymentRows,
            'summary' => $this->buildSummary($loanRows, $repaymentRows),
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

        return view('borrowing_loans.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'borrowingLoan' => null,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');
        $validated = $this->validateLoanPayload($request, $bookId);

        DB::transaction(function () use ($validated, $bookId): void {
            $loan = BorrowingLoan::query()->create(array_merge($validated, [
                'book_id' => $bookId,
            ]));

            $this->regenerateRepaymentSchedule($loan);
        });

        return redirect()
            ->route('borrowing-loans.index', ['book_id' => $bookId])
            ->with('status', '借入金を登録し、返済予定表を作成しました。');
    }

    public function edit(BorrowingLoan $borrowingLoan): View
    {
        $borrowingLoan->load([
            'book.businessOwner',
            'property',
            'department',
            'principalAccountTitle',
            'interestExpenseAccountTitle',
            'paymentAccountTitle',
        ]);

        $selectedBookId = (int) $borrowingLoan->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $formData = $this->loadFormMasterData($selectedBookId);

        return view('borrowing_loans.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'borrowingLoan' => $borrowingLoan,
        ], $formData));
    }

    public function update(Request $request, BorrowingLoan $borrowingLoan): RedirectResponse
    {
        $bookId = (int) $borrowingLoan->book_id;
        $validated = $this->validateLoanPayload($request, $bookId, $borrowingLoan);

        if ($borrowingLoan->repayments()->whereNotNull('journal_entry_id')->exists()) {
            throw ValidationException::withMessages([
                'loan_code' => '返済仕訳を作成済みの借入金は修正できません。必要な場合は先に仕訳一覧で返済仕訳を確認してください。',
            ]);
        }

        DB::transaction(function () use ($borrowingLoan, $validated): void {
            $borrowingLoan->fill($validated);
            $borrowingLoan->save();
            $this->regenerateRepaymentSchedule($borrowingLoan);
        });

        return redirect()
            ->route('borrowing-loans.index', ['book_id' => $bookId])
            ->with('status', '借入金を更新し、返済予定表を作り直しました。');
    }

    public function destroy(BorrowingLoan $borrowingLoan): RedirectResponse
    {
        $bookId = (int) $borrowingLoan->book_id;

        if ($borrowingLoan->repayments()->whereNotNull('journal_entry_id')->exists()) {
            throw ValidationException::withMessages([
                'loan_code' => '返済仕訳を作成済みの借入金は削除できません。必要な場合は先に仕訳一覧で返済仕訳を確認してください。',
            ]);
        }

        $borrowingLoan->delete();

        return redirect()
            ->route('borrowing-loans.index', ['book_id' => $bookId])
            ->with('status', '借入金を削除しました。');
    }

    public function storeRepaymentJournals(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $bookId = (int) $validated['book_id'];
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        $repayments = BorrowingRepayment::query()
            ->with([
                'borrowingLoan.principalAccountTitle',
                'borrowingLoan.interestExpenseAccountTitle',
                'borrowingLoan.paymentAccountTitle',
                'borrowingLoan.department',
                'journalEntry',
            ])
            ->whereHas('borrowingLoan', function ($query) use ($bookId): void {
                $query
                    ->where('book_id', $bookId)
                    ->where('status', 'active');
            })
            ->whereDate('due_on', '>=', $dateFrom)
            ->whereDate('due_on', '<=', $dateTo)
            ->orderBy('due_on')
            ->orderBy('id')
            ->get();

        $createdOrUpdatedCount = 0;
        $skippedCount = 0;

        DB::transaction(function () use ($repayments, $bookId, &$createdOrUpdatedCount, &$skippedCount): void {
            foreach ($repayments as $repayment) {
                if ((float) $repayment->total_amount <= 0) {
                    $skippedCount++;
                    continue;
                }

                $this->saveRepaymentJournal($repayment, $bookId);
                $createdOrUpdatedCount++;
            }
        });

        return redirect()
            ->route('borrowing-loans.index', [
                'book_id' => $bookId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'status' => 'active',
            ])
            ->with('status', "借入返済仕訳を {$createdOrUpdatedCount} 件作成・更新しました。対象外 {$skippedCount} 件。");
    }

    private function validateLoanPayload(
        Request $request,
        int $bookId,
        ?BorrowingLoan $borrowingLoan = null
    ): array {
        $loanCodeRule = Rule::unique('borrowing_loans', 'loan_code')
            ->where(fn ($query) => $query->where('book_id', $bookId));

        if ($borrowingLoan !== null) {
            $loanCodeRule = $loanCodeRule->ignore($borrowingLoan->id);
        }

        $validated = $request->validate([
            'property_id' => [
                'nullable',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'principal_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('category', 'liability')
                        ->where('is_active', true)
                ),
            ],
            'interest_expense_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('category', 'expense')
                        ->where('is_active', true)
                ),
            ],
            'payment_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('category', 'asset')
                        ->where('is_active', true)
                ),
            ],
            'loan_code' => ['required', 'string', 'max:30', $loanCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'lender_name' => ['nullable', 'string', 'max:120'],
            'borrowed_on' => ['required', 'date'],
            'principal_amount' => ['required', 'numeric', 'gt:0'],
            'annual_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'term_months' => ['required', 'integer', 'min:1', 'max:600'],
            'repayment_start_date' => ['required', 'date'],
            'monthly_repayment_day' => ['required', 'integer', 'min:1', 'max:31'],
            'repayment_method' => ['required', 'in:equal_principal,equal_payment'],
            'status' => ['required', 'in:active,paid_off'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['annual_interest_rate'] = (float) ($validated['annual_interest_rate'] ?? 0);

        return $validated;
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
        $liabilityAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'liability')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $expenseAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'expense')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $assetAccountTitles = AccountTitle::query()
            ->where('book_id', $bookId)
            ->where('category', 'asset')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->get();

        $properties = Property::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->get();

        $departments = Department::query()
            ->where('book_id', $bookId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->get();

        return [
            'liabilityAccountTitles' => $liabilityAccountTitles,
            'expenseAccountTitles' => $expenseAccountTitles,
            'assetAccountTitles' => $assetAccountTitles,
            'properties' => $properties,
            'departments' => $departments,
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'liabilityAccountTitles' => collect(),
            'expenseAccountTitles' => collect(),
            'assetAccountTitles' => collect(),
            'properties' => collect(),
            'departments' => collect(),
        ];
    }

    private function regenerateRepaymentSchedule(BorrowingLoan $loan): void
    {
        if ($loan->repayments()->whereNotNull('journal_entry_id')->exists()) {
            throw ValidationException::withMessages([
                'loan_code' => '返済仕訳を作成済みの借入金は返済予定表を作り直せません。',
            ]);
        }

        $loan->repayments()->delete();
        $loan->repayments()->createMany($this->buildRepaymentScheduleRows($loan));
    }

    private function buildRepaymentScheduleRows(BorrowingLoan $loan): array
    {
        $principal = round((float) $loan->principal_amount, 2);
        $remaining = $principal;
        $termMonths = max((int) $loan->term_months, 1);
        $monthlyRate = ((float) $loan->annual_interest_rate / 100) / 12;
        $rows = [];
        $equalPaymentAmount = null;

        if ($loan->repayment_method === 'equal_payment' && $monthlyRate > 0) {
            $equalPaymentAmount = round(
                $principal * $monthlyRate / (1 - pow(1 + $monthlyRate, -$termMonths)),
                2
            );
        }

        for ($periodNo = 1; $periodNo <= $termMonths; $periodNo++) {
            $interestAmount = round($remaining * $monthlyRate, 2);

            if ($loan->repayment_method === 'equal_payment' && $equalPaymentAmount !== null) {
                $principalAmount = round($equalPaymentAmount - $interestAmount, 2);
            } else {
                $principalAmount = round($principal / $termMonths, 2);
            }

            if ($periodNo === $termMonths || $principalAmount > $remaining) {
                $principalAmount = round($remaining, 2);
            }

            $principalAmount = max($principalAmount, 0);
            $totalAmount = round($principalAmount + $interestAmount, 2);
            $remaining = max(round($remaining - $principalAmount, 2), 0);

            $rows[] = [
                'period_no' => $periodNo,
                'due_on' => $this->buildDueOn($loan, $periodNo - 1),
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'total_amount' => $totalAmount,
                'remaining_principal_after' => $remaining,
                'status' => 'scheduled',
                'note' => null,
            ];
        }

        return $rows;
    }

    private function buildDueOn(BorrowingLoan $loan, int $monthIndex): string
    {
        $base = CarbonImmutable::parse($loan->repayment_start_date)
            ->startOfMonth()
            ->addMonthsNoOverflow($monthIndex);

        $day = min(max((int) $loan->monthly_repayment_day, 1), $base->daysInMonth);

        return $base->day($day)->format('Y-m-d');
    }

    private function buildLoanRow(BorrowingLoan $loan, ?string $dateFrom, ?string $dateTo): object
    {
        $periodRepayments = $loan->repayments
            ->filter(function (BorrowingRepayment $repayment) use ($dateFrom, $dateTo): bool {
                $dueOn = $repayment->due_on?->format('Y-m-d');

                if ($dueOn === null) {
                    return false;
                }

                if (!empty($dateFrom) && $dueOn < $dateFrom) {
                    return false;
                }

                if (!empty($dateTo) && $dueOn > $dateTo) {
                    return false;
                }

                return true;
            })
            ->values();

        $remainingPrincipalAfterPeriod = $this->calculateRemainingPrincipalAfterPeriod($loan, $dateTo);

        return (object) [
            'loan' => $loan,
            'period_repayments' => $periodRepayments,
            'period_principal_total' => round($periodRepayments->sum(fn (BorrowingRepayment $repayment) => (float) $repayment->principal_amount), 2),
            'period_interest_total' => round($periodRepayments->sum(fn (BorrowingRepayment $repayment) => (float) $repayment->interest_amount), 2),
            'period_total' => round($periodRepayments->sum(fn (BorrowingRepayment $repayment) => (float) $repayment->total_amount), 2),
            'remaining_principal_after_period' => $remainingPrincipalAfterPeriod,
            'journal_count' => $periodRepayments->filter(fn (BorrowingRepayment $repayment) => $repayment->journal_entry_id !== null)->count(),
        ];
    }

    private function calculateRemainingPrincipalAfterPeriod(BorrowingLoan $loan, ?string $dateTo): float
    {
        if (empty($dateTo)) {
            return round((float) $loan->principal_amount, 2);
        }

        $lastRepayment = $loan->repayments
            ->filter(fn (BorrowingRepayment $repayment) => $repayment->due_on?->format('Y-m-d') <= $dateTo)
            ->sortBy(fn (BorrowingRepayment $repayment) => $repayment->due_on?->format('Y-m-d') . '|' . str_pad((string) $repayment->period_no, 6, '0', STR_PAD_LEFT))
            ->last();

        if ($lastRepayment === null) {
            return round((float) $loan->principal_amount, 2);
        }

        return round((float) $lastRepayment->remaining_principal_after, 2);
    }

    private function saveRepaymentJournal(BorrowingRepayment $repayment, int $bookId): JournalEntry
    {
        $loan = $repayment->borrowingLoan;
        $voucherNo = $this->buildVoucherNo($repayment);
        $principalAmount = round((float) $repayment->principal_amount, 2);
        $interestAmount = round((float) $repayment->interest_amount, 2);
        $totalAmount = round((float) $repayment->total_amount, 2);

        $journalEntry = $repayment->journalEntry
            ?? JournalEntry::query()
                ->where('book_id', $bookId)
                ->where('entry_type', 'loan_repayment')
                ->where('voucher_no', $voucherNo)
                ->first()
            ?? new JournalEntry();

        $journalEntry->fill([
            'book_id' => $bookId,
            'journal_description_id' => null,
            'entry_date' => $repayment->due_on?->format('Y-m-d'),
            'voucher_no' => $voucherNo,
            'description_text' => '借入金返済 ' . $loan->name,
            'note' => '借入コード: ' . $loan->loan_code . ' / 第' . $repayment->period_no . '回返済',
            'total_amount' => $totalAmount,
            'entry_type' => 'loan_repayment',
            'status' => 'posted',
        ]);

        $journalEntry->save();
        $journalEntry->lines()->delete();

        $lineNote = '借入返済: ' . $loan->loan_code . ' ' . $loan->name;
        $lines = [];
        $lineNo = 1;

        if ($principalAmount > 0) {
            $lines[] = [
                'line_no' => $lineNo++,
                'side' => 'debit',
                'account_title_id' => $loan->principal_account_title_id,
                'sub_account_title_id' => null,
                'department_id' => $loan->department_id,
                'amount' => $principalAmount,
                'line_note' => $lineNote,
            ];
        }

        if ($interestAmount > 0) {
            $lines[] = [
                'line_no' => $lineNo++,
                'side' => 'debit',
                'account_title_id' => $loan->interest_expense_account_title_id,
                'sub_account_title_id' => null,
                'department_id' => $loan->department_id,
                'amount' => $interestAmount,
                'line_note' => $lineNote,
            ];
        }

        $lines[] = [
            'line_no' => $lineNo,
            'side' => 'credit',
            'account_title_id' => $loan->payment_account_title_id,
            'sub_account_title_id' => null,
            'department_id' => null,
            'amount' => $totalAmount,
            'line_note' => $lineNote,
        ];

        $journalEntry->lines()->createMany($lines);

        $repayment->forceFill([
            'journal_entry_id' => $journalEntry->id,
            'status' => 'journaled',
        ])->save();

        return $journalEntry;
    }

    private function buildVoucherNo(BorrowingRepayment $repayment): string
    {
        return 'LN'
            . str_pad((string) $repayment->id, 8, '0', STR_PAD_LEFT)
            . '-'
            . $repayment->due_on?->format('ymd');
    }

    private function buildSummary(Collection $loanRows, Collection $repaymentRows): array
    {
        return [
            'loans_count' => $loanRows->count(),
            'principal_total' => round($loanRows->sum(fn ($row) => (float) $row->loan->principal_amount), 2),
            'remaining_principal_total' => round($loanRows->sum(fn ($row) => (float) $row->remaining_principal_after_period), 2),
            'period_principal_total' => round($repaymentRows->sum(fn ($row) => (float) $row->repayment->principal_amount), 2),
            'period_interest_total' => round($repaymentRows->sum(fn ($row) => (float) $row->repayment->interest_amount), 2),
            'period_total' => round($repaymentRows->sum(fn ($row) => (float) $row->repayment->total_amount), 2),
            'journal_count' => $repaymentRows->filter(fn ($row) => $row->repayment->journal_entry_id !== null)->count(),
        ];
    }
}