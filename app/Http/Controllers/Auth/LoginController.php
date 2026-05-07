<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(Request $request)
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

        $user = Auth::user()->load(['role', 'outlet']);

        if (! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'login' => 'Akun kamu sedang nonaktif. Hubungi admin untuk mengaktifkan kembali.',
                ])
                ->onlyInput('login');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}