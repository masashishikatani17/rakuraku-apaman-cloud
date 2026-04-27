<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BusinessOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBusinessOwnerId = $request->filled('business_owner_id')
            ? (int) $request->input('business_owner_id')
            : null;

        $businessOwners = BusinessOwner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $booksQuery = Book::query()
            ->with(['businessOwner', 'setting'])
            ->withCount([
                'propertyOwners',
                'accountTitles',
                'journalDescriptions',
                'departments',
                'journalEntries',
            ])
            ->orderBy('business_owner_id')
            ->orderByDesc('period_start_date')
            ->orderBy('id');

        if ($selectedBusinessOwnerId !== null) {
            $booksQuery->where('business_owner_id', $selectedBusinessOwnerId);
        }

        $books = $booksQuery->get();

        return view('books.index', [
            'businessOwners' => $businessOwners,
            'books' => $books,
            'selectedBusinessOwnerId' => $selectedBusinessOwnerId,
        ]);
    }

    public function create(Request $request): View
    {
        $businessOwners = BusinessOwner::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedBusinessOwnerId = $request->filled('business_owner_id')
            ? (int) $request->input('business_owner_id')
            : null;

        return view('books.create', [
            'businessOwners' => $businessOwners,
            'selectedBusinessOwnerId' => $selectedBusinessOwnerId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_owner_id' => ['required', 'integer', 'exists:business_owners,id'],
            'book_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('books', 'book_code')->where(
                    fn ($query) => $query->where('business_owner_id', $request->input('business_owner_id'))
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'period_start_date' => ['required', 'date'],
            'period_end_date' => ['required', 'date', 'after_or_equal:period_start_date'],
            'status' => ['required', 'in:draft,open,closed'],
            'migration_source' => ['nullable', 'string', 'max:30'],
            'db_version' => ['nullable', 'string', 'max:30'],
            'memo' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'accounting_method' => ['required', 'in:double_entry,single_entry'],
            'tax_processing_method' => ['nullable', 'in:inclusive,exclusive,separate'],
            'rounding_mode' => ['required', 'in:round,floor,ceil'],
            'is_department_enabled' => ['required', 'boolean'],
            'is_sub_account_enabled' => ['required', 'boolean'],
            'closing_month' => ['nullable', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_department_enabled'] = $request->boolean('is_department_enabled');
        $validated['is_sub_account_enabled'] = $request->boolean('is_sub_account_enabled');

        DB::transaction(function () use ($validated): void {
            $book = Book::create([
                'business_owner_id' => $validated['business_owner_id'],
                'book_code' => $validated['book_code'],
                'name' => $validated['name'],
                'period_start_date' => $validated['period_start_date'],
                'period_end_date' => $validated['period_end_date'],
                'status' => $validated['status'],
                'migration_source' => $validated['migration_source'],
                'db_version' => $validated['db_version'],
                'memo' => $validated['memo'],
                'is_active' => $validated['is_active'],
            ]);

            $book->setting()->create([
                'accounting_method' => $validated['accounting_method'],
                'tax_processing_method' => $validated['tax_processing_method'],
                'rounding_mode' => $validated['rounding_mode'],
                'is_department_enabled' => $validated['is_department_enabled'],
                'is_sub_account_enabled' => $validated['is_sub_account_enabled'],
                'closing_month' => $validated['closing_month'],
                'notes' => $validated['notes'],
            ]);
        });

        return redirect()
            ->route('books.index', ['business_owner_id' => $validated['business_owner_id']])
            ->with('status', '帳簿を登録しました。');
    }
}