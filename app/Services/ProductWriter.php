<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The one place that writes a Product (create, general fields, outlet assignment). Used by the
 * classic Product routes (ProductViewController) and by the Product Workspace, so both follow exactly
 * the same rules. Moved out of ProductViewController without behaviour changes:
 *
 *  - outlet ids must be accessible to the user;
 *  - outlets the user cannot access are KEPT on the Product;
 *  - every Variant that has outlets is trimmed to the Product's outlets, and a Variant left with no
 *    outlet is deactivated (Variants without any outlet row are left alone, as before).
 *
 * planOutletChange() computes those effects without writing, so a preview always matches the save.
 *
 * Gaining an outlet never touches the Product's Variants on its own. The caller may opt in
 * ($assignVariants) to also assign the Product's ACTIVE Variants to the newly added outlets; inactive
 * Variants are never activated or assigned, nothing is created, and Variant outlets stay a subset of
 * the Product's outlets.
 */
class ProductWriter
{
    public function __construct(private readonly BackofficeOutletContext $context) {}

    /** Brand, Category, name, code, description, status. $product = null for create. */
    public function generalRules(?Product $product = null): array
    {
        $categoryRule = $product === null
            ? Rule::exists('product_categories', 'id')->where('is_active', true)
            : Rule::exists('product_categories', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $product->product_category_id));

        return [
            'brand_id' => 'required|exists:brands,id',
            'product_category_id' => ['required', $categoryRule],
            'name' => 'required|string|max:255',
            'code' => ['required', 'string', 'max:255', Rule::unique('products', 'code')->whereNull('deleted_at')->ignore($product?->id)],
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ];
    }

    public function outletRules(): array
    {
        return [
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:outlets,id',
        ];
    }

    /** Optional opt-in: also assign the Product's active Variants to the outlets being added. */
    public function assignVariantsRules(): array
    {
        return ['assign_variants' => 'nullable|boolean'];
    }

    public function assertAccessibleOutletIds(User $user, array $outletIds): void
    {
        $allowed = $this->editableOutletIds($user);
        $invalid = collect($outletIds)->map(fn ($id) => (int) $id)->diff($allowed);

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'outlet_ids' => 'Ada outlet tidak aktif atau tidak tersedia untuk akun ini.',
            ]);
        }
    }

    /** $validated: general fields + outlet_ids (already validated). */
    public function create(User $user, array $validated): Product
    {
        $this->assertAccessibleOutletIds($user, $validated['outlet_ids']);

        return DB::transaction(function () use ($validated) {
            $product = Product::create(collect($validated)->except('outlet_ids')->toArray());
            $product->outlets()->sync($validated['outlet_ids'] ?? []);

            return $product;
        });
    }

    /** Classic full update (general fields + outlets in one request), exactly as before. */
    public function update(User $user, Product $product, array $validated): void
    {
        $this->assertAccessibleOutletIds($user, $validated['outlet_ids']);
        $plan = $this->planOutletChange($user, $product, $validated['outlet_ids'], (bool) ($validated['assign_variants'] ?? false));

        DB::transaction(function () use ($product, $validated, $plan) {
            $product->update(collect($validated)->except(['outlet_ids', 'assign_variants'])->toArray());
            $this->applyOutletPlan($product, $plan);
        });
    }

    /** Workspace "General": the Product's own fields only. Outlets and Variants are not touched. */
    public function updateGeneral(Product $product, array $attributes): void
    {
        $product->update(collect($attributes)->only(array_keys($this->generalRules($product)))->toArray());
    }

    /** Workspace "Outlets": returns the plan that was applied. */
    public function updateOutlets(User $user, Product $product, array $outletIds, bool $assignVariants = false): array
    {
        $this->assertAccessibleOutletIds($user, $outletIds);
        $plan = $this->planOutletChange($user, $product, $outletIds, $assignVariants);

        DB::transaction(fn () => $this->applyOutletPlan($product, $plan));

        return $plan;
    }

    /**
     * What saving these (accessible) outlet ids would do, without writing anything.
     *
     * `assignable` lists the ACTIVE Variants that do not yet have a newly added outlet (what the opt-in would
     * assign); `skipped_inactive` counts the inactive ones, which are never touched. `assignments` is what the
     * save really assigns: `assignable` when $assignVariants is true, otherwise empty.
     *
     * @return array{final_outlet_ids: int[], added: int[], removed: int[], locked: int[], variants: array, assignable: array, assignments: array, assign_variants: bool, skipped_inactive: int, promos: array, outlet_names: array<int, string>, has_consequences: bool}
     */
    public function planOutletChange(User $user, Product $product, array $outletIds, bool $assignVariants = false): array
    {
        $editable = $this->editableOutletIds($user);
        $current = $product->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id);
        $locked = $current->diff($editable)->values();

        $final = collect($outletIds)
            ->map(fn ($id) => (int) $id)
            ->merge($locked)
            ->unique()
            ->values();

        $removed = $current->diff($final)->values();

        $variants = $product->variants()->with('outlets:id')->orderBy('name')->get()
            ->map(function (ProductVariant $variant) use ($final) {
                $variantOutletIds = $variant->outlets->pluck('id')->map(fn ($id) => (int) $id);

                if ($variantOutletIds->isEmpty()) {
                    return null;   // untouched by the current rule
                }

                $remaining = $variantOutletIds->intersect($final)->values();

                return [
                    'id' => (int) $variant->id,
                    'name' => $variant->name,
                    'was_active' => (bool) $variant->is_active,
                    'removed_outlet_ids' => $variantOutletIds->diff($final)->values()->all(),
                    'remaining_outlet_ids' => $remaining->all(),
                    'will_deactivate' => $remaining->isEmpty(),
                ];
            })
            ->filter(fn ($row) => $row !== null && $row['removed_outlet_ids'] !== [])
            ->values();

        $added = $final->diff($current)->values();
        [$assignable, $skippedInactive] = $this->assignableVariants($product, $added);

        $promos = $this->affectedPromos($product, $variants, $removed);

        $outletIds = $current->merge($final)->merge($promos->flatMap(fn ($promo) => $promo['conflict_outlet_ids']))->unique()->all();

        return [
            'final_outlet_ids' => $final->all(),
            'added' => $added->all(),
            'removed' => $removed->all(),
            'locked' => $locked->all(),
            'variants' => $variants->all(),
            'assignable' => $assignable->all(),
            'assignments' => $assignVariants ? $assignable->all() : [],
            'assign_variants' => $assignVariants,
            'skipped_inactive' => $skippedInactive,
            'promos' => $promos->all(),
            'outlet_names' => Outlet::whereIn('id', $outletIds)->pluck('name', 'id')->all(),
            'has_consequences' => $variants->isNotEmpty() || $promos->isNotEmpty() || ($assignVariants && $assignable->isNotEmpty()),
        ];
    }

    private function applyOutletPlan(Product $product, array $plan): void
    {
        $product->outlets()->sync($plan['final_outlet_ids']);

        foreach ($plan['variants'] as $row) {
            $variant = ProductVariant::find($row['id']);
            $variant->outlets()->sync($row['remaining_outlet_ids']);

            if ($row['will_deactivate']) {
                $variant->update(['is_active' => false]);
            }
        }

        // Opt-in only: ADD the new Product outlets to existing active Variants (never removes, never creates).
        foreach ($plan['assignments'] as $row) {
            ProductVariant::query()
                ->where('product_id', $product->id)
                ->where('is_active', true)
                ->whereKey($row['id'])
                ->first()
                ?->outlets()->syncWithoutDetaching($row['add_outlet_ids']);
        }
    }

    /**
     * Active Variants of the Product that lack some of the newly added outlets.
     *
     * @return array{0: Collection<int, array{id: int, name: string, add_outlet_ids: int[]}>, 1: int} [rows, inactive Variants left alone]
     */
    private function assignableVariants(Product $product, Collection $added): array
    {
        if ($added->isEmpty()) {
            return [collect(), 0];
        }

        $variants = $product->variants()->with('outlets:id')->orderBy('name')->get();

        $rows = $variants
            ->filter(fn (ProductVariant $variant) => $variant->is_active)
            ->map(fn (ProductVariant $variant) => [
                'id' => (int) $variant->id,
                'name' => $variant->name,
                'add_outlet_ids' => $added->diff($variant->outlets->pluck('id')->map(fn ($id) => (int) $id))->values()->all(),
            ])
            ->filter(fn (array $row) => $row['add_outlet_ids'] !== [])
            ->values();

        return [$rows, $variants->reject(fn (ProductVariant $variant) => $variant->is_active)->count()];
    }

    /**
     * Promos to warn about (never changed here): those using a Variant that loses an outlet, and those
     * using any Variant of this Product at an outlet the Product is being removed from.
     */
    private function affectedPromos(Product $product, Collection $affectedVariants, Collection $removedOutletIds): Collection
    {
        $variantIds = $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($variantIds === [] || ($affectedVariants->isEmpty() && $removedOutletIds->isEmpty())) {
            return collect();
        }

        $affectedIds = $affectedVariants->pluck('id')->all();
        $names = $product->variants()->pluck('name', 'id');

        return Promo::with(['outlets:id', 'requirements', 'rewards'])
            ->referencingVariants($variantIds)
            ->orderBy('name')
            ->get()
            ->map(function (Promo $promo) use ($variantIds, $affectedIds, $removedOutletIds, $names) {
                $used = $promo->requirements->pluck('product_variant_id')
                    ->merge($promo->rewards->pluck('product_variant_id'))
                    ->push($promo->requirement_product_variant_id, $promo->reward_product_variant_id)
                    ->filter()->map(fn ($id) => (int) $id)
                    ->intersect($variantIds)->unique()->values();

                $conflicts = $promo->outlets->pluck('id')->map(fn ($id) => (int) $id)->intersect($removedOutletIds)->values();

                if ($used->intersect($affectedIds)->isEmpty() && $conflicts->isEmpty()) {
                    return null;
                }

                return [
                    'id' => (int) $promo->id,
                    'name' => $promo->name,
                    'live' => $promo->isActiveStatus(),
                    'variant_names' => $used->map(fn ($id) => $names[$id] ?? '#'.$id)->all(),
                    'conflict_outlet_ids' => $conflicts->all(),
                ];
            })
            ->filter()
            ->values();
    }

    private function editableOutletIds(User $user): Collection
    {
        return $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
    }
}
