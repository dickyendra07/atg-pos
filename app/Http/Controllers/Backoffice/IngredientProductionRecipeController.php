<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientProductionRecipe;
use App\Models\IngredientProductionRecipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class IngredientProductionRecipeController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke halaman Production Recipes.');
        }

        return $user;
    }

    protected function getOutputIngredients()
    {
        return Ingredient::with('category')
            ->where('ingredient_type', Ingredient::TYPE_SEMI_FINISHED)
            ->orderBy('name')
            ->get();
    }

    protected function getInputIngredients()
    {
        return Ingredient::with('category')
            ->where('ingredient_type', Ingredient::TYPE_RAW)
            ->orderBy('name')
            ->get();
    }

    public function index()
    {
        $user = $this->authorizeAccess();

        $recipes = IngredientProductionRecipe::with([
            'outputIngredient.category',
            'items.inputIngredient.category',
        ])->latest()->get();

        return view('backoffice.production-recipes.index', [
            'user' => $user,
            'recipes' => $recipes,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        return view('backoffice.production-recipes.create', [
            'user' => $user,
            'outputIngredients' => $this->getOutputIngredients(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'output_ingredient_id' => [
                'required',
                'exists:ingredients,id',
                'unique:ingredient_production_recipes,output_ingredient_id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'output_qty' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['required', 'boolean'],
        ]);

        $outputIngredient = Ingredient::findOrFail($validated['output_ingredient_id']);

        if (! $outputIngredient->isSemiFinished()) {
            return back()
                ->withErrors([
                    'output_ingredient_id' => 'Output ingredient harus bertipe Setengah Jadi.',
                ])
                ->withInput();
        }

        $recipe = IngredientProductionRecipe::create([
            'output_ingredient_id' => $outputIngredient->id,
            'name' => $validated['name'],
            'output_qty' => $validated['output_qty'],
            'output_unit' => $outputIngredient->unit,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.production-recipes.edit', $recipe->id)
            ->with('success', 'Production recipe baru berhasil ditambahkan.');
    }

    public function edit(IngredientProductionRecipe $productionRecipe)
    {
        $user = $this->authorizeAccess();

        $productionRecipe->load([
            'outputIngredient.category',
            'items.inputIngredient.category',
        ]);

        return view('backoffice.production-recipes.edit', [
            'user' => $user,
            'productionRecipe' => $productionRecipe,
            'outputIngredients' => $this->getOutputIngredients(),
            'inputIngredients' => $this->getInputIngredients(),
        ]);
    }

    public function update(Request $request, IngredientProductionRecipe $productionRecipe)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'output_ingredient_id' => [
                'required',
                'exists:ingredients,id',
                Rule::unique('ingredient_production_recipes', 'output_ingredient_id')->ignore($productionRecipe->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'output_qty' => ['required', 'numeric', 'min:0.01'],
            'is_active' => ['required', 'boolean'],
        ]);

        $outputIngredient = Ingredient::findOrFail($validated['output_ingredient_id']);

        if (! $outputIngredient->isSemiFinished()) {
            return back()
                ->withErrors([
                    'output_ingredient_id' => 'Output ingredient harus bertipe Setengah Jadi.',
                ])
                ->withInput();
        }

        $productionRecipe->update([
            'output_ingredient_id' => $outputIngredient->id,
            'name' => $validated['name'],
            'output_qty' => $validated['output_qty'],
            'output_unit' => $outputIngredient->unit,
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.production-recipes.edit', $productionRecipe->id)
            ->with('success', 'Header production recipe berhasil diupdate.');
    }

    public function storeItem(Request $request, IngredientProductionRecipe $productionRecipe)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'input_ingredient_id' => ['required', 'exists:ingredients,id'],
            'qty' => ['required', 'numeric', 'min:0.01'],
        ]);

        $inputIngredient = Ingredient::findOrFail($validated['input_ingredient_id']);

        if (! $inputIngredient->isRaw()) {
            return redirect()
                ->route('backoffice.production-recipes.edit', $productionRecipe->id)
                ->with('error', 'Bahan input untuk Batch 2 hanya boleh ingredient tipe Mentah.');
        }

        if ((int) $productionRecipe->output_ingredient_id === (int) $inputIngredient->id) {
            return redirect()
                ->route('backoffice.production-recipes.edit', $productionRecipe->id)
                ->with('error', 'Output ingredient tidak boleh dipakai lagi sebagai bahan input.');
        }

        $alreadyExists = $productionRecipe->items()
            ->where('input_ingredient_id', $inputIngredient->id)
            ->exists();

        if ($alreadyExists) {
            return redirect()
                ->route('backoffice.production-recipes.edit', $productionRecipe->id)
                ->with('error', 'Ingredient itu sudah ada di production recipe ini.');
        }

        $productionRecipe->items()->create([
            'input_ingredient_id' => $inputIngredient->id,
            'qty' => $validated['qty'],
            'unit' => $inputIngredient->unit,
        ]);

        return redirect()
            ->route('backoffice.production-recipes.edit', $productionRecipe->id)
            ->with('success', 'Bahan input berhasil ditambahkan.');
    }

    public function destroyItem(IngredientProductionRecipe $productionRecipe, IngredientProductionRecipeItem $item)
    {
        $this->authorizeAccess();

        if ((int) $item->ingredient_production_recipe_id !== (int) $productionRecipe->id) {
            abort(404);
        }

        $ingredientName = $item->inputIngredient?->name ?? 'Item';
        $item->delete();

        return redirect()
            ->route('backoffice.production-recipes.edit', $productionRecipe->id)
            ->with('success', 'Bahan input "' . $ingredientName . '" berhasil dihapus.');
    }
}