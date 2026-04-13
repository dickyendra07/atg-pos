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
        $user = Auth::user()->load(['role', 'outlet']);

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
        return Role::orderBy('name')->get();
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
            'role_id' => ['required', 'exists:roles,id'],
            'outlet_id' => ['nullable', 'exists:outlets,id'],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                'min:6',
            ],
        ]);

        $role = Role::find($validated['role_id']);
        $roleCode = $role?->code;

        if (in_array($roleCode, ['admin_outlet', 'kasir']) && empty($validated['outlet_id'])) {
            return back()
                ->withErrors(['outlet_id' => 'Outlet wajib dipilih untuk admin outlet / kasir.'])
                ->withInput()
                ->throwResponse();
        }

        if (in_array($roleCode, ['owner', 'admin_pusat'])) {
            $validated['outlet_id'] = null;
        }

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

        $users = User::with(['role', 'outlet'])
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

        User::create($validated);

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
            'managedUser' => $managedUser->load(['role', 'outlet']),
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

        $managedUser->update($validated);

        return redirect()
            ->route('backoffice.users.index')
            ->with('success', 'User berhasil diupdate.');
    }
}