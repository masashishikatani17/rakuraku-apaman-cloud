<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\PaymentAccount;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentAccountController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $paymentAccountsQuery = PaymentAccount::query()
            ->with(['book.businessOwner', 'accountTitle', 'subAccountTitle'])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('account_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $paymentAccountsQuery->where('book_id', $selectedBookId);
        }

        $paymentAccounts = $paymentAccountsQuery->get();

        return view('payment_accounts.index', [
            'books' => $books,
            'paymentAccounts' => $paymentAccounts,
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

        return view('payment_accounts.create', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'paymentAccount' => null,
        ], $formData));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);
        $this->ensureSubAccountMatchesAccountTitle($validated, $bookId);

        $validated['book_id'] = $bookId;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        PaymentAccount::create($validated);

        return redirect()
            ->route('payment-accounts.index', ['book_id' => $bookId])
            ->with('status', '入金口座を登録しました。');
    }

    public function edit(PaymentAccount $paymentAccount): View
    {
        $bookId = (int) $paymentAccount->book_id;

        $books = $this->getSelectableBooks($bookId);
        $selectedBook = $books->firstWhere('id', $bookId);

        return view('payment_accounts.edit', array_merge([
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $bookId,
            'paymentAccount' => $paymentAccount,
        ], $this->loadFormMasterData($bookId)));
    }

    public function update(Request $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        $bookId = (int) $paymentAccount->book_id;

        $validated = $this->validatePayload($request, $bookId, $paymentAccount);
        $this->ensureSubAccountMatchesAccountTitle($validated, $bookId);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $paymentAccount->update($validated);

        return redirect()
            ->route('payment-accounts.index', ['book_id' => $bookId])
            ->with('status', '入金口座を更新しました。');
    }

    public function destroy(PaymentAccount $paymentAccount): RedirectResponse
    {
        $bookId = (int) $paymentAccount->book_id;

        $paymentAccount->delete();

        return redirect()
            ->route('payment-accounts.index', ['book_id' => $bookId])
            ->with('status', '入金口座を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?PaymentAccount $paymentAccount = null
    ): array {
        $uniqueAccountCodeRule = Rule::unique('payment_accounts', 'account_code')->where(
            fn ($query) => $query->where('book_id', $bookId)
        );

        if ($paymentAccount !== null) {
            $uniqueAccountCodeRule = $uniqueAccountCodeRule->ignore($paymentAccount->id);
        }

        return $request->validate([
            'account_code' => ['required', 'string', 'max:20', $uniqueAccountCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'account_type' => ['nullable', 'in:ordinary,current,savings,other'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_holder' => ['nullable', 'string', 'max:120'],
            'account_title_id' => [
                'nullable',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'sub_account_title_id' => ['nullable', 'integer', 'exists:sub_account_titles,id'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function ensureSubAccountMatchesAccountTitle(array $validated, int $bookId): void
    {
        if (empty($validated['sub_account_title_id'])) {
            return;
        }

        if (empty($validated['account_title_id'])) {
            throw ValidationException::withMessages([
                'sub_account_title_id' => '補助科目を選択する場合は、勘定科目も選択してください。',
            ]);
        }

        $subAccountTitle = SubAccountTitle::query()
            ->with('accountTitle')
            ->find($validated['sub_account_title_id']);

        if (
            $subAccountTitle === null
            || (int) $subAccountTitle->account_title_id !== (int) $validated['account_title_id']
            || (int) $subAccountTitle->accountTitle?->book_id !== $bookId
        ) {
            throw ValidationException::withMessages([
                'sub_account_title_id' => '選択した補助科目が、選択した勘定科目または帳簿と一致していません。',
            ]);
        }
    }

    private function loadFormMasterData(int $bookId): array
    {
        return [
            'accountTitles' => AccountTitle::query()
                ->where('book_id', $bookId)
                ->orderBy('sort_order')
                ->orderBy('account_code')
                ->get(),
            'subAccountTitles' => SubAccountTitle::query()
                ->with('accountTitle')
                ->whereHas('accountTitle', fn ($query) => $query->where('book_id', $bookId))
                ->orderBy('account_title_id')
                ->orderBy('sort_order')
                ->orderBy('sub_account_code')
                ->get(),
        ];
    }

    private function emptyFormMasterData(): array
    {
        return [
            'accountTitles' => collect(),
            'subAccountTitles' => collect(),
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