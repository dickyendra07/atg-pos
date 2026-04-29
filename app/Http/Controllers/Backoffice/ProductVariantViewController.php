<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductVariantViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
        ];

        if (! in_array($user->role?->code, $allowedRoles, true)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Variants.');
        }

        return $user;
    }

    protected function normalizeVariantRows(array $rows): array
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

    protected function validateDuplicateCodesInPayload(array $rows): void
    {
        $codes = collect($rows)
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->values();

        $duplicates = $codes
            ->duplicates()
            ->unique()
            ->values();

        if ($duplicates->isNotEmpty()) {
            abort(422, 'Ada kode variant yang duplikat di form: ' . $duplicates->implode(', '));
        }
    }

    public function index()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $variants = ProductVariant::with(['product.brand', 'product.category', 'outlet'])
            ->orderBy('product_id')
            ->orderBy('name')
            ->get();

        $groupedProducts = $variants
            ->groupBy('product_id')
            ->map(function ($items) {
                $first = $items->first();

                return [
                    'product' => $first?->product,
                    'variants' => $items->values(),
                    'first_variant_id' => $first?->id,
                    'active_count' => $items->where('is_active', true)->count(),
                ];
            })
            ->values();

        return view('backoffice.variants.index', [
            'user' => $user,
            'variants' => $variants,
            'groupedProducts' => $groupedProducts,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        $outlets = Outlet::orderBy('name')->get();

        return view('backoffice.variants.create', [
            'user' => $user,
            'products' => $products,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variants' => 'required|array|min:1',
            'variants.*.outlet_id' => 'nullable|exists:outlets,id',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.code' => 'required|string|max:50',
            'variants.*.outlet_ids' => 'nullable|array',
            'variants.*.outlet_ids.*' => 'nullable|exists:outlets,id',
            'variants.*.price_dine_in' => 'required|numeric|min:0',
            'variants.*.price_delivery' => 'required|numeric|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ], [
            'variants.required' => 'Minimal harus ada 1 variant.',
            'variants.*.name.required' => 'Nama variant wajib diisi di setiap baris.',
            'variants.*.code.required' => 'Kode variant wajib diisi di setiap baris.',
            'variants.*.price_dine_in.required' => 'Harga dine in wajib diisi di setiap baris.',
            'variants.*.price_delivery.required' => 'Harga delivery wajib diisi di setiap baris.',
        ]);

        $rows = $this->normalizeVariantRows($validated['variants'] ?? []);

        if (count($rows) === 0) {
            return back()
                ->withErrors(['variants' => 'Minimal harus ada 1 variant yang valid.'])
                ->withInput();
        }

        $duplicateKeys = collect($rows)
            ->map(fn ($row) => ($row['outlet_id'] ?: 'ALL') . '|' . strtoupper(trim((string) $row['code'])))
            ->values();

        $duplicateCodes = $duplicateKeys
            ->duplicates()
            ->unique()
            ->map(fn ($key) => explode('|', $key)[1] ?? $key)
            ->values();

        $codes = collect($rows)->pluck('code')->all();

        if ($duplicateCodes->isNotEmpty()) {
            return back()
                ->withErrors([
                    'variants' => 'Ada kode variant yang duplikat di form: ' . $duplicateCodes->implode(', '),
                ])
                ->withInput();
        }

        $existingCodes = collect($rows)
            ->filter(function ($row) use ($validated) {
                return ProductVariant::where('product_id', $validated['product_id'])
                    ->where('outlet_id', $row['outlet_id'])
                    ->whereRaw('UPPER(code) = ?', [strtoupper($row['code'])])
                    ->exists();
            })
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->unique()
            ->values();

        if ($existingCodes->isNotEmpty()) {
            return back()
                ->withErrors([
                    'variants' => 'Kode variant sudah dipakai pada product ini: ' . $existingCodes->implode(', '),
                ])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $rows) {
            foreach ($rows as $row) {
                ProductVariant::create([
                    'product_id' => $validated['product_id'],
                    'outlet_id' => $row['outlet_id'],
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'price' => $row['price_dine_in'],
                    'price_dine_in' => $row['price_dine_in'],
                    'price_delivery' => $row['price_delivery'],
                    'is_active' => $row['is_active'],
                ]);
            }
        });

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', count($rows) . ' variant baru berhasil ditambahkan.');
    }

    public function edit(ProductVariant $variant)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        $outlets = Outlet::orderBy('name')->get();

        $variant->load(['product.brand', 'product.category', 'outlet']);

        $productVariants = ProductVariant::with(['product.brand', 'product.category', 'outlet'])
            ->where('product_id', $variant->product_id)
            ->orderBy('name')
            ->get();

        return view('backoffice.variants.edit', [
            'user' => $user,
            'variant' => $variant,
            'products' => $products,
            'outlets' => $outlets,
            'productVariants' => $productVariants,
        ]);
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $this->authorizeAccess();

        $existingGroup = ProductVariant::where('product_id', $variant->product_id)
            ->get()
            ->keyBy('id');

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variants' => 'required|array|min:1',
            'variants.*.outlet_id' => 'nullable|exists:outlets,id',
            'variants.*.id' => 'nullable|integer',
            'variants.*.outlet_id' => 'nullable|exists:outlets,id',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.code' => 'required|string|max:50',
            'variants.*.outlet_ids' => 'nullable|array',
            'variants.*.outlet_ids.*' => 'nullable|exists:outlets,id',
            'variants.*.price_dine_in' => 'required|numeric|min:0',
            'variants.*.price_delivery' => 'required|numeric|min:0',
            'variants.*.is_active' => 'nullable|boolean',
        ], [
            'variants.required' => 'Minimal harus ada 1 variant.',
            'variants.*.name.required' => 'Nama variant wajib diisi di setiap baris.',
            'variants.*.code.required' => 'Kode variant wajib diisi di setiap baris.',
            'variants.*.price_dine_in.required' => 'Harga dine in wajib diisi di setiap baris.',
            'variants.*.price_delivery.required' => 'Harga delivery wajib diisi di setiap baris.',
        ]);

        $rows = $this->normalizeVariantRows($validated['variants'] ?? []);

        if (count($rows) === 0) {
            return back()
                ->withErrors(['variants' => 'Minimal harus ada 1 variant yang valid.'])
                ->withInput();
        }

        $submittedIds = collect($rows)
            ->pluck('id')
            ->filter()
            ->values();

        $duplicateKeys = collect($rows)
            ->map(fn ($row) => ($row['outlet_id'] ?: 'ALL') . '|' . strtoupper(trim((string) $row['code'])))
            ->values();

        $duplicateCodes = $duplicateKeys
            ->duplicates()
            ->unique()
            ->map(fn ($key) => explode('|', $key)[1] ?? $key)
            ->values();

        $codes = collect($rows)
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->values();

        if ($duplicateCodes->isNotEmpty()) {
            return back()
                ->withErrors([
                    'variants' => 'Ada kode variant yang duplikat di form: ' . $duplicateCodes->implode(', '),
                ])
                ->withInput();
        }

        $conflictingCodes = collect($rows)
            ->filter(function ($row) use ($validated, $submittedIds) {
                return ProductVariant::query()
                    ->where('product_id', $validated['product_id'])
                    ->where('outlet_id', $row['outlet_id'])
                    ->whereRaw('UPPER(code) = ?', [strtoupper($row['code'])])
                    ->when($submittedIds->isNotEmpty(), function ($query) use ($submittedIds) {
                        $query->whereNotIn('id', $submittedIds->all());
                    })
                    ->exists();
            })
            ->pluck('code')
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->unique()
            ->values();

        if ($conflictingCodes->isNotEmpty()) {
            return back()
                ->withErrors([
                    'variants' => 'Kode variant sudah dipakai pada product tujuan: ' . $conflictingCodes->implode(', '),
                ])
                ->withInput();
        }

        $removedIds = $existingGroup->keys()->diff($submittedIds);

        foreach ($removedIds as $removedId) {
            $removedVariant = $existingGroup->get($removedId);

            if (! $removedVariant) {
                continue;
            }

            $removedVariant->loadCount([
                'recipe',
                'salesTransactionItems',
            ]);

            if ($removedVariant->recipe_count > 0 || $removedVariant->sales_transaction_items_count > 0) {
                return back()
                    ->withErrors([
                        'variants' => 'Variant "' . $removedVariant->name . '" tidak bisa dihapus karena masih dipakai di recipe / transaksi.',
                    ])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($validated, $rows, $existingGroup, $removedIds) {
            foreach ($removedIds as $removedId) {
                $removedVariant = $existingGroup->get($removedId);

                if ($removedVariant) {
                    $removedVariant->delete();
                }
            }

            foreach ($rows as $row) {
                if (! empty($row['id']) && $existingGroup->has($row['id'])) {
                    $existingGroup[$row['id']]->update([
                        'product_id' => $validated['product_id'],
                        'outlet_id' => $row['outlet_id'],
                        'name' => $row['name'],
                        'code' => $row['code'],
                        'price' => $row['price_dine_in'],
                        'price_dine_in' => $row['price_dine_in'],
                        'price_delivery' => $row['price_delivery'],
                        'is_active' => $row['is_active'],
                    ]);
                } else {
                    ProductVariant::create([
                        'product_id' => $validated['product_id'],
                        'outlet_id' => $row['outlet_id'],
                        'name' => $row['name'],
                        'code' => $row['code'],
                        'price' => $row['price_dine_in'],
                        'price_dine_in' => $row['price_dine_in'],
                        'price_delivery' => $row['price_delivery'],
                        'is_active' => $row['is_active'],
                    ]);
                }
            }
        });

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Group variant berhasil diupdate.');
    }

    public function destroy(ProductVariant $variant)
    {
        $this->authorizeAccess();

        $variant->loadCount([
            'recipe',
            'salesTransactionItems',
        ]);

        if ($variant->recipe_count > 0 || $variant->sales_transaction_items_count > 0) {
            return redirect()
                ->route('backoffice.variants.index')
                ->with('error', 'Variant tidak bisa dihapus karena masih dipakai di recipe / transaksi.');
        }

        $variantName = $variant->name;
        $variant->delete();

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant "' . $variantName . '" berhasil dihapus.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        return view('backoffice.variants.import', [
            'user' => $user,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_variants_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['product_code', 'outlet_code', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active']);
            fputcsv($handle, ['black_tea', '', 'Regular', 'R', '14000', '14000', '1']);
            fputcsv($handle, ['black_tea', 'outlet_bintaro', 'Large', 'L', '16000', '16000', '1']);

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'variants_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['product_code', 'product_name', 'outlet_code', 'outlet_name', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active']);

            ProductVariant::with(['product', 'outlet'])
                ->orderBy('product_id')
                ->orderBy('name')
                ->chunk(200, function ($variants) use ($handle) {
                    foreach ($variants as $variant) {
                        fputcsv($handle, [
                            $variant->product->code ?? '',
                            $variant->product->name ?? '',
                            $variant->outlet->code ?? '',
                            $variant->outlet->name ?? 'Semua Outlet',
                            $variant->name,
                            $variant->code,
                            (float) ($variant->price_dine_in ?? $variant->price ?? 0),
                            (float) ($variant->price_delivery ?? $variant->price ?? 0),
                            $variant->is_active ? '1' : '0',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function importStore(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
        ]);

        $realPath = $request->file('file')->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return redirect()
                ->route('backoffice.variants.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.variants.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.variants.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(fn ($value) => trim(strtolower($value)), $header);

        $expectedHeader = [
            'product_code',
            'outlet_code',
            'name',
            'code',
            'price_dine_in',
            'price_delivery',
            'is_active',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.variants.import')
                ->with('error', 'Header CSV tidak sesuai template. Urutannya harus: product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            $rowNumber = $index + 2;

            if (trim($line) === '') {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: baris kosong.";
                continue;
            }

            $row = str_getcsv($line, $delimiter);

            if (count($row) < 7) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: jumlah kolom kurang dari 7.";
                continue;
            }

            $productCode = trim($row[0] ?? '');
            $outletCode = trim($row[1] ?? '');
            $name = trim($row[2] ?? '');
            $code = strtoupper(trim($row[3] ?? ''));
            $priceDineInRaw = trim($row[4] ?? '');
            $priceDeliveryRaw = trim($row[5] ?? '');
            $isActiveRaw = trim($row[6] ?? '');

            if ($productCode === '' || $name === '' || $code === '') {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: product_code, name, dan code wajib diisi.";
                continue;
            }

            $priceDineIn = is_numeric($priceDineInRaw) ? (float) $priceDineInRaw : null;
            $priceDelivery = is_numeric($priceDeliveryRaw) ? (float) $priceDeliveryRaw : null;

            if ($priceDineIn === null || $priceDineIn < 0) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: price_dine_in tidak valid.";
                continue;
            }

            if ($priceDelivery === null || $priceDelivery < 0) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: price_delivery tidak valid.";
                continue;
            }

            $product = Product::whereRaw('LOWER(code) = ?', [mb_strtolower($productCode)])->first();

            if (! $product) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: product code '{$productCode}' tidak ditemukan.";
                continue;
            }

            $outletId = null;

            if ($outletCode !== '') {
                $outlet = Outlet::whereRaw('LOWER(code) = ?', [mb_strtolower($outletCode)])->first();

                if (! $outlet) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: outlet code '{$outletCode}' tidak ditemukan.";
                    continue;
                }

                $outletId = $outlet->id;
            }

            $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

            $variant = ProductVariant::where('product_id', $product->id)
                ->where('outlet_id', $outletId)
                ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
                ->first();

            if ($variant) {
                $variant->update([
                    'name' => $name,
                    'price' => $priceDineIn,
                    'price_dine_in' => $priceDineIn,
                    'price_delivery' => $priceDelivery,
                    'is_active' => $isActive,
                ]);
                $updated++;
            } else {
                ProductVariant::create([
                    'product_id' => $product->id,
                    'outlet_id' => null,
                    'name' => $name,
                    'code' => $code,
                    'price' => $priceDineIn,
                    'price_dine_in' => $priceDineIn,
                    'price_delivery' => $priceDelivery,
                    'is_active' => $isActive,
                ]);
                $imported++;
            }
        }

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', "Import variants selesai. Baru: {$imported}. Update: {$updated}. Dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }
}