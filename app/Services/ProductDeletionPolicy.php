<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one place that decides whether a Product may be deleted for good.
 *
 * Default is NO. A Product is only "disposable" when nothing that matters historically or
 * commercially points at it or at its Variants. The check is deliberately GLOBAL (every outlet,
 * active or not): the Active Backoffice Outlet only filters what a list shows, it never narrows
 * this safety check.
 *
 * What blocks (and why a foreign key is not trusted to be "safe"):
 *  - sales_transaction_items.product_id / product_variant_id are ON DELETE SET NULL. The row survives
 *    but loses its link to the Product, which breaks reporting and audit -> block.
 *  - promo_requirements.product_variant_id is ON DELETE CASCADE: deleting a Variant would silently
 *    delete Promo configuration -> block. promo_rewards / promos.requirement_/reward_product_variant_id
 *    are SET NULL: they would silently change what a Promo does -> block.
 *  - Recipes that point across Products (recipe.product_id and recipe.product_variant_id belong to
 *    different Products): deleting would remove or detach another Product's Recipe -> block.
 *
 * What does NOT block (cascades, all configuration of a Product nobody has ever sold or promoted):
 *  - product_variants (ON DELETE CASCADE) and their product_variant_outlet rows
 *  - recipes (ON DELETE CASCADE on product_id) and their recipe_items
 *  - product_outlet rows
 *
 * Inventory (stock balances/movements/transfers/production) is Ingredient-based and has no reference
 * to Products, so it is not part of this check.
 */
class ProductDeletionPolicy
{
    public const BLOCK_SALES = 'sales_history';

    public const BLOCK_PROMO = 'promo';

    public const BLOCK_RECIPE = 'recipe_inconsistent';

    private const BLOCK_TEXT = [
        self::BLOCK_SALES => 'sudah memiliki riwayat transaksi',
        self::BLOCK_PROMO => 'masih digunakan oleh Promo',
        self::BLOCK_RECIPE => 'memiliki data Recipe yang tidak konsisten (hubungi Back Office)',
    ];

    /**
     * @return array{can_hard_delete: bool, can_inactivate: bool, blockers: array<int, array{code: string, message: string}>, warnings: string[], impact: array{variants: int, recipes: int, outlets: int}}
     */
    public function evaluate(Product $product): array
    {
        return $this->evaluateMany(collect([$product]))[$product->id];
    }

    /**
     * Same verdict for many Products using a fixed number of queries (for the Product Index).
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array>
     */
    public function evaluateMany(Collection $products): array
    {
        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($productIds === []) {
            return [];
        }

        // variant -> product, for every Variant of these Products
        $variantOwner = DB::table('product_variants')->whereIn('product_id', $productIds)->pluck('product_id', 'id')
            ->map(fn ($id) => (int) $id)->all();
        $variantIds = array_map('intval', array_keys($variantOwner));

        $productsWithSales = $this->idsOf(
            DB::table('sales_transaction_items')->whereIn('product_id', $productIds)->distinct()->pluck('product_id')
        );
        $variantsWithSales = $variantIds === [] ? [] : $this->idsOf(
            DB::table('sales_transaction_items')->whereIn('product_variant_id', $variantIds)->distinct()->pluck('product_variant_id')
        );

        $variantsInPromo = [];

        if ($variantIds !== []) {
            foreach ([
                ['promo_requirements', 'product_variant_id'],
                ['promo_rewards', 'product_variant_id'],
                ['promos', 'requirement_product_variant_id'],
                ['promos', 'reward_product_variant_id'],
            ] as [$table, $column]) {
                $variantsInPromo = array_merge(
                    $variantsInPromo,
                    $this->idsOf(DB::table($table)->whereIn($column, $variantIds)->distinct()->pluck($column))
                );
            }
        }

        $recipes = DB::table('recipes')
            ->whereIn('product_id', $productIds)
            ->when($variantIds !== [], fn ($query) => $query->orWhereIn('product_variant_id', $variantIds))
            ->get(['id', 'product_id', 'product_variant_id']);

        // owners of Recipe variants that are not among our own Variants (cross-product anomaly)
        $unknownVariantIds = $recipes->pluck('product_variant_id')->filter()->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => isset($variantOwner[$id]))->unique()->values()->all();
        $foreignOwner = $unknownVariantIds === [] ? [] : DB::table('product_variants')->whereIn('id', $unknownVariantIds)->pluck('product_id', 'id')
            ->map(fn ($id) => (int) $id)->all();
        $ownerOf = fn (int $variantId) => $variantOwner[$variantId] ?? $foreignOwner[$variantId] ?? null;

        $outletCounts = DB::table('product_outlet')->whereIn('product_id', $productIds)
            ->selectRaw('product_id, count(*) as total')->groupBy('product_id')->pluck('total', 'product_id');

        $verdicts = [];

        foreach ($products as $product) {
            $id = (int) $product->id;
            $ownVariantIds = array_map('intval', array_keys(array_filter($variantOwner, fn ($owner) => $owner === $id)));
            $blockers = [];

            if (in_array($id, $productsWithSales, true) || array_intersect($ownVariantIds, $variantsWithSales) !== []) {
                $blockers[] = $this->blocker(self::BLOCK_SALES);
            }

            if (array_intersect($ownVariantIds, $variantsInPromo) !== []) {
                $blockers[] = $this->blocker(self::BLOCK_PROMO);
            }

            $recipeCount = 0;
            $inconsistent = false;

            foreach ($recipes as $recipe) {
                $recipeProduct = (int) $recipe->product_id;
                $variantProduct = $recipe->product_variant_id ? $ownerOf((int) $recipe->product_variant_id) : null;
                $touchesThis = $recipeProduct === $id || $variantProduct === $id;

                if (! $touchesThis) {
                    continue;
                }

                if ($variantProduct !== null && $variantProduct !== $recipeProduct) {
                    $inconsistent = true;
                }

                if ($recipeProduct === $id) {
                    $recipeCount++;
                }
            }

            if ($inconsistent) {
                $blockers[] = $this->blocker(self::BLOCK_RECIPE);
            }

            $warnings = [];

            if ($product->is_active) {
                $warnings[] = 'Product masih aktif dan akan hilang dari Cashier.';
            }

            $verdict = [
                'can_hard_delete' => $blockers === [],
                'can_inactivate' => true,
                'blockers' => $blockers,
                'warnings' => $warnings,
                'impact' => [
                    'variants' => count($ownVariantIds),
                    'recipes' => $recipeCount,
                    'outlets' => (int) ($outletCounts[$id] ?? 0),
                ],
            ];

            // ready-made wording for the UI (one source for the texts as well)
            $verdict['blocked_message'] = $this->blockedMessage($verdict);
            $verdict['confirm_note'] = $this->confirmNote($verdict);

            $verdicts[$id] = $verdict;
        }

        return $verdicts;
    }

    /**
     * Human wording for a verdict, e.g. "Product tidak dapat dihapus permanen karena sudah memiliki
     * riwayat transaksi dan masih digunakan oleh Promo."
     */
    public function blockedMessage(array $verdict): string
    {
        $reasons = array_column($verdict['blockers'], 'message');

        if ($reasons === []) {
            return '';
        }

        $last = array_pop($reasons);
        $joined = $reasons === [] ? $last : implode(', ', $reasons).' dan '.$last;

        return 'Product tidak dapat dihapus permanen karena '.$joined.'.';
    }

    /** What else disappears with a safe permanent delete, for the confirmation dialog. */
    public function confirmNote(array $verdict): string
    {
        $parts = [];

        if (($verdict['impact']['variants'] ?? 0) > 0) {
            $parts[] = $verdict['impact']['variants'].' Variant';
        }

        if (($verdict['impact']['recipes'] ?? 0) > 0) {
            $parts[] = $verdict['impact']['recipes'].' Recipe';
        }

        $lines = [];

        if ($parts !== []) {
            $lines[] = implode(' dan ', $parts).' yang belum pernah dipakai juga akan dihapus.';
        }

        return trim(implode(' ', array_merge($lines, $verdict['warnings'] ?? [])));
    }

    /**
     * Deletes the Product and its disposable children, or returns the blocking verdict. The verdict is
     * computed again INSIDE the transaction on the locked Product row, so a Product that became used
     * after the page was rendered is refused.
     *
     * @return array{deleted: bool, verdict: array}
     */
    public function deletePermanently(int $productId): array
    {
        return DB::transaction(function () use ($productId) {
            // Order matters on MySQL/InnoDB: take the locks BEFORE any plain read, so the verdict below is
            // computed after them. A concurrent sale/promo insert that references this Product or one of its
            // Variants needs a shared lock on that parent row, so it waits for us and then fails its foreign
            // key instead of slipping in between our check and our delete.
            $product = Product::query()->lockForUpdate()->find($productId);

            if (! $product) {
                return ['deleted' => false, 'verdict' => ['can_hard_delete' => false, 'blockers' => [], 'missing' => true]];
            }

            DB::table('product_variants')->where('product_id', $product->id)->lockForUpdate()->pluck('id');

            $verdict = $this->evaluate($product);

            if (! $verdict['can_hard_delete']) {
                return ['deleted' => false, 'verdict' => $verdict];
            }

            // Explicit, in dependency order, so the result never depends on how a given database
            // enforces ON DELETE rules.
            $variantIds = DB::table('product_variants')->where('product_id', $product->id)->pluck('id')->all();
            $recipeIds = DB::table('recipes')->where('product_id', $product->id)->pluck('id')->all();

            DB::table('recipe_items')->whereIn('recipe_id', $recipeIds)->delete();
            DB::table('recipes')->whereIn('id', $recipeIds)->delete();
            DB::table('product_variant_outlet')->whereIn('product_variant_id', $variantIds)->delete();
            DB::table('product_variants')->whereIn('id', $variantIds)->delete();
            DB::table('product_outlet')->where('product_id', $product->id)->delete();
            $product->delete();

            return ['deleted' => true, 'verdict' => $verdict];
        });
    }

    private function blocker(string $code): array
    {
        return ['code' => $code, 'message' => self::BLOCK_TEXT[$code]];
    }

    /** @return int[] */
    private function idsOf(Collection $values): array
    {
        return $values->filter()->map(fn ($id) => (int) $id)->values()->all();
    }
}
