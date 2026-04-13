<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OutletViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Outlet.');
        }

        return $user;
    }

    public function index()
    {
        $user = $this->authorizeAccess();

        $outlets = Outlet::latest()->get();

        return view('backoffice.outlets.index', [
            'user' => $user,
            'outlets' => $outlets,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        return view('backoffice.outlets.create', [
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:outlets,code',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        Outlet::create($validated);

        return redirect()
            ->route('backoffice.outlets.index')
            ->with('success', 'Outlet baru berhasil ditambahkan.');
    }

    public function edit(Outlet $outlet)
    {
        $user = $this->authorizeAccess();

        return view('backoffice.outlets.edit', [
            'user' => $user,
            'outlet' => $outlet,
        ]);
    }

    public function update(Request $request, Outlet $outlet)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('outlets', 'code')->ignore($outlet->id),
            ],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $outlet->update($validated);

        return redirect()
            ->route('backoffice.outlets.index')
            ->with('success', 'Outlet berhasil diupdate.');
    }

    public function destroy(Outlet $outlet)
    {
        $this->authorizeAccess();

        if ($outlet->users()->exists()) {
            return redirect()
                ->route('backoffice.outlets.index')
                ->with('error', 'Outlet tidak bisa dihapus karena masih dipakai oleh user.');
        }

        $outlet->delete();

        return redirect()
            ->route('backoffice.outlets.index')
            ->with('success', 'Outlet berhasil dihapus.');
    }
}