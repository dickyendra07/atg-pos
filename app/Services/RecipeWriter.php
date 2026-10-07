<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The one place that writes Recipes and Recipe items. Used by the classic Recipe pages
 * (RecipeViewController) and by the Product Workspace, so both follow the same rules. Moved out of
 * RecipeViewController; the classic behaviour is kept except where noted:
 *
 *  - a Recipe is GLOBAL per ProductVariant (no outlet of its own); a new one may only be created for a
 *    Variant without any Recipe (the classic unique rule), and product_id follows the Variant;
 *  - a Recipe item's Ingredient must be active and available in EVERY outlet of the Recipe's Variant,
 *    may appear only once per Recipe (new rows), and qty is numeric >= 0.01; a new item copies the
 *    Ingredient's unit; existing items keep their stored qty/unit unless that value is edited;
 *  - FIX: setting a Recipe active is refused while ANOTHER Recipe of the same Variant is active, so no
 *    path can create a second active Recipe (the database has no unique constraint for this).
 *
 * This is an editing workflow, never data remediation: nothing here repairs, merges, deactivates or
 * picks one of several active Recipes, converts units or rewrites a quantity the user did not edit.
 * Who may write is decided by the callers with RecipeAccessPolicy (+ the page roles), not here.
 *
 * The database does not guarantee one active Recipe per Variant. Writes that depend on it lock the
 * Variant row first (SELECT ... FOR UPDATE where the driver supports it), so concurrent activations of
 * the same Variant are serialised and re-check the current state.
 */
class RecipeWriter
{
    public const AMBIGUOUS_MESSAGE = 'Lebih dari satu Recipe aktif. Perlu review data terlebih dahulu.';

    public const OTHER_ACTIVE_MESSAGE = 'Variant ini sudah memiliki Recipe aktif lain. Nonaktifkan Recipe tersebut terlebih dahulu; sistem tidak menonaktifkannya otomatis.';

    public const ALREADY_EXISTS_MESSAGE = 'Variant ini sudah memiliki Recipe. Buka Recipe yang ada; Recipe baru tidak dibuat.';

    public const DUPLICATE_ITEM_MESSAGE = 'Ingredient itu sudah ada di recipe ini. Edit qty-nya dulu atau hapus lalu tambah ulang.';

    public const INACTIVE_INGREDIENT_MESSAGE = 'Ingredient tidak aktif dan tidak bisa ditambahkan ke recipe.';

    public const MISSING_OUTLET_MESSAGE = 'Ingredient harus tersedia di seluruh outlet Variant recipe ini.';

    public const STALE_QTY_MESSAGE = 'Qty bahan ini sudah diubah di tempat lain sejak Recipe dibuka. Tutup lalu buka lagi Recipe ini.';

    /** Recipe item quantity rule (classic storeItem / updateItem). */
    public static function qtyRule(): string
    {
        return 'required|numeric|min:0.01';
    }

    public static function nameRule(): string
    {
        return 'required|string|max:255';
    }

    /** Default name of a new Recipe (same convention as the Recipe import). */
    public static function defaultName(ProductVariant $variant): string
    {
        return ($variant->product?->name ?? 'Recipe').' - '.$variant->name;
    }

    // ---- Shared rules ---------------------------------------------------------------------------------

    /** Outlets a Recipe Ingredient must be available in: every outlet of the Recipe's Variant. */
    public function requiredOutletIds(?ProductVariant $variant): Collection
    {
        if (! $variant) {
            return collect();
        }

        return $variant->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->values();
    }

    public function ingredientMissingOutletIds(Collection $requiredOutletIds, Ingredient $ingredient): Collection
    {
        return $requiredOutletIds->diff($ingredient->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id))->values();
    }

    /** Why this Ingredient cannot be added to a Recipe of the Variant (null = allowed). */
    public function ingredientProblem(?Ingredient $ingredient, Collection $requiredOutletIds): ?string
    {
        if (! $ingredient) {
            return 'Ingredient tidak ditemukan.';
        }

        if (! $ingredient->is_active) {
            return self::INACTIVE_INGREDIENT_MESSAGE;
        }

        if ($this->ingredientMissingOutletIds($requiredOutletIds, $ingredient)->isNotEmpty()) {
            return self::MISSING_OUTLET_MESSAGE;
        }

        return null;
    }

    /**
     * Ingredients that can be added to a Recipe of $variant (same rule as addItem): active, available
     * in every outlet of the Variant, and not in $excludeIds (the Recipe's current Ingredients).
     */
    public function selectableIngredients(?ProductVariant $variant, iterable $excludeIds = []): Collection
    {
        $requiredOutletIds = $this->requiredOutletIds($variant);

        return Ingredient::with(['category'])
            ->where('is_active', true)
            ->whereNotIn('id', collect($excludeIds)->map(fn ($id) => (int) $id)->all())
            ->where(function ($query) use ($requiredOutletIds) {
                foreach ($requiredOutletIds as $outletId) {
                    $query->whereHas('outlets', fn ($outletQuery) => $outletQuery->where('outlets.id', $outletId));
                }
            })
            ->orderByRaw("
                CASE
                    WHEN ingredient_type = 'semi_finished' THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('name')
            ->get();
    }

    public function containsIngredient(Recipe $recipe, int $ingredientId): bool
    {
        return $recipe->items()->where('ingredient_id', $ingredientId)->exists();
    }

    /**
     * Problems that keep this Recipe from being activated (empty = can be activated). Mirrors what
     * SaleEligibilityService requires of the one active Recipe, checked against every outlet of the
     * Variant. Units are not compared: the system has no unit conversion.
     *
     * @return string[]
     */
    public function activationProblems(Recipe $recipe, ProductVariant $variant): array
    {
        $problems = [];

        if ((int) $recipe->product_variant_id !== (int) $variant->id || (int) $recipe->product_id !== (int) $variant->product_id) {
            return ['Recipe ini tidak terhubung ke Product / Variant yang benar.'];
        }

        $otherActive = Recipe::where('product_variant_id', $variant->id)
            ->whereKeyNot($recipe->id)
            ->where('is_active', true)
            ->exists();

        if ($otherActive) {
            $problems[] = self::OTHER_ACTIVE_MESSAGE;
        }

        $items = $recipe->items()->with(['ingredient.outlets:id'])->orderBy('id')->get();

        if ($items->isEmpty()) {
            $problems[] = 'Recipe belum memiliki bahan. Tambahkan minimal 1 bahan sebelum mengaktifkan.';

            return $problems;
        }

        $required = $this->requiredOutletIds($variant);

        foreach ($items as $item) {
            $ingredient = $item->ingredient;

            if (! $ingredient) {
                $problems[] = 'Recipe memiliki bahan dengan Ingredient yang tidak valid.';

                continue;
            }

            if (! $ingredient->is_active) {
                $problems[] = 'Ingredient "'.$ingredient->name.'" tidak aktif.';
            }

            if ($required->diff($ingredient->outlets->pluck('id')->map(fn ($id) => (int) $id))->isNotEmpty()) {
                $problems[] = 'Ingredient "'.$ingredient->name.'" belum tersedia di seluruh outlet Variant ini.';
            }

            if ((float) $item->qty <= 0) {
                $problems[] = 'Qty Ingredient "'.$ingredient->name.'" harus lebih dari 0.';
            }
        }

        return array_values(array_unique($problems));
    }

    // ---- Classic Recipe pages -------------------------------------------------------------------------

    public function create(ProductVariant $variant, string $name, bool $isActive): Recipe
    {
        return DB::transaction(function () use ($variant, $name, $isActive) {
            $variant = $this->lockVariant($variant);

            if (Recipe::where('product_variant_id', $variant->id)->exists()) {
                throw ValidationException::withMessages(['product_variant_id' => 'The product variant id has already been taken.']);
            }

            return Recipe::create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'name' => $name,
                'is_active' => $isActive,
            ]);
        });
    }

    /** Header of the classic editor: Variant (re-point), name and status. */
    public function updateHeader(Recipe $recipe, ProductVariant $variant, string $name, bool $isActive): void
    {
        DB::transaction(function () use ($recipe, $variant, $name, $isActive) {
            $variant = $this->lockVariant($variant);

            // Only BECOMING active (or moving while active) is guarded: an already active Recipe of an
            // ambiguous Variant can still be renamed or set inactive here, which is how it gets resolved.
            $becomesActive = $isActive && (! $recipe->is_active || (int) $recipe->product_variant_id !== (int) $variant->id);

            if ($becomesActive && $this->otherActiveExists($variant, $recipe)) {
                throw ValidationException::withMessages(['is_active' => self::OTHER_ACTIVE_MESSAGE]);
            }

            $recipe->update([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'name' => $name,
                'is_active' => $isActive,
            ]);
        });
    }

    public function deactivate(Recipe $recipe): void
    {
        $recipe->update(['is_active' => false]);
    }

    /** One new item, unit copied from the Ingredient. Throws on an inactive / out-of-outlet / duplicate Ingredient. */
    public function addItem(Recipe $recipe, Ingredient $ingredient, mixed $qty): RecipeItem
    {
        return DB::transaction(function () use ($recipe, $ingredient, $qty) {
            Recipe::whereKey($recipe->id)->lockForUpdate()->first();

            if ($this->containsIngredient($recipe, (int) $ingredient->id)) {
                throw ValidationException::withMessages(['ingredient_id' => self::DUPLICATE_ITEM_MESSAGE]);
            }

            if ($problem = $this->ingredientProblem($ingredient, $this->requiredOutletIds($recipe->variant()->firstOrFail()))) {
                throw ValidationException::withMessages(['ingredient_id' => $problem]);
            }

            return $recipe->items()->create([
                'ingredient_id' => $ingredient->id,
                'qty' => $qty,
                'unit' => $ingredient->unit,
            ]);
        });
    }

    /** Only qty changes; the stored unit is kept as it is. */
    public function updateItemQty(RecipeItem $item, mixed $qty): void
    {
        $item->update(['qty' => $qty]);
    }

    public function removeItem(RecipeItem $item): void
    {
        $item->delete();
    }

    // ---- Product Workspace ----------------------------------------------------------------------------

    /**
     * New Recipe for a Variant WITHOUT any Recipe, created INACTIVE (activating is a separate, explicit
     * action). Optional first items follow the same rules as addItem().
     */
    public function createForVariant(Product $product, ProductVariant $variant, array $input): Recipe
    {
        $data = $this->validateWorkspaceInput($input, null);

        return DB::transaction(function () use ($product, $variant, $data) {
            $variant = $this->lockWorkspaceVariant($product, $variant);

            if (Recipe::where('product_variant_id', $variant->id)->exists()) {
                throw ValidationException::withMessages(['recipe' => self::ALREADY_EXISTS_MESSAGE]);
            }

            $recipe = Recipe::create([
                'product_id' => $variant->product_id,
                'product_variant_id' => $variant->id,
                'name' => $data['name'],
                'is_active' => false,
            ]);

            $this->insertNewItems($recipe, $variant, $data['new_items'], []);

            return $recipe;
        });
    }

    /**
     * Saves the Recipe drawer: name, edited quantities, removed rows and new rows. A row the user did not
     * touch is never written, so its stored qty/unit stay exactly as they are. Ambiguous Variants are
     * refused. Returns what was changed.
     *
     * Input: name, items[<itemId>][qty|original_qty|remove], new_items[n][ingredient_id|qty]
     *
     * @return array{name: bool, updated: int, removed: int, added: int}
     */
    public function saveFromWorkspace(Product $product, ProductVariant $variant, Recipe $recipe, array $input): array
    {
        $data = $this->validateWorkspaceInput($input, $recipe);

        return DB::transaction(function () use ($product, $variant, $recipe, $data) {
            $variant = $this->lockWorkspaceVariant($product, $variant, $recipe);
            $recipe = Recipe::whereKey($recipe->id)->lockForUpdate()->firstOrFail();
            $stored = $recipe->items()->get()->keyBy('id');
            $summary = ['name' => false, 'updated' => 0, 'removed' => 0, 'added' => 0];
            $errors = [];

            foreach ($data['items'] as $itemId => $row) {
                $item = $stored->get($itemId);

                if (! $item) {
                    $errors['items.'.$itemId.'.qty'] = 'Bahan Recipe ini sudah tidak ada. Tutup lalu buka lagi Recipe ini.';

                    continue;
                }

                if ($row['remove']) {
                    continue;
                }

                if ($row['changed'] && ! self::sameQty($item->getRawOriginal('qty'), $row['original_qty'])) {
                    $errors['items.'.$itemId.'.qty'] = self::STALE_QTY_MESSAGE;
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            $removedIngredientIds = [];

            foreach ($data['items'] as $itemId => $row) {
                $item = $stored->get($itemId);

                if ($row['remove']) {
                    $removedIngredientIds[] = (int) $item->ingredient_id;
                    $item->delete();
                    $summary['removed']++;
                } elseif ($row['changed']) {
                    $item->update(['qty' => $row['qty']]);
                    $summary['updated']++;
                }
            }

            // An Ingredient still present in a kept row (e.g. a historical duplicate) stays "in the Recipe".
            $keptIngredientIds = $stored->reject(fn (RecipeItem $item) => ($data['items'][$item->id]['remove'] ?? false))
                ->pluck('ingredient_id')->map(fn ($id) => (int) $id)->all();

            $summary['added'] = $this->insertNewItems($recipe, $variant, $data['new_items'], $keptIngredientIds);

            if ($data['name'] !== $recipe->name) {
                $recipe->update(['name' => $data['name']]);
                $summary['name'] = true;
            }

            return $summary;
        });
    }

    /** Explicit activation. Refused (nothing changes) unless every activation rule holds. */
    public function activate(Product $product, ProductVariant $variant, Recipe $recipe): void
    {
        DB::transaction(function () use ($product, $variant, $recipe) {
            $variant = $this->lockWorkspaceVariant($product, $variant, $recipe);
            $recipe = Recipe::whereKey($recipe->id)->lockForUpdate()->firstOrFail();

            if ($recipe->is_active) {
                throw ValidationException::withMessages(['recipe' => 'Recipe ini sudah aktif.']);
            }

            if ($problems = $this->activationProblems($recipe, $variant)) {
                throw ValidationException::withMessages(['recipe' => 'Recipe tidak dapat diaktifkan: '.implode(' ', $problems)]);
            }

            $recipe->update(['is_active' => true]);
        });
    }

    /** Explicit deactivation from the workspace (not offered for an ambiguous Variant). */
    public function deactivateInWorkspace(Product $product, ProductVariant $variant, Recipe $recipe): void
    {
        DB::transaction(function () use ($product, $variant, $recipe) {
            $this->lockWorkspaceVariant($product, $variant, $recipe);
            $recipe = Recipe::whereKey($recipe->id)->lockForUpdate()->firstOrFail();

            if (! $recipe->is_active) {
                throw ValidationException::withMessages(['recipe' => 'Recipe ini sudah nonaktif.']);
            }

            $this->deactivate($recipe);
        });
    }

    /** Recipe belongs to the Variant (and its Product), Variant belongs to the Product. */
    public static function owns(Product $product, ProductVariant $variant, ?Recipe $recipe = null): bool
    {
        if ((int) $variant->product_id !== (int) $product->id) {
            return false;
        }

        return $recipe === null
            || ((int) $recipe->product_variant_id === (int) $variant->id && (int) $recipe->product_id === (int) $product->id);
    }

    /** More than one active Recipe for this Variant (never resolved automatically). */
    public function isAmbiguous(ProductVariant $variant): bool
    {
        return Recipe::where('product_variant_id', $variant->id)->where('is_active', true)->count() > 1;
    }

    /** Same stored decimal: "46084", "46084.0" and "46084.00" are one value; anything else differs. */
    public static function sameQty(mixed $a, mixed $b): bool
    {
        $a = is_string($a) ? trim($a) : $a;
        $b = is_string($b) ? trim($b) : $b;

        if (is_numeric($a) && is_numeric($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return (string) $a === (string) $b;
    }

    // ---- Internals ------------------------------------------------------------------------------------

    private function lockVariant(ProductVariant $variant): ProductVariant
    {
        return ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail();
    }

    private function otherActiveExists(ProductVariant $variant, Recipe $recipe): bool
    {
        return Recipe::where('product_variant_id', $variant->id)
            ->whereKeyNot($recipe->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Locks the Variant, then re-checks (with the current rows) that it still belongs to the Product, the
     * Recipe still belongs to it, and the Variant is not ambiguous. Every workspace write starts here.
     */
    private function lockWorkspaceVariant(Product $product, ProductVariant $variant, ?Recipe $recipe = null): ProductVariant
    {
        $variant = $this->lockVariant($variant);
        $current = $recipe ? Recipe::whereKey($recipe->id)->first() : null;

        if (($recipe && ! $current) || ! self::owns($product, $variant, $current)) {
            abort(404);
        }

        if ($this->isAmbiguous($variant)) {
            throw ValidationException::withMessages(['recipe' => self::AMBIGUOUS_MESSAGE.' Recipe ini tidak dapat diubah dari Product Workspace.']);
        }

        return $variant;
    }

    /**
     * Shape + field rules of the drawer input. Existing rows are only validated when the user changed
     * them; empty new rows (no Ingredient, no qty) are ignored.
     *
     * @return array{name: string, items: array<int, array{qty: mixed, original_qty: mixed, remove: bool, changed: bool}>, new_items: array<int, array{ingredient_id: int, qty: mixed}>}
     */
    private function validateWorkspaceInput(array $input, ?Recipe $recipe): array
    {
        $rules = ['name' => self::nameRule()];
        $items = [];
        $newItems = [];

        foreach ((array) ($input['items'] ?? []) as $itemId => $row) {
            if (! is_array($row) || ! ctype_digit((string) $itemId) || $recipe === null) {
                throw ValidationException::withMessages(['recipe' => 'Data bahan Recipe tidak valid.']);
            }

            $remove = filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $qty = $row['qty'] ?? null;
            $original = $row['original_qty'] ?? null;
            $changed = ! $remove && ! self::sameQty($qty, $original);

            if ($changed) {
                $rules['items.'.$itemId.'.qty'] = self::qtyRule();
            }

            $items[(int) $itemId] = ['qty' => $qty, 'original_qty' => $original, 'remove' => $remove, 'changed' => $changed];
        }

        // Row keys are kept as sent, so a field error lands on the row the user sees.
        foreach ((array) ($input['new_items'] ?? []) as $index => $row) {
            if (! ctype_digit((string) $index)) {
                throw ValidationException::withMessages(['recipe' => 'Data bahan Recipe tidak valid.']);
            }

            $index = (int) $index;
            $row = is_array($row) ? $row : [];
            $ingredientId = trim((string) ($row['ingredient_id'] ?? ''));
            $qty = trim((string) ($row['qty'] ?? ''));

            if ($ingredientId === '' && $qty === '') {
                continue;
            }

            $rules['new_items.'.$index.'.ingredient_id'] = 'required|integer|exists:ingredients,id,deleted_at,NULL';
            $rules['new_items.'.$index.'.qty'] = self::qtyRule();
            $newItems[$index] = ['ingredient_id' => $ingredientId, 'qty' => $qty];
        }

        $payload = [
            'name' => $input['name'] ?? null,
            'items' => collect($items)->map(fn ($row) => ['qty' => $row['qty']])->all(),
            'new_items' => $newItems,
        ];

        Validator::make($payload, $rules, [
            'name.required' => 'Nama Recipe wajib diisi.',
            '*.*.qty.required' => 'Qty wajib diisi.',
            '*.*.qty.numeric' => 'Qty harus berupa angka (gunakan titik untuk desimal).',
            '*.*.qty.min' => 'Qty harus lebih dari 0.',
            '*.*.ingredient_id.required' => 'Pilih Ingredient.',
            '*.*.ingredient_id.exists' => 'Ingredient tidak ditemukan.',
        ])->validate();

        return [
            'name' => trim((string) $input['name']),
            'items' => $items,
            'new_items' => collect($newItems)->map(fn ($row) => ['ingredient_id' => (int) $row['ingredient_id'], 'qty' => $row['qty']])->all(),
        ];
    }

    /** @return int number of rows inserted */
    private function insertNewItems(Recipe $recipe, ProductVariant $variant, array $newItems, array $presentIngredientIds): int
    {
        if ($newItems === []) {
            return 0;
        }

        $required = $this->requiredOutletIds($variant);
        $ingredients = Ingredient::with('outlets:id')->whereIn('id', collect($newItems)->pluck('ingredient_id')->all())->get()->keyBy('id');
        $seen = array_fill_keys($presentIngredientIds, true);
        $errors = [];

        foreach ($newItems as $index => $row) {
            $ingredient = $ingredients->get($row['ingredient_id']);
            $key = 'new_items.'.$index.'.ingredient_id';

            if (isset($seen[$row['ingredient_id']])) {
                $errors[$key] = self::DUPLICATE_ITEM_MESSAGE;
            } elseif ($problem = $this->ingredientProblem($ingredient, $required)) {
                $errors[$key] = $problem;
            }

            $seen[$row['ingredient_id']] = true;
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        foreach ($newItems as $row) {
            $recipe->items()->create([
                'ingredient_id' => $row['ingredient_id'],
                'qty' => $row['qty'],
                'unit' => $ingredients->get($row['ingredient_id'])->unit,
            ]);
        }

        return count($newItems);
    }
}
