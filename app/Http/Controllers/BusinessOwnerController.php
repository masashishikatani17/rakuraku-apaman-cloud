<?php

namespace App\Http\Controllers;

use App\Models\BusinessOwner;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessOwnerController extends Controller
{
    public function index(): View
    {
        $businessOwners = BusinessOwner::query()
            ->withCount('books')
            ->orderBy('id')
            ->get();

        return view('business_owners.index', [
            'businessOwners' => $businessOwners,
        ]);
    }

    public function create(): View
    {
        return view('business_owners.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'owner_code' => ['nullable', 'string', 'max:20', 'unique:business_owners,owner_code'],
            'name' => ['required', 'string', 'max:120'],
            'name_kana' => ['nullable', 'string', 'max:120'],
            'owner_type' => ['required', 'in:individual,corporate'],
            'postal_code' => ['nullable', 'string', 'max:8'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'memo' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        BusinessOwner::create($validated);

        return redirect()
            ->route('business-owners.index')
            ->with('status', '事業主を登録しました。');
    }
}