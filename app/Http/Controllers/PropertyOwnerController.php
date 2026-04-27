<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PropertyOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyOwnerController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $propertyOwnersQuery = PropertyOwner::query()
            ->with(['book.businessOwner'])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('owner_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $propertyOwnersQuery->where('book_id', $selectedBookId);
        }

        $propertyOwners = $propertyOwnersQuery->get();

        return view('property_owners.index', [
            'books' => $books,
            'propertyOwners' => $propertyOwners,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        return view('property_owners.create', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        PropertyOwner::create($validated);

        return redirect()
            ->route('property-owners.index', ['book_id' => $validated['book_id']])
            ->with('status', '所有者を登録しました。');
    }

    public function edit(PropertyOwner $propertyOwner): View
    {
        $selectedBookId = (int) $propertyOwner->book_id;
        $books = $this->getSelectableBooks($selectedBookId);

        return view('property_owners.edit', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
            'propertyOwner' => $propertyOwner,
        ]);
    }

    public function update(Request $request, PropertyOwner $propertyOwner): RedirectResponse
    {
        $validated = $this->validatePayload($request, $propertyOwner);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $propertyOwner->update($validated);

        return redirect()
            ->route('property-owners.index', ['book_id' => $propertyOwner->book_id])
            ->with('status', '所有者を更新しました。');
    }

    public function destroy(PropertyOwner $propertyOwner): RedirectResponse
    {
        $bookId = (int) $propertyOwner->book_id;

        $propertyOwner->delete();

        return redirect()
            ->route('property-owners.index', ['book_id' => $bookId])
            ->with('status', '所有者を削除しました。');
    }

    private function validatePayload(
        Request $request,
        ?PropertyOwner $propertyOwner = null
    ): array {
        $uniqueOwnerCodeRule = Rule::unique('property_owners', 'owner_code')->where(
            fn ($query) => $query->where('book_id', $request->input('book_id'))
        );

        if ($propertyOwner !== null) {
            $uniqueOwnerCodeRule = $uniqueOwnerCodeRule->ignore($propertyOwner->id);
        }

        return $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'owner_code' => ['required', 'integer', 'between:1,9999', $uniqueOwnerCodeRule],
            'classification_code' => ['nullable', 'integer', 'between:0,99'],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'blue_return_deduction_code' => ['nullable', 'integer', 'between:0,99'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);
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