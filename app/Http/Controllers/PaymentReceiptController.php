<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentAccount;
use App\Models\PaymentReceipt;
use App\Models\PaymentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $paymentReceiptsQuery = PaymentReceipt::query()
            ->with([
                'book.businessOwner',
                'paymentSchedule',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem',
                'paymentAccount',
            ])
            ->orderBy('book_id')
            ->orderByDesc('received_on')
            ->orderByDesc('id');

        if ($selectedBookId !== null) {
            $paymentReceiptsQuery->where('book_id', $selectedBookId);
        }

        $paymentReceipts = $paymentReceiptsQuery->get();

        return view('payment_receipts.index', [
            'books' => $books,
            'paymentReceipts' => $paymentReceipts,
            'selectedBookId' => $selectedBookId,
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

        return view('payment_receipts.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'paymentReceipt' => null,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);

        DB::transaction(function () use ($validated, $bookId): void {
            $schedule = PaymentSchedule::query()
                ->where('book_id', $bookId)
                ->findOrFail($validated['payment_schedule_id']);

            PaymentReceipt::create([
                'book_id' => $bookId,
                'payment_schedule_id' => $schedule->id,
                'rental_contract_id' => $schedule->rental_contract_id,
                'contract_tenant_id' => $schedule->contract_tenant_id,
                'payment_item_id' => $schedule->payment_item_id,
                'payment_account_id' => $validated['payment_account_id'] ?? $schedule->payment_account_id,
                'received_on' => $validated['received_on'],
                'amount' => $validated['amount'],
                'payer_name' => $validated['payer_name'] ?? null,
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->recalculateScheduleStatus((int) $schedule->id);
        });

        return redirect()
            ->route('payment-receipts.index', ['book_id' => $bookId])
            ->with('status', '入金を登録しました。');
    }

    public function edit(PaymentReceipt $paymentReceipt): View
    {
        $bookId = (int) $paymentReceipt->book_id;

        $books = $this->getSelectableBooks($bookId);
        $selectedBook = $books->firstWhere('id', $bookId);

        return view('payment_receipts.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $bookId,
            'paymentReceipt' => $paymentReceipt,
        ], $this->loadFormMasterData($bookId)));
    }

    public function update(Request $request, PaymentReceipt $paymentReceipt): RedirectResponse
    {
        $bookId = (int) $paymentReceipt->book_id;
        $oldScheduleId = (int) $paymentReceipt->payment_schedule_id;

        $validated = $this->validatePayload($request, $bookId);

        DB::transaction(function () use ($paymentReceipt, $validated, $bookId, $oldScheduleId): void {
            $schedule = PaymentSchedule::query()
                ->where('book_id', $bookId)
                ->findOrFail($validated['payment_schedule_id']);

            $paymentReceipt->update([
                'book_id' => $bookId,
                'payment_schedule_id' => $schedule->id,
                'rental_contract_id' => $schedule->rental_contract_id,
                'contract_tenant_id' => $schedule->contract_tenant_id,
                'payment_item_id' => $schedule->payment_item_id,
                'payment_account_id' => $validated['payment_account_id'] ?? $schedule->payment_account_id,
                'received_on' => $validated['received_on'],
                'amount' => $validated['amount'],
                'payer_name' => $validated['payer_name'] ?? null,
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
            ]);

            $this->recalculateScheduleStatus($oldScheduleId);
            $this->recalculateScheduleStatus((int) $schedule->id);
        });

        return redirect()
            ->route('payment-receipts.index', ['book_id' => $bookId])
            ->with('status', '入金を更新しました。');
    }

    public function destroy(PaymentReceipt $paymentReceipt): RedirectResponse
    {
        $bookId = (int) $paymentReceipt->book_id;
        $scheduleId = (int) $paymentReceipt->payment_schedule_id;

        DB::transaction(function () use ($paymentReceipt, $scheduleId): void {
            $paymentReceipt->delete();
            $this->recalculateScheduleStatus($scheduleId);
        });

        return redirect()
            ->route('payment-receipts.index', ['book_id' => $bookId])
            ->with('status', '入金を削除しました。');
    }

    private function validatePayload(Request $request, int $bookId): array
    {
        return $request->validate([
            'payment_schedule_id' => [
                'required',
                'integer',
                Rule::exists('payment_schedules', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_accounts', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'received_on' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'status' => ['required', 'in:confirmed,cancelled'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function recalculateScheduleStatus(int $scheduleId): void
    {
        $schedule = PaymentSchedule::query()->find($scheduleId);

        if ($schedule === null) {
            return;
        }

        if ($schedule->status === 'cancelled') {
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

    private function loadFormMasterData(int $bookId): array
    {
        return [
            'paymentSchedules' => PaymentSchedule::query()
                ->with(['contractTenant', 'rentalContract.property', 'rentalContract.propertyUnit', 'paymentItem'])
                ->where('book_id', $bookId)
                ->orderBy('due_on')
                ->orderBy('id')
                ->get(),
            'paymentAccounts' => PaymentAccount::query()
                ->where('book_id', $bookId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get(),
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'paymentSchedules' => collect(),
            'paymentAccounts' => collect(),
        ];
    }

    private function getSelectableBooks(?int $selectedBookId = null)
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