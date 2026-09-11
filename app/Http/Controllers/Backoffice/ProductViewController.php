<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BackofficeOutletContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductViewController extends Controller
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

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Products.');
        }

        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $categories = ProductCategory::orderBy('name')->get();
        $activeOutletId = $this->outletContext()->activeOutletId($user);

        $products = Product::with(['brand', 'category', 'variants', 'outlets'])
            ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
            ->when($request->filled('category_id'), function ($query) use ($request) {
                $query->where('product_category_id', $request->category_id);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = trim((string) $request->search);

                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%'.$keyword.'%')
                        ->orWhere('code', 'like', '%'.$keyword.'%')
                        ->orWhereHas('brand', function ($brandQuery) use ($keyword) {
                            $brandQuery->where('name', 'like', '%'.$keyword.'%');
                        })
                        ->orWhereHas('category', function ($categoryQuery) use ($keyword) {
                            $categoryQuery->where('name', 'like', '%'.$keyword.'%');
                        })
                        ->orWhereHas('variants', function ($variantQuery) use ($keyword) {
                            $variantQuery->where('name', 'like', '%'.$keyword.'%')
                                ->orWhere('code', 'like', '%'.$keyword.'%');
                        });
                });
            })
            ->orderBy('product_category_id')
            ->orderBy('name')
            ->get();

        $productGroups = $products
            ->groupBy(function ($product) {
                return $product->category->name ?? 'Uncategorized';
            })
            ->sortKeys();

        return view('backoffice.products.index', [
            'user' => $user,
            'products' => $products,
            'productGroups' => $productGroups,
            'categories' => $categories,
            'filters' => [
                'search' => $request->search,
                'category_id' => $request->category_id,
            ],
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $brands = Brand::orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();
        $outlets = $this->outletContext()->accessibleOutlets($user);

        return view('backoffice.products.create', [
            'user' => $user,
            'brands' => $brands,
            'categories' => $categories,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:outlets,id',
        ]);

        $this->validateAccessibleOutletIds(Auth::user(), $validated['outlet_ids']);

        DB::transaction(function () use ($validated) {
            $product = Product::create(
                collect($validated)
                    ->except('outlet_ids')
                    ->toArray()
            );

            $product->outlets()->sync($validated['outlet_ids'] ?? []);
        });

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product baru berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $brands = Brand::orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();
        $outlets = $this->outletContext()->accessibleOutlets($user);

        $product->load('outlets');

        return view('backoffice.products.edit', [
            'user' => $user,
            'product' => $product,
            'brands' => $brands,
            'categories' => $categories,
            'outlets' => $outlets,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code,'.$product->id,
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
            'outlet_ids' => 'required|array|min:1',
            'outlet_ids.*' => 'exists:outlets,id',
        ]);

        $this->validateAccessibleOutletIds(Auth::user(), $validated['outlet_ids']);

        $editableOutletIds = $this->outletContext()->accessibleOutlets(Auth::user())->pluck('id')->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($product, $validated, $editableOutletIds) {
            $product->update(
                collect($validated)
                    ->except('outlet_ids')
                    ->toArray()
            );

            $productOutletIds = collect($validated['outlet_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->merge($product->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->diff($editableOutletIds))
                ->unique()
                ->values();

            $product->outlets()->sync($productOutletIds->all());

            $product->variants()->with('outlets')->get()->each(function ($variant) use ($productOutletIds) {
                if ($variant->outlets->isEmpty()) {
                    return;
                }

                $validVariantOutletIds = $variant->outlets
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->intersect($productOutletIds)
                    ->values()
                    ->all();

                $variant->outlets()->sync($validVariantOutletIds);

                if (empty($validVariantOutletIds)) {
                    $variant->update(['is_active' => false]);
                }
            });
        });

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product berhasil diupdate.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeAccess();

        $productName = $product->name;
        $product->update(['is_active' => false]);

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product "'.$productName.'" berhasil dinonaktifkan. Variant, outlet, recipe, dan riwayat transaksi tetap tersimpan.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        return view('backoffice.products.import', [
            'user' => $user,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_products_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['brand_name', 'category_name', 'name', 'code', 'description', 'is_active']);
            fputcsv($handle, ['ATG Beverage', 'Minuman', 'Thai Tea', 'THAI-TEA', 'Minuman thai tea', '1']);
            fputcsv($handle, ['ATG Food', 'Snack', 'French Fries', 'FRIES', 'Kentang goreng', '1']);

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv(): StreamedResponse
    {
        $user = $this->authorizeAccess();
        $activeOutletId = $this->outletContext()->activeOutletId($user);

        $filename = 'products_export_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($activeOutletId) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['brand_name', 'category_name', 'name', 'code', 'description', 'is_active']);

            Product::with(['brand', 'category'])
                ->when($activeOutletId, fn ($query) => $query->availableAtOutlet($activeOutletId))
                ->orderBy('name')
                ->chunk(200, function ($products) use ($handle) {
                    foreach ($products as $product) {
                        fputcsv($handle, [
                            $product->brand->name ?? '',
                            $product->category->name ?? '',
                            $product->name,
                            $product->code,
                            $product->description ?? '',
                            $product->is_active ? '1' : '0',
                        ]);
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function importStore(Request $request)
    {
        $user = $this->authorizeAccess();
        $activeOutletId = $this->outletContext()->activeOutletId($user);

        if (! $activeOutletId) {
            return back()->with('error', 'Pilih Active Outlet terlebih dahulu. Import Product tidak boleh membuat availability global.');
        }

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
        ]);

        $realPath = $request->file('file')->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return redirect()
                ->route('backoffice.products.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.products.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.products.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(fn ($value) => trim(strtolower($value)), $header);

        $expectedHeader = [
            'brand_name',
            'category_name',
            'name',
            'code',
            'description',
            'is_active',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.products.import')
                ->with('error', 'Header CSV tidak sesuai template. Urutannya harus: brand_name,category_name,name,code,description,is_active');
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

            if (count($row) < 6) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: jumlah kolom kurang dari 6.";

                continue;
            }

            $brandName = trim($row[0] ?? '');
            $categoryName = trim($row[1] ?? '');
            $name = trim($row[2] ?? '');
            $code = trim($row[3] ?? '');
            $description = trim($row[4] ?? '');
            $isActiveRaw = trim($row[5] ?? '');

            if ($brandName === '' || $categoryName === '' || $name === '' || $code === '') {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: brand, category, name, dan code wajib diisi.";

                continue;
            }

            $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($brandName)])->first();
            if (! $brand) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: brand '{$brandName}' tidak ditemukan.";

                continue;
            }

            $category = ProductCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();
            if (! $category) {
                $skipped++;
                $errors[] = "Baris {$rowNumber}: category '{$categoryName}' tidak ditemukan.";

                continue;
            }

            $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

            $product = Product::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

            if ($product) {
                $product->update([
                    'brand_id' => $brand->id,
                    'product_category_id' => $category->id,
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'is_active' => $isActive,
                ]);
                $product->outlets()->syncWithoutDetaching([$activeOutletId]);
                $updated++;
            } else {
                $product = Product::create([
                    'brand_id' => $brand->id,
                    'product_category_id' => $category->id,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description !== '' ? $description : null,
                    'is_active' => $isActive,
                ]);
                $product->outlets()->sync([$activeOutletId]);
                $imported++;
            }
        }

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Import products selesai. Baru: {$imported}. Update: {$updated}. Dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }

    protected function validateAccessibleOutletIds($user, array $outletIds): void
    {
        $allowed = $this->outletContext()->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
        $invalid = collect($outletIds)->map(fn ($id) => (int) $id)->diff($allowed);

        if ($invalid->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'outlet_ids' => 'Ada outlet tidak aktif atau tidak tersedia untuk akun ini.',
            ]);
        }
    }
}
