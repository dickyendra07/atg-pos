<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Variants.');
        }

        return $user;
    }

    public function index()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $variants = ProductVariant::with(['product.brand', 'product.category'])
            ->latest()
            ->get();

        return view('backoffice.variants.index', [
            'user' => $user,
            'variants' => $variants,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        return view('backoffice.variants.create', [
            'user' => $user,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:product_variants,code',
            'price_dine_in' => 'required|numeric|min:0',
            'price_delivery' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        ProductVariant::create([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'price' => $validated['price_dine_in'],
            'price_dine_in' => $validated['price_dine_in'],
            'price_delivery' => $validated['price_delivery'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant baru berhasil ditambahkan.');
    }

    public function edit(ProductVariant $variant)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        return view('backoffice.variants.edit', [
            'user' => $user,
            'variant' => $variant,
            'products' => $products,
        ]);
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:product_variants,code,' . $variant->id,
            'price_dine_in' => 'required|numeric|min:0',
            'price_delivery' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $variant->update([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'price' => $validated['price_dine_in'],
            'price_dine_in' => $validated['price_dine_in'],
            'price_delivery' => $validated['price_delivery'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant berhasil diupdate.');
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

            fputcsv($handle, ['product_code', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active']);
            fputcsv($handle, ['black_tea', 'Regular', 'black_tea_r', '14000', '14000', '1']);
            fputcsv($handle, ['black_tea', 'Large', 'black_tea_l', '16000', '16000', '1']);

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

            fputcsv($handle, ['product_code', 'product_name', 'name', 'code', 'price_dine_in', 'price_delivery', 'is_active']);

            ProductVariant::with(['product'])
                ->orderBy('name')
                ->chunk(200, function ($variants) use ($handle) {
                    foreach ($variants as $variant) {
                        fputcsv($handle, [
                            $variant->product->code ?? '',
                            $variant->product->name ?? '',
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
            'name',
            'code',
            'price_dine_in',
            'price_delivery',
            'is_active',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.variants.import')
                ->with('error', 'Header CSV tidak sesuai template. Urutannya harus: product_code,name,code,price_dine_in,price_delivery,is_active');
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

            $productCode = trim($row[0] ?? '');
            $name = trim($row[1] ?? '');
            $code = trim($row[2] ?? '');
            $priceDineInRaw = trim($row[3] ?? '');
            $priceDeliveryRaw = trim($row[4] ?? '');
            $isActiveRaw = trim($row[5] ?? '');

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

            $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

            $variant = ProductVariant::whereRaw('LOWER(code) = ?', [mb_strtolower($code)])->first();

            if ($variant) {
                $variant->update([
                    'product_id' => $product->id,
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