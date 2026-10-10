<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Services\BackofficeOutletContext;
use App\Services\ProductAccessPolicy;
use App\Services\ProductWorkspace;
use App\Services\RecipeAccessPolicy;
use App\Services\RecipeImportWriter;
use App\Services\RecipeWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecipeViewController extends Controller
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
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Recipes.');
        }

        return $user;
    }

    /**
     * One policy instance per REQUEST (it memoises the user's outlet context). Kept on the request
     * rather than on the controller, because controller instances can outlive a request (tests, Octane).
     */
    protected function recipePolicy(): RecipeAccessPolicy
    {
        $request = request();

        if (! $request->attributes->has('recipe_access_policy')) {
            $request->attributes->set('recipe_access_policy', app(RecipeAccessPolicy::class));
        }

        return $request->attributes->get('recipe_access_policy');
    }

    /**
     * VIEW scope shared by Index, Create dropdown, Edit and export: everything for full-access roles,
     * otherwise the user's accessible outlets (the Back Office has no selectable Active Outlet).
     *
     * @return int[]|null null = unrestricted
     */
    protected function outletScope($user): ?array
    {
        return $this->recipePolicy()->viewScope($user);
    }

    /**
     * A Recipe has no outlet of its own; it belongs to the outlets where its Product + Variant are
     * available. Direct URLs must not bypass a limited user's outlet access.
     */
    protected function authorizeRecipeView(Recipe $recipe, $user): void
    {
        if (! $this->recipePolicy()->canViewRecipe($user, $recipe)) {
            abort(403, 'Recipe ini tidak tersedia pada outlet yang dapat kamu akses.');
        }
    }

    /**
     * Recipes are global per Variant, so a change reaches every outlet that uses the Variant.
     * See RecipeAccessPolicy for the mutation rule; the UI mirrors it but this is the real guard.
     */
    protected function authorizeRecipeMutation(Recipe $recipe, $user): void
    {
        $this->authorizeRecipeView($recipe, $user);

        $message = $this->recipePolicy()->mutationDeniedMessage($user, $recipe->variant);

        if ($message !== null) {
            abort(403, $message);
        }
    }

    /**
     * Why an import row's Variant may not be changed (null = allowed). Same rule as the forms; the
     * row is skipped and reported while the rest of the file keeps being processed.
     */
    protected function importVariantDenial(ProductVariant $variant, $user): ?string
    {
        return $this->recipePolicy()->mutationDeniedMessage($user, $variant);
    }

    protected function assertVariantMutable(ProductVariant $variant, $user): void
    {
        if ($message = $this->recipePolicy()->mutationDeniedMessage($user, $variant)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['product_variant_id' => $message]);
        }
    }

    /** Recipe writes and their rules (shared with the Product Workspace). */
    protected function writer(): RecipeWriter
    {
        return app(RecipeWriter::class);
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $recipes = Recipe::with([
            'variant.product.outlets',
            'variant.outlets',
            'items.ingredient.category',
        ])
            ->inOutletScope($this->outletScope($user))
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
                    $q->where('name', 'like', '%'.$keyword.'%')
                        ->orWhereHas('variant', function ($variantQuery) use ($keyword) {
                            $variantQuery->where('name', 'like', '%'.$keyword.'%')
                                ->orWhere('code', 'like', '%'.$keyword.'%')
                                ->orWhereHas('product', function ($productQuery) use ($keyword) {
                                    $productQuery->where('name', 'like', '%'.$keyword.'%');
                                });
                        });
                });
            })
            ->latest()
            ->get();

        $mutableRecipeIds = $recipes
            ->filter(fn (Recipe $recipe) => $this->recipePolicy()->canMutateVariantRecipe($user, $recipe->variant))
            ->pluck('id')
            ->all();

        return view('backoffice.recipes.index', [
            'user' => $user,
            'recipes' => $recipes,
            'mutableRecipeIds' => $mutableRecipeIds,
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

        // Only Variants this user may create a Recipe for in the current context (global Recipe rule),
        // and never those of a deleted Product (the product() relation is withTrashed).
        $variants = ProductVariant::with(['product.outlets', 'outlets', 'recipe'])
            ->inOutletScope($this->outletScope($user))
            ->whereHas('product', fn ($query) => $query->whereNull('products.deleted_at'))
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductVariant $variant) => $this->recipePolicy()->canMutateVariantRecipe($user, $variant))
            ->values();

        return view('backoffice.recipes.create', [
            'user' => $user,
            'variants' => $variants,
            'menuOptions' => $this->menuOptions($variants),
        ]);
    }

    /**
     * Menu -> Variant options for the create form, built only from the Variants already filtered by
     * scope + mutation rule. The auto-generated name comes from RecipeWriter::defaultName() so the
     * preview and the stored name can never use two different rules.
     *
     * A Variant that already has a Recipe is listed (so the form can explain it and link to it) but
     * flagged `existing`; the form does not let it be picked and store() rejects it anyway. Every
     * listed Variant is inside the user's view scope, which is exactly the rule for viewing its Recipe.
     */
    protected function menuOptions($variants): array
    {
        return $variants
            ->groupBy('product_id')
            ->map(fn ($group) => [
                'id' => $group->first()->product_id,
                'name' => $group->first()->product->name,
                'variants' => $group->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'recipe_name' => RecipeWriter::defaultName($variant),
                    'existing' => $variant->recipe ? [
                        'name' => $variant->recipe->name,
                        'url' => route('backoffice.recipes.edit', $variant->recipe),
                    ] : null,
                ])->values()->all(),
            ])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        // `name` is deliberately not accepted: the stored name is always derived from the Variant below.
        // `product_id` is optional so older callers that only send the Variant keep working; when
        // present it must be the Variant's own Product.
        $validated = $request->validate([
            'product_id' => 'nullable|integer|exists:products,id,deleted_at,NULL',
            'product_variant_id' => 'required|integer|exists:product_variants,id,deleted_at,NULL|unique:recipes,product_variant_id',
            'is_active' => 'required|boolean',
        ], [
            'product_id.exists' => 'Menu / Product tidak ditemukan atau sudah dihapus.',
            'product_variant_id.required' => 'Pilih Variant / Size terlebih dahulu.',
            'product_variant_id.exists' => 'Variant tidak ditemukan atau sudah dihapus.',
            'product_variant_id.unique' => 'Variant ini sudah memiliki Recipe. Buka Recipe yang ada, jangan buat yang baru.',
        ]);

        $variant = ProductVariant::with('product')->findOrFail($validated['product_variant_id']);

        if (! empty($validated['product_id']) && (int) $validated['product_id'] !== (int) $variant->product_id) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_variant_id' => 'Variant tidak sesuai dengan Menu / Product yang dipilih.',
            ]);
        }

        if (! $variant->product || $variant->product->trashed()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'product_id' => 'Menu / Product tidak ditemukan atau sudah dihapus.',
            ]);
        }

        $this->assertVariantMutable($variant, $user);

        $recipe = $this->writer()->create($variant, RecipeWriter::defaultName($variant), (bool) $validated['is_active']);

        return BackofficeReturnUrl::redirect($request, 'backoffice.recipes.index', [], 'recipe-'.$recipe->id)
            ->with('success', 'Recipe berhasil ditambahkan.');
    }

    public function edit(Request $request, Recipe $recipe)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);
        $scope = $this->outletScope($user);

        $this->authorizeRecipeView($recipe, $user);

        // The standalone Recipe editor (this page) is the normal place to manage a Recipe: the Recipe tab is not part
        // of the Product Workspace rail any more, so Edit stays here. Who may open or change what is unchanged:
        // authorizeRecipeView() above, authorizeRecipeMutation()/RecipeAccessPolicy on every write, and a Recipe whose
        // ownership is inconsistent is still never opened for the Product roles (safe message, nothing written).
        // (Old Workspace links such as ?section=recipe&recipe=ID keep rendering; nothing sends users there from here.)
        $recipe->load(['variant.product']);

        if (ProductAccessPolicy::hasProductRole($user)) {
            if ($problem = app(ProductWorkspace::class)->recipeOwnershipProblem($recipe)) {
                return BackofficeReturnUrl::redirect($request, 'backoffice.recipes.index', [], 'recipe-'.$recipe->id)->with('error', $problem);
            }
        }

        $recipe->load([
            'items.ingredient.category',
            'items.ingredient.outlets:id',
            'variant.product.outlets',
            'variant.outlets',
        ]);

        $mutation = $this->recipePolicy()->mutationStatus($user, $recipe->variant);
        $canMutate = $mutation['allowed'];

        // The Recipe's own Variant is always offered, so the outlet scope can never make the
        // select fall back to another Variant and silently re-point the Recipe on save. Other Variants
        // are only offered when moving the Recipe to them would be allowed (read-only: none).
        $variants = ProductVariant::with(['product.outlets', 'outlets'])
            ->where(function ($query) use ($scope, $recipe, $canMutate) {
                $query->where('product_variants.id', $recipe->product_variant_id);

                if ($canMutate) {
                    $scope === null
                        ? $query->orWhereRaw('1 = 1')
                        : $query->orWhere(fn ($inner) => $inner->inOutletScope($scope));
                }
            })
            ->orderBy('name')
            ->get()
            ->filter(fn (ProductVariant $variant) => (int) $variant->id === (int) $recipe->product_variant_id
                || $this->recipePolicy()->canMutateVariantRecipe($user, $variant))
            ->values();

        // Same rule as storeItem(): active, not already in the Recipe, and available in every SELLABLE outlet
        // of the Recipe's Variant. Ingredients that fail it stay visible (disabled) with the reason.
        $requiredOutletIds = $this->writer()->requiredOutletIds($recipe->variant);
        $requiredNames = $this->writer()->outletNames($requiredOutletIds);
        $choices = $this->writer()->ingredientChoices($recipe->variant, $recipe->items->pluck('ingredient_id'));

        // Stored items are never changed here; invalid ones are only flagged (read-only).
        $itemWarnings = $recipe->items->mapWithKeys(function (RecipeItem $item) use ($requiredOutletIds, $requiredNames) {
            if (! $item->ingredient) {
                return [$item->id => ['Ingredient tidak ditemukan.']];
            }

            $reason = RecipeWriter::availabilityReason($this->writer()->ingredientAvailability($item->ingredient, $requiredOutletIds, $requiredNames));

            return [$item->id => $reason ? [ucfirst($reason).'. '.RecipeWriter::FIX_GUIDANCE] : []];
        });

        return view('backoffice.recipes.edit', [
            'user' => $user,
            'recipe' => $recipe,
            'variants' => $variants,
            'ingredients' => $choices['eligible'],
            'unavailableIngredients' => $choices['unavailable'],
            'itemWarnings' => $itemWarnings,
            'canMutate' => $canMutate,
            'mutationNotice' => $mutation['message'],
            'variantOutlets' => $requiredNames->values(),
        ]);
    }

    public function update(Request $request, Recipe $recipe)
    {
        $user = $this->authorizeAccess();
        $this->authorizeRecipeMutation($recipe, $user);

        $validated = $request->validate([
            'product_variant_id' => 'required|exists:product_variants,id,deleted_at,NULL|unique:recipes,product_variant_id,'.$recipe->id,
            'name' => 'required|string|max:255',
            'is_active' => 'required|boolean',
        ]);

        $variant = ProductVariant::findOrFail($validated['product_variant_id']);

        // Moving the Recipe also changes which outlets the target Variant can sell with it, so the
        // target must pass the mutation rule too. Keeping the current Variant is already authorised.
        if ((int) $variant->id !== (int) $recipe->product_variant_id) {
            $this->assertVariantMutable($variant, $user);
        }

        $this->writer()->updateHeader($recipe, $variant, $validated['name'], (bool) $validated['is_active']);

        return BackofficeReturnUrl::redirect($request, 'backoffice.recipes.index', [], 'recipe-'.$recipe->id)
            ->with('success', 'Recipe berhasil diperbarui. Status terbaru sudah diterapkan ke Cashier.');
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        $user = $this->authorizeAccess();
        $this->authorizeRecipeMutation($recipe, $user);

        $this->writer()->deactivate($recipe);

        // Inactivating keeps the recipe listed, so it can stay the anchor - unless the list is filtered
        // to active recipes, where it just left: then no anchor (the saved scroll offset is used).
        $anchor = BackofficeReturnUrl::returnQueryParam($request, 'status') === 'active' ? null : 'recipe-'.$recipe->id;

        return BackofficeReturnUrl::redirect($request, 'backoffice.recipes.index', [], $anchor)
            ->with('success', 'Recipe berhasil dinonaktifkan.');
    }

    public function storeItem(Request $request, Recipe $recipe)
    {
        $user = $this->authorizeAccess();
        $this->authorizeRecipeMutation($recipe, $user);

        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id,deleted_at,NULL',
            'qty' => RecipeWriter::qtyRule(),
        ]);

        if ($this->writer()->containsIngredient($recipe, (int) $validated['ingredient_id'])) {
            return redirect(BackofficeReturnUrl::routeWithReturn($request, 'backoffice.recipes.edit', [$recipe->id], 'recipe-add-item'))
                ->with('error', RecipeWriter::DUPLICATE_ITEM_MESSAGE);
        }

        $item = $this->writer()->addItem($recipe, Ingredient::findOrFail($validated['ingredient_id']), $validated['qty']);

        // Stay on this Recipe's edit page (and keep its way back to the list), at the new row.
        return redirect(BackofficeReturnUrl::routeWithReturn($request, 'backoffice.recipes.edit', [$recipe->id], 'recipe-item-'.$item->id))
            ->with('success', 'Bahan Recipe berhasil ditambahkan.');
    }

    public function updateItem(Request $request, Recipe $recipe, RecipeItem $item)
    {
        $user = $this->authorizeAccess();
        $this->authorizeRecipeMutation($recipe, $user);

        if ((int) $item->recipe_id !== (int) $recipe->id) {
            abort(404);
        }

        $validated = $request->validate([
            'qty' => RecipeWriter::qtyRule(),
        ]);

        $this->writer()->updateItemQty($item, $validated['qty']);

        return redirect(BackofficeReturnUrl::routeWithReturn($request, 'backoffice.recipes.edit', [$recipe->id], 'recipe-item-'.$item->id))
            ->with('success', 'Jumlah bahan berhasil diperbarui.');
    }

    public function destroyItem(Request $request, Recipe $recipe, RecipeItem $item)
    {
        $user = $this->authorizeAccess();
        $this->authorizeRecipeMutation($recipe, $user);

        if ((int) $item->recipe_id !== (int) $recipe->id) {
            abort(404);
        }

        $ingredientName = $item->ingredient?->name ?? 'Item';
        $this->writer()->removeItem($item);

        // The row is gone; land on the items list instead.
        return redirect(BackofficeReturnUrl::routeWithReturn($request, 'backoffice.recipes.edit', [$recipe->id], 'recipe-items'))
            ->with('success', 'Bahan Recipe "'.$ingredientName.'" berhasil dihapus.');
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
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
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
        $user = $this->authorizeAccess();
        // Same outlet scope as the Recipe Index.
        $scope = $this->outletScope($user);

        $filename = 'pos_recipe_master_'.now()->format('Ymd_His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->stream(function () use ($scope) {

            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'no',
                'product_code',
                'product_name',
                'variant_code',
                'variant_name',
                'ingredient_code',
                'ingredient_name',
                'qty',
                'unit',
            ]);

            $no = 1;

            Recipe::with([
                'variant.product',
                'items.ingredient',
            ])
                ->inOutletScope($scope)
                ->orderBy('name')
                ->chunk(200, function ($recipes) use ($handle, &$no) {

                    foreach ($recipes as $recipe) {

                        foreach ($recipe->items as $item) {

                            fputcsv($handle, [
                                $no++,
                                $recipe->variant?->product?->code ?? '',
                                $recipe->variant?->product?->name ?? '',
                                $recipe->variant?->code ?? '',
                                $recipe->variant?->name ?? '',
                                $item->ingredient?->code ?? '',
                                $item->ingredient?->name ?? '',
                                (float) $item->qty,
                                $item->unit ?? $item->ingredient?->unit ?? '',
                            ]);

                        }

                    }

                });

            fclose($handle);

        }, 200, $headers);
    }

    public function importStore(Request $request)
    {
        $user = $this->authorizeAccess();

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
            return $this->importClientRecipeSpreadsheet($realPath, $user);
        }

        return $this->importLegacyRecipeCsv($realPath, $user);
    }

    protected function importLegacyRecipeCsv(string $realPath, $user)
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

        // Staged import (see RecipeImportWriter): rows are validated against the Recipe Editor rules while
        // reading, nothing is written until the end, and a Variant with ANY rejected row is not touched at all.
        $import = new RecipeImportWriter($this->writer(), $this->recipePolicy(), $user);

        foreach (array_slice($lines, 1) as $index => $line) {
            $rowNumber = $index + 2;

            if (trim($line) === '') {
                $import->skipRow("Baris {$rowNumber}: baris kosong.");

                continue;
            }

            $row = str_getcsv($line, $delimiter);

            if (count($row) < 4) {
                // A malformed row of a Variant we can still identify makes that Variant's Recipe incomplete,
                // so the whole Variant is rejected (all-or-nothing). Only an unidentifiable row is just skipped.
                $partialCode = trim($row[0] ?? '');
                $partialVariant = $partialCode === '' ? null : ProductVariant::with('product')
                    ->whereRaw('LOWER(code) = ?', [mb_strtolower($partialCode)])
                    ->first();

                if ($partialVariant) {
                    $import->rejectRow($partialVariant, $rowNumber, 'jumlah kolom kurang dari 4.', "'{$partialCode}'");
                } else {
                    $import->skipRow("Baris {$rowNumber}: jumlah kolom kurang dari 4.");
                }

                continue;
            }

            $variantCode = trim($row[0] ?? '');
            $ingredientName = trim($row[1] ?? '');
            $qty = $this->parseRecipeQty($row[2] ?? null);
            $isActiveRaw = trim($row[3] ?? '');

            if ($variantCode === '') {
                $import->skipRow("Baris {$rowNumber}: variant_code kosong.");

                continue;
            }

            $variant = ProductVariant::with('product')
                ->whereRaw('LOWER(code) = ?', [mb_strtolower($variantCode)])
                ->first();

            if (! $variant) {
                $import->skipRow("Baris {$rowNumber}: variant code '{$variantCode}' tidak ditemukan.");

                continue;
            }

            $label = "'{$variantCode}'";

            if ($ingredientName === '') {
                $import->rejectRow($variant, $rowNumber, 'ingredient_name kosong.', $label);

                continue;
            }

            $import->stage(
                $variant,
                $rowNumber,
                $label,
                $this->findIngredientByName($ingredientName),
                $ingredientName,
                $qty,
                in_array($isActiveRaw, ['1', 'true', 'TRUE', 'yes', 'YES'], true)
            );
        }

        return $this->finishRecipeImport($import->apply(), 'CSV');
    }

    protected function importClientRecipeSpreadsheet(string $realPath, $user)
    {
        $spreadsheet = IOFactory::load($realPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $currentProductName = null;
        $currentProduct = null;
        $currentVariantName = null;
        $currentVariant = null;

        $import = new RecipeImportWriter($this->writer(), $this->recipePolicy(), $user);

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
                    $import->skipRow("Baris {$rowNumber}: product/menu '{$currentProductName}' tidak ditemukan di master Product.");

                    continue;
                }
            }

            if ($variantCell !== '') {
                $currentVariantName = $variantCell;

                if ($currentProduct) {
                    $currentVariant = $this->findVariantForProduct($currentProduct->id, $currentVariantName);
                }

                if (! $currentVariant) {
                    $import->skipRow("Baris {$rowNumber}: variant '{$currentVariantName}' untuk product '{$currentProductName}' tidak ditemukan.");

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
                $import->skipRow("Baris {$rowNumber}: product/variant belum terbaca. Pastikan kolom A berisi *Nama Menu dan kolom B berisi variant.");

                continue;
            }

            $label = "'{$currentVariant->name}' untuk product '{$currentProductName}' (semua baris variant ini)";

            if ($ingredientName === '') {
                $import->rejectRow($currentVariant, $rowNumber, 'ingredient kosong.', $label);

                continue;
            }

            if ($qty !== null && $qty > 5000) {
                $import->rejectRow($currentVariant, $rowNumber, "qty '{$qty}' untuk '{$ingredientName}' terlalu besar. Cek kemungkinan cell Excel salah format.", $label);

                continue;
            }

            // The sheet has no active column. Activation is only REQUESTED here and still has to pass
            // RecipeWriter::activationProblems(); it is never forced.
            $import->stage($currentVariant, $rowNumber, $label, $this->findIngredientByName($ingredientName), $ingredientName, $qty, true, $unitCell);
        }

        return $this->finishRecipeImport($import->apply(), 'Excel client');
    }

    protected function finishRecipeImport(array $result, string $kind)
    {
        $notActivated = $result['not_activated'] > 0 ? " Recipe tidak diaktifkan (belum memenuhi syarat): {$result['not_activated']}." : '';

        return redirect()
            ->route('backoffice.recipes.index')
            ->with('success', "Import recipes {$kind} selesai. Data masuk: {$result['imported']}. Data update: {$result['updated']}. Data dilewati: {$result['skipped']}.{$notActivated}")
            ->with('import_errors', $result['errors']);
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
        $joined = $this->normalizeRecipeName($productCell.' '.$variantCell.' '.$ingredientName);

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
            ->with('outlets:id')
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();
    }
}
