<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles, true)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Ingredients.');
        }

        return $user;
    }

    protected function makeIngredientCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'INGREDIENT';
        }

        $code = $base;
        $counter = 1;

        while (Ingredient::where('code', $code)->exists()) {
            $code = $base . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    protected function makeCategoryCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'CATEGORY';
        }

        $code = $base;
        $counter = 1;

        while (IngredientCategory::where('code', $code)->exists()) {
            $code = $base . '_' . $counter;
            $counter++;
        }

        return $code;
    }

    protected function ingredientTypeOptions(): array
    {
        return Ingredient::ingredientTypeOptions();
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $ingredientsQuery = Ingredient::with('category')->latest();

        if ($request->filled('ingredient_type')) {
            $ingredientsQuery->where('ingredient_type', $request->ingredient_type);
        }

        $ingredients = $ingredientsQuery->get();

        return view('backoffice.ingredients.index', [
            'user' => $user,
            'ingredients' => $ingredients,
            'ingredientTypeOptions' => $this->ingredientTypeOptions(),
            'selectedIngredientType' => $request->input('ingredient_type', ''),
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        $categories = IngredientCategory::orderBy('name')->get();

        return view('backoffice.ingredients.create', [
            'user' => $user,
            'categories' => $categories,
            'ingredientTypeOptions' => $this->ingredientTypeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'ingredient_category_id' => 'required|exists:ingredient_categories,id',
            'name' => 'required|string|max:255|unique:ingredients,name',
            'unit' => 'required|string|max:50',
            'ingredient_type' => 'required|in:' . implode(',', array_keys($this->ingredientTypeOptions())),
            'minimum_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        Ingredient::create([
            'ingredient_category_id' => $validated['ingredient_category_id'],
            'code' => $this->makeIngredientCode($validated['name']),
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'ingredient_type' => $validated['ingredient_type'],
            'minimum_stock' => $validated['minimum_stock'],
            'cost_per_unit' => $validated['cost_per_unit'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.ingredients.index')
            ->with('success', 'Ingredient baru berhasil ditambahkan.');
    }

    public function edit(Ingredient $ingredient)
    {
        $user = $this->authorizeAccess();

        $categories = IngredientCategory::orderBy('name')->get();

        return view('backoffice.ingredients.edit', [
            'user' => $user,
            'ingredient' => $ingredient,
            'categories' => $categories,
            'ingredientTypeOptions' => $this->ingredientTypeOptions(),
        ]);
    }

    public function update(Request $request, Ingredient $ingredient)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'ingredient_category_id' => 'required|exists:ingredient_categories,id',
            'name' => 'required|string|max:255|unique:ingredients,name,' . $ingredient->id,
            'unit' => 'required|string|max:50',
            'ingredient_type' => 'required|in:' . implode(',', array_keys($this->ingredientTypeOptions())),
            'minimum_stock' => 'required|numeric|min:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $newCode = $ingredient->code;

        if ($ingredient->name !== $validated['name']) {
            $newCode = $this->makeIngredientCode($validated['name']);
        }

        $ingredient->update([
            'ingredient_category_id' => $validated['ingredient_category_id'],
            'code' => $newCode,
            'name' => $validated['name'],
            'unit' => $validated['unit'],
            'ingredient_type' => $validated['ingredient_type'],
            'minimum_stock' => $validated['minimum_stock'],
            'cost_per_unit' => $validated['cost_per_unit'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.ingredients.index')
            ->with('success', 'Ingredient berhasil diupdate.');
    }

    public function destroy(Ingredient $ingredient)
    {
        $this->authorizeAccess();

        $ingredient->delete();

        return redirect()
            ->route('backoffice.ingredients.index')
            ->with('success', 'Ingredient berhasil dihapus.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();

        return view('backoffice.ingredients.import', [
            'user' => $user,
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_ingredients_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['name', 'category_name', 'unit', 'ingredient_type', 'minimum_stock', 'cost_per_unit', 'is_active']);
            fputcsv($handle, ['Fresh Milk', 'Milk', 'ml', 'raw', '2000', '18000', '1']);
            fputcsv($handle, ['Liquid Sugar', 'Sweetener', 'ml', 'raw', '1000', '12000', '1']);
            fputcsv($handle, ['Adonan Waffle', 'Waffle Base', 'gram', 'semi_finished', '500', '800', '1']);

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
                ->route('backoffice.ingredients.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.ingredients.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.ingredients.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(function ($value) {
            return trim(strtolower($value));
        }, $header);

        $expectedHeader = [
            'name',
            'category_name',
            'unit',
            'ingredient_type',
            'minimum_stock',
            'cost_per_unit',
            'is_active',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.ingredients.import')
                ->with('error', 'Header CSV tidak sesuai template. Pastikan urutannya: name,category_name,unit,ingredient_type,minimum_stock,cost_per_unit,is_active');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($lines, $delimiter, &$imported, &$skipped, &$errors) {
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

                $name = trim($row[0] ?? '');
                $categoryName = trim($row[1] ?? '');
                $unit = trim($row[2] ?? '');
                $ingredientType = trim(strtolower($row[3] ?? 'raw'));
                $minimumStockRaw = trim($row[4] ?? '');
                $costPerUnitRaw = trim($row[5] ?? '');
                $isActiveRaw = trim($row[6] ?? '');

                if ($name === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: name kosong.";
                    continue;
                }

                if ($categoryName === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: category_name kosong.";
                    continue;
                }

                if ($unit === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: unit kosong.";
                    continue;
                }

                if (! in_array($ingredientType, [Ingredient::TYPE_RAW, Ingredient::TYPE_SEMI_FINISHED], true)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient_type harus raw atau semi_finished.";
                    continue;
                }

                if (! is_numeric($minimumStockRaw)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: minimum_stock tidak valid.";
                    continue;
                }

                if (! is_numeric($costPerUnitRaw)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: cost_per_unit tidak valid.";
                    continue;
                }

                $minimumStock = (float) $minimumStockRaw;
                $costPerUnit = (float) $costPerUnitRaw;
                $isActive = in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;

                $alreadyExists = Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->exists();

                if ($alreadyExists) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$name}' sudah ada.";
                    continue;
                }

                $category = IngredientCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();

                if (! $category) {
                    $category = IngredientCategory::create([
                        'code' => $this->makeCategoryCode($categoryName),
                        'name' => $categoryName,
                        'is_active' => true,
                    ]);
                }

                Ingredient::create([
                    'ingredient_category_id' => $category->id,
                    'code' => $this->makeIngredientCode($name),
                    'name' => $name,
                    'unit' => $unit,
                    'ingredient_type' => $ingredientType,
                    'minimum_stock' => $minimumStock,
                    'cost_per_unit' => $costPerUnit,
                    'is_active' => $isActive,
                ]);

                $imported++;
            }
        });

        return redirect()
            ->route('backoffice.ingredients.index')
            ->with('success', "Import ingredients selesai. Data masuk: {$imported}. Data dilewati: {$skipped}.")
            ->with('import_errors', $errors);
    }
}