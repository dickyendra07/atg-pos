<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Backoffice\CashierShiftViewController;
use App\Http\Controllers\Backoffice\DiscountViewController;
use App\Http\Controllers\Backoffice\IngredientProductionController;
use App\Http\Controllers\Backoffice\IngredientProductionRecipeController;
use App\Http\Controllers\Backoffice\IngredientViewController;
use App\Http\Controllers\Backoffice\OutletViewController;
use App\Http\Controllers\Backoffice\ProductVariantViewController;
use App\Http\Controllers\Backoffice\PromoViewController;
use App\Http\Controllers\Backoffice\ProductViewController;
use App\Http\Controllers\Backoffice\RecipeViewController;
use App\Http\Controllers\Backoffice\StockBalanceViewController;
use App\Http\Controllers\Backoffice\StockMovementViewController;
use App\Http\Controllers\Backoffice\TransactionViewController;
use App\Http\Controllers\Backoffice\TransferViewController;
use App\Http\Controllers\Backoffice\UserManagementController;
use App\Http\Controllers\Backoffice\WarehouseTransferViewController;
use App\Http\Controllers\Backoffice\WarehouseViewController;
use App\Http\Controllers\Cashier\CartController;
use App\Http\Controllers\Cashier\CashierController;
use App\Http\Controllers\Cashier\CashierShiftController;
use App\Http\Controllers\Cashier\MemberCartController;
use App\Http\Controllers\ModeController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', ModeController::class)->name('dashboard');

    Route::get('/cashier', CashierController::class)->name('cashier.index');
    Route::get('/cashier/new-transaction', [CashierController::class, 'newTransaction'])->name('cashier.new-transaction');
    Route::post('/cashier/order-type', [CashierController::class, 'setOrderType'])->name('cashier.set-order-type');
    Route::post('/cashier/shift/start', [CashierShiftController::class, 'start'])->name('cashier.shift.start');
    Route::post('/cashier/shift/end', [CashierShiftController::class, 'end'])->name('cashier.shift.end');
    Route::get('/cashier/shifts/{shift}/print', [CashierShiftController::class, 'print'])->name('cashier.shift.print');

    Route::post('/cashier/cart/add/{variant}', [CartController::class, 'add'])->name('cashier.cart.add');
    Route::post('/cashier/cart/increase/{cartKey}', [CartController::class, 'increase'])->name('cashier.cart.increase');
    Route::post('/cashier/cart/decrease/{cartKey}', [CartController::class, 'decrease'])->name('cashier.cart.decrease');
    Route::post('/cashier/cart/remove/{cartKey}', [CartController::class, 'remove'])->name('cashier.cart.remove');
    Route::post('/cashier/cart/toggle-modifier/{cartKey}', [CartController::class, 'toggleModifier'])->name('cashier.cart.toggle-modifier');
    Route::post('/cashier/cart/clear', [CartController::class, 'clear'])->name('cashier.cart.clear');
    Route::post('/cashier/promos/{promo}/apply', [CartController::class, 'applyPromo'])->name('cashier.promo.apply');
    Route::post('/cashier/checkout', [CartController::class, 'checkout'])->name('cashier.checkout');
    Route::get('/cashier/transactions/{transaction}/receipt', [CartController::class, 'cashierReceipt'])->name('cashier.transactions.receipt');
    Route::post('/cashier/transactions/{transaction}/void', [CartController::class, 'cashierVoid'])->name('cashier.transactions.void');

    Route::post('/cashier/member/attach', [MemberCartController::class, 'attach'])->name('cashier.member.attach');
    Route::post('/cashier/member/quick-register', [MemberCartController::class, 'quickRegister'])->name('cashier.member.quick-register');
    Route::post('/cashier/member/detach', [MemberCartController::class, 'detach'])->name('cashier.member.detach');

    Route::get('/backoffice', BackofficeController::class)->name('backoffice.index');
    Route::post('/backoffice/approval-pins/generate', [BackofficeController::class, 'generateApprovalPin'])->name('backoffice.approval-pins.generate');
    Route::get('/backoffice/print-summary', [BackofficeController::class, 'printSummary'])->name('backoffice.print-summary');

    Route::get('/backoffice/users', [UserManagementController::class, 'index'])->name('backoffice.users.index');
    Route::get('/backoffice/users/create', [UserManagementController::class, 'create'])->name('backoffice.users.create');
    Route::post('/backoffice/users', [UserManagementController::class, 'store'])->name('backoffice.users.store');
    Route::get('/backoffice/users/{managedUser}/edit', [UserManagementController::class, 'edit'])->name('backoffice.users.edit');
    Route::put('/backoffice/users/{managedUser}', [UserManagementController::class, 'update'])->name('backoffice.users.update');
    Route::delete('/backoffice/users/{managedUser}', [UserManagementController::class, 'destroy'])->name('backoffice.users.destroy');

    Route::get('/backoffice/outlets', [OutletViewController::class, 'index'])->name('backoffice.outlets.index');
    Route::get('/backoffice/outlets/create', [OutletViewController::class, 'create'])->name('backoffice.outlets.create');
    Route::post('/backoffice/outlets', [OutletViewController::class, 'store'])->name('backoffice.outlets.store');
    Route::get('/backoffice/outlets/{outlet}/edit', [OutletViewController::class, 'edit'])->name('backoffice.outlets.edit');
    Route::put('/backoffice/outlets/{outlet}', [OutletViewController::class, 'update'])->name('backoffice.outlets.update');
    Route::delete('/backoffice/outlets/{outlet}', [OutletViewController::class, 'destroy'])->name('backoffice.outlets.destroy');

    Route::get('/backoffice/warehouses', [WarehouseViewController::class, 'index'])->name('backoffice.warehouses.index');
    Route::get('/backoffice/warehouses/create', [WarehouseViewController::class, 'create'])->name('backoffice.warehouses.create');
    Route::post('/backoffice/warehouses', [WarehouseViewController::class, 'store'])->name('backoffice.warehouses.store');
    Route::get('/backoffice/warehouses/{warehouse}/edit', [WarehouseViewController::class, 'edit'])->name('backoffice.warehouses.edit');
    Route::put('/backoffice/warehouses/{warehouse}', [WarehouseViewController::class, 'update'])->name('backoffice.warehouses.update');
    Route::get('/backoffice/warehouses/{warehouse}/stock', [WarehouseViewController::class, 'stockIndex'])->name('backoffice.warehouses.stock.index');
    Route::get('/backoffice/warehouses/{warehouse}/stock/create', [WarehouseViewController::class, 'stockCreate'])->name('backoffice.warehouses.stock.create');
    Route::post('/backoffice/warehouses/{warehouse}/stock', [WarehouseViewController::class, 'stockStore'])->name('backoffice.warehouses.stock.store');
    Route::get('/backoffice/warehouses/{warehouse}/movements', [WarehouseViewController::class, 'movementIndex'])->name('backoffice.warehouses.movements.index');

    Route::get('/backoffice/warehouse-transfers', [WarehouseTransferViewController::class, 'index'])->name('backoffice.warehouse-transfers.index');
    Route::get('/backoffice/warehouses/{warehouse}/transfer-to-outlet', [WarehouseTransferViewController::class, 'create'])->name('backoffice.warehouse-transfers.create');
    Route::post('/backoffice/warehouses/{warehouse}/transfer-to-outlet', [WarehouseTransferViewController::class, 'store'])->name('backoffice.warehouse-transfers.store');

    Route::get('/backoffice/transfers/export/csv', [TransferViewController::class, 'exportCsv'])->name('backoffice.transfers.export.csv');
    Route::get('/backoffice/transfers', [TransferViewController::class, 'index'])->name('backoffice.transfers.index');
    Route::get('/backoffice/transfers/create', [TransferViewController::class, 'create'])->name('backoffice.transfers.create');
    Route::get('/backoffice/transfers/available-ingredients', [TransferViewController::class, 'availableIngredients'])->name('backoffice.transfers.available-ingredients');
    Route::post('/backoffice/transfers', [TransferViewController::class, 'store'])->name('backoffice.transfers.store');
    Route::post('/backoffice/transfers/{transfer}/mark-received', [TransferViewController::class, 'markReceived'])->name('backoffice.transfers.mark-received');
    Route::post('/backoffice/transfers/{transfer}/mark-cancelled', [TransferViewController::class, 'markCancelled'])->name('backoffice.transfers.mark-cancelled');
    Route::post('/backoffice/transfers/{transfer}/mark-in-transit', [TransferViewController::class, 'markInTransit'])->name('backoffice.transfers.mark-in-transit');

    Route::get('/backoffice/products/import', [ProductViewController::class, 'importForm'])->name('backoffice.products.import');
    Route::get('/backoffice/products/import/template', [ProductViewController::class, 'downloadTemplate'])->name('backoffice.products.import.template');
    Route::post('/backoffice/products/import', [ProductViewController::class, 'importStore'])->name('backoffice.products.import.store');
    Route::get('/backoffice/products/export/csv', [ProductViewController::class, 'exportCsv'])->name('backoffice.products.export.csv');
    Route::get('/backoffice/products', [ProductViewController::class, 'index'])->name('backoffice.products.index');
    Route::get('/backoffice/products/create', [ProductViewController::class, 'create'])->name('backoffice.products.create');
    Route::post('/backoffice/products', [ProductViewController::class, 'store'])->name('backoffice.products.store');
    Route::get('/backoffice/products/{product}/edit', [ProductViewController::class, 'edit'])->name('backoffice.products.edit');
    Route::put('/backoffice/products/{product}', [ProductViewController::class, 'update'])->name('backoffice.products.update');
    Route::delete('/backoffice/products/{product}', [ProductViewController::class, 'destroy'])->name('backoffice.products.destroy');

    Route::get('/backoffice/variants/import', [ProductVariantViewController::class, 'importForm'])->name('backoffice.variants.import');
    Route::get('/backoffice/variants/import/template', [ProductVariantViewController::class, 'downloadTemplate'])->name('backoffice.variants.import.template');
    Route::post('/backoffice/variants/import', [ProductVariantViewController::class, 'importStore'])->name('backoffice.variants.import.store');
    Route::get('/backoffice/variants/export/csv', [ProductVariantViewController::class, 'exportCsv'])->name('backoffice.variants.export.csv');
    Route::get('/backoffice/variants', [ProductVariantViewController::class, 'index'])->name('backoffice.variants.index');
    Route::get('/backoffice/variants/create', [ProductVariantViewController::class, 'create'])->name('backoffice.variants.create');
    Route::post('/backoffice/variants', [ProductVariantViewController::class, 'store'])->name('backoffice.variants.store');
    Route::get('/backoffice/variants/{variant}/edit', [ProductVariantViewController::class, 'edit'])->name('backoffice.variants.edit');
    Route::put('/backoffice/variants/{variant}', [ProductVariantViewController::class, 'update'])->name('backoffice.variants.update');
    Route::delete('/backoffice/variants/{variant}', [ProductVariantViewController::class, 'destroy'])->name('backoffice.variants.destroy');

    Route::get('/backoffice/ingredients/import', [IngredientViewController::class, 'importForm'])->name('backoffice.ingredients.import');
    Route::get('/backoffice/ingredients/import/template', [IngredientViewController::class, 'downloadTemplate'])->name('backoffice.ingredients.import.template');
    Route::post('/backoffice/ingredients/import', [IngredientViewController::class, 'importStore'])->name('backoffice.ingredients.import.store');
    Route::get('/backoffice/ingredients/export/csv', [IngredientViewController::class, 'exportCsv'])->name('backoffice.ingredients.export.csv');
    Route::get('/backoffice/ingredients', [IngredientViewController::class, 'index'])->name('backoffice.ingredients.index');
    Route::get('/backoffice/ingredients/create', [IngredientViewController::class, 'create'])->name('backoffice.ingredients.create');
    Route::post('/backoffice/ingredients', [IngredientViewController::class, 'store'])->name('backoffice.ingredients.store');
    Route::get('/backoffice/ingredients/{ingredient}/edit', [IngredientViewController::class, 'edit'])->name('backoffice.ingredients.edit');
    Route::put('/backoffice/ingredients/{ingredient}', [IngredientViewController::class, 'update'])->name('backoffice.ingredients.update');
    Route::delete('/backoffice/ingredients/{ingredient}', [IngredientViewController::class, 'destroy'])->name('backoffice.ingredients.destroy');

    Route::get('/backoffice/recipes/import', [RecipeViewController::class, 'importForm'])->name('backoffice.recipes.import');
    Route::get('/backoffice/recipes/import/template', [RecipeViewController::class, 'downloadTemplate'])->name('backoffice.recipes.import.template');
    Route::post('/backoffice/recipes/import', [RecipeViewController::class, 'importStore'])->name('backoffice.recipes.import.store');
    Route::get('/backoffice/recipes/export/csv', [RecipeViewController::class, 'exportCsv'])->name('backoffice.recipes.export.csv');
    Route::get('/backoffice/recipes', [RecipeViewController::class, 'index'])->name('backoffice.recipes.index');
    Route::get('/backoffice/recipes/create', [RecipeViewController::class, 'create'])->name('backoffice.recipes.create');
    Route::post('/backoffice/recipes', [RecipeViewController::class, 'store'])->name('backoffice.recipes.store');
    Route::get('/backoffice/recipes/{recipe}/edit', [RecipeViewController::class, 'edit'])->name('backoffice.recipes.edit');
    Route::put('/backoffice/recipes/{recipe}', [RecipeViewController::class, 'update'])->name('backoffice.recipes.update');
    Route::post('/backoffice/recipes/{recipe}/items', [RecipeViewController::class, 'storeItem'])->name('backoffice.recipes.items.store');
    Route::delete('/backoffice/recipes/{recipe}/items/{item}', [RecipeViewController::class, 'destroyItem'])->name('backoffice.recipes.items.destroy');

    Route::get('/backoffice/production-recipes', [IngredientProductionRecipeController::class, 'index'])->name('backoffice.production-recipes.index');
    Route::get('/backoffice/production-recipes/create', [IngredientProductionRecipeController::class, 'create'])->name('backoffice.production-recipes.create');
    Route::post('/backoffice/production-recipes', [IngredientProductionRecipeController::class, 'store'])->name('backoffice.production-recipes.store');
    Route::get('/backoffice/production-recipes/{productionRecipe}/edit', [IngredientProductionRecipeController::class, 'edit'])->name('backoffice.production-recipes.edit');
    Route::put('/backoffice/production-recipes/{productionRecipe}', [IngredientProductionRecipeController::class, 'update'])->name('backoffice.production-recipes.update');
    Route::post('/backoffice/production-recipes/{productionRecipe}/items', [IngredientProductionRecipeController::class, 'storeItem'])->name('backoffice.production-recipes.items.store');
    Route::delete('/backoffice/production-recipes/{productionRecipe}/items/{item}', [IngredientProductionRecipeController::class, 'destroyItem'])->name('backoffice.production-recipes.items.destroy');

    Route::get('/backoffice/productions', [IngredientProductionController::class, 'index'])->name('backoffice.productions.index');
    Route::get('/backoffice/productions/create', [IngredientProductionController::class, 'create'])->name('backoffice.productions.create');
    Route::post('/backoffice/productions', [IngredientProductionController::class, 'store'])->name('backoffice.productions.store');
    Route::get('/backoffice/productions/{production}', [IngredientProductionController::class, 'show'])->name('backoffice.productions.show');

    Route::get('/backoffice/stock-balances/import', [StockBalanceViewController::class, 'importForm'])->name('backoffice.stock-balances.import');
    Route::get('/backoffice/stock-balances/import/template', [StockBalanceViewController::class, 'downloadTemplate'])->name('backoffice.stock-balances.import.template');
    Route::post('/backoffice/stock-balances/import', [StockBalanceViewController::class, 'importStore'])->name('backoffice.stock-balances.import.store');
    Route::get('/backoffice/stock-balances/export/csv', [StockBalanceViewController::class, 'exportCsv'])->name('backoffice.stock-balances.export.csv');
    Route::get('/backoffice/stock-balances', [StockBalanceViewController::class, 'index'])->name('backoffice.stock-balances.index');
    Route::get('/backoffice/stock-balances/create', [StockBalanceViewController::class, 'create'])->name('backoffice.stock-balances.create');
    Route::post('/backoffice/stock-balances', [StockBalanceViewController::class, 'store'])->name('backoffice.stock-balances.store');
    Route::get('/backoffice/stock-balances/adjustment', [StockBalanceViewController::class, 'createAdjustment'])->name('backoffice.stock-balances.adjustment.create');
    Route::post('/backoffice/stock-balances/adjustment', [StockBalanceViewController::class, 'storeAdjustment'])->name('backoffice.stock-balances.adjustment.store');
    Route::get('/backoffice/stock-balances/opname', [StockBalanceViewController::class, 'createOpname'])->name('backoffice.stock-balances.opname.create');
    Route::post('/backoffice/stock-balances/opname', [StockBalanceViewController::class, 'storeOpname'])->name('backoffice.stock-balances.opname.store');

    Route::get('/backoffice/stock-movements/export/csv', [StockMovementViewController::class, 'exportCsv'])->name('backoffice.stock-movements.export.csv');
    Route::get('/backoffice/stock-movements', StockMovementViewController::class)->name('backoffice.stock-movements.index');

    Route::get('/backoffice/shifts', [CashierShiftViewController::class, 'index'])->name('backoffice.shifts.index');
    Route::get('/backoffice/shifts/{shift}', [CashierShiftViewController::class, 'show'])->name('backoffice.shifts.show');

    Route::get('/backoffice/promos', [PromoViewController::class, 'index'])->name('backoffice.promos.index');
    Route::get('/backoffice/promos/create', [PromoViewController::class, 'create'])->name('backoffice.promos.create');
    Route::post('/backoffice/promos', [PromoViewController::class, 'store'])->name('backoffice.promos.store');
    Route::get('/backoffice/promos/{promo}/edit', [PromoViewController::class, 'edit'])->name('backoffice.promos.edit');
    Route::put('/backoffice/promos/{promo}', [PromoViewController::class, 'update'])->name('backoffice.promos.update');
    Route::delete('/backoffice/promos/{promo}', [PromoViewController::class, 'destroy'])->name('backoffice.promos.destroy');

    Route::get('/backoffice/discounts', [DiscountViewController::class, 'index'])->name('backoffice.discounts.index');
    Route::get('/backoffice/discounts/create', [DiscountViewController::class, 'create'])->name('backoffice.discounts.create');
    Route::post('/backoffice/discounts', [DiscountViewController::class, 'store'])->name('backoffice.discounts.store');
    Route::get('/backoffice/discounts/{discount}/edit', [DiscountViewController::class, 'edit'])->name('backoffice.discounts.edit');
    Route::put('/backoffice/discounts/{discount}', [DiscountViewController::class, 'update'])->name('backoffice.discounts.update');
    Route::delete('/backoffice/discounts/{discount}', [DiscountViewController::class, 'destroy'])->name('backoffice.discounts.destroy');

    Route::get('/backoffice/transactions', [TransactionViewController::class, 'index'])->name('backoffice.transactions.index');
    Route::get('/backoffice/transactions/export/csv', [TransactionViewController::class, 'exportCsv'])->name('backoffice.transactions.export.csv');
    Route::get('/backoffice/transactions/print-summary', [TransactionViewController::class, 'printSummary'])->name('backoffice.transactions.print');
    Route::post('/backoffice/transactions/{transaction}/void', [TransactionViewController::class, 'void'])->name('backoffice.transactions.void');
    Route::get('/backoffice/transactions/{transaction}', [TransactionViewController::class, 'show'])->name('backoffice.transactions.show');
    Route::get('/backoffice/transactions/{transaction}/receipt', [TransactionViewController::class, 'receipt'])->name('backoffice.transactions.receipt');

    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
});