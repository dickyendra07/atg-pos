<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');

    Route::get('/backoffice/login', [LoginController::class, 'showBackoffice'])->name('backoffice.login');
    Route::post('/backoffice/login', [LoginController::class, 'storeBackoffice'])->name('backoffice.login.store');

    Route::get('/cashier/login', [LoginController::class, 'showCashier'])->name('cashier.login');
    Route::post('/cashier/login', [LoginController::class, 'storeCashier'])->name('cashier.login.store');

    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});
