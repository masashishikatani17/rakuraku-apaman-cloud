<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Property;
use App\Models\PropertyUnit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyUnitController extends Controller
{
    public function index(Request $request): View
    {
        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : null;

        $selectedPropertyId = $request->filled('property_id')
            ? (int) $request->input('property_id')
            : null;

        $books = $this->getSelectableBooks($selectedBookId);
        $properties = $this->getSelectableProperties($selectedBookId, $selectedPropertyId);

        $propertyUnitsQuery = PropertyUnit::query()
            ->with([
                'property.book.businessOwner',
                'property.propertyCategory',
                'property.primaryOwner',
            ])
            ->orderBy('property_id')
            ->orderBy('sort_order')
            ->orderBy('unit_no')
            ->orderBy('id');

        if ($selectedPropertyId !== null) {
            $propertyUnitsQuery->where('property_id', $selectedPropertyId);
        } elseif ($selectedBookId !== null) {
            $propertyUnitsQuery->whereHas('property', function ($query) use ($selectedBookId): void {
                $query->where('book_id', $selectedBookId);
            });
        }

        $propertyUnits = $propertyUnitsQuery->get();

        return view('property_units.index', [
            'books' => $books,
            'properties' => $properties,
            'propertyUnits' => $propertyUnits,
            'selectedBookId' => $selectedBookId,
            'selectedPropertyId' => $selectedPropertyId,
        ]);
    }

    public function create(Request $request): View
    {
        $books = $this->getSelectableBooks();

        $selectedBookId = $request->filled('book_id')
            ? (int) $request->input('book_id')
            : ($books->first()?->id);

        $selectedPropertyId = $request->filled('property_id')
            ? (int) $request->input('property_id')
            : null;

        $selectedBook = $selectedBookId !== null
            ? $books->firstWhere('id', $selectedBookId)
            : null;

        if ($selectedBook === null && $books->isNotEmpty()) {
            $selectedBook = $books->first();
            $selectedBookId = (int) $selectedBook->id;
        }

        $properties = $this->getSelectableProperties($selectedBookId, $selectedPropertyId);

        $selectedProperty = $selectedPropertyId !== null
            ? $properties->firstWhere('id', $selectedPropertyId)
            : null;

        if ($selectedProperty === null && $properties->isNotEmpty()) {
            $selectedProperty = $properties->first();
            $selectedPropertyId = (int) $selectedProperty->id;
        }

        return view('property_units.create', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'properties' => $properties,
            'selectedProperty' => $selectedProperty,
            'selectedPropertyId' => $selectedPropertyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
        ]);

        $bookId = (int) $request->input('book_id');

        $validated = $this->validatePayload($request, $bookId);
        $validated['is_new_registration'] = $request->boolean('is_new_registration');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        PropertyUnit::create($validated);

        return redirect()
            ->route('property-units.index', [
                'book_id' => $bookId,
                'property_id' => $validated['property_id'],
            ])
            ->with('status', '部屋・区画を登録しました。');
    }

    public function edit(PropertyUnit $propertyUnit): View
    {
        $propertyUnit->load(['property.book.businessOwner']);

        $selectedBookId = (int) $propertyUnit->property->book_id;
        $selectedPropertyId = (int) $propertyUnit->property_id;

        $books = $this->getSelectableBooks($selectedBookId);
        $selectedBook = $books->firstWhere('id', $selectedBookId);
        $properties = $this->getSelectableProperties($selectedBookId, $selectedPropertyId);
        $selectedProperty = $properties->firstWhere('id', $selectedPropertyId);

        return view('property_units.edit', [
            'books' => $books,
            'selectedBook' => $selectedBook,
            'selectedBookId' => $selectedBookId,
            'properties' => $properties,
            'selectedProperty' => $selectedProperty,
            'selectedPropertyId' => $selectedPropertyId,
            'propertyUnit' => $propertyUnit,
        ]);
    }

    public function update(Request $request, PropertyUnit $propertyUnit): RedirectResponse
    {
        $bookId = (int) $propertyUnit->property->book_id;

        $validated = $this->validatePayload($request, $bookId, $propertyUnit);
        $validated['is_new_registration'] = $request->boolean('is_new_registration');
        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        $propertyUnit->update($validated);

        return redirect()
            ->route('property-units.index', [
                'book_id' => $bookId,
                'property_id' => $validated['property_id'],
            ])
            ->with('status', '部屋・区画を更新しました。');
    }

    public function destroy(PropertyUnit $propertyUnit): RedirectResponse
    {
        $property = $propertyUnit->property;
        $bookId = (int) $property->book_id;
        $propertyId = (int) $propertyUnit->property_id;

        $propertyUnit->delete();

        return redirect()
            ->route('property-units.index', [
                'book_id' => $bookId,
                'property_id' => $propertyId,
            ])
            ->with('status', '部屋・区画を削除しました。');
    }

    private function validatePayload(
        Request $request,
        int $bookId,
        ?PropertyUnit $propertyUnit = null
    ): array {
        $propertyId = (int) $request->input('property_id');

        $uniqueUnitNoRule = Rule::unique('property_units', 'unit_no')->where(
            fn ($query) => $query->where('property_id', $propertyId)
        );

        if ($propertyUnit !== null) {
            $uniqueUnitNoRule = $uniqueUnitNoRule->ignore($propertyUnit->id);
        }

        return $request->validate([
            'property_id' => [
                'required',
                'integer',
                Rule::exists('properties', 'id')->where(
                    fn ($query) => $query->where('book_id', $bookId)
                ),
            ],
            'unit_no' => ['required', 'string', 'max:50', $uniqueUnitNoRule],
            'unit_type' => ['required', 'in:room,parking,other'],
            'area_sqm' => ['nullable', 'numeric', 'min:0'],
            'layout_code' => ['nullable', 'string', 'max:20'],
            'parking_category_code' => ['nullable', 'string', 'max:20'],
            'ended_at' => ['nullable', 'date'],
            'is_new_registration' => ['required', 'boolean'],
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

    private function getSelectableProperties(?int $selectedBookId = null, ?int $selectedPropertyId = null)
    {
        $propertiesQuery = Property::query()
            ->with([
                'book.businessOwner',
                'propertyCategory',
                'primaryOwner',
            ])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('property_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $propertiesQuery->where('book_id', $selectedBookId);
        }

        $properties = $propertiesQuery->get();

        if ($selectedPropertyId !== null && !$properties->contains('id', $selectedPropertyId)) {
            $selectedProperty = Property::query()
                ->with([
                    'book.businessOwner',
                    'propertyCategory',
                    'primaryOwner',
                ])
                ->find($selectedPropertyId);

            if ($selectedProperty !== null) {
                $properties = $properties->prepend($selectedProperty);
            }
        }

        return $properties;
    }
}