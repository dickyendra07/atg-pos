<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ModeController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user()?->load(['role', 'roles', 'outlet', 'outlets']);

        if (! $user) {
            return redirect()->route('backoffice.login');
        }

        $portal = session('auth_portal');

        if ($portal === 'backoffice' && $user->canAccessBackofficeDashboard()) {
            return redirect()->route('backoffice.index');
        }

        if ($portal === 'cashier' && $user->canAccessCashier()) {
            return redirect()->route('cashier.index');
        }

        if ($user->canAccessBackofficeDashboard()) {
            return redirect()->route('backoffice.index');
        }

        if ($user->canAccessCashier()) {
            return redirect()->route('cashier.index');
        }

        Auth::logout();

        return redirect()
            ->route('backoffice.login')
            ->withErrors([
                'login' => 'Akun ini belum punya role akses yang valid.',
            ]);
    }
}
