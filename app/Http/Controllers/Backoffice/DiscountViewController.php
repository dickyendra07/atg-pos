<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscountViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        if (! in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true)) {
            return null;
        }

        return $user;
    }

    protected function validateDiscount(Request $request): array
    {
        return $request->validate([
            'outlet_id' => 'nullable|exists:outlets,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:amount,percent',
            'value' => 'required|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ], [
            'name.required' => 'Nama discount wajib diisi.',
            'type.required' => 'Tipe discount wajib dipilih.',
            'type.in' => 'Tipe discount tidak valid.',
            'value.required' => 'Nilai discount wajib diisi.',
        ]);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke Discounts.');
        }

        $query = Discount::with('outlet')->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return view('backoffice.discounts.index', [
            'user' => $user,
            'discounts' => $query->get(),
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
            'filters' => [
                'search' => $request->search,
                'outlet_id' => $request->outlet_id,
                'type' => $request->type,
                'status' => $request->status,
            ],
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses create discount.');
        }

        return view('backoffice.discounts.create', [
            'user' => $user,
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses create discount.');
        }

        $validated = $this->validateDiscount($request);
        $validated['is_active'] = $request->boolean('is_active');

        Discount::create($validated);

        return redirect()
            ->route('backoffice.discounts.index')
            ->with('success', 'Discount berhasil dibuat.');
    }

    public function edit(Discount $discount)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses edit discount.');
        }

        return view('backoffice.discounts.edit', [
            'user' => $user,
            'discount' => $discount->load('outlet'),
            'outletOptions' => Outlet::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Discount $discount)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses update discount.');
        }

        $validated = $this->validateDiscount($request);
        $validated['is_active'] = $request->boolean('is_active');

        $discount->update($validated);

        return redirect()
            ->route('backoffice.discounts.index')
            ->with('success', 'Discount berhasil diupdate.');
    }

    public function destroy(Discount $discount)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses delete discount.');
        }

        $discount->delete();

        return redirect()
            ->route('backoffice.discounts.index')
            ->with('success', 'Discount berhasil dihapus.');
    }
}
