<?php

use App\Http\Controllers\Cashier\CartController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Cashier\CashierShiftController;
use App\Http\Controllers\Cashier\MemberCartController;
use Illuminate\Support\Facades\Route;

Route::prefix('cashier')->name('cashier.')->group(function () {
    Route::get('/select-outlet', [CashierController::class, 'selectOutletForm'])->name('select-outlet');
    Route::post('/select-outlet', [CashierController::class, 'selectOutletStore'])->name('select-outlet.store');

    Route::get('/', CashierController::class)->name('index');
    Route::get('/new-transaction', [CashierController::class, 'newTransaction'])->name('new-transaction');
    Route::post('/order-type', [CashierController::class, 'setOrderType'])->name('set-order-type');

    Route::post('/shift/start', [CashierShiftController::class, 'start'])->name('shift.start');
    Route::post('/shift/end', [CashierShiftController::class, 'end'])->name('shift.end');
    Route::get('/shifts/{shift}/print', [CashierShiftController::class, 'print'])->name('shift.print');

    Route::post('/cart/add/{variant}', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/increase/{cartKey}', [CartController::class, 'increase'])->name('cart.increase');
    Route::post('/cart/decrease/{cartKey}', [CartController::class, 'decrease'])->name('cart.decrease');
    Route::post('/cart/remove/{cartKey}', [CartController::class, 'remove'])->name('cart.remove');
    Route::post('/cart/toggle-modifier/{cartKey}', [CartController::class, 'toggleModifier'])->name('cart.toggle-modifier');
    Route::post('/cart/clear', [CartController::class, 'clear'])->name('cart.clear');

    Route::post('/promos/{promo}/apply', [CartController::class, 'applyPromo'])->name('promo.apply');
    Route::post('/checkout', [CartController::class, 'checkout'])->name('checkout');

    Route::get('/transactions/{transaction}/receipt', [CartController::class, 'cashierReceipt'])->name('transactions.receipt');
    Route::post('/transactions/{transaction}/void', [CartController::class, 'cashierVoid'])->name('transactions.void');

    Route::post('/member/attach', [MemberCartController::class, 'attach'])->name('member.attach');
    Route::post('/member/quick-register', [MemberCartController::class, 'quickRegister'])->name('member.quick-register');
    Route::post('/member/detach', [MemberCartController::class, 'detach'])->name('member.detach');
});
