<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
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

        $departmentsQuery = Department::query()
            ->with(['book.businessOwner'])
            ->orderBy('book_id')
            ->orderBy('sort_order')
            ->orderBy('department_code')
            ->orderBy('id');

        if ($selectedBookId !== null) {
            $departmentsQuery->where('book_id', $selectedBookId);
        }

        $departments = $departmentsQuery->get();

        return view('departments.index', [
            'books' => $books,
            'departments' => $departments,
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

        return view('departments.create', [
            'books' => $books,
            'selectedBookId' => $selectedBookId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'department_code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('departments', 'department_code')->where(
                    fn ($query) => $query->where('book_id', $request->input('book_id'))
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'note' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        Department::create($validated);

        return redirect()
            ->route('departments.index', ['book_id' => $validated['book_id']])
            ->with('status', '部門を登録しました。');
    }
}