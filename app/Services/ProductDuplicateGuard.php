<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Support\BackofficeReturnUrl;
use Illuminate\Support\Collection;

/**
 * Finds Products that look like the one about to be created: the same normalized name in the same Brand
 * and Category. It only ever WARNS. Two Products may legitimately share a name (they have different codes),
 * so nothing here blocks, merges, renames or deletes anything - the caller decides whether to go on.
 *
 * The safe way to make an existing Product available at another outlet is to assign it there (Product
 * Workspace > Outlets, or Import CSV with the existing Product code), not to create a second Product.
 */
class ProductDuplicateGuard
{
    /** "  Thai   Tea! " and "thai tea" are the same name. */
    public static function normalize(?string $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';

        return trim($text);
    }

    /**
     * Live Products with the same normalized name, Brand and Category. A name that normalizes to nothing
     * never matches.
     *
     * @return Collection<int, Product> with brand, category and outlets loaded
     */
    public function similar(string $name, int $brandId, int $categoryId, ?int $ignoreProductId = null): Collection
    {
        $normalized = self::normalize($name);

        if ($normalized === '') {
            return collect();
        }

        return Product::with(['brand', 'category', 'outlets'])
            ->where('brand_id', $brandId)
            ->where('product_category_id', $categoryId)
            ->when($ignoreProductId !== null, fn ($query) => $query->whereKeyNot($ignoreProductId))
            ->get()
            ->filter(fn (Product $product) => self::normalize($product->name) === $normalized)
            ->values();
    }

    /**
     * What a warning shows about one existing Product: identity, code, status, outlets, and a Workspace link
     * only when the user may open it.
     *
     * @return array{id: int, name: string, code: string, is_active: bool, outlets: string[], url: ?string}
     */
    public function describe(Product $product, User $user): array
    {
        $product->loadMissing('outlets');

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'is_active' => (bool) $product->is_active,
            'outlets' => $product->outlets->pluck('name')->sort()->values()->all(),
            'url' => app(ProductAccessPolicy::class)->canAccess($user, $product)
                ? ProductWorkspace::url($product, 'outlets', BackofficeReturnUrl::fromRequest(request()))
                : null,
        ];
    }

    /** One line for CSV import reports. */
    public function line(Product $product): string
    {
        $product->loadMissing('outlets');

        return '"'.$product->name.'" (kode '.$product->code.', outlet: '.($product->outlets->pluck('name')->sort()->implode(', ') ?: '-').')';
    }
}
