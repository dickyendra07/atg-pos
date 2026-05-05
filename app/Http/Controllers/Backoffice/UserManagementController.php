<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet', 'outlets']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            return null;
        }

        return $user;
    }

    protected function getRoles()
    {
        return Role::whereNotIn('code', ['admin_outlet', 'staff_gudang'])
            ->orderBy('name')
            ->get();
    }

    protected function getOutlets()
    {
        return Outlet::orderBy('name')->get();
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'phone' => ['nullable', 'string', 'max:255'],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'exists:roles,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'outlet_ids' => ['nullable', 'array'],
            'outlet_ids.*' => ['nullable', 'exists:outlets,id'],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                'min:6',
            ],
        ]);

        $roleIds = collect($validated['role_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $primaryRoleId = $roleIds->first();
        $role = Role::find($primaryRoleId);
        $roleCode = $role?->code;

        $outletIds = collect($validated['outlet_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (in_array($roleCode, ['admin_outlet', 'kasir']) && count($outletIds) === 0) {
            return back()
                ->withErrors(['outlet_ids' => 'Minimal pilih 1 outlet untuk admin outlet / kasir.'])
                ->withInput()
                ->throwResponse();
        }

        if (in_array($roleCode, ['owner', 'admin_pusat'])) {
            $validated['outlet_id'] = null;
            $outletIds = [];
        } else {
            $validated['outlet_id'] = $outletIds[0] ?? null;
        }

        $validated['outlet_ids'] = $outletIds;

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        return $validated;
    }

    public function index()
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke User Management.');
        }

        $users = User::with(['role', 'roles', 'outlet', 'outlets'])
            ->latest()
            ->get();

        return view('backoffice.users.index', [
            'user' => $authUser,
            'users' => $users,
        ]);
    }

    public function create()
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke tambah user.');
        }

        return view('backoffice.users.create', [
            'user' => $authUser,
            'roles' => $this->getRoles(),
            'outlets' => $this->getOutlets(),
        ]);
    }

    public function store(Request $request)
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke simpan user.');
        }

        $validated = $this->validateUser($request);

        $outletIds = $validated['outlet_ids'] ?? [];
        unset($validated['outlet_ids']);

        $createdUser = User::create($validated);
        $createdUser->outlets()->sync($outletIds);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User baru berhasil ditambahkan.');
    }

    public function edit(User $managedUser)
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke edit user.');
        }

        return view('backoffice.users.edit', [
            'user' => $authUser,
            'managedUser' => $managedUser->load(['role', 'roles', 'outlet', 'outlets']),
            'roles' => $this->getRoles(),
            'outlets' => $this->getOutlets(),
        ]);
    }

    public function update(Request $request, User $managedUser)
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke update user.');
        }

        $validated = $this->validateUser($request, $managedUser);

        $roleIds = $validated['role_ids'] ?? [];
        $outletIds = $validated['outlet_ids'] ?? [];
        unset($validated['role_ids'], $validated['outlet_ids']);

        $managedUser->update($validated);
        $managedUser->roles()->sync($roleIds);
        $managedUser->outlets()->sync($outletIds);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User berhasil diupdate.');
    }

    public function destroy(User $managedUser)
    {
        $authUser = $this->authorizeAccess();

        if (! $authUser) {
            return redirect()
                ->route('backoffice.index')
                ->with('error', 'Role kamu tidak punya akses ke delete user.');
        }

        if ((int) $managedUser->id === (int) $authUser->id) {
            return redirect()
                ->route('backoffice.users.index')
                ->with('error', 'User yang sedang login tidak bisa menghapus akun sendiri.');
        }

        $managedUser->roles()->detach();
        $managedUser->outlets()->detach();
        $managedUser->delete();

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User berhasil dihapus.');
    }

}
