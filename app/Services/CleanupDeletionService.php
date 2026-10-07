<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY destructive cleanup delete for master data (testing / data-cleanup phase).
 *
 * The ONE place that deletes Product, Variant, Ingredient and Recipe on behalf of the Back Office
 * cleanup action. Every entry point (the generic cleanup endpoint and the legacy Ingredient route)
 * goes through delete(), so none of them can skip the feature flag, the role check, the impact
 * validation or the typed confirmation.
 *
 * Strategy (decided with the business, see the schema audit):
 *  - Product / Variant / Ingredient: ALWAYS a tombstone (SoftDeletes: deleted_at). The row stays in the
 *    database so every historical record that points at it (sales_transaction_items, stock_movements,
 *    stock_transfers, adjustments, purchase receipts, productions, old Promo configuration) is untouched
 *    and still readable through withTrashed() relations. Operationally the row is gone: the global
 *    SoftDeletes scope hides it from every list, picker, search and from the Cashier.
 *    The unique `code` is released by renaming it to "<code>__del<id>" so the same code can be created again.
 *  - Recipe: a REAL delete together with its RecipeItems. A Recipe is configuration only, nothing
 *    historical references it. No other Recipe is touched; the Variant simply ends up without one.
 *
 * Nothing here ever deletes a sale, payment value, stock movement, transfer, adjustment, receipt or
 * production record, and nothing rewrites an active/future Promo or another Recipe: those cases are
 * BLOCKED and listed in the impact summary instead.
 */
class CleanupDeletionService
{
    public const TYPE_PRODUCT = 'product';

    public const TYPE_VARIANT = 'variant';

    public const TYPE_INGREDIENT = 'ingredient';

    public const TYPE_RECIPE = 'recipe';

    public const TYPES = [self::TYPE_PRODUCT, self::TYPE_VARIANT, self::TYPE_INGREDIENT, self::TYPE_RECIPE];

    public const ROLES = ['owner', 'admin_pusat'];

    public const DISABLED_MESSAGE = 'Mode hapus data uji sedang tidak aktif.';

    public const ROLE_DENIED_MESSAGE = 'Hanya owner atau admin pusat yang dapat menghapus data master.';

    public const STATUS_DELETED = 'deleted';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_MISMATCH = 'mismatch';

    public const STATUS_MISSING = 'missing';

    private const LABELS = [
        self::TYPE_PRODUCT => 'Product',
        self::TYPE_VARIANT => 'Variant',
        self::TYPE_INGREDIENT => 'Ingredient',
        self::TYPE_RECIPE => 'Recipe',
    ];

    /** Longest code a `code` column holds (all three are plain varchar(255)). */
    private const CODE_MAX = 255;

    /** How many names a blocker lists before it says "dan N lainnya". */
    private const LIST_LIMIT = 8;

    // ---- Gate: flag + role -------------------------------------------------------------------------------

    public static function enabled(): bool
    {
        return (bool) config('backoffice.destructive_delete_enabled', false);
    }

    /** The role part only: owner / admin_pusat (primary role code, as everywhere else in the Back Office). */
    public static function roleAllowed(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('role');

        return in_array($user->role?->code, self::ROLES, true);
    }

    /** What the UI asks before it draws a delete button. */
    public static function available(?User $user): bool
    {
        return self::enabled() && self::roleAllowed($user);
    }

    /** Server-side gate for every cleanup endpoint: a hidden button is never the protection. */
    public static function authorize(?User $user): void
    {
        abort_unless(self::enabled(), 403, self::DISABLED_MESSAGE);
        abort_unless(self::roleAllowed($user), 403, self::ROLE_DENIED_MESSAGE);
    }

    public static function label(string $type): string
    {
        return self::LABELS[$type];
    }

    public function find(string $type, int $id): ?Model
    {
        return match ($type) {
            self::TYPE_PRODUCT => Product::query()->find($id),
            self::TYPE_VARIANT => ProductVariant::query()->find($id),
            self::TYPE_INGREDIENT => Ingredient::query()->find($id),
            self::TYPE_RECIPE => Recipe::query()->find($id),
        };
    }

    // ---- Impact ------------------------------------------------------------------------------------------

    /**
     * What the confirmation dialog shows.
     *
     * @return array{type: string, type_label: string, id: int, name: string, confirm_text: string, strategy: string, counts: array<int, array{label: string, value: int}>, blockers: array<int, array{code: string, message: string, items: string[]}>, notes: string[], can_delete: bool}
     */
    public function impact(string $type, Model $model): array
    {
        return match ($type) {
            self::TYPE_PRODUCT => $this->productImpact($model),
            self::TYPE_VARIANT => $this->variantImpact($model),
            self::TYPE_INGREDIENT => $this->ingredientImpact($model),
            self::TYPE_RECIPE => $this->recipeImpact($model),
        };
    }

    private function productImpact(Product $product): array
    {
        $variantIds = ProductVariant::query()->where('product_id', $product->id)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $recipes = $this->recipesOfProduct($product->id, $variantIds);
        $promos = $this->promosFor($variantIds);

        $blockers = $this->promoBlockers($promos['blocking'], 'Product');

        if ($this->hasForeignRecipe($recipes, $product->id, $variantIds)) {
            $blockers[] = [
                'code' => 'recipe_inconsistent',
                'message' => 'Product ini memiliki data Recipe yang menyilang ke Product lain. Hubungi Back Office untuk merapikannya dulu.',
                'items' => [],
            ];
        }

        return $this->shape(self::TYPE_PRODUCT, $product, 'tombstone', [
            ['label' => 'Variant', 'value' => count($variantIds)],
            ['label' => 'Recipe (ikut dihapus permanen)', 'value' => $recipes->count()],
            ['label' => 'Item Recipe', 'value' => $recipes->isEmpty() ? 0 : RecipeItem::query()->whereIn('recipe_id', $recipes->pluck('id'))->count()],
            ['label' => 'Penempatan outlet', 'value' => (int) DB::table('product_outlet')->where('product_id', $product->id)->count()],
            ['label' => 'Promo aktif / terjadwal', 'value' => $promos['blocking']->count()],
            ['label' => 'Promo lama (selesai / dihentikan)', 'value' => $promos['historical']->count()],
            ['label' => 'Baris riwayat transaksi', 'value' => $this->salesRows($product->id, $variantIds)],
        ], $blockers, [
            'Product dan semua Variant-nya hilang dari seluruh tampilan operasional dan Cashier.',
            'Recipe milik Variant tersebut dihapus permanen (hanya konfigurasi).',
            'Riwayat transaksi, pembayaran, dan stok tetap tersimpan utuh.',
        ]);
    }

    private function variantImpact(ProductVariant $variant): array
    {
        $recipes = Recipe::query()->where('product_variant_id', $variant->id)->get(['id', 'name']);
        $promos = $this->promosFor([(int) $variant->id]);

        return $this->shape(self::TYPE_VARIANT, $variant, 'tombstone', [
            ['label' => 'Recipe (ikut dihapus permanen)', 'value' => $recipes->count()],
            ['label' => 'Item Recipe', 'value' => $recipes->isEmpty() ? 0 : RecipeItem::query()->whereIn('recipe_id', $recipes->pluck('id'))->count()],
            ['label' => 'Penempatan outlet', 'value' => (int) DB::table('product_variant_outlet')->where('product_variant_id', $variant->id)->count()],
            ['label' => 'Promo aktif / terjadwal', 'value' => $promos['blocking']->count()],
            ['label' => 'Promo lama (selesai / dihentikan)', 'value' => $promos['historical']->count()],
            ['label' => 'Baris riwayat transaksi', 'value' => $this->salesRows(null, [(int) $variant->id])],
        ], $this->promoBlockers($promos['blocking'], 'Variant'), [
            'Variant hilang dari Product Workspace, daftar Variant, Cashier, dan pilihan Recipe/Promo. Variant lain tidak berubah.',
            'Recipe milik Variant ini dihapus permanen (hanya konfigurasi).',
            'Riwayat transaksi tetap tersimpan utuh.',
        ]);
    }

    private function ingredientImpact(Ingredient $ingredient): array
    {
        $recipeRows = DB::table('recipe_items')
            ->join('recipes', 'recipes.id', '=', 'recipe_items.recipe_id')
            ->where('recipe_items.ingredient_id', $ingredient->id)
            ->select('recipes.id', 'recipes.name')->distinct()->orderBy('recipes.name')->get();

        $productionRecipes = DB::table('ingredient_production_recipes')
            ->where('output_ingredient_id', $ingredient->id)
            ->pluck('name', 'id')
            ->all();
        $productionRecipes += DB::table('ingredient_production_recipe_items')
            ->join('ingredient_production_recipes', 'ingredient_production_recipes.id', '=', 'ingredient_production_recipe_items.ingredient_production_recipe_id')
            ->where('ingredient_production_recipe_items.input_ingredient_id', $ingredient->id)
            ->pluck('ingredient_production_recipes.name', 'ingredient_production_recipes.id')
            ->all();

        $blockers = [];

        if ($recipeRows->isNotEmpty()) {
            $blockers[] = [
                'code' => 'recipe',
                'message' => 'Ingredient masih dipakai oleh '.$recipeRows->count().' Recipe. Hapus Ingredient ini dari Recipe tersebut terlebih dahulu; Recipe tidak diubah otomatis.',
                'items' => $this->limited($recipeRows->map(fn ($row) => $row->name.' (#'.$row->id.')')),
            ];
        }

        if ($productionRecipes !== []) {
            $blockers[] = [
                'code' => 'production_recipe',
                'message' => 'Ingredient masih dipakai oleh '.count($productionRecipes).' Resep Produksi. Lepaskan dari Resep Produksi tersebut terlebih dahulu.',
                'items' => $this->limited(collect($productionRecipes)->map(fn ($name, $id) => $name.' (#'.$id.')')->values()),
            ];
        }

        return $this->shape(self::TYPE_INGREDIENT, $ingredient, 'tombstone', [
            ['label' => 'Recipe yang memakai', 'value' => $recipeRows->count()],
            ['label' => 'Resep Produksi yang memakai', 'value' => count($productionRecipes)],
            ['label' => 'Saldo stok (riwayat tetap)', 'value' => (int) DB::table('stock_balances')->where('ingredient_id', $ingredient->id)->count()],
            ['label' => 'Pergerakan stok (riwayat tetap)', 'value' => (int) DB::table('stock_movements')->where('ingredient_id', $ingredient->id)->count()],
            ['label' => 'Transfer stok (riwayat tetap)', 'value' => (int) DB::table('stock_transfers')->where('ingredient_id', $ingredient->id)->count()],
            ['label' => 'Penyesuaian / opname (riwayat tetap)', 'value' => (int) DB::table('stock_adjustment_items')->where('ingredient_id', $ingredient->id)->count()],
            ['label' => 'Penerimaan pembelian (riwayat tetap)', 'value' => (int) DB::table('purchase_receipt_items')->where('ingredient_id', $ingredient->id)->count()],
            ['label' => 'Produksi (riwayat tetap)', 'value' => $this->productionRows($ingredient->id)],
        ], $blockers, [
            'Ingredient hilang dari daftar Ingredient dan semua pilihan. Penempatan outlet-nya dilepas.',
            'Seluruh riwayat stok (pergerakan, transfer, penyesuaian, penerimaan, produksi) tetap tersimpan dan tetap menampilkan nama Ingredient ini.',
        ]);
    }

    private function recipeImpact(Recipe $recipe): array
    {
        $variant = $recipe->product_variant_id ? ProductVariant::withTrashed()->find($recipe->product_variant_id) : null;

        return $this->shape(self::TYPE_RECIPE, $recipe, 'hard', [
            ['label' => 'Item Recipe (ikut dihapus)', 'value' => RecipeItem::query()->where('recipe_id', $recipe->id)->count()],
        ], [], array_values(array_filter([
            $variant ? 'Variant "'.$variant->name.'" akan tidak punya Recipe sampai Recipe baru dibuat. Tidak ada Recipe lain yang dipilih atau diaktifkan otomatis.' : null,
            'Recipe lain, qty, dan unit di tempat lain tidak berubah.',
            'Recipe dihapus permanen (hanya konfigurasi); tidak ada riwayat transaksi atau stok yang disentuh.',
        ])));
    }

    private function shape(string $type, Model $model, string $strategy, array $counts, array $blockers, array $notes): array
    {
        return [
            'type' => $type,
            'type_label' => self::label($type),
            'id' => (int) $model->getKey(),
            'name' => (string) $model->name,
            'confirm_text' => trim((string) $model->name),
            'strategy' => $strategy,
            'counts' => $counts,
            'blockers' => array_values($blockers),
            'notes' => $notes,
            'can_delete' => $blockers === [],
        ];
    }

    // ---- Delete ------------------------------------------------------------------------------------------

    /**
     * Runs the whole cleanup delete. The caller has already passed authorize(). The confirmation text is
     * checked first (no database work on a mismatch), the impact is computed AGAIN inside the transaction on
     * the locked row so a Promo/Recipe created after the dialog was drawn still blocks it.
     *
     * @return array{status: string, impact?: array, name?: string}
     */
    public function delete(string $type, int $id, string $confirmation): array
    {
        $model = $this->find($type, $id);

        if (! $model) {
            return ['status' => self::STATUS_MISSING];
        }

        if (trim($confirmation) === '' || trim($confirmation) !== trim((string) $model->name)) {
            return ['status' => self::STATUS_MISMATCH, 'name' => (string) $model->name];
        }

        return DB::transaction(function () use ($type, $id) {
            // Lock first, read second (a concurrent insert that references the row then waits for us and fails its foreign key).
            $model = match ($type) {
                self::TYPE_PRODUCT => Product::query()->lockForUpdate()->find($id),
                self::TYPE_VARIANT => ProductVariant::query()->lockForUpdate()->find($id),
                self::TYPE_INGREDIENT => Ingredient::query()->lockForUpdate()->find($id),
                self::TYPE_RECIPE => Recipe::query()->lockForUpdate()->find($id),
            };

            if (! $model) {
                return ['status' => self::STATUS_MISSING];
            }

            if ($type === self::TYPE_PRODUCT) {
                ProductVariant::query()->where('product_id', $id)->lockForUpdate()->pluck('id');
            }

            $impact = $this->impact($type, $model);

            if (! $impact['can_delete']) {
                return ['status' => self::STATUS_BLOCKED, 'impact' => $impact];
            }

            match ($type) {
                self::TYPE_PRODUCT => $this->deleteProduct($model),
                self::TYPE_VARIANT => $this->deleteVariant($model),
                self::TYPE_INGREDIENT => $this->deleteIngredient($model),
                self::TYPE_RECIPE => $this->deleteRecipe($model),
            };

            return ['status' => self::STATUS_DELETED, 'impact' => $impact, 'name' => $impact['name']];
        });
    }

    private function deleteProduct(Product $product): void
    {
        $variants = ProductVariant::query()->where('product_id', $product->id)->get();
        $variantIds = $variants->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->deleteRecipes($this->recipesOfProduct($product->id, $variantIds)->pluck('id')->all());

        DB::table('product_variant_outlet')->whereIn('product_variant_id', $variantIds)->delete();
        DB::table('product_outlet')->where('product_id', $product->id)->delete();

        foreach ($variants as $variant) {
            $this->tombstone($variant);
        }

        $this->tombstone($product);
    }

    private function deleteVariant(ProductVariant $variant): void
    {
        $this->deleteRecipes(Recipe::query()->where('product_variant_id', $variant->id)->pluck('id')->all());

        DB::table('product_variant_outlet')->where('product_variant_id', $variant->id)->delete();

        $this->tombstone($variant);
    }

    private function deleteIngredient(Ingredient $ingredient): void
    {
        // Only the availability pivot is configuration. Balances, movements, transfers, adjustments,
        // receipts and productions are history and stay exactly as they are.
        DB::table('ingredient_outlet')->where('ingredient_id', $ingredient->id)->delete();

        $this->tombstone($ingredient);
    }

    private function deleteRecipe(Recipe $recipe): void
    {
        $this->deleteRecipes([(int) $recipe->id]);
    }

    /** Real delete of the given Recipes and their items; touches nothing else. */
    private function deleteRecipes(array $recipeIds): void
    {
        if ($recipeIds === []) {
            return;
        }

        RecipeItem::query()->whereIn('recipe_id', $recipeIds)->delete();
        Recipe::query()->whereIn('id', $recipeIds)->delete();
    }

    /** Releases the unique code, then soft-deletes. Same transaction as the caller's. */
    private function tombstone(Model $model): void
    {
        $model->code = $this->tombstoneCode($model);
        $model->save();
        $model->delete();
    }

    /**
     * "<original code>__del<id>", cut so the whole code fits the column and the suffix stays intact. The id
     * makes it unique already; the loop only guards against a user having typed the same code by hand.
     */
    public function tombstoneCode(Model $model): string
    {
        $table = $model->getTable();
        $original = (string) $model->getOriginal('code');
        $base = '__del'.$model->getKey();
        $suffix = $base;
        $attempt = 0;

        do {
            $candidate = mb_substr($original, 0, self::CODE_MAX - mb_strlen($suffix)).$suffix;
            $taken = DB::table($table)->where('code', $candidate)->where('id', '!=', $model->getKey())
                ->when($table === 'product_variants', fn ($query) => $query->where('product_id', $model->product_id))
                ->exists();
            $suffix = $base.'x'.(++$attempt);
        } while ($taken);

        return $candidate;
    }

    // ---- Dependency helpers ------------------------------------------------------------------------------

    /** Recipes that belong to this Product or to one of its Variants. */
    private function recipesOfProduct(int $productId, array $variantIds): Collection
    {
        return Recipe::query()
            ->where('product_id', $productId)
            ->when($variantIds !== [], fn ($query) => $query->orWhereIn('product_variant_id', $variantIds))
            ->get(['id', 'name', 'product_id', 'product_variant_id']);
    }

    /** A Recipe whose Product and Variant owner disagree: deleting it would reach into another Product. */
    private function hasForeignRecipe(Collection $recipes, int $productId, array $variantIds): bool
    {
        return $recipes->contains(function (Recipe $recipe) use ($productId, $variantIds) {
            $viaProduct = (int) $recipe->product_id === $productId;
            $viaVariant = $recipe->product_variant_id && in_array((int) $recipe->product_variant_id, $variantIds, true);

            if ($viaProduct && $recipe->product_variant_id && ! $viaVariant) {
                // our Recipe, but it targets a Variant of someone else (a live or tombstoned one)
                return true;
            }

            return $viaVariant && ! $viaProduct;
        });
    }

    /**
     * Promos that reference any of these Variants, split into the ones that block and the ones that are
     * historical. A Promo blocks while it is not discontinued and has not ended yet: draft, active and
     * scheduled Promos can still go live, so they must be resolved by the user first. Nothing about a Promo
     * is ever rewritten here.
     *
     * @return array{blocking: Collection<int, Promo>, historical: Collection<int, Promo>}
     */
    public function promosFor(array $variantIds): array
    {
        if ($variantIds === []) {
            return ['blocking' => collect(), 'historical' => collect()];
        }

        $today = now()->toDateString();
        $promos = Promo::query()->referencingVariants($variantIds)
            ->get(['id', 'name', 'status', 'is_active', 'start_date', 'end_date']);

        [$blocking, $historical] = $promos->partition(function (Promo $promo) use ($today) {
            if ($promo->status === 'discontinued') {
                return false;
            }

            return $promo->end_date === null || $promo->end_date->toDateString() >= $today;
        });

        return ['blocking' => $blocking->values(), 'historical' => $historical->values()];
    }

    private function promoBlockers(Collection $blocking, string $subject): array
    {
        if ($blocking->isEmpty()) {
            return [];
        }

        $today = now()->toDateString();

        return [[
            'code' => 'promo',
            'message' => $subject.' ini masih dipakai oleh '.$blocking->count().' Promo yang aktif atau masih bisa aktif. Selesaikan / hentikan Promo tersebut dulu; Promo tidak diubah otomatis.',
            'items' => $this->limited($blocking->map(function (Promo $promo) use ($today) {
                $state = match (true) {
                    $promo->start_date && $promo->start_date->toDateString() > $today => 'terjadwal',
                    $promo->status === 'draft' => 'draft',
                    default => 'aktif',
                };

                return $promo->name.' ('.$state.', #'.$promo->id.')';
            })),
        ]];
    }

    private function salesRows(?int $productId, array $variantIds): int
    {
        return (int) DB::table('sales_transaction_items')
            ->where(function ($query) use ($productId, $variantIds) {
                if ($productId) {
                    $query->orWhere('product_id', $productId);
                }

                if ($variantIds !== []) {
                    $query->orWhereIn('product_variant_id', $variantIds);
                }
            })
            ->count();
    }

    private function productionRows(int $ingredientId): int
    {
        return (int) DB::table('ingredient_production_items')->where('ingredient_id', $ingredientId)->count()
            + (int) DB::table('ingredient_productions')->where('output_ingredient_id', $ingredientId)->count();
    }

    /** @return string[] */
    private function limited(Collection $names): array
    {
        $shown = $names->take(self::LIST_LIMIT)->values()->all();

        if ($names->count() > self::LIST_LIMIT) {
            $shown[] = 'dan '.($names->count() - self::LIST_LIMIT).' lainnya';
        }

        return $shown;
    }
}
