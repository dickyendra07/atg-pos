<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The one place that writes ProductVariants. Used by the classic Variant group editor
 * (ProductVariantViewController) and by the Product Workspace (one Variant at a time), so both follow
 * the same rules. Moved out of ProductVariantViewController; behaviour kept except where noted:
 *
 *  - every Variant needs at least one outlet; outlets must be accessible to the user and a subset of
 *    the Product's outlets;
 *  - codes are upper-cased and unique per Product. The Variant Code is internal: the Back Office forms no
 *    longer show it. A new Variant gets one generated from its Product code + name, an existing Variant
 *    keeps the code it has, and an explicitly submitted code (CSV import, API, tests) is still honoured;
 *  - the legacy `price` column follows the dine-in price;
 *  - the legacy `outlet_id` column is NOT part of availability and is never written from input: availability
 *    is only the product_variant_outlet pivot (inside the Product's outlets). A new Variant leaves the column
 *    empty, an existing one keeps whatever it stores, in the classic editor and the Workspace alike, and a
 *    submitted `outlet_id` is ignored (it can neither widen nor bypass the outlet checks);
 *  - SHARED VARIANTS: name, code, prices, status and Product of a Variant that is also assigned to an outlet the
 *    user cannot access may only be changed by Owner / Admin Pusat (SharedCatalogPolicy). A limited user may still
 *    manage Variants whose outlets are all within their access, and still change outlet assignment inside it. The
 *    check is here, on every write path and before anything is written; an unchanged value never counts;
 *  - FIX: saving a Variant KEEPS its outlets that the user cannot access (a limited user only changes
 *    the outlets they can see; outlets outside their access stay assigned, as long as the Product is
 *    still available there). Previously the outlet list was replaced, silently dropping them.
 *
 * Removing a Variant is only "deactivate" here. The group editor does NOT delete anything: leaving an
 * existing Variant out of the submitted rows is refused (REMOVAL_MESSAGE), because a form edit must never
 * remove a Variant by accident. Removal is the explicit, flag-gated cleanup delete ("Hapus dari Sistem"),
 * which always tombstones (CleanupDeletionService); this class never deletes a Variant.
 */
class VariantWriter
{
    public const REMOVAL_MESSAGE = 'Variant yang sudah tersimpan tidak bisa dihapus lewat editor ini. Biarkan Variant tetap ada di daftar, atau gunakan aksi "Hapus dari Sistem" di Product Workspace (Variants & Pricing) untuk menghapusnya.';

    public function __construct(
        private readonly BackofficeOutletContext $context,
        private readonly SharedCatalogPolicy $shared,
    ) {}

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
            // Optional: the forms do not send it (see resolveCodes()); a submitted one is still validated.
            $prefix.'code' => 'nullable|string|max:50',
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
        [$row] = $this->resolveCodes($product, [$row]);

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
        [$row] = $this->resolveCodes($product, [['id' => $variant->id] + $row], collect([$variant->id => $variant]));

        $this->shared->assertVariantChange($user, $variant, $this->globalFields($product, $row));
        $this->assertOutletScope($user, $product, [$row], fn () => 'outlet_ids');
        $this->assertCodesFree($product, [$row], [$variant->id], 'code', 'Kode variant sudah dipakai pada product ini: ');

        $outletIds = $this->withPreserved($row['outlet_ids'], $this->preservedOutletIds($user, $product, $variant));

        DB::transaction(function () use ($product, $variant, $row, $outletIds) {
            $variant->update($this->attributes($product, $row));
            $variant->outlets()->sync($outletIds);
        });
    }

    /** "Nonaktifkan": history, outlets and Recipe stay. A status change is a global change, so a shared Variant needs Owner / Admin Pusat. */
    public function deactivate(User $user, ProductVariant $variant): void
    {
        $this->shared->assertVariantChange($user, $variant, ['is_active' => false]);

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

        $rows = $this->resolveCodes($product, $rows);

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
     * $product. Rows may be edited, moved or added, but an existing Variant may never be left out: nothing is
     * deleted here (see the class header).
     */
    public function updateGroup(User $user, ProductVariant $anchor, Product $product, array $rows): void
    {
        $existingGroup = ProductVariant::where('product_id', $anchor->product_id)->get()->keyBy('id');

        $this->assertOutletScope($user, $product, $rows);

        if (count($rows) === 0) {
            throw ValidationException::withMessages(['variants' => 'Minimal harus ada 1 variant yang valid.']);
        }

        $submittedIds = collect($rows)->pluck('id')->filter()->values();

        $rows = $this->resolveCodes($product, $rows, $existingGroup);

        // Every row is checked before any is written: one refused row leaves the whole group untouched.
        foreach ($rows as $index => $row) {
            if (! empty($row['id']) && $existingGroup->has($row['id'])) {
                $this->shared->assertVariantChange($user, $existingGroup[$row['id']], $this->globalFields($product, $row), fn (string $field) => 'variants.'.$index.'.'.$field);
            }
        }

        $this->assertNoDuplicateCodesInPayload($rows);
        $this->assertCodesFree($product, $rows, $submittedIds->all(), 'variants', 'Kode variant sudah dipakai pada product tujuan: ');

        $omitted = $existingGroup->keys()->diff($submittedIds);

        if ($omitted->isNotEmpty()) {
            $names = $omitted->map(fn ($id) => '"'.$existingGroup->get($id)?->name.'"')->implode(', ');

            throw ValidationException::withMessages([
                'variants' => 'Variant '.$names.' tidak ada di form. '.self::REMOVAL_MESSAGE,
            ]);
        }

        // Computed before writing: the outlets each kept Variant has outside the user's access.
        $preserved = collect($rows)
            ->filter(fn ($row) => ! empty($row['id']) && $existingGroup->has($row['id']))
            ->mapWithKeys(fn ($row) => [$row['id'] => $this->preservedOutletIds($user, $product, $existingGroup[$row['id']])]);

        DB::transaction(function () use ($product, $rows, $existingGroup, $preserved) {
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

    /**
     * Fills in the Variant Code of every row that did not submit one:
     *
     *  - an existing Variant (row id found in $existing) keeps the code it already has, untouched;
     *  - a new Variant gets a generated code: "<PRODUCT CODE>-<VARIANT NAME>" upper-cased, A-Z / 0-9 / "-"
     *    only, at most 50 characters, with "-2", "-3" ... appended when the Product already uses it.
     *
     * Codes already stored for the Product and codes in the same payload are reserved first, so generating
     * never collides with them. A code a caller submitted explicitly is left exactly as given.
     *
     * @param  Collection<int, ProductVariant>|null  $existing  Variants a row id may refer to, keyed by id
     */
    private function resolveCodes(Product $product, array $rows, ?Collection $existing = null): array
    {
        $existing ??= collect();

        $reserved = ProductVariant::query()
            ->where('product_id', $product->id)
            ->pluck('code')
            ->merge(collect($rows)->pluck('code'))
            ->merge($existing->pluck('code'))
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->flip()
            ->all();

        foreach ($rows as $index => $row) {
            if (($row['code'] ?? '') !== '') {
                continue;
            }

            $current = ! empty($row['id']) ? $existing->get($row['id']) : null;

            // Exactly as stored (no trimming, no case change): an existing code is never rewritten.
            if ($current && (string) $current->code !== '') {
                $rows[$index]['code'] = (string) $current->code;

                continue;
            }

            $code = self::generateCode($product, (string) ($row['name'] ?? ''), $reserved);
            $reserved[$code] = true;
            $rows[$index]['code'] = $code;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $reserved  upper-cased codes that may not be used (keys)
     */
    public static function generateCode(Product $product, string $name, array $reserved = []): string
    {
        $slug = fn (string $text): string => trim((string) preg_replace('/[^A-Z0-9]+/', '-', strtoupper(Str::ascii($text))), '-');

        $prefix = $slug((string) ($product->code ?: $product->name));
        $base = trim($prefix.'-'.($slug($name) ?: 'V'), '-');
        $base = rtrim(substr($base, 0, 50), '-') ?: 'V';

        $code = $base;

        for ($suffix = 2; isset($reserved[$code]); $suffix++) {
            $tail = '-'.$suffix;
            $code = rtrim(substr($base, 0, 50 - strlen($tail)), '-').$tail;
        }

        return $code;
    }

    /** The Variant's own (global) attributes of one normalized row, as the policy compares them. */
    private function globalFields(Product $product, array $row): array
    {
        return [
            'product_id' => $product->id,
            'name' => $row['name'],
            'code' => $row['code'],
            'price_dine_in' => $row['price_dine_in'],
            'price_delivery' => $row['price_delivery'],
            'is_active' => $row['is_active'],
        ];
    }

    private function singleRow(array $input): array
    {
        $row = $this->normalizeRows([$input])[0] ?? null;

        if ($row === null) {
            throw ValidationException::withMessages(['name' => 'Nama variant wajib diisi.']);
        }

        return $row;
    }

    private function attributes(Product $product, array $row): array
    {
        return [
            'product_id' => $product->id,
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
            ->map(fn ($row) => strtoupper(trim((string) $row['code'])))
            ->duplicates()
            ->unique()
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
