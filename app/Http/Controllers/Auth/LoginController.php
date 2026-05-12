<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showBackoffice()
    {
        return view('auth.login', [
            'portal' => 'backoffice',
            'portalTitle' => 'Back Office Login',
            'portalSubtitle' => 'Masuk untuk mengelola dashboard, inventory, recipe, promo, user, dan report.',
            'loginRoute' => route('backoffice.login.store'),
        ]);
    }

    public function showCashier()
    {
        return view('auth.login', [
            'portal' => 'cashier',
            'portalTitle' => 'Cashier Login',
            'portalSubtitle' => 'Masuk khusus untuk operasional kasir outlet.',
            'loginRoute' => route('cashier.login.store'),
        ]);
    }

    public function show()
    {
        return redirect()->route('backoffice.login');
    }

    public function storeBackoffice(Request $request)
    {
        return $this->attemptPortalLogin($request, 'backoffice');
    }

    public function storeCashier(Request $request)
    {
        return $this->attemptPortalLogin($request, 'cashier');
    }

    public function store(Request $request)
    {
        return $this->storeBackoffice($request);
    }

    protected function attemptPortalLogin(Request $request, string $portal)
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = trim((string) $validated['login']);
        $password = (string) $validated['password'];
        $remember = $request->boolean('remember');
        $loginField = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        if (! Auth::attempt([$loginField => $login, 'password' => $password], $remember)) {
            return back()
                ->withErrors([
                    'login' => 'Username/email atau password tidak valid.',
                ])
                ->onlyInput('login');
        }

        $request->session()->regenerate();

        $user = Auth::user()->load(['role', 'roles', 'outlet', 'outlets']);

        if (! $user->is_active) {
            return $this->rejectLogin($request, 'Akun kamu sedang nonaktif. Hubungi admin untuk mengaktifkan kembali.');
        }

        session(['auth_portal' => $portal]);

        if ($portal === 'cashier' && ! $user->canAccessCashier()) {
            return $this->rejectLogin($request, 'Akun ini bukan akun kasir. Silakan login melalui Back Office atau gunakan akun kasir terpisah.');
        }

        if ($portal === 'backoffice' && ! $user->canAccessBackofficeDashboard()) {
            return $this->rejectLogin($request, 'Akun ini tidak punya akses Back Office. Silakan login melalui Cashier jika kamu kasir.');
        }

        if ($portal === 'cashier') {
            $outlets = $user->cashierAccessibleOutlets();

            if ($outlets->isEmpty()) {
                return $this->rejectLogin($request, 'Akun kasir ini belum punya akses outlet. Hubungi admin Back Office.');
            }

            if ($outlets->count() === 1) {
                session(['cashier_outlet_id' => $outlets->first()->id]);

                return redirect()->intended(route('cashier.index'));
            }

            session()->forget('cashier_outlet_id');

            return redirect()->route('cashier.select-outlet');
        }

        session()->forget('cashier_outlet_id');

        return redirect()->intended(route('backoffice.index'));
    }

    protected function rejectLogin(Request $request, string $message)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return back()
            ->withErrors([
                'login' => $message,
            ])
            ->onlyInput('login');
    }

    public function destroy(Request $request)
    {
        $portal = session('auth_portal');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($portal === 'cashier') {
            return redirect()->route('cashier.login');
        }

        return redirect()->route('backoffice.login');
    }
}
