<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\PropertyOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);

        $propertiesQuery = Property::query()
            ->with([
                'book.businessOwner',
                'propertyCategory',
                'primaryOwner',
                'representativeOwner',
            ])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $propertiesQuery->where('book_id', $selectedBookId);
        }

        $properties = $propertiesQuery->get();

        return view('properties.index', [
            'books' => $books,
            'properties' => $properties,
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

        $propertyCategories = collect();
        $propertyOwners = collect();

        if ($selectedBookId !== null) {
            $propertyCategories = PropertyCategory::query()
                ->where('book_id', $selectedBookId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('category_code')
                ->get();

            $propertyOwners = PropertyOwner::query()
                ->where('book_id', $selectedBookId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('owner_code')
                ->get();
        }

        return view('properties.create', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'propertyCategories' => $propertyCategories,
            'propertyOwners' => $propertyOwners,
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
        $validated['parking_total'] = $validated['parking_total']
            ?? (($validated['parking_monthly_indoor'] ?? 0)
                 ($validated['parking_monthly_outdoor'] ?? 0)
                 ($validated['parking_hourly'] ?? 0));

        Property::create($validated);

        return redirect()
            ->route('properties.index', ['book_id' => $bookId])
            ->with('status', '物件を登録しました。');
    }

    public function edit(Property $property): View
    {
        $selectedBookId = (int) $property->book_id;
        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);

        $propertyCategories = PropertyCategory::query()
            ->where('book_id', $selectedBookId)
            ->orderBy('sort_order')
            ->orderBy('category_code')
            ->get();

        $propertyOwners = PropertyOwner::query()
            ->where('book_id', $selectedBookId)
            ->orderBy('sort_order')
            ->orderBy('owner_code')
            ->get();

        return view('properties.edit', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'propertyCategories' => $propertyCategories,
            'propertyOwners' => $propertyOwners,
            'property' => $property,
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $bookId = (int) $property->book_id;

        $validated = $this->validatePayload($request, $bookId, $property);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['parking_total'] = $validated['parking_total']
            ?? (($validated['parking_monthly_indoor'] ?? 0)
                + ($validated['parking_monthly_outdoor'] ?? 0)
                + ($validated['parking_hourly'] ?? 0));

        $property->update($validated);

        return redirect()
            ->route('properties.index', ['book_id' => $bookId])
            ->with('status', '物件を更新しました。');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $bookId = (int) $property->book_id;

        $property->delete();

        return redirect()
            ->route('properties.index', ['book_id' => $bookId])
            ->with('status', '物件を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?Property $property = null
    ): array {
        $uniquePropertyCodeRule = Rule::unique('properties', 'property_code')->where(
            fn ($query) => $query->where('book_id', $bookId)
        );

        if ($property !== null) {
            $uniquePropertyCodeRule = $uniquePropertyCodeRule->ignore($property->id);
        }

        return $request->validate([
            'property_category_id' => [
                'required',
                'integer',
                Rule::exists('property_categories', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'property_code' => ['required', 'string', 'max:20', $uniquePropertyCodeRule],
            'name' => ['required', 'string', 'max:120'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'name_reading' => ['nullable', 'string', 'max:120'],
            'postal_code_1' => ['nullable', 'string', 'max:3'],
            'postal_code_2' => ['nullable', 'string', 'max:4'],
            'address' => ['nullable', 'string', 'max:255'],
            'ownership_form' => ['nullable', 'string', 'max:50'],
            'primary_owner_id' => [
                'required',
                'integer',
                Rule::exists('property_owners', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'representative_owner_id' => [
                'nullable',
                'integer',
                Rule::exists('property_owners', 'id')->where(
                    fn ($query) => $query
                        ->where('book_id', $bookId)
                        ->where('is_active', true)
                ),
            ],
            'right_form' => ['nullable', 'string', 'max:50'],
            'land_area_sqm' => ['nullable', 'numeric', 'min:0'],
            'building_area_sqm' => ['nullable', 'numeric', 'min:0'],
            'residential_floor_area' => ['nullable', 'numeric', 'min:0'],
            'business_floor_area' => ['nullable', 'numeric', 'min:0'],
            'parking_monthly_indoor' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'parking_monthly_outdoor' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'parking_hourly' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'parking_total' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'built_at' => ['nullable', 'date'],
            'structure' => ['nullable', 'string', 'max:100'],
            'floors' => ['nullable', 'string', 'max:50'],
            'layout_summary' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string'],
            'note2' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
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