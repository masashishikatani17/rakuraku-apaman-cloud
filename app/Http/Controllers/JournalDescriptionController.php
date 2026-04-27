<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\JournalDescription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JournalDescriptionController extends Controller
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

        $journalDescriptionsQuery = JournalDescription::query()
            ->with(['book.businessOwner'])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('description_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $journalDescriptionsQuery->where('book_id', $selectedBookId);
        }

        $journalDescriptions = $journalDescriptionsQuery->get();

        return view('journal_descriptions.index', [
            'books' => $books,
            'journalDescriptions' => $journalDescriptions,
            'selectedBookId' => $selectedBookId,
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

        return view('journal_descriptions.create', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'description_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('journal_descriptions', 'description_code')->where(
                    fn ($query) => $query->where('book_id', $request->input('book_id'))
                ),
            ],
            'description_text' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        JournalDescription::create($validated);

        return redirect()
            ->route('journal-descriptions.index', ['book_id' => $validated['book_id']])
            ->with('status', '摘要を登録しました。');
    }
}