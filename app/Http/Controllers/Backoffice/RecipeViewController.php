<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Recipes.');
        }

        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $recipes = Recipe::with([
            'variant.product',
            'items.ingredient.category',
        ])
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->status === 'active') {
                    $query->where('is_active', true);
                }

                if ($request->status === 'inactive') {
                    $query->where('is_active', false);
                }
            })
            ->when($request->filled('ingredient_type'), function ($query) use ($request) {
                $query->whereHas('items.ingredient', function ($ingredientQuery) use ($request) {
                    $ingredientQuery->where('ingredient_type', $request->ingredient_type);
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $keyword = trim((string) $request->search);

                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', '%' . $keyword . '%')
                        ->orWhereHas('variant', function ($variantQuery) use ($keyword) {
                            $variantQuery->where('name', 'like', '%' . $keyword . '%')
                                ->orWhere('code', 'like', '%' . $keyword . '%')
                                ->orWhereHas('product', function ($productQuery) use ($keyword) {
                                    $productQuery->where('name', 'like', '%' . $keyword . '%');
                                });
                        })
                        ->orWhereHas('items.ingredient', function ($ingredientQuery) use ($keyword) {
                            $ingredientQuery->where('name', 'like', '%' . $keyword . '%')
                                ->orWhereHas('category', function ($categoryQuery) use ($keyword) {
                                    $categoryQuery->where('name', 'like', '%' . $keyword . '%');
                                });
                        });
                });
            })
            ->latest()
            ->get();

        return view('backoffice.recipes.index', [
            'user' => $user,
            'recipes' => $recipes,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'ingredient_type' => $request->ingredient_type,
            ],
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $variants = ProductVariant::with(['product'])
            ->orderBy('name')
            ->get();

        return view('backoffice.recipes.create', [
            'user' => $user,
            'variants' => $variants,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id|unique:recipes,product_variant_id',
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $validated['product_variant_id'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.recipes.index')
            ->with('success', 'Recipe baru berhasil ditambahkan.');
    }

    public function edit(Recipe $recipe)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $recipe->load([
            'items.ingredient.category',
            'variant.product',
        ]);

        $variants = ProductVariant::with(['product'])
            ->orderBy('name')
            ->get();

        $ingredients = Ingredient::with(['category'])
            ->where('is_active', true)
            ->orderByRaw("
                CASE
                    WHEN ingredient_type = 'semi_finished' THEN 0
                    ELSE 1
                END
            ")
            ->orderBy('name')
            ->get();

        return view('backoffice.recipes.edit', [
            'user' => $user,
            'recipe' => $recipe,
            'variants' => $variants,
            'ingredients' => $ingredients,
        ]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id|unique:recipes,product_variant_id,' . $recipe->id,
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        $recipe->update([
            'product_id' => $variant->product_id,
            'product_variant_id' => $validated['product_variant_id'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.recipes.edit', $recipe->id)
            ->with('success', 'Recipe header berhasil diupdate.');
    }

    public function storeItem(Request $request, Recipe $recipe)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'qty' => 'required|numeric|min:0.01',
        ]);

        $existingItem = $recipe->items()
            ->where('ingredient_id', $validated['ingredient_id'])
            ->first();

        if ($existingItem) {
            return redirect()
                ->route('backoffice.recipes.edit', $recipe->id)
                ->with('error', 'Ingredient itu sudah ada di recipe ini. Edit qty-nya dulu atau hapus lalu tambah ulang.');
        }

        $ingredient = Ingredient::findOrFail($validated['ingredient_id']);

        $recipe->items()->create([
            'ingredient_id' => $validated['ingredient_id'],
            'qty' => $validated['qty'],
            'unit' => $ingredient->unit,
        ]);

        return redirect()
            ->route('backoffice.recipes.edit', $recipe->id)
            ->with('success', 'Recipe item berhasil ditambahkan.');
    }

    public function destroyItem(Recipe $recipe, RecipeItem $item)
    {
        $this->authorizeAccess();

        if ((int) $item->recipe_id !== (int) $recipe->id) {
            abort(404);
        }

        $ingredientName = $item->ingredient?->name ?? 'Item';
        $item->delete();

        return redirect()
            ->route('backoffice.recipes.edit', $recipe->id)
            ->with('success', 'Recipe item "' . $ingredientName . '" berhasil dihapus.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        return view('backoffice.recipes.import', [
            'user' => $user,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_recipes_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['variant_code', 'ingredient_name', 'qty', 'is_active']);
            fputcsv($handle, ['r', 'Black Tea', '10', '1']);
            fputcsv($handle, ['r', 'Liquid Sugar', '20', '1']);
            fputcsv($handle, ['l', 'Black Tea', '15', '1']);
            fputcsv($handle, ['l', 'Liquid Sugar', '25', '1']);

            fclose($handle);
        }, 200, $headers);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'recipes_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'recipe_name',
                'product_name',
                'variant_name',
                'variant_code',
                'ingredient_name',
                'ingredient_category',
                'ingredient_type',
                'qty',
                'unit',
                'is_active',
            ]);

            Recipe::with([
                'variant.product',
                'items.ingredient.category',
            ])
                ->orderBy('name')
                ->chunk(200, function ($recipes) use ($handle) {
                    foreach ($recipes as $recipe) {
                        if ($recipe->items->count() === 0) {
                            fputcsv($handle, [
                                $recipe->name,
                                $recipe->variant->product->name ?? '',
                                $recipe->variant->name ?? '',
                                $recipe->variant->code ?? '',
                                '',
                                '',
                                '',
                                '',
                                '',
                                $recipe->is_active ? '1' : '0',
                            ]);
                            continue;
                        }

                        foreach ($recipe->items as $item) {
                            fputcsv($handle, [
                                $recipe->name,
                                $recipe->variant->product->name ?? '',
                                $recipe->variant->name ?? '',
                                $recipe->variant->code ?? '',
                                $item->ingredient->name ?? '',
                                $item->ingredient->category->name ?? '',
                                $item->ingredient?->ingredientTypeLabel() ?? '',
                                (float) $item->qty,
                                $item->unit ?? $item->ingredient->unit ?? '',
                                $recipe->is_active ? '1' : '0',
                            ]);
                        }
                    }
                });

            fclose($handle);
        }, 200, $headers);
    }

    public function importStore(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx',
        ], [
            'file.required' => 'File import wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV, XLS, atau XLSX.',
        ]);

        $uploadedFile = $request->file('file');
        $realPath = $uploadedFile->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return redirect()
                ->route('backoffice.recipes.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return $this->importClientRecipeSpreadsheet($realPath);
        }

        return $this->importLegacyRecipeCsv($realPath);
    }

    protected function importLegacyRecipeCsv(string $realPath)
    {
        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.recipes.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.recipes.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(fn ($value) => trim(strtolower($value)), $header);

        $expectedHeader = [
            'variant_code',
            'ingredient_name',
            'qty',
            'is_active',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.recipes.import')
                ->with('error', 'Header CSV tidak sesuai template. Pastikan urutannya: variant_code,ingredient_name,qty,is_active');
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($lines, $delimiter, &$imported, &$updated, &$skipped, &$errors) {
            foreach (array_slice($lines, 1) as $index => $line) {
                $rowNumber = $index + 2;

                if (trim($line) === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: baris kosong.";
                    continue;
                }

                $row = str_getcsv($line, $delimiter);

                if (count($row) < 4) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: jumlah kolom kurang dari 4.";
                    continue;
                }

                $variantCode = trim($row[0] ?? '');
                $ingredientName = trim($row[1] ?? '');
                $qty = $this->parseRecipeQty($row[2] ?? null);
                $isActiveRaw = trim($row[3] ?? '');

                if ($variantCode === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: variant_code kosong.";
                    continue;
                }

                if ($ingredientName === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient_name kosong.";
                    continue;
                }

                if ($qty === null || $qty <= 0) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty tidak valid.";
                    continue;
                }

                $variant = ProductVariant::with('product')
                    ->whereRaw('LOWER(code) = ?', [mb_strtolower($variantCode)])
                    ->first();

                if (! $variant) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: variant code '{$variantCode}' tidak ditemukan.";
                    continue;
                }

                $ingredient = $this->findIngredientByName($ingredientName);

                if (! $ingredient) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' tidak ditemukan.";
                    continue;
                }

                $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

                $recipe = Recipe::firstOrCreate(
                    ['product_variant_id' => $variant->id],
                    [
                        'product_id' => $variant->product_id,
                        'name' => ($variant->product->name ?? 'Recipe') . ' - ' . $variant->name,
                        'is_active' => $isActive,
                    ]
                );

                if (! $recipe->wasRecentlyCreated && $recipe->is_active !== (bool) $isActive) {
                    $recipe->update([
                        'is_active' => $isActive,
                    ]);
                }

                $recipeItem = RecipeItem::where('recipe_id', $recipe->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->first();

                if ($recipeItem) {
                    $recipeItem->update([
                        'qty' => $qty,
                        'unit' => $ingredient->unit,
                    ]);
                    $updated++;
                    continue;
                }

                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'qty' => $qty,
                    'unit' => $ingredient->unit,
                ]);

                $imported++;
            }
        });

        return redirect()
            ->route('backoffice.recipes.index')
            ->with('success', "Import recipes CSV selesai. Data masuk: {$imported}. Data update: {$updated}. Data dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }

    protected function importClientRecipeSpreadsheet(string $realPath)
    {
        $spreadsheet = IOFactory::load($realPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $currentProductName = null;
        $currentProduct = null;
        $currentVariantName = null;
        $currentVariant = null;

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($rows, &$currentProductName, &$currentProduct, &$currentVariantName, &$currentVariant, &$imported, &$updated, &$skipped, &$errors) {
            foreach ($rows as $rowNumber => $row) {
                $productCell = $this->cleanRecipeCell($row['A'] ?? '');
                $variantCell = $this->cleanRecipeCell($row['B'] ?? '');
                $ingredientName = $this->cleanRecipeCell($row['C'] ?? '');
                $qty = $this->parseRecipeQty($row['D'] ?? null);
                $unitCell = $this->cleanRecipeCell($row['E'] ?? '');

                if ($rowNumber <= 1 && $this->looksLikeRecipeHeader($productCell, $variantCell, $ingredientName)) {
                    continue;
                }

                if ($productCell !== '') {
                    $currentProductName = ltrim($productCell, "* \t\n\r\0\x0B");
                    $currentProduct = $this->findProductByName($currentProductName);
                    $currentVariantName = null;
                    $currentVariant = null;

                    if (! $currentProduct) {
                        $skipped++;
                        $errors[] = "Baris {$rowNumber}: product/menu '{$currentProductName}' tidak ditemukan di master Product.";
                        continue;
                    }
                }

                if ($variantCell !== '') {
                    $currentVariantName = $variantCell;

                    if ($currentProduct) {
                        $currentVariant = $this->findVariantForProduct($currentProduct->id, $currentVariantName);
                    }

                    if (! $currentVariant) {
                        $skipped++;
                        $errors[] = "Baris {$rowNumber}: variant '{$currentVariantName}' untuk product '{$currentProductName}' tidak ditemukan.";
                        continue;
                    }
                }

                if ($ingredientName !== '' && $currentProduct) {
                    $possibleVariant = $this->findVariantForProduct($currentProduct->id, $ingredientName);

                    if ($possibleVariant) {
                        $currentVariantName = $ingredientName;
                        $currentVariant = $possibleVariant;
                        continue;
                    }
                }

                if ($ingredientName === '' && ($qty === null || $qty <= 0)) {
                    continue;
                }

                if (! $currentProduct || ! $currentVariant) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: product/variant belum terbaca. Pastikan kolom A berisi *Nama Menu dan kolom B berisi variant.";
                    continue;
                }

                if ($ingredientName === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient kosong.";
                    continue;
                }

                if ($qty === null || $qty <= 0) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty untuk ingredient '{$ingredientName}' tidak valid.";
                    continue;
                }

                if ($qty > 5000) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty '{$qty}' untuk '{$ingredientName}' terlalu besar. Cek kemungkinan cell Excel salah format.";
                    continue;
                }

                $ingredient = $this->findIngredientByName($ingredientName);

                if (! $ingredient) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' tidak ditemukan di master Ingredient.";
                    continue;
                }

                $recipe = Recipe::firstOrCreate(
                    ['product_variant_id' => $currentVariant->id],
                    [
                        'product_id' => $currentVariant->product_id,
                        'name' => ($currentProduct->name ?? $currentProductName) . ' - ' . $currentVariant->name,
                        'is_active' => true,
                    ]
                );

                if (! $recipe->is_active) {
                    $recipe->update(['is_active' => true]);
                }

                $recipeItem = RecipeItem::where('recipe_id', $recipe->id)
                    ->where('ingredient_id', $ingredient->id)
                    ->first();

                if ($recipeItem) {
                    $recipeItem->update([
                        'qty' => $qty,
                        'unit' => $ingredient->unit ?: $unitCell,
                    ]);
                    $updated++;
                    continue;
                }

                RecipeItem::create([
                    'recipe_id' => $recipe->id,
                    'ingredient_id' => $ingredient->id,
                    'qty' => $qty,
                    'unit' => $ingredient->unit ?: $unitCell,
                ]);

                $imported++;
            }
        });

        return redirect()
            ->route('backoffice.recipes.index')
            ->with('success', "Import recipes Excel client selesai. Data masuk: {$imported}. Data update: {$updated}. Data dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }

    protected function cleanRecipeCell($value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }

    protected function normalizeRecipeName(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }

    protected function parseRecipeQty($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = trim((string) $value);
        $clean = str_replace([' ', ','], ['', '.'], $clean);

        return is_numeric($clean) ? (float) $clean : null;
    }

    protected function looksLikeRecipeHeader(string $productCell, string $variantCell, string $ingredientName): bool
    {
        $joined = $this->normalizeRecipeName($productCell . ' ' . $variantCell . ' ' . $ingredientName);

        return str_contains($joined, 'produk')
            || str_contains($joined, 'product')
            || str_contains($joined, 'ingredient')
            || str_contains($joined, 'bahan');
    }

    protected function findProductByName(string $name): ?Product
    {
        $normalized = $this->normalizeRecipeName($name);

        return Product::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();
    }

    protected function findVariantForProduct(int $productId, string $variantName): ?ProductVariant
    {
        $normalized = $this->normalizeRecipeName($variantName);

        return ProductVariant::with('product')
            ->where('product_id', $productId)
            ->where(function ($query) use ($normalized) {
                $query->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
                    ->orWhereRaw('LOWER(TRIM(code)) = ?', [$normalized]);
            })
            ->first();
    }

    protected function findIngredientByName(string $name): ?Ingredient
    {
        $normalized = $this->normalizeRecipeName($name);

        return Ingredient::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();
    }

}