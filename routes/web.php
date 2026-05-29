<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

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
