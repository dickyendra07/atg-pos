<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\BackofficeOutletContext;
use App\Services\ProductAccessPolicy;
use App\Services\CleanupDeletionService as Cleanup;
use App\Services\ProductWorkspace;
use App\Services\ProductWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (! ProductAccessPolicy::hasProductRole($user)) {
            abort(403, ProductAccessPolicy::ROLE_DENIED_MESSAGE);
        }

        return $user;
    }

    protected function writer(): ProductWriter
    {
        return app(ProductWriter::class);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $categories = ProductCategory::orderBy('name')->get();

        $products = Product::with(['brand', 'category', 'variants', 'outlets'])
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
        $categories = ProductCategory::where('is_active', true)->orderBy('name')->get();
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
        $user = $this->authorizeAccess();

        $validated = $request->validate(array_merge($this->writer()->generalRules(), $this->writer()->outletRules()));

        $product = $this->writer()->create($user, $validated);

        // Next step of setting up a Product: its Variants, in the Product Workspace (which keeps the
        // list context, so closing the workspace still lands on the list the user came from).
        return redirect(ProductWorkspace::url($product, 'variants', BackofficeReturnUrl::fromRequest($request)))
            ->with('success', 'Product berhasil ditambahkan.');
    }

    /**
     * The Product Workspace: one page per Product with General, Outlets, Variants & Pricing, Recipe,
     * Stock & Readiness and Promo sections (?section=...). Carries the list context (return_to).
     */
    public function edit(Request $request, Product $product)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        app(ProductAccessPolicy::class)->authorize($user, $product);

        $workspace = app(ProductWorkspace::class);
        $data = $workspace->viewData($request, $product, $user, $request->query('section'));

        // Deep link from the classic Recipe pages: ?recipe=<id> lands on the Recipe section, brings that Recipe
        // into view and, when the user may edit it, opens its drawer. Any other state is only shown.
        $link = $workspace->recipeDeepLink($data['workspace'], $request->query('recipe'));
        $data['openRecipeDrawer'] = $link && $link['form_url'] ? $link : null;
        $data['focusRecipeId'] = $link['id'] ?? null;

        if ($link !== null) {
            $data['section'] = 'recipe';
        }

        return view('backoffice.products.workspace', $data);
    }

    public function update(Request $request, Product $product)
    {
        $user = $this->authorizeAccess();
        app(ProductAccessPolicy::class)->authorize($user, $product);

        $validated = $request->validate(array_merge($this->writer()->generalRules($product), $this->writer()->outletRules()));

        $this->writer()->update($user, $product, $validated);

        return BackofficeReturnUrl::redirect($request, 'backoffice.products.index', [], 'product-'.$product->id)
            ->with('success', 'Product berhasil diperbarui.');
    }

    public function destroy(Request $request, Product $product)
    {
        $user = $this->authorizeAccess();
        app(ProductAccessPolicy::class)->authorize($user, $product);

        $product->update(['is_active' => false]);

        // Inactivating keeps the row in the list, so it can stay the anchor.
        return BackofficeReturnUrl::redirect($request, 'backoffice.products.index', [], 'product-'.$product->id)
            ->with('success', 'Product berhasil dinonaktifkan. Histori dan data terkait tetap disimpan.');
    }

    /**
     * Legacy URL, kept so old links and forms resolve - but it is NOT a delete path of its own any more.
     * It used to physically delete Products that looked unused. It now hands over to the one cleanup
     * service: feature flag, owner/admin_pusat, impact check, typed confirmation, and the Product (with its
     * Variants) is tombstoned, never physically deleted.
     */
    public function destroyPermanent(Request $request, int $productId)
    {
        return app(CleanupDeleteController::class)->destroy($request, Cleanup::TYPE_PRODUCT, $productId, app(Cleanup::class));
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
        $this->authorizeAccess();

        $filename = 'products_export_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['brand_name', 'category_name', 'name', 'code', 'description', 'is_active']);

            Product::with(['brand', 'category'])
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

        $request->validate([
            'outlet_id' => 'required|integer',
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'outlet_id.required' => 'Pilih outlet tujuan terlebih dahulu. Import Product tidak boleh membuat availability global.',
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
        ]);

        // The Back Office has no Active Outlet any more: the import names its own target outlet, and it
        // must be one this user can access (same rule the old Active Outlet selector enforced).
        $targetOutletId = (int) $request->input('outlet_id');

        if (! $this->outletContext()->canAccess($user, $targetOutletId)) {
            return back()->withInput()->with('error', 'Outlet tujuan tidak aktif atau tidak tersedia untuk akun ini.');
        }

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
                $product->outlets()->syncWithoutDetaching([$targetOutletId]);
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
                $product->outlets()->sync([$targetOutletId]);
                $imported++;
            }
        }

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', "Import products selesai. Baru: {$imported}. Update: {$updated}. Dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }

}
