<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PaymentAccount;
use App\Models\PaymentItem;
use App\Models\PaymentSchedule;
use App\Models\RentalContract;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $paymentSchedulesQuery = PaymentSchedule::query()
            ->with([
                'book.businessOwner',
                'rentalContract.property',
                'rentalContract.propertyUnit',
                'contractTenant',
                'paymentItem',
                'paymentAccount',
            ])
            ->orderBy('book_id')
            ->orderBy('due_on')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $paymentSchedulesQuery->where('book_id', $selectedBookId);
        }

        $paymentSchedules = $paymentSchedulesQuery->get();

        return view('payment_schedules.index', [
            'books' => $books,
            'paymentSchedules' => $paymentSchedules,
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

        return view('payment_schedules.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'paymentSchedule' => null,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);
        $this->fillDerivedContractColumns($validated, $bookId);
        $this->normalizeAmountsAndStatus($validated);

        PaymentSchedule::create($validated);

        return redirect()
            ->route('payment-schedules.index', ['book_id' => $bookId])
            ->with('status', '入金予定を登録しました。');
    }

    public function edit(PaymentSchedule $paymentSchedule): View
    {
        $bookId = (int) $paymentSchedule->book_id;

        $books = $this->getSelectableBooks($bookId);
        $selectedBook = $books->firstWhere('id', $bookId);

        return view('payment_schedules.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $bookId,
            'paymentSchedule' => $paymentSchedule,
        ], $this->loadFormMasterData($bookId)));
    }

    public function update(Request $request, PaymentSchedule $paymentSchedule): RedirectResponse
    {
        $bookId = (int) $paymentSchedule->book_id;

        $validated = $this->validatePayload($request, $bookId, $paymentSchedule);
        $this->fillDerivedContractColumns($validated, $bookId);
        $this->normalizeAmountsAndStatus($validated);

        $paymentSchedule->update($validated);

        return redirect()
            ->route('payment-schedules.index', ['book_id' => $bookId])
            ->with('status', '入金予定を更新しました。');
    }

    public function destroy(PaymentSchedule $paymentSchedule): RedirectResponse
    {
        $bookId = (int) $paymentSchedule->book_id;

        if ($paymentSchedule->receipts()->exists()) {
            return redirect()
                ->route('payment-schedules.index', ['book_id' => $bookId])
                ->with('error', 'この入金予定には入金実績があるため削除できません。');
        }

        $paymentSchedule->delete();

        return redirect()
            ->route('payment-schedules.index', ['book_id' => $bookId])
            ->with('status', '入金予定を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?PaymentSchedule $paymentSchedule = null
    ): array {
        $uniqueScheduleRule = Rule::unique('payment_schedules', 'payment_item_id')
            ->where(fn ($query) => $query
                ->where('rental_contract_id', $request->input('rental_contract_id'))
                ->where('due_on', $request->input('due_on')));

        if ($paymentSchedule !== null) {
            $uniqueScheduleRule = $uniqueScheduleRule->ignore($paymentSchedule->id);
        }

        return $request->validate([
            'rental_contract_id' => [
                'required',
                'integer',
                Rule::exists('rental_contracts', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'payment_item_id' => [
                'required',
                'integer',
                Rule::exists('payment_items', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
                $uniqueScheduleRule,
            ],
            'payment_account_id' => [
                'nullable',
                'integer',
                Rule::exists('payment_accounts', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'target_year_month' => ['required', 'regex:/^\d{4}\-\d{2}$/'],
            'due_on' => ['required', 'date'],
            'expected_amount' => ['required', 'numeric', 'min:0'],
            'received_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:unpaid,partial,paid,cancelled'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function fillDerivedContractColumns(array &$validated, int $bookId): void
    {
        $rentalContract = RentalContract::query()
            ->where('book_id', $bookId)
            ->findOrFail($validated['rental_contract_id']);

        $validated['book_id'] = $bookId;
        $validated['contract_tenant_id'] = $rentalContract->contract_tenant_id;
    }

    private function normalizeAmountsAndStatus(array &$validated): void
    {
        $validated['received_amount'] = $validated['received_amount'] ?? 0;

        if ($validated['status'] !== 'cancelled') {
            if ((float) $validated['received_amount'] <= 0) {
                $validated['status'] = 'unpaid';
            } elseif ((float) $validated['received_amount'] < (float) $validated['expected_amount']) {
                $validated['status'] = 'partial';
            } else {
                $validated['status'] = 'paid';
            }
        }
    }

    private function loadFormMasterData(int $bookId): array
    {
        return [
            'rentalContracts' => RentalContract::query()
                ->with(['contractTenant', 'property', 'propertyUnit'])
                ->where('book_id', $bookId)
                ->orderBy('id')
                ->get(),
            'paymentItems' => PaymentItem::query()
                ->where('book_id', $bookId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('item_code')
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
            'rentalContracts' => collect(),
            'paymentItems' => collect(),
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