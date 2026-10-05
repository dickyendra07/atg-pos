<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one place that writes ProductVariants. Used by the classic Variant group editor
 * (ProductVariantViewController) and by the Product Workspace (one Variant at a time), so both follow
 * the same rules. Moved out of ProductVariantViewController; behaviour kept except where noted:
 *
 *  - every Variant needs at least one outlet; outlets must be accessible to the user and a subset of
 *    the Product's outlets;
 *  - codes are upper-cased and unique per Product;
 *  - the legacy `price` column follows the dine-in price;
 *  - FIX: saving a Variant KEEPS its outlets that the user cannot access (a limited user only changes
 *    the outlets they can see; outlets outside their access stay assigned, as long as the Product is
 *    still available there). Previously the outlet list was replaced, silently dropping them.
 *
 * Removing a Variant is only "deactivate" here. The group editor's existing removal of unused rows
 * stays in updateGroup() unchanged and is not offered anywhere else.
 */
class VariantWriter
{
    public function __construct(private readonly BackofficeOutletContext $context) {}

    /**
     * Server-side twin of cleanCurrencyNumber() in the Variant editor: "Rp. 12.000" -> "12000",
     * a stored decimal like "12000.00" -> "12000" (not 1200000).
     */
    public static function normalizeRupiah(mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));
        $raw = preg_replace('/[.,]00$/', '', $raw);

        return preg_replace('/[^\d]/', '', $raw);
    }

    /** Validation rules for one Variant row; $prefix is 'variants.*.' for the group editor, '' for one. */
    public function rowRules(string $prefix = ''): array
    {
        return [
            $prefix.'name' => 'required|string|max:255',
            $prefix.'code' => 'required|string|max:50',
            $prefix.'outlet_ids' => 'required|array|min:1',
            $prefix.'outlet_ids.*' => 'nullable|exists:outlets,id',
            $prefix.'price_dine_in' => 'required|numeric|min:0',
            $prefix.'price_delivery' => 'required|numeric|min:0',
            $prefix.'is_active' => 'nullable|boolean',
        ];
    }

    public function normalizeRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = strtoupper(trim((string) ($row['code'] ?? '')));

            $outletIds = collect($row['outlet_ids'] ?? [])
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $isCompletelyEmpty =
                $name === '' &&
                $code === '' &&
                trim((string) ($row['price_dine_in'] ?? '')) === '' &&
                trim((string) ($row['price_delivery'] ?? '')) === '';

            if ($isCompletelyEmpty) {
                continue;
            }

            $normalized[] = [
                'id' => ! empty($row['id']) ? (int) $row['id'] : null,
                'outlet_id' => ! empty($row['outlet_id']) ? (int) $row['outlet_id'] : null,
                'name' => $name,
                'code' => $code,
                'outlet_ids' => $outletIds,
                'price_dine_in' => (float) ($row['price_dine_in'] ?? 0),
                'price_delivery' => (float) ($row['price_delivery'] ?? 0),
                'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : true,
            ];
        }

        return $normalized;
    }

    /**
     * Requested outlets must exist in the row, be accessible to the user and be Product outlets.
     *
     * @param  callable(int): string|null  $errorKey  validation key per row index
     */
    public function assertOutletScope(User $user, Product $product, array $rows, ?callable $errorKey = null): void
    {
        $errorKey ??= fn (int $index) => 'variants.'.$index.'.outlet_ids';

        $parentOutletIds = $product->outlets()
            ->pluck('outlets.id')
            ->map(fn ($id) => (int) $id);

        $accessibleIds = $this->accessibleOutletIds($user);

        foreach ($rows as $index => $row) {
            $requestedOutletIds = collect($row['outlet_ids'] ?? [])
                ->when(! empty($row['outlet_id']), fn ($ids) => $ids->push((int) $row['outlet_id']))
                ->map(fn ($id) => (int) $id)
                ->unique();

            $invalidOutletIds = $requestedOutletIds->diff($parentOutletIds);

            if ($requestedOutletIds->isEmpty()) {
                throw ValidationException::withMessages([
                    $errorKey($index) => 'Minimal pilih 1 outlet untuk setiap Variant.',
                ]);
            }

            if ($requestedOutletIds->diff($accessibleIds)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $errorKey($index) => 'Ada outlet tidak aktif atau tidak tersedia untuk akun ini.',
                ]);
            }

            if ($invalidOutletIds->isNotEmpty()) {
                $invalidOutletNames = Outlet::whereIn('id', $invalidOutletIds->all())
                    ->orderBy('name')
                    ->pluck('name')
                    ->implode(', ');

                throw ValidationException::withMessages([
                    $errorKey($index) => 'Outlet variant harus merupakan subset outlet Product. Outlet tidak valid: '.$invalidOutletNames,
                ]);
            }
        }
    }

    /**
     * Outlets of $variant the user cannot access, which a save must keep - limited to outlets the
     * Product is still available in (a Variant never sits outside its Product's outlets).
     *
     * @return int[]
     */
    public function preservedOutletIds(User $user, Product $product, ProductVariant $variant): array
    {
        $productOutletIds = $product->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id);

        return $variant->outlets()->pluck('outlets.id')
            ->map(fn ($id) => (int) $id)
            ->diff($this->accessibleOutletIds($user))
            ->intersect($productOutletIds)
            ->values()
            ->all();
    }

    // ---- Product Workspace: one Variant ---------------------------------------------------------------

    public function create(User $user, Product $product, array $input): ProductVariant
    {
        $row = $this->singleRow($input);

        $this->assertOutletScope($user, $product, [$row], fn () => 'outlet_ids');
        $this->assertCodesFree($product, [$row], [], 'code', 'Kode variant sudah dipakai pada product ini: ');

        return DB::transaction(function () use ($product, $row) {
            $variant = ProductVariant::create($this->attributes($product, $row));
            $variant->outlets()->sync($row['outlet_ids']);

            return $variant;
        });
    }

    public function update(User $user, Product $product, ProductVariant $variant, array $input): void
    {
        $row = $this->singleRow($input);

        $this->assertOutletScope($user, $product, [$row], fn () => 'outlet_ids');
        $this->assertCodesFree($product, [$row], [$variant->id], 'code', 'Kode variant sudah dipakai pada product ini: ');

        $outletIds = $this->withPreserved($row['outlet_ids'], $this->preservedOutletIds($user, $product, $variant));

        DB::transaction(function () use ($product, $variant, $row, $outletIds) {
            $variant->update(['outlet_id' => $variant->outlet_id] + $this->attributes($product, $row));
            $variant->outlets()->sync($outletIds);
        });
    }

    /** "Nonaktifkan": history, outlets and Recipe stay. */
    public function deactivate(ProductVariant $variant): void
    {
        $variant->update(['is_active' => false]);
    }

    // ---- Classic group editor -------------------------------------------------------------------------

    /** @return int number of Variants created */
    public function createGroup(User $user, Product $product, array $rows): int
    {
        $this->assertOutletScope($user, $product, $rows);

        if (count($rows) === 0) {
            throw ValidationException::withMessages(['variants' => 'Minimal harus ada 1 variant yang valid.']);
        }

        $this->assertNoDuplicateCodesInPayload($rows);
        $this->assertCodesFree($product, $rows, [], 'variants', 'Kode variant sudah dipakai pada product ini: ');

        DB::transaction(function () use ($product, $rows) {
            foreach ($rows as $row) {
                $variant = ProductVariant::create($this->attributes($product, $row));
                $variant->outlets()->sync($row['outlet_ids']);
            }
        });

        return count($rows);
    }

    /**
     * The group editor: every Variant of $anchor's Product submitted at once, possibly moved to
     * $product. Rows left out are removed, but only when no Recipe or sale uses them (existing rule).
     */
    public function updateGroup(User $user, ProductVariant $anchor, Product $product, array $rows): void
    {
        $existingGroup = ProductVariant::where('product_id', $anchor->product_id)->get()->keyBy('id');

        $this->assertOutletScope($user, $product, $rows);

        if (count($rows) === 0) {
            throw ValidationException::withMessages(['variants' => 'Minimal harus ada 1 variant yang valid.']);
        }

        $submittedIds = collect($rows)->pluck('id')->filter()->values();

        $this->assertNoDuplicateCodesInPayload($rows);
        $this->assertCodesFree($product, $rows, $submittedIds->all(), 'variants', 'Kode variant sudah dipakai pada product tujuan: ');

        $removedIds = $existingGroup->keys()->diff($submittedIds);

        foreach ($removedIds as $removedId) {
            $removedVariant = $existingGroup->get($removedId);

            if (! $removedVariant) {
                continue;
            }

            $removedVariant->loadCount(['recipe', 'salesTransactionItems']);

            if ($removedVariant->recipe_count > 0 || $removedVariant->sales_transaction_items_count > 0) {
                throw ValidationException::withMessages([
                    'variants' => 'Variant "'.$removedVariant->name.'" tidak bisa dihapus karena masih dipakai di recipe / transaksi.',
                ]);
            }
        }

        // Computed before writing: the outlets each kept Variant has outside the user's access.
        $preserved = collect($rows)
            ->filter(fn ($row) => ! empty($row['id']) && $existingGroup->has($row['id']))
            ->mapWithKeys(fn ($row) => [$row['id'] => $this->preservedOutletIds($user, $product, $existingGroup[$row['id']])]);

        DB::transaction(function () use ($product, $rows, $existingGroup, $removedIds, $preserved) {
            foreach ($removedIds as $removedId) {
                $existingGroup->get($removedId)?->delete();
            }

            foreach ($rows as $row) {
                if (! empty($row['id']) && $existingGroup->has($row['id'])) {
                    $existingGroup[$row['id']]->update($this->attributes($product, $row));
                    $existingGroup[$row['id']]->outlets()->sync($this->withPreserved($row['outlet_ids'], $preserved[$row['id']] ?? []));
                } else {
                    $newVariant = ProductVariant::create($this->attributes($product, $row));
                    $newVariant->outlets()->sync($row['outlet_ids']);
                }
            }
        });
    }

    // ---- internals ------------------------------------------------------------------------------------

    private function singleRow(array $input): array
    {
        $row = $this->normalizeRows([$input])[0] ?? null;

        if ($row === null) {
            throw ValidationException::withMessages(['name' => 'Nama variant wajib diisi.']);
        }

        return ['outlet_id' => null] + $row;
    }

    private function attributes(Product $product, array $row): array
    {
        return [
            'product_id' => $product->id,
            'outlet_id' => $row['outlet_id'],
            'name' => $row['name'],
            'code' => $row['code'],
            'price' => $row['price_dine_in'],
            'price_dine_in' => $row['price_dine_in'],
            'price_delivery' => $row['price_delivery'],
            'is_active' => $row['is_active'],
        ];
    }

    private function withPreserved(array $submitted, array $preserved): array
    {
        return collect($submitted)->merge($preserved)->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    private function assertNoDuplicateCodesInPayload(array $rows): void
    {
        $duplicateCodes = collect($rows)
            ->map(fn ($row) => ($row['outlet_id'] ?: 'ALL').'|'.strtoupper(trim((string) $row['code'])))
            ->duplicates()
            ->unique()
            ->map(fn ($key) => explode('|', $key)[1] ?? $key)
            ->values();

        if ($duplicateCodes->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => 'Ada kode variant yang duplikat di form: '.$duplicateCodes->implode(', '),
            ]);
        }
    }

    /**
     * Codes are unique per Product (database index product_id + code). The check used to also match the
     * legacy outlet_id column, which let a clash through to a database error; it now follows the index.
     */
    private function assertCodesFree(Product $product, array $rows, array $ignoreIds, string $errorKey, string $message): void
    {
        $taken = collect($rows)
            ->filter(fn ($row) => ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereRaw('UPPER(code) = ?', [strtoupper($row['code'])])
                ->when($ignoreIds !== [], fn ($query) => $query->whereNotIn('id', $ignoreIds))
                ->exists())
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->unique()
            ->values();

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages([$errorKey => $message.$taken->implode(', ')]);
        }
    }

    private function accessibleOutletIds(User $user): Collection
    {
        return $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
    }
}
