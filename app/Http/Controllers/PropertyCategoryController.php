<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\PropertyCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $propertyCategoriesQuery = PropertyCategory::query()
            ->with(['book.businessOwner'])
            ->withCount('properties')
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('category_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $propertyCategoriesQuery->where('book_id', $selectedBookId);
        }

        $propertyCategories = $propertyCategoriesQuery->get();

        return view('property_categories.index', [
            'books' => $books,
            'propertyCategories' => $propertyCategories,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function create(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        return view('property_categories.create', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);
        $validated['book_id'] = $bookId;
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        PropertyCategory::create($validated);

        return redirect()
            ->route('property-categories.index', ['book_id' => $bookId])
            ->with('status', '物件区分を登録しました。');
    }

    public function edit(PropertyCategory $propertyCategory): View
    {
        $selectedBookId = (int) $propertyCategory->book_id;

        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);

        return view('property_categories.edit', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'propertyCategory' => $propertyCategory,
        ]);
    }

    public function update(Request $request, PropertyCategory $propertyCategory): RedirectResponse
    {
        $bookId = (int) $propertyCategory->book_id;

        $validated = $this->validatePayload($request, $bookId, $propertyCategory);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $propertyCategory->update($validated);

        return redirect()
            ->route('property-categories.index', ['book_id' => $bookId])
            ->with('status', '物件区分を更新しました。');
    }

    public function destroy(PropertyCategory $propertyCategory): RedirectResponse
    {
        $bookId = (int) $propertyCategory->book_id;

        if ($propertyCategory->properties()->exists()) {
            return redirect()
                ->route('property-categories.index', ['book_id' => $bookId])
                ->with('error', 'この物件区分は物件で使用中のため削除できません。');
        }

        $propertyCategory->delete();

        return redirect()
            ->route('property-categories.index', ['book_id' => $bookId])
            ->with('status', '物件区分を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?PropertyCategory $propertyCategory = null
    ): array {
        $uniqueCategoryCodeRule = Rule::unique('property_categories', 'category_code')->where(
            fn ($query) => $query->where('book_id', $bookId)
        );

        if ($propertyCategory !== null) {
            $uniqueCategoryCodeRule = $uniqueCategoryCodeRule->ignore($propertyCategory->id);
        }

        return $request->validate([
            'category_code' => ['required', 'string', 'max:20', $uniqueCategoryCodeRule],
            'name' => ['required', 'string', 'max:120'],
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