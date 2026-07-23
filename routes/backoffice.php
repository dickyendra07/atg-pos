<?php

use App\Http\Controllers\Backoffice\BackofficeController;
use App\Http\Controllers\Backoffice\CashierShiftViewController;
use App\Http\Controllers\Backoffice\DiscountViewController;
use App\Http\Controllers\Backoffice\IngredientProductionController;
use App\Http\Controllers\Backoffice\IngredientProductionRecipeController;
use App\Http\Controllers\Backoffice\IngredientViewController;
use App\Http\Controllers\Backoffice\OutletViewController;
use App\Http\Controllers\Backoffice\ProductVariantViewController;
use App\Http\Controllers\Backoffice\ProductViewController;
use App\Http\Controllers\Backoffice\PromoViewController;
use App\Http\Controllers\Backoffice\RecipeViewController;
use App\Http\Controllers\Backoffice\StockBalanceViewController;
use App\Http\Controllers\Backoffice\StockMovementViewController;
use App\Http\Controllers\Backoffice\TransactionViewController;
use App\Http\Controllers\Backoffice\TransferViewController;
use App\Http\Controllers\Backoffice\UserManagementController;
use App\Http\Controllers\Backoffice\WarehouseTransferViewController;
use App\Http\Controllers\Backoffice\WarehouseViewController;
use Illuminate\Support\Facades\Route;

Route::prefix('backoffice')->name('backoffice.')->group(function () {
    Route::get('/', BackofficeController::class)->name('index');
    Route::post('/approval-pins/generate', [BackofficeController::class, 'generateApprovalPin'])->name('approval-pins.generate');
    Route::get('/print-summary', [BackofficeController::class, 'printSummary'])->name('print-summary');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{managedUser}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::put('/users/{managedUser}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('/users/{managedUser}', [UserManagementController::class, 'destroy'])->name('users.destroy');

    Route::get('/outlets', [OutletViewController::class, 'index'])->name('outlets.index');
    Route::get('/outlets/create', [OutletViewController::class, 'create'])->name('outlets.create');
    Route::post('/outlets', [OutletViewController::class, 'store'])->name('outlets.store');
    Route::get('/outlets/{outlet}/edit', [OutletViewController::class, 'edit'])->name('outlets.edit');
    Route::put('/outlets/{outlet}', [OutletViewController::class, 'update'])->name('outlets.update');
    Route::delete('/outlets/{outlet}', [OutletViewController::class, 'destroy'])->name('outlets.destroy');

    Route::get('/warehouses', [WarehouseViewController::class, 'index'])->name('warehouses.index');
    Route::get('/warehouses/create', [WarehouseViewController::class, 'create'])->name('warehouses.create');
    Route::post('/warehouses', [WarehouseViewController::class, 'store'])->name('warehouses.store');
    Route::get('/warehouses/{warehouse}/edit', [WarehouseViewController::class, 'edit'])->name('warehouses.edit');
    Route::put('/warehouses/{warehouse}', [WarehouseViewController::class, 'update'])->name('warehouses.update');
    Route::get('/warehouses/{warehouse}/stock', [WarehouseViewController::class, 'stockIndex'])->name('warehouses.stock.index');
    Route::get('/warehouses/{warehouse}/stock/create', [WarehouseViewController::class, 'stockCreate'])->name('warehouses.stock.create');
    Route::post('/warehouses/{warehouse}/stock', [WarehouseViewController::class, 'stockStore'])->name('warehouses.stock.store');
    Route::get('/warehouses/{warehouse}/movements', [WarehouseViewController::class, 'movementIndex'])->name('warehouses.movements.index');

    Route::get('/warehouse-transfers', [WarehouseTransferViewController::class, 'index'])->name('warehouse-transfers.index');
    Route::get('/warehouses/{warehouse}/transfer-to-outlet', [WarehouseTransferViewController::class, 'create'])->name('warehouse-transfers.create');
    Route::post('/warehouses/{warehouse}/transfer-to-outlet', [WarehouseTransferViewController::class, 'store'])->name('warehouse-transfers.store');

    Route::get('/transfers/export/csv', [TransferViewController::class, 'exportCsv'])->name('transfers.export.csv');
    Route::get('/transfers', [TransferViewController::class, 'index'])->name('transfers.index');
    Route::get('/transfers/create', [TransferViewController::class, 'create'])->name('transfers.create');
    Route::get('/transfers/available-ingredients', [TransferViewController::class, 'availableIngredients'])->name('transfers.available-ingredients');
    Route::post('/transfers', [TransferViewController::class, 'store'])->name('transfers.store');
    Route::post('/transfers/{transfer}/mark-received', [TransferViewController::class, 'markReceived'])->name('transfers.mark-received');
    Route::post('/transfers/{transfer}/mark-cancelled', [TransferViewController::class, 'markCancelled'])->name('transfers.mark-cancelled');
    Route::post('/transfers/{transfer}/mark-in-transit', [TransferViewController::class, 'markInTransit'])->name('transfers.mark-in-transit');

    Route::get('/products/import', [ProductViewController::class, 'importForm'])->name('products.import');
    Route::get('/products/import/template', [ProductViewController::class, 'downloadTemplate'])->name('products.import.template');
    Route::post('/products/import', [ProductViewController::class, 'importStore'])->name('products.import.store');
    Route::get('/products/export/csv', [ProductViewController::class, 'exportCsv'])->name('products.export.csv');
    Route::get('/products', [ProductViewController::class, 'index'])->name('products.index');
    Route::get('/products/create', [ProductViewController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductViewController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductViewController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductViewController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductViewController::class, 'destroy'])->name('products.destroy');

    Route::get('/variants/import', [ProductVariantViewController::class, 'importForm'])->name('variants.import');
    Route::get('/variants/import/template', [ProductVariantViewController::class, 'downloadTemplate'])->name('variants.import.template');
    Route::post('/variants/import', [ProductVariantViewController::class, 'importStore'])->name('variants.import.store');
    Route::get('/variants/export/csv', [ProductVariantViewController::class, 'exportCsv'])->name('variants.export.csv');
    Route::get('/variants', [ProductVariantViewController::class, 'index'])->name('variants.index');
    Route::get('/variants/create', [ProductVariantViewController::class, 'create'])->name('variants.create');
    Route::post('/variants', [ProductVariantViewController::class, 'store'])->name('variants.store');
    Route::get('/variants/{variant}/edit', [ProductVariantViewController::class, 'edit'])->name('variants.edit');
    Route::put('/variants/{variant}', [ProductVariantViewController::class, 'update'])->name('variants.update');
    Route::delete('/variants/{variant}', [ProductVariantViewController::class, 'destroy'])->name('variants.destroy');

    Route::get('/ingredients/import', [IngredientViewController::class, 'importForm'])->name('ingredients.import');
    Route::get('/ingredients/import/template', [IngredientViewController::class, 'downloadTemplate'])->name('ingredients.import.template');
    Route::post('/ingredients/import', [IngredientViewController::class, 'importStore'])->name('ingredients.import.store');
    Route::get('/ingredients/export/csv', [IngredientViewController::class, 'exportCsv'])->name('ingredients.export.csv');
    Route::get('/ingredients', [IngredientViewController::class, 'index'])->name('ingredients.index');
    Route::get('/ingredients/create', [IngredientViewController::class, 'create'])->name('ingredients.create');
    Route::post('/ingredients', [IngredientViewController::class, 'store'])->name('ingredients.store');
    Route::get('/ingredients/{ingredient}/edit', [IngredientViewController::class, 'edit'])->name('ingredients.edit');
    Route::put('/ingredients/{ingredient}', [IngredientViewController::class, 'update'])->name('ingredients.update');
    Route::delete('/ingredients/{ingredient}', [IngredientViewController::class, 'destroy'])->name('ingredients.destroy');

    Route::get('/recipes/import', [RecipeViewController::class, 'importForm'])->name('recipes.import');
    Route::get('/recipes/import/template', [RecipeViewController::class, 'downloadTemplate'])->name('recipes.import.template');
    Route::post('/recipes/import', [RecipeViewController::class, 'importStore'])->name('recipes.import.store');
    Route::get('/recipes/export/csv', [RecipeViewController::class, 'exportCsv'])->name('recipes.export.csv');
    Route::get('/recipes', [RecipeViewController::class, 'index'])->name('recipes.index');
    Route::get('/recipes/create', [RecipeViewController::class, 'create'])->name('recipes.create');
    Route::post('/recipes', [RecipeViewController::class, 'store'])->name('recipes.store');
    Route::get('/recipes/{recipe}/edit', [RecipeViewController::class, 'edit'])->name('recipes.edit');
    Route::put('/recipes/{recipe}', [RecipeViewController::class, 'update'])->name('recipes.update');
    Route::delete('/recipes/{recipe}', [RecipeViewController::class, 'destroy'])->name('recipes.destroy');
    Route::post('/recipes/{recipe}/items', [RecipeViewController::class, 'storeItem'])->name('recipes.items.store');
    Route::put('/recipes/{recipe}/items/{item}', [RecipeViewController::class, 'updateItem'])->name('recipes.items.update');
    Route::delete('/recipes/{recipe}/items/{item}', [RecipeViewController::class, 'destroyItem'])->name('recipes.items.destroy');

    Route::get('/production-recipes', [IngredientProductionRecipeController::class, 'index'])->name('production-recipes.index');
    Route::get('/production-recipes/create', [IngredientProductionRecipeController::class, 'create'])->name('production-recipes.create');
    Route::post('/production-recipes', [IngredientProductionRecipeController::class, 'store'])->name('production-recipes.store');
    Route::get('/production-recipes/{productionRecipe}/edit', [IngredientProductionRecipeController::class, 'edit'])->name('production-recipes.edit');
    Route::put('/production-recipes/{productionRecipe}', [IngredientProductionRecipeController::class, 'update'])->name('production-recipes.update');
    Route::post('/production-recipes/{productionRecipe}/items', [IngredientProductionRecipeController::class, 'storeItem'])->name('production-recipes.items.store');
    Route::delete('/production-recipes/{productionRecipe}/items/{item}', [IngredientProductionRecipeController::class, 'destroyItem'])->name('production-recipes.items.destroy');

    Route::get('/productions', [IngredientProductionController::class, 'index'])->name('productions.index');
    Route::get('/productions/create', [IngredientProductionController::class, 'create'])->name('productions.create');
    Route::post('/productions', [IngredientProductionController::class, 'store'])->name('productions.store');
    Route::get('/productions/{production}', [IngredientProductionController::class, 'show'])->name('productions.show');

    Route::get('/stock-balances/import', [StockBalanceViewController::class, 'importForm'])->name('stock-balances.import');
    Route::get('/stock-balances/import/template', [StockBalanceViewController::class, 'downloadTemplate'])->name('stock-balances.import.template');
    Route::post('/stock-balances/import', [StockBalanceViewController::class, 'importStore'])->name('stock-balances.import.store');
    Route::get('/stock-balances/export/csv', [StockBalanceViewController::class, 'exportCsv'])->name('stock-balances.export.csv');
    Route::get('/stock-balances', [StockBalanceViewController::class, 'index'])->name('stock-balances.index');
    Route::get('/stock-balances/create', [StockBalanceViewController::class, 'create'])->name('stock-balances.create');
    Route::post('/stock-balances', [StockBalanceViewController::class, 'store'])->name('stock-balances.store');
    Route::get('/stock-balances/adjustment', [StockBalanceViewController::class, 'createAdjustment'])->name('stock-balances.adjustment.create');
    Route::post('/stock-balances/adjustment', [StockBalanceViewController::class, 'storeAdjustment'])->name('stock-balances.adjustment.store');
    Route::get('/stock-balances/opname', [StockBalanceViewController::class, 'createOpname'])->name('stock-balances.opname.create');
    Route::post('/stock-balances/opname', [StockBalanceViewController::class, 'storeOpname'])->name('stock-balances.opname.store');

    Route::get('/stock-movements/export/csv', [StockMovementViewController::class, 'exportCsv'])->name('stock-movements.export.csv');
    Route::get('/stock-movements', StockMovementViewController::class)->name('stock-movements.index');

    Route::get('/shifts', [CashierShiftViewController::class, 'index'])->name('shifts.index');
    Route::get('/shifts/{shift}', [CashierShiftViewController::class, 'show'])->name('shifts.show');

    Route::get('/promos', [PromoViewController::class, 'index'])->name('promos.index');
    Route::get('/promos/create', [PromoViewController::class, 'create'])->name('promos.create');
    Route::post('/promos', [PromoViewController::class, 'store'])->name('promos.store');
    Route::get('/promos/{promo}/edit', [PromoViewController::class, 'edit'])->name('promos.edit');
    Route::put('/promos/{promo}', [PromoViewController::class, 'update'])->name('promos.update');
    Route::delete('/promos/{promo}', [PromoViewController::class, 'destroy'])->name('promos.destroy');

    Route::get('/discounts', [DiscountViewController::class, 'index'])->name('discounts.index');
    Route::get('/discounts/create', [DiscountViewController::class, 'create'])->name('discounts.create');
    Route::post('/discounts', [DiscountViewController::class, 'store'])->name('discounts.store');
    Route::get('/discounts/{discount}/edit', [DiscountViewController::class, 'edit'])->name('discounts.edit');
    Route::put('/discounts/{discount}', [DiscountViewController::class, 'update'])->name('discounts.update');
    Route::delete('/discounts/{discount}', [DiscountViewController::class, 'destroy'])->name('discounts.destroy');

    Route::get('/transactions', [TransactionViewController::class, 'index'])->name('transactions.index');
    Route::get('/transactions/export/csv', [TransactionViewController::class, 'exportCsv'])->name('transactions.export.csv');
    Route::get('/transactions/print-summary', [TransactionViewController::class, 'printSummary'])->name('transactions.print');
    Route::post('/transactions/{transaction}/void', [TransactionViewController::class, 'void'])->name('transactions.void');
    Route::get('/transactions/{transaction}', [TransactionViewController::class, 'show'])->name('transactions.show');
    Route::get('/transactions/{transaction}/receipt', [TransactionViewController::class, 'receipt'])->name('transactions.receipt');
});
