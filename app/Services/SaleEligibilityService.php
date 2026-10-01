<?php

namespace App\Services;

use App\Exceptions\SaleNotEligibleException;
use App\Models\Outlet;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\SalesTransaction;
use Illuminate\Support\Collection;
use RuntimeException;

class SaleEligibilityService
{
    /**
     * Validate every sale dependency and return ingredient quantities keyed by ingredient ID.
     */
    public function requirementsForCart(array $cart, ?int $outletId): array
    {
        if (! $outletId) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        $outlet = Outlet::find($outletId);

        if (! $outlet) {
            throw new RuntimeException('Outlet transaksi tidak valid.');
        }

        if (empty($cart)) {
            throw new RuntimeException('Keranjang masih kosong.');
        }

        $cartRows = collect($cart)->map(function ($item) {
            return [
                'variant_id' => (int) ($item['variant_id'] ?? 0),
                'qty' => (float) ($item['qty'] ?? 0),
            ];
        });

        if ($cartRows->contains(fn (array $row) => $row['variant_id'] <= 0 || $row['qty'] <= 0)) {
            throw new RuntimeException('Keranjang berisi product variant atau qty yang tidak valid.');
        }

        $quantitiesByVariant = $cartRows
            ->groupBy('variant_id')
            ->map(fn ($rows) => (float) $rows->sum('qty'));

        $variantIds = $quantitiesByVariant->keys()->map(fn ($id) => (int) $id)->all();

        [$variants, $recipesByVariant] = $this->loadVariantsAndRecipes($variantIds);

        $requirements = [];

        foreach ($quantitiesByVariant as $variantId => $soldQty) {
            $variantRequirements = $this->checkVariant(
                (int) $variantId,
                $variants->get((int) $variantId),
                $recipesByVariant->get((int) $variantId, collect()),
                $outlet,
                $soldQty
            );

            foreach ($variantRequirements as $ingredientId => $qty) {
                $requirements[$ingredientId] = ($requirements[$ingredientId] ?? 0) + $qty;
            }
        }

        return $requirements;
    }

    /**
     * Sale status of many Variants at one outlet, using exactly the rules requirementsForCart() enforces
     * (they share checkVariant()). Constant number of queries however many Variants are asked about.
     *
     * @param  int[]  $variantIds
     * @return array<int, array{eligible: bool, reason: ?string, message: ?string}> keyed by Variant ID
     */
    public function variantStatuses(array $variantIds, ?int $outletId): array
    {
        $variantIds = array_values(array_unique(array_map('intval', $variantIds)));
        $outlet = $outletId ? Outlet::find($outletId) : null;

        if ($variantIds === [] || ! $outlet) {
            return [];
        }

        [$variants, $recipesByVariant] = $this->loadVariantsAndRecipes($variantIds);

        $statuses = [];

        foreach ($variantIds as $variantId) {
            try {
                $this->checkVariant(
                    $variantId,
                    $variants->get($variantId),
                    $recipesByVariant->get($variantId, collect()),
                    $outlet,
                    1.0
                );

                $statuses[$variantId] = ['eligible' => true, 'reason' => null, 'message' => null];
            } catch (SaleNotEligibleException $e) {
                $statuses[$variantId] = ['eligible' => false, 'reason' => $e->reason, 'message' => $e->cashierMessage];
            }
        }

        return $statuses;
    }

    private function loadVariantsAndRecipes(array $variantIds): array
    {
        $variants = ProductVariant::with([
            'product.outlets:id',
            'outlets:id',
        ])->whereIn('id', $variantIds)->get()->keyBy('id');

        $recipesByVariant = Recipe::with([
            'items.ingredient.outlets:id',
        ])->whereIn('product_variant_id', $variantIds)->get()->groupBy('product_variant_id');

        return [$variants, $recipesByVariant];
    }

    /**
     * The single place that decides whether one Variant can be sold at $outlet. Returns the ingredient
     * quantities needed for $soldQty (keyed by ingredient ID) or throws SaleNotEligibleException.
     *
     * Stock on hand is deliberately NOT a rule here: a zero/missing/negative balance never blocks a sale.
     */
    private function checkVariant(int $variantId, ?ProductVariant $variant, Collection $variantRecipes, Outlet $outlet, float $soldQty): array
    {
        $outletId = (int) $outlet->id;
        $fail = fn (string $message, string $reason, string $cashierMessage, ?string $displayName = null) => new SaleNotEligibleException($message, $reason, $cashierMessage, $variantId, $displayName);

        if (! $variant || ! $variant->product) {
            throw $fail('Product variant pada keranjang sudah tidak tersedia.', 'variant_missing', 'Variant sudah tidak tersedia.');
        }

        $displayName = trim($variant->product->name.' - '.$variant->name, ' -');

        if (! $variant->product->is_active) {
            throw $fail('Produk “'.$displayName.'” tidak aktif. Transaksi tidak dapat dilanjutkan.', 'product_inactive', 'Product sedang nonaktif.', $displayName);
        }

        if (! $variant->is_active) {
            throw $fail('Variant “'.$displayName.'” tidak aktif. Transaksi tidak dapat dilanjutkan.', 'variant_inactive', 'Variant sedang nonaktif.', $displayName);
        }

        if (! $variant->product->outlets->contains('id', $outletId)) {
            throw $fail('Produk “'.$displayName.'” tidak tersedia untuk '.$outlet->name.'.', 'product_not_at_outlet', 'Product tidak tersedia di '.$outlet->name.'.', $displayName);
        }

        if (! $variant->outlets->contains('id', $outletId)) {
            throw $fail('Variant “'.$displayName.'” tidak tersedia untuk '.$outlet->name.'.', 'variant_not_at_outlet', 'Variant tidak tersedia di '.$outlet->name.'.', $displayName);
        }

        if ($variantRecipes->isEmpty()) {
            throw $fail('Produk “'.$displayName.'” belum memiliki recipe. Transaksi tidak dapat dilanjutkan.', 'recipe_missing', 'Recipe belum tersedia.', $displayName);
        }

        $activeRecipes = $variantRecipes->where('is_active', true)->values();

        if ($activeRecipes->isEmpty()) {
            throw $fail('Produk “'.$displayName.'” belum memiliki recipe aktif. Transaksi tidak dapat dilanjutkan.', 'recipe_inactive', 'Recipe sedang nonaktif.', $displayName);
        }

        if ($activeRecipes->count() > 1) {
            throw $fail('Produk “'.$displayName.'” memiliki lebih dari satu recipe aktif. Hubungi Back Office.', 'recipe_ambiguous', 'Ada lebih dari satu Recipe aktif. Hubungi Back Office.', $displayName);
        }

        $recipe = $activeRecipes->first();

        if ((int) $recipe->product_id !== (int) $variant->product_id) {
            throw $fail('Recipe produk “'.$displayName.'” tidak terhubung ke product yang benar.', 'recipe_product_mismatch', 'Recipe tidak terhubung ke product yang benar. Hubungi Back Office.', $displayName);
        }

        if ($recipe->items->isEmpty()) {
            throw $fail('Recipe aktif untuk produk “'.$displayName.'” belum memiliki bahan. Transaksi tidak dapat dilanjutkan.', 'recipe_empty', 'Recipe belum memiliki bahan.', $displayName);
        }

        $requirements = [];

        foreach ($recipe->items as $recipeItem) {
            $ingredient = $recipeItem->ingredient;

            if (! $ingredient) {
                throw $fail('Recipe “'.$recipe->name.'” memiliki ingredient yang tidak valid.', 'ingredient_invalid', 'Recipe memiliki bahan yang tidak valid. Hubungi Back Office.', $displayName);
            }

            if (! $ingredient->is_active) {
                throw $fail('Ingredient “'.$ingredient->name.'” tidak aktif. Transaksi tidak dapat dilanjutkan.', 'ingredient_inactive', 'Ingredient '.$ingredient->name.' sedang nonaktif.', $displayName);
            }

            if (! $ingredient->outlets->contains('id', $outletId)) {
                throw $fail('Ingredient “'.$ingredient->name.'” belum tersedia untuk '.$outlet->name.'.', 'ingredient_not_at_outlet', 'Ingredient '.$ingredient->name.' belum tersedia di '.$outlet->name.'.', $displayName);
            }

            $recipeQty = (float) $recipeItem->qty;

            if ($recipeQty <= 0) {
                throw $fail('Qty ingredient “'.$ingredient->name.'” pada recipe “'.$recipe->name.'” harus lebih dari 0.', 'ingredient_qty_invalid', 'Qty ingredient '.$ingredient->name.' pada Recipe harus lebih dari 0.', $displayName);
            }

            $ingredientId = (int) $ingredient->id;
            $requirements[$ingredientId] = ($requirements[$ingredientId] ?? 0) + ($recipeQty * $soldQty);
        }

        return $requirements;
    }

    public function requirementsForTransaction(SalesTransaction $transaction): array
    {
        $transaction->loadMissing('items');

        $cart = $transaction->items->map(fn ($item) => [
            'variant_id' => $item->product_variant_id,
            'qty' => $item->qty,
        ])->all();

        return $this->requirementsForCart($cart, $transaction->outlet_id);
    }
}
