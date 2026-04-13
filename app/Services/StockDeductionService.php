<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockDeductionService
{
    public function validateCartStock(array $cart, ?int $outletId): void
    {
        if (! $outletId) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        $requirements = $this->buildIngredientRequirementsFromCart($cart);

        if (empty($requirements)) {
            return;
        }

        $errors = [];

        foreach ($requirements as $ingredientId => $qtyNeeded) {
            $stockBalance = StockBalance::with('ingredient')
                ->where('ingredient_id', $ingredientId)
                ->where('location_type', 'outlet')
                ->where('location_id', $outletId)
                ->first();

            $availableQty = (float) ($stockBalance?->qty_on_hand ?? 0);
            $ingredientName = $stockBalance?->ingredient?->name
                ?? Ingredient::find($ingredientId)?->name
                ?? ('Ingredient ID ' . $ingredientId);

            if ($availableQty < $qtyNeeded) {
                $errors[] = $ingredientName
                    . ' tidak cukup. Butuh '
                    . number_format($qtyNeeded, 2, ',', '.')
                    . ', tersedia '
                    . number_format($availableQty, 2, ',', '.');
            }
        }

        if (! empty($errors)) {
            throw new RuntimeException(implode(' | ', $errors));
        }
    }

    public function deductFromTransaction(SalesTransaction $transaction): void
    {
        $transaction->loadMissing(['items']);

        if (! $transaction->outlet_id) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        $requirements = $this->buildIngredientRequirementsFromTransaction($transaction);

        if (empty($requirements)) {
            return;
        }

        DB::transaction(function () use ($transaction, $requirements) {
            foreach ($requirements as $ingredientId => $qtyUsed) {
                $stockBalance = StockBalance::where('ingredient_id', $ingredientId)
                    ->where('location_type', 'outlet')
                    ->where('location_id', $transaction->outlet_id)
                    ->lockForUpdate()
                    ->first();

                $availableQty = (float) ($stockBalance?->qty_on_hand ?? 0);

                if (! $stockBalance || $availableQty < $qtyUsed) {
                    $ingredientName = Ingredient::find($ingredientId)?->name ?? ('Ingredient ID ' . $ingredientId);

                    throw new RuntimeException(
                        'Stock deduction gagal untuk '
                        . $ingredientName
                        . '. Butuh '
                        . number_format($qtyUsed, 2, ',', '.')
                        . ', tersedia '
                        . number_format($availableQty, 2, ',', '.')
                        . '.'
                    );
                }

                $stockBalance->update([
                    'qty_on_hand' => $availableQty - $qtyUsed,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredientId,
                    'location_type' => 'outlet',
                    'location_id' => $transaction->outlet_id,
                    'movement_type' => 'sales_usage',
                    'qty_in' => 0,
                    'qty_out' => $qtyUsed,
                    'reference_type' => 'sales_transaction',
                    'reference_id' => $transaction->id,
                    'note' => 'Auto deduction from cashier checkout ' . ($transaction->transaction_number ?? ('#' . $transaction->id)),
                ]);
            }
        });
    }

    public function restoreFromVoidedTransaction(SalesTransaction $transaction): void
    {
        $transaction->loadMissing(['items']);

        if (! $transaction->outlet_id) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        $requirements = $this->buildIngredientRequirementsFromTransaction($transaction);

        if (empty($requirements)) {
            return;
        }

        DB::transaction(function () use ($transaction, $requirements) {
            foreach ($requirements as $ingredientId => $qtyRestore) {
                $stockBalance = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $ingredientId,
                        'location_type' => 'outlet',
                        'location_id' => $transaction->outlet_id,
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $currentQty = (float) $stockBalance->qty_on_hand;

                $stockBalance->update([
                    'qty_on_hand' => $currentQty + $qtyRestore,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredientId,
                    'location_type' => 'outlet',
                    'location_id' => $transaction->outlet_id,
                    'movement_type' => 'sales_void_restore',
                    'qty_in' => $qtyRestore,
                    'qty_out' => 0,
                    'reference_type' => 'sales_transaction_void',
                    'reference_id' => $transaction->id,
                    'note' => 'Stock restored from void transaction ' . ($transaction->transaction_number ?? ('#' . $transaction->id)),
                ]);
            }
        });
    }

    protected function buildIngredientRequirementsFromCart(array $cart): array
    {
        $requirements = [];

        foreach ($cart as $cartItem) {
            $variantId = (int) ($cartItem['variant_id'] ?? 0);
            $cartQty = (float) ($cartItem['qty'] ?? 0);

            if ($variantId <= 0 || $cartQty <= 0) {
                continue;
            }

            $recipe = Recipe::with(['items.ingredient'])
                ->where('product_variant_id', $variantId)
                ->where('is_active', true)
                ->first();

            if (! $recipe || $recipe->items->isEmpty()) {
                continue;
            }

            foreach ($recipe->items as $recipeItem) {
                if (! $recipeItem->ingredient_id) {
                    continue;
                }

                $qtyNeeded = (float) $recipeItem->qty * $cartQty;

                if (! isset($requirements[$recipeItem->ingredient_id])) {
                    $requirements[$recipeItem->ingredient_id] = 0;
                }

                $requirements[$recipeItem->ingredient_id] += $qtyNeeded;
            }
        }

        return $requirements;
    }

    protected function buildIngredientRequirementsFromTransaction(SalesTransaction $transaction): array
    {
        $requirements = [];

        foreach ($transaction->items as $transactionItem) {
            $variantId = (int) ($transactionItem->product_variant_id ?? 0);
            $soldQty = (float) ($transactionItem->qty ?? 0);

            if ($variantId <= 0 || $soldQty <= 0) {
                continue;
            }

            $recipe = Recipe::with(['items.ingredient'])
                ->where('product_variant_id', $variantId)
                ->where('is_active', true)
                ->first();

            if (! $recipe || $recipe->items->isEmpty()) {
                continue;
            }

            foreach ($recipe->items as $recipeItem) {
                if (! $recipeItem->ingredient_id) {
                    continue;
                }

                $qtyNeeded = (float) $recipeItem->qty * $soldQty;

                if (! isset($requirements[$recipeItem->ingredient_id])) {
                    $requirements[$recipeItem->ingredient_id] = 0;
                }

                $requirements[$recipeItem->ingredient_id] += $qtyNeeded;
            }
        }

        return $requirements;
    }
}