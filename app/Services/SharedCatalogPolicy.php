<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Who may change the GLOBAL attributes of a Product or Variant.
 *
 * A Product / Variant is one global row. Its own attributes (Product: brand, category, name, code,
 * description, status; Variant: name, code, both prices, status, which Product it belongs to) apply at EVERY
 * outlet it is assigned to. So a limited user (Admin Outlet) may change them only when every outlet the item is
 * assigned to is an outlet that user can access, i.e. the item is "theirs". An item that is also assigned to an
 * outlet outside their access is shared with someone they cannot see: only Owner / Admin Pusat may change those
 * attributes. Same rule as RecipeAccessPolicy (a change must not reach an outlet the user cannot access).
 *
 * Outlet ASSIGNMENT is deliberately not covered here: assigning / unassigning outlets inside the user's own
 * access stays allowed (ProductWriter / VariantWriter already keep the outlets outside it).
 *
 * Only attributes that really CHANGE are refused, so a form that re-submits unchanged values (the classic group
 * editor sends every row) never fails. A refusal is a validation error (422 / redirect back with errors), never a
 * silent ignore, and it is raised before anything is written. An item with no outlet at all affects nobody and is
 * not "shared".
 */
class SharedCatalogPolicy
{
    public const PRODUCT_FIELDS = ['brand_id', 'product_category_id', 'name', 'code', 'description', 'is_active'];

    public const VARIANT_FIELDS = ['product_id', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active'];

    private const LABELS = [
        'brand_id' => 'brand',
        'product_category_id' => 'kategori',
        'name' => 'nama',
        'code' => 'kode',
        'description' => 'deskripsi',
        'is_active' => 'status aktif',
        'product_id' => 'Product induk',
        'price_dine_in' => 'harga dine in',
        'price_delivery' => 'harga delivery',
    ];

    public function __construct(private readonly BackofficeOutletContext $context) {}

    /** Outlets of the Product that $user cannot access (always [] for Owner / Admin Pusat). @return int[] */
    public function productOutletsOutsideAccess(User $user, Product $product): array
    {
        return $this->outsideAccess($user, $product->outlets()->pluck('outlets.id'));
    }

    /** Outlets of the Variant that $user cannot access (always [] for Owner / Admin Pusat). @return int[] */
    public function variantOutletsOutsideAccess(User $user, ProductVariant $variant): array
    {
        return $this->outsideAccess($user, $variant->outlets()->pluck('outlets.id'));
    }

    /** @param  array<string, mixed>  $attributes  Product attributes about to be written (only the keys present are compared) */
    public function changedProductFields(Product $product, array $attributes): array
    {
        $same = [
            'brand_id' => fn ($new) => (int) $new === (int) $product->brand_id,
            'product_category_id' => fn ($new) => (int) $new === (int) $product->product_category_id,
            'name' => fn ($new) => trim((string) $new) === trim((string) $product->name),
            'code' => fn ($new) => trim((string) $new) === trim((string) $product->code),
            'description' => fn ($new) => trim((string) $new) === trim((string) $product->description),
            'is_active' => fn ($new) => (bool) $new === (bool) $product->is_active,
        ];

        return $this->changed($same, $attributes);
    }

    /**
     * @param  array<string, mixed>  $row  Variant attributes about to be written (only the keys present are compared)
     * @return string[] changed field names
     */
    public function changedVariantFields(ProductVariant $variant, array $row): array
    {
        $same = [
            'product_id' => fn ($new) => (int) $new === (int) $variant->product_id,
            'name' => fn ($new) => trim((string) $new) === trim((string) $variant->name),
            'code' => fn ($new) => strtoupper(trim((string) $new)) === strtoupper(trim((string) $variant->code)),
            'price_dine_in' => fn ($new) => round((float) $new, 2) === round((float) ($variant->price_dine_in ?? $variant->price ?? 0), 2),
            'price_delivery' => fn ($new) => round((float) $new, 2) === round((float) ($variant->price_delivery ?? $variant->price ?? 0), 2),
            'is_active' => fn ($new) => (bool) $new === (bool) $variant->is_active,
        ];

        return $this->changed($same, $row);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  callable(string): string|null  $errorKey  validation key per field (default: the field name)
     *
     * @throws ValidationException
     */
    public function assertProductChange(User $user, Product $product, array $attributes, ?callable $errorKey = null): void
    {
        $changed = $this->changedProductFields($product, $attributes);

        if ($changed === []) {
            return;
        }

        $this->refuseWhenShared($this->productOutletsOutsideAccess($user, $product), 'Product', $changed, $errorKey);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  callable(string): string|null  $errorKey
     *
     * @throws ValidationException
     */
    public function assertVariantChange(User $user, ProductVariant $variant, array $row, ?callable $errorKey = null): void
    {
        $changed = $this->changedVariantFields($variant, $row);

        if ($changed === []) {
            return;
        }

        $this->refuseWhenShared($this->variantOutletsOutsideAccess($user, $variant), 'Variant', $changed, $errorKey);
    }

    // ---------------------------------------------------------------------------------------------

    /** @return int[] */
    private function outsideAccess(User $user, $outletIds): array
    {
        if ($user->isFullAccessUser()) {
            return [];
        }

        $accessible = $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);

        return $outletIds->map(fn ($id) => (int) $id)->diff($accessible)->values()->all();
    }

    private function changed(array $same, array $attributes): array
    {
        $changed = [];

        foreach ($same as $field => $isSame) {
            if (array_key_exists($field, $attributes) && ! $isSame($attributes[$field])) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /** @param  int[]  $outsideAccess */
    private function refuseWhenShared(array $outsideAccess, string $what, array $changed, ?callable $errorKey): void
    {
        if ($outsideAccess === []) {
            return;
        }

        $errorKey ??= fn (string $field) => $field;
        $outlets = Outlet::whereIn('id', $outsideAccess)->orderBy('name')->pluck('name')->implode(', ');
        $fields = collect($changed)->map(fn ($field) => self::LABELS[$field] ?? $field)->implode(', ');
        $message = $what.' ini juga dipakai di outlet di luar akses akun ini ('.$outlets.'). Perubahan '.$fields
            .' berlaku untuk semua outlet tersebut, jadi hanya Owner/Admin Pusat yang boleh mengubahnya. Perubahan outlet tetap bisa disimpan.';

        throw ValidationException::withMessages(
            collect($changed)->mapWithKeys(fn ($field) => [$errorKey($field) => $message])->all()
        );
    }
}
