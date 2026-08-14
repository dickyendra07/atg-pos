<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\SalesTransaction;
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

        $variants = ProductVariant::with([
            'product.outlets:id',
            'outlets:id',
        ])->whereIn('id', $variantIds)->get()->keyBy('id');

        $recipesByVariant = Recipe::with([
            'items.ingredient.outlets:id',
        ])->whereIn('product_variant_id', $variantIds)->get()->groupBy('product_variant_id');

        $requirements = [];

        foreach ($quantitiesByVariant as $variantId => $soldQty) {
            $variant = $variants->get((int) $variantId);

            if (! $variant || ! $variant->product) {
                throw new RuntimeException('Product variant pada keranjang sudah tidak tersedia.');
            }

            $displayName = trim($variant->product->name.' - '.$variant->name, ' -');

            if (! $variant->product->is_active) {
                throw new RuntimeException('Produk “'.$displayName.'” tidak aktif. Transaksi tidak dapat dilanjutkan.');
            }

            if (! $variant->is_active) {
                throw new RuntimeException('Variant “'.$displayName.'” tidak aktif. Transaksi tidak dapat dilanjutkan.');
            }

            if (! $variant->product->outlets->contains('id', $outletId)) {
                throw new RuntimeException('Produk “'.$displayName.'” tidak tersedia untuk '.$outlet->name.'.');
            }

            if ($variant->outlets->isNotEmpty() && ! $variant->outlets->contains('id', $outletId)) {
                throw new RuntimeException('Variant “'.$displayName.'” tidak tersedia untuk '.$outlet->name.'.');
            }

            $variantRecipes = $recipesByVariant->get((int) $variantId, collect());

            if ($variantRecipes->isEmpty()) {
                throw new RuntimeException('Produk “'.$displayName.'” belum memiliki recipe. Transaksi tidak dapat dilanjutkan.');
            }

            $activeRecipes = $variantRecipes->where('is_active', true)->values();

            if ($activeRecipes->isEmpty()) {
                throw new RuntimeException('Produk “'.$displayName.'” belum memiliki recipe aktif. Transaksi tidak dapat dilanjutkan.');
            }

            if ($activeRecipes->count() > 1) {
                throw new RuntimeException('Produk “'.$displayName.'” memiliki lebih dari satu recipe aktif. Hubungi Back Office.');
            }

            $recipe = $activeRecipes->first();

            if ((int) $recipe->product_id !== (int) $variant->product_id) {
                throw new RuntimeException('Recipe produk “'.$displayName.'” tidak terhubung ke product yang benar.');
            }

            if ($recipe->items->isEmpty()) {
                throw new RuntimeException('Recipe aktif untuk produk “'.$displayName.'” belum memiliki bahan. Transaksi tidak dapat dilanjutkan.');
            }

            foreach ($recipe->items as $recipeItem) {
                $ingredient = $recipeItem->ingredient;

                if (! $ingredient) {
                    throw new RuntimeException('Recipe “'.$recipe->name.'” memiliki ingredient yang tidak valid.');
                }

                if (! $ingredient->is_active) {
                    throw new RuntimeException('Ingredient “'.$ingredient->name.'” tidak aktif. Transaksi tidak dapat dilanjutkan.');
                }

                if (! $ingredient->outlets->contains('id', $outletId)) {
                    throw new RuntimeException('Ingredient “'.$ingredient->name.'” belum tersedia untuk '.$outlet->name.'.');
                }

                $recipeQty = (float) $recipeItem->qty;

                if ($recipeQty <= 0) {
                    throw new RuntimeException('Qty ingredient “'.$ingredient->name.'” pada recipe “'.$recipe->name.'” harus lebih dari 0.');
                }

                $ingredientId = (int) $ingredient->id;
                $requirements[$ingredientId] = ($requirements[$ingredientId] ?? 0) + ($recipeQty * $soldQty);
            }
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
