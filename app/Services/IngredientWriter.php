<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one place that creates and updates Ingredients (global rows; Recipes reference them, no Product
 * owns one). Used by the Ingredient pages (IngredientViewController) and by the Product Workspace,
 * moved out of the controller without behaviour changes:
 *
 *  - category must be active (or the Ingredient's current one), unit is a canonical unit, name unique;
 *  - code is generated from the name (and regenerated when the name changes);
 *  - outlet ids must be accessible, and outlets the user cannot access are KEPT on update.
 *
 * Deleting is not here on purpose: the existing hard delete cascades Recipe items and stays limited to
 * the Ingredient page. The workspace only deactivates (is_active).
 */
class IngredientWriter
{
    /** Roles of the Ingredient pages (primary role), as IngredientViewController checks. */
    public const ROLES = ['owner', 'admin_pusat', 'admin_outlet', 'staff_gudang'];

    public function __construct(private readonly BackofficeOutletContext $context) {}

    public static function hasIngredientRole(User $user): bool
    {
        return in_array($user->role?->code, self::ROLES, true);
    }

    /**
     * Whether $user may open/edit this Ingredient from a workspace: full-access roles always; limited
     * roles only when the Ingredient is assigned to one of their outlets.
     */
    public function canEdit(User $user, Ingredient $ingredient): bool
    {
        if ($user->isFullAccessUser()) {
            return true;
        }

        $accessible = $this->accessibleOutletIds($user)->all();

        if ($accessible === []) {
            return false;
        }

        // Same answer without a query when the outlets are already loaded (Product Workspace lists).
        if ($ingredient->relationLoaded('outlets')) {
            return $ingredient->outlets->pluck('id')->map(fn ($id) => (int) $id)->intersect($accessible)->isNotEmpty();
        }

        return $ingredient->outlets()->whereIn('outlets.id', $accessible)->exists();
    }

    public function rules(?Ingredient $ingredient = null): array
    {
        $categoryRule = $ingredient === null
            ? Rule::exists('ingredient_categories', 'id')->where('is_active', true)
            : Rule::exists('ingredient_categories', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $ingredient->ingredient_category_id));

        return [
            'ingredient_category_id' => ['required', $categoryRule],
            'name' => 'required|string|max:255|unique:ingredients,name'.($ingredient ? ','.$ingredient->id : ''),
            'unit' => ['required', 'string', Rule::in($ingredient ? Ingredient::unitOptions($ingredient->unit) : Ingredient::UNITS)],
            'ingredient_type' => 'required|in:'.implode(',', array_keys(Ingredient::ingredientTypeOptions())),
            'minimum_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:outlets,id',
        ];
    }

    public function makeCode(string $name, ?int $ignoreId = null): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'INGREDIENT';
        }

        $code = $base;
        $counter = 1;

        while (
            Ingredient::query()
                ->where('code', $code)
                ->when($ignoreId, function ($query) use ($ignoreId) {
                    $query->where('id', '!=', $ignoreId);
                })
                ->exists()
        ) {
            $code = $base.'_'.$counter;
            $counter++;
        }

        return $code;
    }

    public function assertAccessibleOutletIds(User $user, array $outletIds): void
    {
        if (collect($outletIds)->map(fn ($id) => (int) $id)->diff($this->accessibleOutletIds($user))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'outlet_ids' => 'Ada outlet tidak aktif atau tidak tersedia untuk akun ini.',
            ]);
        }
    }

    public function create(User $user, array $validated): Ingredient
    {
        $this->assertAccessibleOutletIds($user, $validated['outlet_ids']);

        return DB::transaction(function () use ($validated) {
            $ingredient = Ingredient::create([
                'ingredient_category_id' => $validated['ingredient_category_id'],
                'code' => $this->makeCode($validated['name']),
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'ingredient_type' => $validated['ingredient_type'],
                'minimum_stock' => $validated['minimum_stock'],
                'cost_per_unit' => $validated['cost_per_unit'],
                'is_active' => $validated['is_active'],
            ]);

            $ingredient->outlets()->sync($validated['outlet_ids'] ?? []);

            return $ingredient;
        });
    }

    public function update(User $user, Ingredient $ingredient, array $validated): void
    {
        $this->assertAccessibleOutletIds($user, $validated['outlet_ids']);

        $editableOutletIds = $this->accessibleOutletIds($user);

        $newCode = $ingredient->code;

        if ($ingredient->name !== $validated['name']) {
            $newCode = $this->makeCode($validated['name'], $ingredient->id);
        }

        DB::transaction(function () use ($ingredient, $validated, $newCode, $editableOutletIds) {
            $ingredient->update([
                'ingredient_category_id' => $validated['ingredient_category_id'],
                'code' => $newCode,
                'name' => $validated['name'],
                'unit' => $validated['unit'],
                'ingredient_type' => $validated['ingredient_type'],
                'minimum_stock' => $validated['minimum_stock'],
                'cost_per_unit' => $validated['cost_per_unit'],
                'is_active' => $validated['is_active'],
            ]);

            $ingredient->outlets()->sync($this->finalOutletIds($ingredient, $validated['outlet_ids'], $editableOutletIds));
        });
    }

    /** Submitted (accessible) outlets plus the Ingredient's current outlets outside the user's access. */
    public function finalOutletIds(Ingredient $ingredient, array $submitted, Collection $editableOutletIds): array
    {
        return collect($submitted)
            ->map(fn ($id) => (int) $id)
            ->merge($ingredient->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->diff($editableOutletIds))
            ->unique()->values()->all();
    }

    public function accessibleOutletIds(User $user): Collection
    {
        return $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
    }
}
