<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountTitleController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        $accountTitlesQuery = AccountTitle::query()
            ->with(['book.businessOwner'])
            ->withCount('subAccountTitles')
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('account_code');

        if ($selectedBookId !== null) {
            $accountTitlesQuery->where('book_id', $selectedBookId);
        }

        $accountTitles = $accountTitlesQuery->get();

        return view('account_titles.index', [
            'books' => $books,
            'accountTitles' => $accountTitles,
            'selectedBookId' => $selectedBookId,
            'consumptionTaxCategoryLabels' => AccountTitle::consumptionTaxCategoryLabels(),
            'realEstateStatementCategoryLabels' => AccountTitle::realEstateStatementCategoryLabels(),
        ]);
    }

    public function create(Request $request): View
    {
        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        return view('account_titles.create', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
            'consumptionTaxCategoryLabels' => AccountTitle::consumptionTaxCategoryLabels(),
            'realEstateStatementCategoryLabels' => AccountTitle::realEstateStatementCategoryLabels(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'account_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('account_titles', 'account_code')->where(
                    fn ($query) => $query->where('book_id', $request->input('book_id'))
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'consumption_tax_category' => ['nullable', Rule::in(array_keys(AccountTitle::consumptionTaxCategoryLabels()))],
            'consumption_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'real_estate_statement_category' => ['nullable', Rule::in(array_keys(AccountTitle::realEstateStatementCategoryLabels()))],
            'allows_sub_account' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['allows_sub_account'] = $request->boolean('allows_sub_account');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['consumption_tax_category'] = $validated['consumption_tax_category'] ?? 'auto';
        $validated['consumption_tax_rate'] = $validated['consumption_tax_rate'] ?? null;
        $validated['real_estate_statement_category'] = $validated['real_estate_statement_category'] ?? 'auto';

        AccountTitle::create($validated);

        return redirect()
            ->route('account-titles.index', ['book_id' => $validated['book_id']])
            ->with('status', '勘定科目を登録しました。');
    }

    public function edit(AccountTitle $accountTitle): View
    {
        $accountTitle->load('book.businessOwner');

        return view('account_titles.edit', [
            'accountTitle' => $accountTitle,
            'consumptionTaxCategoryLabels' => AccountTitle::consumptionTaxCategoryLabels(),
            'realEstateStatementCategoryLabels' => AccountTitle::realEstateStatementCategoryLabels(),
        ]);
    }

    public function update(Request $request, AccountTitle $accountTitle): RedirectResponse
    {
        $validated = $request->validate([
            'account_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('account_titles', 'account_code')
                    ->where(fn ($query) => $query->where('book_id', $accountTitle->book_id))
                    ->ignore($accountTitle->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'normal_balance' => ['required', 'in:debit,credit'],
            'consumption_tax_category' => ['nullable', Rule::in(array_keys(AccountTitle::consumptionTaxCategoryLabels()))],
            'consumption_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'real_estate_statement_category' => ['nullable', Rule::in(array_keys(AccountTitle::realEstateStatementCategoryLabels()))],
            'allows_sub_account' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['allows_sub_account'] = $request->boolean('allows_sub_account');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['consumption_tax_category'] = $validated['consumption_tax_category'] ?? 'auto';
        $validated['consumption_tax_rate'] = $validated['consumption_tax_rate'] ?? null;
        $validated['real_estate_statement_category'] = $validated['real_estate_statement_category'] ?? 'auto';

        $accountTitle->fill($validated);
        $accountTitle->save();

        return redirect()
            ->route('account-titles.index', ['book_id' => $accountTitle->book_id])
            ->with('status', '勘定科目を更新しました。');
    }
}