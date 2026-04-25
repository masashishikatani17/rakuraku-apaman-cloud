<?php

namespace App\Http\Controllers;

use App\Models\AccountTitle;
use App\Models\Book;
use App\Models\SubAccountTitle;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubAccountTitleController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $selectedAccountTitleId = $request->filled('account_title_id')
            ? (int) $request->input('account_title_id')
            : null;

        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        $accountTitlesQuery = AccountTitle::query()
            ->with(['book.businessOwner'])
            ->where('is_active', true)
            ->where('allows_sub_account', true)
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('account_code');

        if ($selectedBookId !== null) {
            $accountTitlesQuery->where('book_id', $selectedBookId);
        }

        $accountTitles = $accountTitlesQuery->get();

        $subAccountTitlesQuery = SubAccountTitle::query()
            ->with(['accountTitle.book.businessOwner'])
            ->orderBy('account_title_id')
            ->orderBy('sort_order')
            ->orderBy('sub_account_code');

        if ($selectedAccountTitleId !== null) {
            $subAccountTitlesQuery->where('account_title_id', $selectedAccountTitleId);
        } elseif ($selectedBookId !== null) {
            $subAccountTitlesQuery->whereHas('accountTitle', function ($query) use ($selectedBookId): void {
                $query->where('book_id', $selectedBookId);
            });
        }

        $subAccountTitles = $subAccountTitlesQuery->get();

        return view('sub_account_titles.index', [
            'books' => $books,
            'accountTitles' => $accountTitles,
            'subAccountTitles' => $subAccountTitles,
            'selectedBookId' => $selectedBookId,
            'selectedAccountTitleId' => $selectedAccountTitleId,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $selectedAccountTitleId = $request->filled('account_title_id')
            ? (int) $request->input('account_title_id')
            : null;

        $books = Book::query()
            ->with('businessOwner')
            ->where('is_active', true)
            ->orderBy('business_owner_id')
            ->orderBy('name')
            ->get();

        $accountTitlesQuery = AccountTitle::query()
            ->with(['book.businessOwner'])
            ->where('is_active', true)
            ->where('allows_sub_account', true)
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('account_code');

        if ($selectedBookId !== null) {
            $accountTitlesQuery->where('book_id', $selectedBookId);
        }

        $accountTitles = $accountTitlesQuery->get();

        return view('sub_account_titles.create', [
            'books' => $books,
            'accountTitles' => $accountTitles,
            'selectedBookId' => $selectedBookId,
            'selectedAccountTitleId' => $selectedAccountTitleId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account_title_id' => [
                'required',
                'integer',
                Rule::exists('account_titles', 'id')->where(
                    fn ($query) => $query
                        ->where('allows_sub_account', true)
                        ->where('is_active', true)
                ),
            ],
            'sub_account_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sub_account_titles', 'sub_account_code')->where(
                    fn ($query) => $query->where('account_title_id', $request->input('account_title_id'))
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $accountTitle = AccountTitle::query()
            ->select(['id', 'book_id'])
            ->findOrFail($validated['account_title_id']);

        SubAccountTitle::create($validated);

        return redirect()
            ->route('sub-account-titles.index', [
                'book_id' => $accountTitle->book_id,
                'account_title_id' => $accountTitle->id,
            ])
            ->with('status', '補助科目を登録しました。');
    }
}