<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentReceipt;
use App\Models\PaymentReconciliationAction;
use App\Models\PaymentSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentReconciliationActionController extends Controller
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

        $shortageRows = collect();
        $overpaymentRows = collect();
        $targetSchedules = collect();
        $actions = collect();

        if ($selectedBook !== null) {
            $bookId = (int) $selectedBook->id;
            $shortageRows = $this->buildReconciliationRows($bookId, $dateFrom, $dateTo)
                ->filter(fn (object $row) => $row->remaining_after_carryover > 0)
                ->values();

            $overpaymentRows = $this->buildReconciliationRows($bookId, $dateFrom, $dateTo)
                ->filter(fn (object $row) => $row->overpaid_after_application > 0)
                ->values();

            $targetSchedules = $this->buildTargetSchedules($bookId);
            $actions = $this->buildActionRows($bookId, $dateFrom, $dateTo);
        }

        return view('payment_reconciliation_actions.index', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'shortageRows' => $shortageRows,
            'overpaymentRows' => $overpaymentRows,
            'targetSchedules' => $targetSchedules,
            'actions' => $actions,
            'summary' => $this->buildSummary($shortageRows, $overpaymentRows, $actions),
            'actionTypeLabels' => PaymentReconciliationAction::ACTION_TYPES,
            'statusLabels' => PaymentReconciliationAction::STATUSES,
        ]);
    }

    public function carryoverShortage(Request $request): RedirectResponse
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
            'target_year_month' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'due_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $sourceSchedule = $this->getScheduleWithConfirmedReceipts($bookId, (int) $validated['source_payment_schedule_id']);
        $remaining = $this->calculateRemainingAfterCarryover($sourceSchedule);

        $amount = round((float) $validated['amount'], 2);

        if ($amount > $remaining) {
            throw ValidationException::withMessages([
                'amount' => '繰越額は未処理の不足額以下にしてください。未処理不足額: ' . number_format($remaining, 2),
            ]);
        }

        DB::transaction(function () use ($validated, $sourceSchedule, $bookId, $amount): void {
            $createdSchedule = PaymentSchedule::query()->create([
                'book_id' => $bookId,
                'rental_contract_id' => $sourceSchedule->rental_contract_id,
                'contract_tenant_id' => $sourceSchedule->contract_tenant_id,
                'payment_item_id' => $sourceSchedule->payment_item_id,
                'payment_account_id' => $sourceSchedule->payment_account_id,
                'target_year_month' => $validated['target_year_month'],
                'due_on' => $validated['due_on'],
                'expected_amount' => $amount,
                'received_amount' => 0,
                'status' => 'unpaid',
                'note' => trim('不足額繰越元: 入金予定ID ' . $sourceSchedule->id . "\n" . (string) ($validated['note'] ?? '')),
            ]);

            PaymentReconciliationAction::query()->create([
                'book_id' => $bookId,
                'action_type' => 'shortage_carryover',
                'source_payment_schedule_id' => $sourceSchedule->id,
                'target_payment_schedule_id' => null,
                'created_payment_schedule_id' => $createdSchedule->id,
                'payment_receipt_id' => null,
                'action_on' => now()->format('Y-m-d'),
                'amount' => $amount,
                'status' => 'posted',
                'note' => $validated['note'] ?? null,
            ]);
        });

        return redirect()
            ->route('payment-reconciliation-actions.index', ['book_id' => $bookId])
            ->with('status', '不足額を翌月以降の入金予定として繰り越しました。');
    }

    public function applyOverpayment(Request $request): RedirectResponse
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
            'note' => ['nullable', 'string'],
        ]);

        $bookId = (int) $validated['book_id'];
        $sourceSchedule = $this->getScheduleWithConfirmedReceipts($bookId, (int) $validated['source_payment_schedule_id']);
        $targetSchedule = $this->getScheduleWithConfirmedReceipts($bookId, (int) $validated['target_payment_schedule_id']);

        if ((int) $sourceSchedule->id === (int) $targetSchedule->id) {
            throw ValidationException::withMessages([
                'target_payment_schedule_id' => '過入金の充当先には、元の入金予定とは別の入金予定を選択してください。',
            ]);
        }

        $overpayment = $this->calculateOverpaymentAfterApplication($sourceSchedule);
        $targetShortage = $this->calculateRemainingAfterCarryover($targetSchedule);
        $amount = round((float) $validated['amount'], 2);

        if ($amount > $overpayment) {
            throw ValidationException::withMessages([
                'amount' => '充当額は未処理の過入金額以下にしてください。未処理過入金額: ' . number_format($overpayment, 2),
            ]);
        }

        if ($amount > $targetShortage) {
            throw ValidationException::withMessages([
                'amount' => '充当額は充当先の不足額以下にしてください。充当先不足額: ' . number_format($targetShortage, 2),
            ]);
        }

        DB::transaction(function () use ($validated, $sourceSchedule, $targetSchedule, $bookId, $amount): void {
            $receipt = PaymentReceipt::query()->create([
                'book_id' => $bookId,
                'payment_schedule_id' => $targetSchedule->id,
                'rental_contract_id' => $targetSchedule->rental_contract_id,
                'contract_tenant_id' => $targetSchedule->contract_tenant_id,
                'payment_item_id' => $targetSchedule->payment_item_id,
                'payment_account_id' => $targetSchedule->payment_account_id ?? $sourceSchedule->payment_account_id,
                'received_on' => $validated['action_on'],
                'amount' => $amount,
                'payer_name' => '過入金充当',
                'status' => 'confirmed',
                'note' => trim('過入金充当元: 入金予定ID ' . $sourceSchedule->id . "\n" . (string) ($validated['note'] ?? '')),
            ]);

            PaymentReconciliationAction::query()->create([
                'book_id' => $bookId,
                'action_type' => 'overpayment_application',
                'source_payment_schedule_id' => $sourceSchedule->id,
                'target_payment_schedule_id' => $targetSchedule->id,
                'created_payment_schedule_id' => null,
                'payment_receipt_id' => $receipt->id,
                'action_on' => $validated['action_on'],
                'amount' => $amount,
                'status' => 'posted',
                'note' => $validated['note'] ?? null,
            ]);

            $this->recalculateScheduleStatus((int) $targetSchedule->id);
        });

        return redirect()
            ->route('payment-reconciliation-actions.index', ['book_id' => $bookId])
            ->with('status', '過入金を別の入金予定へ充当しました。');
    }

    public function destroy(PaymentReconciliationAction $paymentReconciliationAction): RedirectResponse
    {
        $bookId = (int) $paymentReconciliationAction->book_id;

        DB::transaction(function () use ($paymentReconciliationAction): void {
            if ($paymentReconciliationAction->status === 'cancelled') {
                return;
            }

            if ($paymentReconciliationAction->paymentReceipt !== null) {
                if ($paymentReconciliationAction->paymentReceipt->journal_entry_id !== null) {
                    throw ValidationException::withMessages([
                        'payment_receipt_id' => '充当入金が仕訳作成済みのため、先に賃貸入金仕訳を取り消してください。',
                    ]);
                }

                $targetScheduleId = (int) $paymentReconciliationAction->paymentReceipt->payment_schedule_id;
                $paymentReconciliationAction->paymentReceipt->delete();
                $this->recalculateScheduleStatus($targetScheduleId);
            }

            if ($paymentReconciliationAction->createdPaymentSchedule !== null) {
                if ($paymentReconciliationAction->createdPaymentSchedule->receipts()->exists()) {
                    throw ValidationException::withMessages([
                        'created_payment_schedule_id' => '繰越先の入金予定に入金実績があるため、取消できません。',
                    ]);
                }

                $paymentReconciliationAction->createdPaymentSchedule->delete();
            }

            $paymentReconciliationAction->update([
                'status' => 'cancelled',
            ]);
        });

        return redirect()
            ->route('payment-reconciliation-actions.index', ['book_id' => $bookId])
            ->with('status', '入金差額処理を取り消しました。');
    }

    private function buildReconciliationRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = PaymentSchedule::query()
            ->with([
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem',
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
                $schedule = $this->attachReceiptAndActionTotals($schedule);

                $expectedAmount = round((float) $schedule->expected_amount, 2);
                $confirmedReceivedAmount = round((float) $schedule->confirmed_received_total, 2);
                $outgoingApplicationAmount = round((float) $schedule->outgoing_application_total, 2);
                $shortageCarryoverAmount = round((float) $schedule->shortage_carryover_total, 2);
                $netReceivedAmount = round(max($confirmedReceivedAmount - $outgoingApplicationAmount, 0), 2);
                $remainingAmount = round(max($expectedAmount - $netReceivedAmount, 0), 2);
                $overpaymentAmount = round(max($netReceivedAmount - $expectedAmount, 0), 2);

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
                    'expected_amount' => $expectedAmount,
                    'confirmed_received_amount' => $confirmedReceivedAmount,
                    'outgoing_application_amount' => $outgoingApplicationAmount,
                    'net_received_amount' => $netReceivedAmount,
                    'remaining_amount' => $remainingAmount,
                    'shortage_carryover_amount' => $shortageCarryoverAmount,
                    'remaining_after_carryover' => round(max($remainingAmount - $shortageCarryoverAmount, 0), 2),
                    'overpaid_amount' => $overpaymentAmount,
                    'overpaid_after_application' => $overpaymentAmount,
                    'default_next_year_month' => $this->nextYearMonth($schedule->target_year_month ?: $schedule->due_on?->format('Y-m')),
                    'default_next_due_on' => $this->nextDueOn($schedule->due_on?->format('Y-m-d')),
                ];
            });
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
                $schedule = $this->attachReceiptAndActionTotals($schedule);

                $expectedAmount = round((float) $schedule->expected_amount, 2);
                $confirmedReceivedAmount = round((float) $schedule->confirmed_received_total, 2);
                $remaining = round(max($expectedAmount - $confirmedReceivedAmount, 0), 2);

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

    private function buildActionRows(int $bookId, ?string $dateFrom, ?string $dateTo): Collection
    {
        $query = PaymentReconciliationAction::query()
            ->with([
                'sourcePaymentSchedule.contractTenant',
                'targetPaymentSchedule.contractTenant',
                'createdPaymentSchedule',
                'paymentReceipt',
            ])
            ->where('book_id', $bookId)
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

    private function getScheduleWithConfirmedReceipts(int $bookId, int $scheduleId): PaymentSchedule
    {
        $schedule = PaymentSchedule::query()
            ->with(['receipts', 'contractTenant', 'paymentItem'])
            ->where('book_id', $bookId)
            ->findOrFail($scheduleId);

        return $this->attachReceiptAndActionTotals($schedule);
    }

    private function attachReceiptAndActionTotals(PaymentSchedule $schedule): PaymentSchedule
    {
        $confirmedReceivedTotal = PaymentReceipt::query()
            ->where('payment_schedule_id', $schedule->id)
            ->where('status', 'confirmed')
            ->sum('amount');

        $outgoingApplicationTotal = PaymentReconciliationAction::query()
            ->where('source_payment_schedule_id', $schedule->id)
            ->where('action_type', 'overpayment_application')
            ->where('status', 'posted')
            ->sum('amount');

        $shortageCarryoverTotal = PaymentReconciliationAction::query()
            ->where('source_payment_schedule_id', $schedule->id)
            ->where('action_type', 'shortage_carryover')
            ->where('status', 'posted')
            ->sum('amount');

        $schedule->confirmed_received_total = round((float) $confirmedReceivedTotal, 2);
        $schedule->outgoing_application_total = round((float) $outgoingApplicationTotal, 2);
        $schedule->shortage_carryover_total = round((float) $shortageCarryoverTotal, 2);

        return $schedule;
    }

    private function calculateRemainingAfterCarryover(PaymentSchedule $schedule): float
    {
        $expected = round((float) $schedule->expected_amount, 2);
        $received = round((float) $schedule->confirmed_received_total - (float) $schedule->outgoing_application_total, 2);
        $remaining = round(max($expected - $received, 0), 2);

        return round(max($remaining - (float) $schedule->shortage_carryover_total, 0), 2);
    }

    private function calculateOverpaymentAfterApplication(PaymentSchedule $schedule): float
    {
        $expected = round((float) $schedule->expected_amount, 2);
        $received = round((float) $schedule->confirmed_received_total - (float) $schedule->outgoing_application_total, 2);

        return round(max($received - $expected, 0), 2);
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

    private function nextYearMonth(?string $yearMonth): string
    {
        if (empty($yearMonth)) {
            return now()->addMonthNoOverflow()->format('Y-m');
        }

        return CarbonImmutable::createFromFormat('Y-m-d', $yearMonth . '-01')
            ->addMonthNoOverflow()
            ->format('Y-m');
    }

    private function nextDueOn(?string $dueOn): string
    {
        if (empty($dueOn)) {
            return now()->addMonthNoOverflow()->format('Y-m-d');
        }

        return CarbonImmutable::parse($dueOn)
            ->addMonthNoOverflow()
            ->format('Y-m-d');
    }

    private function buildSummary(Collection $shortageRows, Collection $overpaymentRows, Collection $actions): array
    {
        return [
            'shortage_count' => $shortageRows->count(),
            'shortage_total' => round($shortageRows->sum(fn (object $row) => (float) $row->remaining_after_carryover), 2),
            'overpayment_count' => $overpaymentRows->count(),
            'overpayment_total' => round($overpaymentRows->sum(fn (object $row) => (float) $row->overpaid_after_application), 2),
            'actions_count' => $actions->count(),
            'posted_actions_count' => $actions->where('status', 'posted')->count(),
            'cancelled_actions_count' => $actions->where('status', 'cancelled')->count(),
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