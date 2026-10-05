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
            'code' => 'required|string|max:255|unique:products,code'.($product ? ','.$product->id : ''),
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
        $plan = $this->planOutletChange($user, $product, $validated['outlet_ids']);

        DB::transaction(function () use ($product, $validated, $plan) {
            $product->update(collect($validated)->except('outlet_ids')->toArray());
            $this->applyOutletPlan($product, $plan);
        });
    }

    /** Workspace "General": the Product's own fields only. Outlets and Variants are not touched. */
    public function updateGeneral(Product $product, array $attributes): void
    {
        $product->update(collect($attributes)->only(array_keys($this->generalRules($product)))->toArray());
    }

    /** Workspace "Outlets": returns the plan that was applied. */
    public function updateOutlets(User $user, Product $product, array $outletIds): array
    {
        $this->assertAccessibleOutletIds($user, $outletIds);
        $plan = $this->planOutletChange($user, $product, $outletIds);

        DB::transaction(fn () => $this->applyOutletPlan($product, $plan));

        return $plan;
    }

    /**
     * What saving these (accessible) outlet ids would do, without writing anything.
     *
     * @return array{final_outlet_ids: int[], added: int[], removed: int[], locked: int[], variants: array, promos: array, outlet_names: array<int, string>, has_consequences: bool}
     */
    public function planOutletChange(User $user, Product $product, array $outletIds): array
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

        $promos = $this->affectedPromos($product, $variants, $removed);

        $outletIds = $current->merge($final)->merge($promos->flatMap(fn ($promo) => $promo['conflict_outlet_ids']))->unique()->all();

        return [
            'final_outlet_ids' => $final->all(),
            'added' => $final->diff($current)->values()->all(),
            'removed' => $removed->all(),
            'locked' => $locked->all(),
            'variants' => $variants->all(),
            'promos' => $promos->all(),
            'outlet_names' => Outlet::whereIn('id', $outletIds)->pluck('name', 'id')->all(),
            'has_consequences' => $variants->isNotEmpty() || $promos->isNotEmpty(),
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
