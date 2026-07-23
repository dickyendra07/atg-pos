<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/cashier-lite', function () {
    session([
        'cashier_lite_mode' => true,
    ]);

    if (Auth::check()) {
        session([
            'auth_portal' => 'cashier',
        ]);

        return redirect()->route('cashier.index');
    }

    return redirect()->route('cashier.login');
})->name('cashier.lite.entry');

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        if (session('auth_portal') === 'cashier') {
            return redirect()->route('cashier.index');
        }

        return redirect()->route('backoffice.index');
    })->name('dashboard');

    require __DIR__.'/cashier.php';
    require __DIR__.'/backoffice.php';
});
