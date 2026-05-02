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

class PaymentOverpaymentDepositApplicationController extends Controller
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

        $depositRows = collect();
        $targetSchedules = collect();
        $liabilityAccountTitles = collect();
        $actions = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;

            $depositRows = $this->buildDepositRows($bookId, $dateFrom, $dateTo);
            $targetSchedules = $this->buildTargetSchedules($bookId);
            $liabilityAccountTitles = AccountTitle::query()
                ->where('book_id', $bookId)
                ->where('category', 'liability')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get();

            $actions = $this->buildApplicationActions($bookId, $dateFrom, $dateTo);
        }

        return view('payment_overpayment_deposit_applications.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'depositRows' => $depositRows,
            'targetSchedules' => $targetSchedules,
            'liabilityAccountTitles' => $liabilityAccountTitles,
            'actions' => $actions,
            'summary' => $this->buildSummary($depositRows, $actions),
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
            'target_payment_schedule_id' => [
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
        $targetSchedule = $this->getSchedule($bookId, (int) $validated['target_payment_schedule_id']);

        if ((int) $sourceSchedule->id === (int) $targetSchedule->id) {
            throw ValidationException::withMessages([
                'target_payment_schedule_id' => '預り金の充当先には、元の入金予定とは別の入金予定を選択してください。',
            ]);
        }

        $sourceSchedule = $this->attachDepositTotals($sourceSchedule);
        $targetSchedule = $this->attachReceiptTotals($targetSchedule);

        $depositRemaining = $this->calculateDepositRemaining($sourceSchedule);
        $targetRemaining = $this->calculateTargetRemaining($targetSchedule);
        $amount = round((float) $validated['amount'], 2);

        if ($amount > $depositRemaining) {
            throw ValidationException::withMessages([
                'amount' => '充当額は未処理の預り金残高以下にしてください。未処理預り金残高: ' . number_format($depositRemaining, 2),
            ]);
        }

        if ($amount > $targetRemaining) {
            throw ValidationException::withMessages([
                'amount' => '充当額は充当先の不足額以下にしてください。充当先不足額: ' . number_format($targetRemaining, 2),
            ]);
        }

        if ($targetSchedule->paymentItem?->account_title_id === null) {
            throw ValidationException::withMessages([
                'target_payment_schedule_id' => '充当先の入金項目に会計科目が設定されていません。入金項目マスタを確認してください。',
            ]);
        }

        DB::transaction(function () use ($validated, $sourceSchedule, $targetSchedule, $bookId, $amount): void {
            $propertyId = $targetSchedule->rentalContract?->property_id;
            $descriptionText = trim((string) ($validated['description_text'] ?? ''));

            if ($descriptionText === '') {
                $descriptionText = $this->makeDescriptionText($sourceSchedule, $targetSchedule);
            }

            $journalEntry = JournalEntry::query()->create([
                'book_id' => $bookId,
                'journal_description_id' => null,
                'entry_date' => $validated['action_on'],
                'voucher_no' => $validated['voucher_no'] ?: $this->makeVoucherNo($sourceSchedule, $targetSchedule),
                'description_text' => $descriptionText,
                'note' => trim((string) ($validated['note'] ?? '') . "\n預り金充当: 元入金予定ID " . $sourceSchedule->id . ' / 充当先入金予定ID ' . $targetSchedule->id),
                'total_amount' => $amount,
                'entry_type' => 'overpayment_deposit_application',
                'status' => 'posted',
            ]);

            $journalEntry->lines()->createMany([
                [
                    'line_no' => 1,
                    'side' => 'debit',
                    'account_title_id' => $validated['deposit_liability_account_title_id'],
                    'sub_account_title_id' => null,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $amount,
                    'line_note' => '過入金預り金の取崩',
                ],
                [
                    'line_no' => 2,
                    'side' => 'credit',
                    'account_title_id' => $targetSchedule->paymentItem->account_title_id,
                    'sub_account_title_id' => $targetSchedule->paymentItem->sub_account_title_id,
                    'department_id' => null,
                    'property_id' => $propertyId,
                    'amount' => $amount,
                    'line_note' => '預り金を入金予定へ充当',
                ],
            ]);

            $receipt = PaymentReceipt::query()->create([
                'book_id' => $bookId,
                'payment_schedule_id' => $targetSchedule->id,
                'rental_contract_id' => $targetSchedule->rental_contract_id,
                'contract_tenant_id' => $targetSchedule->contract_tenant_id,
                'payment_item_id' => $targetSchedule->payment_item_id,
                'payment_account_id' => $targetSchedule->payment_account_id ?? $sourceSchedule->payment_account_id,
                'journal_entry_id' => $journalEntry->id,
                'received_on' => $validated['action_on'],
                'amount' => $amount,
                'payer_name' => '預り金充当',
                'status' => 'confirmed',
                'note' => trim('過入金預り金から充当 / 元入金予定ID ' . $sourceSchedule->id . "\n" . (string) ($validated['note'] ?? '')),
            ]);

            PaymentReconciliationAction::query()->create([
                'book_id' => $bookId,
                'action_type' => 'deposit_application',
                'source_payment_schedule_id' => $sourceSchedule->id,
                'target_payment_schedule_id' => $targetSchedule->id,
                'created_payment_schedule_id' => null,
                'payment_receipt_id' => $receipt->id,
                'journal_entry_id' => $journalEntry->id,
                'action_on' => $validated['action_on'],
                'amount' => $amount,
                'status' => 'posted',
                'note' => $validated['note'] ?? null,
            ]);

            $this->recalculateScheduleStatus((int) $targetSchedule->id);
        });

        return redirect()
            ->route('payment-overpayment-deposit-applications.index', ['book_id' => $bookId])
            ->with('status', '預り金を入金予定へ充当しました。');
    }

    public function destroy(PaymentReconciliationAction $paymentReconciliationAction): RedirectResponse
    {
        $bookId = (int) $paymentReconciliationAction->book_id;

        if ($paymentReconciliationAction->action_type !== 'deposit_application') {
            return redirect()
                ->route('payment-overpayment-deposit-applications.index', ['book_id' => $bookId])
                ->with('error', '預り金充当処理以外はこの画面から取消できません。');
        }

        DB::transaction(function () use ($paymentReconciliationAction): void {
            $paymentReconciliationAction->load(['paymentReceipt', 'journalEntry']);
            $targetScheduleId = $paymentReconciliationAction->paymentReceipt?->payment_schedule_id
                ?? $paymentReconciliationAction->target_payment_schedule_id;

            if ($paymentReconciliationAction->paymentReceipt !== null) {
                $paymentReconciliationAction->paymentReceipt->delete();
            }

            if ($paymentReconciliationAction->journalEntry !== null) {
                if ($paymentReconciliationAction->journalEntry->entry_type !== 'overpayment_deposit_application') {
                    throw ValidationException::withMessages([
                        'journal_entry_id' => 'この仕訳は預り金充当仕訳ではないため、この画面からは取消できません。',
                    ]);
                }

                $paymentReconciliationAction->journalEntry->delete();
            }

            $paymentReconciliationAction->update([
                'payment_receipt_id' => null,
                'journal_entry_id' => null,
                'status' => 'cancelled',
            ]);

            if ($targetScheduleId !== null) {
                $this->recalculateScheduleStatus((int) $targetScheduleId);
            }
        });

        return redirect()
            ->route('payment-overpayment-deposit-applications.index', ['book_id' => $bookId])
            ->with('status', '預り金充当処理を取り消しました。');
    }

    private function buildDepositRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $sourceScheduleIds = PaymentReconciliationAction::query()
            ->where('book_id', $bookId)
            ->where('action_type', 'overpayment_deposit')
            ->where('status', 'posted')
            ->when(!empty($dateFrom), fn ($query) => $query->whereDate('action_on', '>=', $dateFrom))
            ->when(!empty($dateTo), fn ($query) => $query->whereDate('action_on', '<=', $dateTo))
            ->pluck('source_payment_schedule_id')
            ->unique()
            ->values();

        if ($sourceScheduleIds->isEmpty()) {
            return collect();
        }

        return PaymentSchedule::query()
            ->with([
                'contractTenant',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'paymentItem',
                'paymentAccount',
            ])
            ->where('book_id', $bookId)
            ->whereIn('id', $sourceScheduleIds)
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(function (PaymentSchedule $schedule): object {
                $schedule = $this->attachDepositTotals($schedule);

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
                    'deposited_amount' => round((float) $schedule->deposited_amount, 2),
                    'applied_amount' => round((float) $schedule->deposit_application_amount, 2),
                    'remaining_deposit_amount' => $this->calculateDepositRemaining($schedule),
                    'default_description' => '預り金充当 / ' . ($schedule->contractTenant?->name ?? '契約者不明'),
                ];
            })
            ->filter(fn (object $row) => $row->remaining_deposit_amount > 0)
            ->values();
    }

    private function buildTargetSchedules(int $bookId): Collection
    {
        return PaymentSchedule::query()
            ->with(['contractTenant', 'rentalContract.property', 'rentalContract.propertyUnit', 'paymentItem'])
            ->where('book_id', $bookId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->where('status', '<>', 'cancelled')
            ->orderBy('due_on')
            ->orderBy('id')
            ->get()
            ->map(function (PaymentSchedule $schedule): object {
                $schedule = $this->attachReceiptTotals($schedule);
                $remaining = $this->calculateTargetRemaining($schedule);

                return (object) [
                    'id' => (int) $schedule->id,
                    'label' => ($schedule->due_on?->format('Y-m-d') ?? '予定日なし')
                        . ' / '
                        . ($schedule->contractTenant?->name ?? '契約者不明')
                        . ' / '
                        . ($schedule->rentalContract?->property?->name ?? '物件不明')
                        . ' / '
                        . ($schedule->paymentItem?->name ?? '項目不明')
                        . ' / 不足 '
                        . number_format($remaining, 0),
                    'remaining_amount' => $remaining,
                ];
            })
            ->filter(fn (object $row) => $row->remaining_amount > 0)
            ->values();
    }

    private function buildApplicationActions(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        return PaymentReconciliationAction::query()
            ->with([
                'sourcePaymentSchedule.contractTenant',
                'targetPaymentSchedule.contractTenant',
                'targetPaymentSchedule.paymentItem',
                'paymentReceipt',
                'journalEntry',
            ])
            ->where('book_id', $bookId)
            ->where('action_type', 'deposit_application')
            ->when(!empty($dateFrom), fn ($query) => $query->whereDate('action_on', '>=', $dateFrom))
            ->when(!empty($dateTo), fn ($query) => $query->whereDate('action_on', '<=', $dateTo))
            ->orderByDesc('action_on')
            ->orderByDesc('id')
            ->get();
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

    private function attachDepositTotals(PaymentSchedule $schedule): PaymentSchedule
    {
        $depositedAmount = PaymentReconciliationAction::query()
            ->where('source_payment_schedule_id', $schedule->id)
            ->where('action_type', 'overpayment_deposit')
            ->where('status', 'posted')
            ->sum('amount');

        $applicationAmount = PaymentReconciliationAction::query()
            ->where('source_payment_schedule_id', $schedule->id)
            ->where('action_type', 'deposit_application')
            ->where('status', 'posted')
            ->sum('amount');

        $schedule->deposited_amount = round((float) $depositedAmount, 2);
        $schedule->deposit_application_amount = round((float) $applicationAmount, 2);

        return $schedule;
    }

    private function attachReceiptTotals(PaymentSchedule $schedule): PaymentSchedule
    {
        $confirmedReceivedTotal = PaymentReceipt::query()
            ->where('payment_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->sum('amount');

        $schedule->confirmed_received_total = round((float) $confirmedReceivedTotal, 2);

        return $schedule;
    }

    private function calculateDepositRemaining(PaymentSchedule $schedule): float
    {
        return round(max((float) $schedule->deposited_amount - (float) $schedule->deposit_application_amount, 0), 2);
    }

    private function calculateTargetRemaining(PaymentSchedule $schedule): float
    {
        return round(max((float) $schedule->expected_amount - (float) $schedule->confirmed_received_total, 0), 2);
    }

    private function recalculateScheduleStatus(int $scheduleId): void
    {
        $schedule = PaymentSchedule::query()->find($scheduleId);

        if ($schedule === null || $schedule->status === 'cancelled') {
            return;
        }

        $receivedAmount = PaymentReceipt::query()
            ->where('payment_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->sum('amount');

        $receivedAmount = round((float) $receivedAmount, 2);
        $expectedAmount = round((float) $schedule->expected_amount, 2);

        if ($receivedAmount <= 0) {
            $status = 'unpaid';
        } elseif ($receivedAmount < $expectedAmount) {
            $status = 'partial';
        } else {
            $status = 'paid';
        }

        $schedule->update([
            'received_amount' => $receivedAmount,
            'status' => $status,
        ]);
    }

    private function makeDescriptionText(PaymentSchedule $sourceSchedule, PaymentSchedule $targetSchedule): string
    {
        return mb_substr(
            '預り金充当 / '
            . ($sourceSchedule->contractTenant?->name ?? '契約者不明')
            . ' → '
            . ($targetSchedule->paymentItem?->name ?? '入金項目不明'),
            0,
            255
        );
    }

    private function makeVoucherNo(PaymentSchedule $sourceSchedule, PaymentSchedule $targetSchedule): string
    {
        $baseVoucherNo = 'OPA' . str_pad((string) $sourceSchedule->id, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string) $targetSchedule->id, 4, '0', STR_PAD_LEFT);
        $voucherNo = mb_substr($baseVoucherNo, 0, 20);
        $suffix = 1;

        while (JournalEntry::query()->where('book_id', $sourceSchedule->book_id)->where('voucher_no', $voucherNo)->exists()) {
            $voucherNo = mb_substr($baseVoucherNo, 0, 16) . '-' . $suffix;
            $suffix++;
        }

        return $voucherNo;
    }

    private function buildSummary(Collection $depositRows, Collection $actions): array
    {
        return [
            'deposit_source_count' => $depositRows->count(),
            'remaining_deposit_total' => round($depositRows->sum(fn (object $row) => (float) $row->remaining_deposit_amount), 2),
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