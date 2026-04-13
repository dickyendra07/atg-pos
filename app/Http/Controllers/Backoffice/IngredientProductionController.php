<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientProduction;
use App\Models\IngredientProductionRecipe;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IngredientProductionController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke halaman Productions.');
        }

        return $user;
    }

    protected function getLocationName(string $type, int $id): string
    {
        if ($type === 'warehouse') {
            return Warehouse::find($id)?->name ?? ('Warehouse #' . $id);
        }

        if ($type === 'outlet') {
            return Outlet::find($id)?->name ?? ('Outlet #' . $id);
        }

        return '-';
    }

    protected function validateLocation(string $locationType, int $locationId): void
    {
        if ($locationType === 'warehouse') {
            $exists = Warehouse::query()
                ->where('is_active', true)
                ->whereKey($locationId)
                ->exists();

            if (! $exists) {
                abort(422, 'Warehouse produksi tidak ditemukan atau tidak aktif.');
            }

            return;
        }

        if ($locationType === 'outlet') {
            $exists = Outlet::query()
                ->where('is_active', true)
                ->whereKey($locationId)
                ->exists();

            if (! $exists) {
                abort(422, 'Outlet produksi tidak ditemukan atau tidak aktif.');
            }

            return;
        }

        abort(422, 'Location type produksi tidak valid.');
    }

    public function index()
    {
        $user = $this->authorizeAccess();

        $productions = IngredientProduction::with([
            'recipe.outputIngredient.category',
            'outputIngredient.category',
            'producedBy',
        ])->latest()->get()->map(function ($production) {
            $production->location_name = $this->getLocationName(
                (string) $production->location_type,
                (int) $production->location_id
            );

            return $production;
        });

        return view('backoffice.productions.index', [
            'user' => $user,
            'productions' => $productions,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        $recipes = IngredientProductionRecipe::with([
            'outputIngredient.category',
            'items.inputIngredient.category',
        ])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('backoffice.productions.create', [
            'user' => $user,
            'recipes' => $recipes,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->authorizeAccess();

        $validated = $request->validate([
            'ingredient_production_recipe_id' => 'required|exists:ingredient_production_recipes,id',
            'location_type' => 'required|in:warehouse,outlet',
            'location_id' => 'required|integer|min:1',
            'batch_qty' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        $this->validateLocation($validated['location_type'], (int) $validated['location_id']);

        $recipe = IngredientProductionRecipe::with([
            'outputIngredient.category',
            'items.inputIngredient.category',
        ])->findOrFail($validated['ingredient_production_recipe_id']);

        if (! $recipe->is_active) {
            return back()
                ->withErrors([
                    'ingredient_production_recipe_id' => 'Production recipe sedang tidak aktif.',
                ])
                ->withInput();
        }

        if (! $recipe->outputIngredient || ! $recipe->outputIngredient->isSemiFinished()) {
            return back()
                ->withErrors([
                    'ingredient_production_recipe_id' => 'Output recipe harus ingredient setengah jadi.',
                ])
                ->withInput();
        }

        if ($recipe->items->isEmpty()) {
            return back()
                ->withErrors([
                    'ingredient_production_recipe_id' => 'Recipe ini belum punya bahan input.',
                ])
                ->withInput();
        }

        foreach ($recipe->items as $item) {
            if (! $item->inputIngredient || ! $item->inputIngredient->isRaw()) {
                return back()
                    ->withErrors([
                        'ingredient_production_recipe_id' => 'Batch 3 hanya menerima bahan input bertipe mentah.',
                    ])
                    ->withInput();
            }
        }

        $batchQty = (float) $validated['batch_qty'];
        $outputQty = (float) $recipe->output_qty * $batchQty;

        try {
            $production = DB::transaction(function () use ($validated, $recipe, $user, $batchQty, $outputQty) {
                $inputIngredientIds = $recipe->items
                    ->pluck('input_ingredient_id')
                    ->unique()
                    ->values();

                $lockedInputStocks = StockBalance::where('location_type', $validated['location_type'])
                    ->where('location_id', $validated['location_id'])
                    ->whereIn('ingredient_id', $inputIngredientIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('ingredient_id');

                foreach ($recipe->items as $recipeItem) {
                    $ingredient = $recipeItem->inputIngredient;
                    $requiredQty = (float) $recipeItem->qty * $batchQty;
                    $stockBalance = $lockedInputStocks->get($ingredient->id);

                    if (! $stockBalance) {
                        throw new \RuntimeException(
                            'Stok bahan "' . $ingredient->name . '" belum tersedia di lokasi produksi.'
                        );
                    }

                    $currentQty = (float) $stockBalance->qty_on_hand;

                    if ($requiredQty > $currentQty) {
                        throw new \RuntimeException(
                            'Stok bahan "' . $ingredient->name . '" hanya ' . number_format($currentQty, 2, ',', '.') .
                            ' ' . $ingredient->unit . ', tidak cukup untuk kebutuhan produksi ' .
                            number_format($requiredQty, 2, ',', '.') . ' ' . $ingredient->unit . '.'
                        );
                    }
                }

                $outputStock = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $recipe->output_ingredient_id,
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $outputStock = StockBalance::whereKey($outputStock->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $production = IngredientProduction::create([
                    'ingredient_production_recipe_id' => $recipe->id,
                    'output_ingredient_id' => $recipe->output_ingredient_id,
                    'location_type' => $validated['location_type'],
                    'location_id' => $validated['location_id'],
                    'batch_qty' => $batchQty,
                    'output_qty' => $outputQty,
                    'output_unit' => $recipe->output_unit,
                    'status' => 'completed',
                    'note' => $validated['note'] ?? null,
                    'produced_by_user_id' => $user->id,
                    'produced_at' => now(),
                ]);

                foreach ($recipe->items as $recipeItem) {
                    $ingredient = $recipeItem->inputIngredient;
                    $requiredQty = (float) $recipeItem->qty * $batchQty;

                    $stockBalance = $lockedInputStocks->get($ingredient->id);
                    $currentQty = (float) $stockBalance->qty_on_hand;

                    $stockBalance->update([
                        'qty_on_hand' => $currentQty - $requiredQty,
                    ]);

                    $production->items()->create([
                        'ingredient_id' => $ingredient->id,
                        'item_type' => 'input',
                        'qty' => $requiredQty,
                        'unit' => $recipeItem->unit,
                    ]);

                    StockMovement::create([
                        'ingredient_id' => $ingredient->id,
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                        'movement_type' => 'production_out',
                        'qty_in' => 0,
                        'qty_out' => $requiredQty,
                        'reference_type' => 'ingredient_production',
                        'reference_id' => $production->id,
                        'note' => 'Produksi #' . $production->id . ' - pemakaian bahan untuk ' . ($recipe->name ?? 'production recipe'),
                    ]);
                }

                $outputStock->update([
                    'qty_on_hand' => (float) $outputStock->qty_on_hand + $outputQty,
                ]);

                $production->items()->create([
                    'ingredient_id' => $recipe->output_ingredient_id,
                    'item_type' => 'output',
                    'qty' => $outputQty,
                    'unit' => $recipe->output_unit,
                ]);

                StockMovement::create([
                    'ingredient_id' => $recipe->output_ingredient_id,
                    'location_type' => $validated['location_type'],
                    'location_id' => $validated['location_id'],
                    'movement_type' => 'production_in',
                    'qty_in' => $outputQty,
                    'qty_out' => 0,
                    'reference_type' => 'ingredient_production',
                    'reference_id' => $production->id,
                    'note' => 'Produksi #' . $production->id . ' - hasil produksi dari ' . ($recipe->name ?? 'production recipe'),
                ]);

                return $production;
            });
        } catch (\RuntimeException $e) {
            return back()
                ->withErrors([
                    'ingredient_production_recipe_id' => $e->getMessage(),
                ])
                ->withInput();
        }

        return redirect()
            ->route('backoffice.productions.show', $production->id)
            ->with('success', 'Produksi berhasil disimpan dan stok sudah diperbarui.');
    }

    public function show(IngredientProduction $production)
    {
        $user = $this->authorizeAccess();

        $production->load([
            'recipe.outputIngredient.category',
            'outputIngredient.category',
            'producedBy',
            'items.ingredient.category',
        ]);

        $production->location_name = $this->getLocationName(
            (string) $production->location_type,
            (int) $production->location_id
        );

        return view('backoffice.productions.show', [
            'user' => $user,
            'production' => $production,
        ]);
    }
}