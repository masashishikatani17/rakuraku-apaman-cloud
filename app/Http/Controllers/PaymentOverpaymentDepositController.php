<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\JournalEntry;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliationAction;
use App\Models\PaymentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentOverpaymentDepositController extends Controller
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

        $overpaymentRows = collect();
        $liabilityAccountTitles = collect();
        $actions = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;
            $overpaymentRows = $this->buildOverpaymentRows($bookId, $dateFrom, $dateTo);
            $liabilityAccountTitles = AccountTitle::query()
                ->where('book_id', $bookId)
                ->where('category', 'liability')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get();

            $actions = $this->buildDepositActions($bookId, $dateFrom, $dateTo);
        }

        return view('payment_overpayment_deposits.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'overpaymentRows' => $overpaymentRows,
            'liabilityAccountTitles' => $liabilityAccountTitles,
            'actions' => $actions,
            'summary' => $this->buildSummary($overpaymentRows, $actions),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'source_payment_schedule_id' => [
                'required',
                'integer',
                Rule::exists('payment_schedules', 'id')->where(
                    fn ($query) => $query->where('book_id', (int) $request->input('book_id'))
                ),
            ],
            'action_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_liability_account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', (int) $request->input('book_id'))
                        ->where('category', 'liability')
                        ->where('is_active', true)
                ),
            ],
            'voucher_no' => ['nullable', 'string', 'max:20'],
            'description_text' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $sourceSchedule = $this->getSchedule($bookId, (int) $validated['source_payment_schedule_id']);
        $sourceSchedule = $this->attachTotals($sourceSchedule);
        $overpaymentAmount = $this->calculateOverpayment($sourceSchedule);
        $amount = round((float) $validated['amount'], 2);

        if ($amount > $overpaymentAmount) {
            throw ValidationException::withMessages([
                'amount' => '預り金へ振り替える金額は未処理の過入金額以下にしてください。未処理過入金額: ' . number_format($overpaymentAmount, 2),
            ]);
        }

        if ($sourceSchedule->paymentItem?->account_title_id === null) {
            throw ValidationException::withMessages([
                'source_payment_schedule_id' => '入金項目に会計科目が設定されていません。入金項目マスタを確認してください。',
            ]);
        }

        DB::transaction(function () use ($validated, $sourceSchedule, $bookId, $amount): void {
            $propertyId = $sourceSchedule->rentalContract?->property_id;
            $descriptionText = trim((string) ($validated['description_text'] ?? ''));

            if ($descriptionText === '') {
                $descriptionText = $this->makeDescriptionText($sourceSchedule);
            }

            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['action_on'],
                'voucher_no' => $validated['voucher_no'] ?: $this->makeVoucherNo($sourceSchedule),
                'description_text' => $descriptionText,
                'note' => trim((string) ($validated['note'] ?? '') . "\n過入金預り処理: 入金予定ID " . $sourceSchedule->id),
                'total_amount' => $amount,
                'entry_type' => 'overpayment_deposit',
                'status' => 'posted',
            ]);

            $journalEntry->lines()->createMany([
                [
                    'line_no' => 1,
                    'side' => 'debit',
                    'account_title_id' => $sourceSchedule->paymentItem->account_title_id,
                    'sub_account_title_id' => $sourceSchedule->paymentItem->sub_account_title_id,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $amount,
                    'line_note' => '過入金を収益から預り金へ振替',
                ],
                [
                    'line_no' => 2,
                    'side' => 'credit',
                    'account_title_id' => $validated['deposit_liability_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $amount,
                    'line_note' => '過入金預り',
                ],
            ]);

            PaymentReconciliationAction::query()->create([
                'book_id' => $bookId,
                'action_type' => 'overpayment_deposit',
                'source_payment_schedule_id' => $sourceSchedule->id,
                'target_payment_schedule_id' => null,
                'created_payment_schedule_id' => null,
                'payment_receipt_id' => null,
                'journal_entry_id' => $journalEntry->id,
                'action_on' => $validated['action_on'],
                'amount' => $amount,
                'status' => 'posted',
                'note' => $validated['note'] ?? null,
            ]);
        });

        return redirect()
            ->route('payment-overpayment-deposits.index', ['book_id' => $bookId])
            ->with('status', '過入金を預り金へ振り替える仕訳を作成しました。');
    }

    public function destroy(PaymentReconciliationAction $paymentReconciliationAction): RedirectResponse
    {
        $bookId = (int) $paymentReconciliationAction->book_id;

        if ($paymentReconciliationAction->action_type !== 'overpayment_deposit') {
            return redirect()
                ->route('payment-overpayment-deposits.index', ['book_id' => $bookId])
                ->with('error', '過入金預り処理以外はこの画面から取消できません。');
        }

        DB::transaction(function () use ($paymentReconciliationAction): void {
            $paymentReconciliationAction->load('journalEntry');

            if ($paymentReconciliationAction->journalEntry !== null) {
                if ($paymentReconciliationAction->journalEntry->entry_type !== 'overpayment_deposit') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => 'この仕訳は過入金預り仕訳ではないため、この画面からは取消できません。',
                    ]);
                }

                $paymentReconciliationAction->journalEntry->delete();
            }

            $paymentReconciliationAction->update([
                'journal_entry_id' => null,
                'status' => 'cancelled',
            ]);
        });

        return redirect()
            ->route('payment-overpayment-deposits.index', ['book_id' => $bookId])
            ->with('status', '過入金預り仕訳を取り消しました。');
    }

    private function buildOverpaymentRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = PaymentSchedule::query()
            ->with([
                'contractTenant',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'paymentItem.accountTitle',
                'paymentAccount',
            ])
            ->where('book_id', $bookId)
            ->where('status', '<>', 'cancelled')
            ->orderBy('due_on')
            ->orderBy('id');

        if (!empty($dateFrom)) {
            $query->whereDate('due_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('due_on', '<=', $dateTo);
        }

        return $query
            ->get()
            ->map(function (PaymentSchedule $schedule): object {
                $schedule = $this->attachTotals($schedule);
                $overpaymentAmount = $this->calculateOverpayment($schedule);

                return (object) [
                    'payment_schedule_id' => (int) $schedule->id,
                    'book_id' => (int) $schedule->book_id,
                    'due_on' => $schedule->due_on?->format('Y-m-d'),
                    'target_year_month' => $schedule->target_year_month,
                    'tenant_name' => $schedule->contractTenant?->name,
                    'property_name' => $schedule->rentalContract?->property?->name,
                    'unit_no' => $schedule->rentalContract?->propertyUnit?->unit_no,
                    'payment_item_name' => $schedule->paymentItem?->name,
                    'payment_account_name' => $schedule->paymentAccount?->name,
                    'revenue_account_name' => $schedule->paymentItem?->accountTitle?->name,
                    'expected_amount' => round((float) $schedule->expected_amount, 2),
                    'confirmed_received_amount' => round((float) $schedule->confirmed_received_total, 2),
                    'already_processed_amount' => round((float) $schedule->processed_overpayment_total, 2),
                    'overpayment_amount' => $overpaymentAmount,
                    'default_description' => $this->makeDescriptionText($schedule),
                ];
            })
            ->filter(fn (object $row) => $row->overpayment_amount > 0)
            ->values();
    }

    private function buildDepositActions(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = PaymentReconciliationAction::query()
            ->with([
                'sourcePaymentSchedule.contractTenant',
                'sourcePaymentSchedule.paymentItem',
                'journalEntry',
            ])
            ->where('book_id', $bookId)
            ->where('action_type', 'overpayment_deposit')
            ->orderByDesc('action_on')
            ->orderByDesc('id');

        if (!empty($dateFrom)) {
            $query->whereDate('action_on', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('action_on', '<=', $dateTo);
        }

        return $query->get();
    }

    private function getSchedule(int $bookId, int $scheduleId): PaymentSchedule
    {
        return PaymentSchedule::query()
            ->with([
                'contractTenant',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'paymentItem.accountTitle',
                'paymentAccount',
            ])
            ->where('book_id', $bookId)
            ->findOrFail($scheduleId);
    }

    private function attachTotals(PaymentSchedule $schedule): PaymentSchedule
    {
        $confirmedReceivedTotal = PaymentReceipt::query()
            ->where('payment_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->sum('amount');

        $processedOverpaymentTotal = PaymentReconciliationAction::query()
            ->where('source_payment_schedule_id', $schedule->id)
            ->whereIn('action_type', ['overpayment_application', 'overpayment_deposit'])
            ->where('status', 'posted')
            ->sum('amount');

        $schedule->confirmed_received_total = round((float) $confirmedReceivedTotal, 2);
        $schedule->processed_overpayment_total = round((float) $processedOverpaymentTotal, 2);

        return $schedule;
    }

    private function calculateOverpayment(PaymentSchedule $schedule): float
    {
        $expectedAmount = round((float) $schedule->expected_amount, 2);
        $confirmedReceivedTotal = round((float) $schedule->confirmed_received_total, 2);
        $processedOverpaymentTotal = round((float) $schedule->processed_overpayment_total, 2);

        return round(max($confirmedReceivedTotal - $expectedAmount - $processedOverpaymentTotal, 0), 2);
    }

    private function makeDescriptionText(PaymentSchedule $schedule): string
    {
        return mb_substr(
            '過入金預り / '
            . ($schedule->contractTenant?->name ?? '契約者不明')
            . ' / '
            . ($schedule->paymentItem?->name ?? '入金項目不明'),
            0,
            255
        );
    }

    private function makeVoucherNo(PaymentSchedule $schedule): string
    {
        $baseVoucherNo = 'OPD' . str_pad((string) $schedule->id, 7, '0', STR_PAD_LEFT);
        $voucherNo = $baseVoucherNo;
        $suffix = 1;

        while (JournalEntry::query()->where('book_id', $schedule->book_id)->where('voucher_no', $voucherNo)->exists()) {
            $voucherNo = mb_substr($baseVoucherNo, 0, 16) . '-' . $suffix;
            $suffix++;
        }

        return $voucherNo;
    }

    private function buildSummary(Collection $rows, Collection $actions): array
    {
        return [
            'overpayment_count' => $rows->count(),
            'overpayment_total' => round($rows->sum(fn (object $row) => (float) $row->overpayment_amount), 2),
            'actions_count' => $actions->count(),
            'posted_actions_count' => $actions->where('status', 'posted')->count(),
            'cancelled_actions_count' => $actions->where('status', 'cancelled')->count(),
            'posted_amount_total' => round($actions->where('status', 'posted')->sum(fn (PaymentReconciliationAction $action) => (float) $action->amount), 2),
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