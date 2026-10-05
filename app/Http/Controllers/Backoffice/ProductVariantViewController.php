<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Services\BackofficeOutletContext;
use App\Services\VariantWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductVariantViewController extends Controller
{
    protected function outletContext(): BackofficeOutletContext
    {
        return app(BackofficeOutletContext::class);
    }

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

    /** A Variant is reachable by ID, so its Product's outlet scope is checked here (as the Product pages do). */
    protected function authorizeVariantProduct($user, ProductVariant $variant): void
    {
        $product = $variant->product;

        abort_unless($product !== null, 404);
        app(\App\Services\ProductAccessPolicy::class)->authorize($user, $product);
    }

    protected function writer(): VariantWriter
    {
        return app(VariantWriter::class);
    }

    /** Group-editor rules: the shared row rules plus the legacy per-row outlet_id column. */
    protected function groupRules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'variants' => 'required|array|min:1',
            'variants.*.id' => 'nullable|integer',
            'variants.*.outlet_id' => 'nullable|exists:outlets,id',
        ] + $this->writer()->rowRules('variants.*.');
    }

    protected function groupMessages(): array
    {
        return [
            'variants.required' => 'Minimal harus ada 1 variant.',
            'variants.*.name.required' => 'Nama variant wajib diisi di setiap baris.',
            'variants.*.code.required' => 'Kode variant wajib diisi di setiap baris.',
            'variants.*.price_dine_in.required' => 'Harga dine in wajib diisi di setiap baris.',
            'variants.*.price_delivery.required' => 'Harga delivery wajib diisi di setiap baris.',
        ];
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $activeOutletId = $this->outletContext()->activeOutletId($user);
        $variants = ProductVariant::with(['product.brand', 'product.category', 'outlet', 'outlets'])
            ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('product', fn ($productQuery) => $productQuery->where('product_category_id', (int) $request->input('category_id'))))
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
            'categories' => ProductCategory::orderBy('name')->get(),
            'selectedCategoryId' => $request->input('category_id'),
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $activeOutletId = $this->outletContext()->activeOutletId($user);
        $products = Product::with(['brand', 'category', 'outlets'])
            ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
            ->orderBy('name')
            ->get();

        $outlets = $this->outletContext()->accessibleOutlets($user);

        return view('backoffice.variants.create', [
            'user' => $user,
            'products' => $products,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        $validated = $request->validate($this->groupRules(), $this->groupMessages());

        $rows = $this->writer()->normalizeRows($validated['variants'] ?? []);
        $product = Product::findOrFail($validated['product_id']);
        $created = $this->writer()->createGroup($user, $product, $rows);

        return BackofficeReturnUrl::redirect($request, 'backoffice.variants.index', [], 'variant-group-'.$validated['product_id'])
            ->with('success', $created === 1 ? 'Variant berhasil ditambahkan.' : $created.' variant berhasil ditambahkan.');
    }

    public function edit(ProductVariant $variant)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);
        $this->authorizeVariantProduct($user, $variant);

        $activeOutletId = $this->outletContext()->activeOutletId($user);
        $products = Product::with(['brand', 'category', 'outlets'])
            ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
            ->orderBy('name')
            ->get();

        $outlets = $this->outletContext()->accessibleOutlets($user);

        $variant->load(['product.brand', 'product.category', 'outlet', 'outlets']);

        $productVariants = ProductVariant::with(['product.brand', 'product.category', 'outlet', 'outlets'])
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
        $user = $this->authorizeAccess();
        $this->authorizeVariantProduct($user, $variant);

        $validated = $request->validate($this->groupRules(), $this->groupMessages());

        $rows = $this->writer()->normalizeRows($validated['variants'] ?? []);
        $product = Product::findOrFail($validated['product_id']);
        $this->writer()->updateGroup($user, $variant, $product, $rows);

        return BackofficeReturnUrl::redirect($request, 'backoffice.variants.index', [], 'variant-group-'.$validated['product_id'])
            ->with('success', 'Variant berhasil diperbarui.');
    }

    public function destroy(Request $request, ProductVariant $variant)
    {
        $user = $this->authorizeAccess();
        $this->authorizeVariantProduct($user, $variant);

        $variantName = $variant->name;
        $this->writer()->deactivate($variant);

        // Inactivating keeps the variant listed under its product group, so the group stays the anchor.
        return BackofficeReturnUrl::redirect($request, 'backoffice.variants.index', [], 'variant-group-'.$variant->product_id)
            ->with('success', 'Variant "'.$variantName.'" berhasil dinonaktifkan. Product, outlet, recipe, dan riwayat transaksi tetap tersimpan.');
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
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['product_code', 'outlet_code', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active']);
            fputcsv($handle, ['black_tea', 'outlet_bintaro', 'Regular', 'R', '14000', '14000', '1']);
            fputcsv($handle, ['black_tea', 'outlet_bintaro', 'Large', 'L', '16000', '16000', '1']);

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv(): StreamedResponse
    {
        $user = $this->authorizeAccess();
        $activeOutletId = $this->outletContext()->activeOutletId($user);

        $filename = 'pos_product_master_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($activeOutletId) {

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'no',
                'brand',
                'category',
                'product_code',
                'product_name',
                'variant_name',
                'variant_code',
                'price',
                'delivery_price',
                'status',
            ]);

            $no = 1;

            ProductVariant::with([
                'product.brand',
                'product.category',
            ])
                ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
                ->orderBy('product_id')
                ->orderBy('name')
                ->chunk(200, function ($variants) use ($handle, &$no) {

                    foreach ($variants as $variant) {

                        fputcsv($handle, [
                            $no++,
                            $variant->product?->brand?->name ?? '',
                            $variant->product?->category?->name ?? '',
                            $variant->product?->code ?? '',
                            $variant->product?->name ?? '',
                            $variant->name,
                            $variant->code,
                            (float) ($variant->price_dine_in ?? $variant->price ?? 0),
                            (float) ($variant->price_delivery ?? $variant->price ?? 0),
                            $variant->is_active ? 'Aktif' : 'Non Aktif',
                        ]);

                    }

                });

            fclose($handle);

        }, 200, $headers);
    }

    public function importStore(Request $request)
    {
        $user = $this->authorizeAccess();

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

            if ($productCode === '' || $outletCode === '' || $name === '' || $code === '') {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: product_code, outlet_code, name, dan code wajib diisi.";

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

            $outlet = Outlet::whereRaw('LOWER(code) = ?', [mb_strtolower($outletCode)])->first();

            if (! $outlet || ! $outlet->is_active || ! $this->outletContext()->canAccess($user, (int) $outlet->id)) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: outlet code '{$outletCode}' tidak aktif atau tidak tersedia untuk akun ini.";

                continue;
            }

            $outletId = $outlet->id;

            if (! $product->outlets()->whereKey($outletId)->exists()) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: outlet '{$outletCode}' belum tersedia pada Product '{$product->name}'.";

                continue;
            }

            $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

            $variant = ProductVariant::where('product_id', $product->id)
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
                $variant->outlets()->syncWithoutDetaching([$outletId]);
                $updated++;
            } else {
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'outlet_id' => null,
                    'name' => $name,
                    'code' => $code,
                    'price' => $priceDineIn,
                    'price_dine_in' => $priceDineIn,
                    'price_delivery' => $priceDelivery,
                    'is_active' => $isActive,
                ]);
                $variant->outlets()->sync([$outletId]);
                $imported++;
            }
        }

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', "Import variants selesai. Baru: {$imported}. Update: {$updated}. Dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }
}
