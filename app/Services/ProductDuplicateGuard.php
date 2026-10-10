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
 *
 * A warning never exposes more than the user may see: a similar Product is described in full (id, name, code,
 * status, outlets, link) only when ProductAccessPolicy lets the user open it (Owner / Admin Pusat: always). For
 * a similar Product outside that access the user only learns that "N more look-alike Products exist outside
 * your access" and is pointed to Owner / Admin Pusat - no id, code, name, outlet list or link. Every warning
 * (Add Product, rename, CSV import) goes through split() / importLines() so they all follow the same rule.
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
     * Full identity of one existing Product for a warning, or null when $user may not open it (callers never
     * show anything else about such a Product).
     *
     * @return array{id: int, name: string, code: string, is_active: bool, outlets: string[], url: ?string}|null
     */
    public function describe(Product $product, User $user): ?array
    {
        if (! app(ProductAccessPolicy::class)->canAccess($user, $product)) {
            return null;
        }

        $product->loadMissing('outlets');

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'code' => $product->code,
            'is_active' => (bool) $product->is_active,
            'outlets' => $product->outlets->pluck('name')->sort()->values()->all(),
            'url' => ProductWorkspace::url($product, 'outlets', BackofficeReturnUrl::fromRequest(request())),
        ];
    }

    /**
     * Splits look-alikes into what $user may see in full and how many are hidden from them.
     *
     * @param  Collection<int, Product>  $similar
     * @return array{visible: array<int, array>, hidden: int}
     */
    public function split(Collection $similar, User $user): array
    {
        $visible = $similar->map(fn (Product $product) => $this->describe($product, $user))->filter()->values();

        return ['visible' => $visible->all(), 'hidden' => $similar->count() - $visible->count()];
    }

    /** Shown whenever look-alikes exist that the user may not see: no identity, only where to go. */
    public static function hiddenNotice(int $hidden): string
    {
        return 'Ada '.$hidden.' Product serupa lain di luar akses akun ini (detailnya tidak ditampilkan). Hubungi Owner/Admin Pusat untuk menyelesaikan duplikasi katalog.';
    }

    /** One human sentence for a JSON / toast confirmation (rename). */
    public function confirmationMessage(array $split): string
    {
        $parts = [];

        if ($split['visible'] !== []) {
            $parts[] = 'Product serupa sudah ada: '.collect($split['visible'])
                ->map(fn ($row) => $row['name'].' (kode '.$row['code'].', outlet: '.(implode(', ', $row['outlets']) ?: '-').')')
                ->implode('; ').'.';
        }

        if ($split['hidden'] > 0) {
            $parts[] = self::hiddenNotice($split['hidden']);
        }

        return implode(' ', $parts).' Sebaiknya tambahkan outlet ke Product yang sudah ada, bukan membuat Product kembar. Tetap simpan perubahan ini?';
    }

    /** What a CSV import row reports about look-alikes, with the same visibility rule. */
    public function importLines(Collection $similar, User $user): string
    {
        $split = $this->split($similar, $user);

        $lines = collect($split['visible'])
            ->map(fn ($row) => '"'.$row['name'].'" (kode '.$row['code'].', outlet: '.(implode(', ', $row['outlets']) ?: '-').')')
            ->all();

        if ($split['hidden'] > 0) {
            $lines[] = self::hiddenNotice($split['hidden']);
        }

        return implode('; ', $lines);
    }
}
