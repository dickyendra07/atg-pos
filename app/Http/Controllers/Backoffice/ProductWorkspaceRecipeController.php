<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Backoffice\Concerns\RespondsAsProductWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\User;
use App\Services\IngredientWriter;
use App\Services\RecipeAccessPolicy;
use App\Services\RecipeWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Recipe in the Product Workspace, per Variant. Writes go through RecipeWriter (the same rules as the
 * classic Recipe pages); permanent Recipe deletion is not offered here.
 *
 * Every endpoint: the Product page role + Product outlet scope (RespondsAsProductWorkspace), then
 * {variant} must belong to {product} and {recipe} to {variant} (ids are taken from the route only),
 * then RecipeAccessPolicy must allow changing that Variant's Recipe in the current Active Outlet
 * context. A Variant with more than one active Recipe is read-only here (checked again under lock).
 */
class ProductWorkspaceRecipeController extends Controller
{
    use RespondsAsProductWorkspace;

    public function createForm(Request $request, Product $product, ProductVariant $variant, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, null, $writer);

        if (Recipe::where('product_variant_id', $variant->id)->exists()) {
            return response()->json(['ok' => false, 'message' => RecipeWriter::ALREADY_EXISTS_MESSAGE], 409);
        }

        return $this->form($request, $user, $product, $variant, null, $writer);
    }

    public function editForm(Request $request, Product $product, ProductVariant $variant, Recipe $recipe, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, $recipe, $writer);

        return $this->form($request, $user, $product, $variant, $recipe, $writer);
    }

    /** Ingredients a Recipe of this Variant can use right now (refreshes the drawer's selects). */
    public function ingredientOptions(Request $request, Product $product, ProductVariant $variant, RecipeWriter $writer)
    {
        $recipe = $request->filled('recipe') ? Recipe::find((int) $request->query('recipe')) : null;
        abort_if($request->filled('recipe') && ! $recipe, 404);

        $this->authorizeRecipeWork($request, $product, $variant, $recipe, $writer);

        return response()->json([
            'ok' => true,
            'ingredients' => $this->ingredientOptionList($writer->selectableIngredients($variant, $recipe ? $recipe->items()->pluck('ingredient_id') : [])),
        ]);
    }

    public function store(Request $request, Product $product, ProductVariant $variant, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, null, $writer);

        $recipe = $writer->createForVariant($product, $variant, $request->only(['name', 'new_items']));

        return $this->saved($request, $product, $user, 'recipe',
            'Recipe "'.$recipe->name.'" berhasil dibuat sebagai nonaktif. Periksa bahannya, lalu klik Aktifkan agar Variant dapat dijual.');
    }

    public function update(Request $request, Product $product, ProductVariant $variant, Recipe $recipe, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, $recipe, $writer);

        $summary = $writer->saveFromWorkspace($product, $variant, $recipe, $request->only(['name', 'items', 'new_items']));
        $changed = $summary['name'] || $summary['updated'] || $summary['removed'] || $summary['added'];

        return $this->saved($request, $product, $user, 'recipe', $changed
            ? 'Recipe "'.$recipe->fresh()->name.'" berhasil disimpan.'
            : 'Tidak ada perubahan pada Recipe "'.$recipe->name.'".');
    }

    public function activate(Request $request, Product $product, ProductVariant $variant, Recipe $recipe, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, $recipe, $writer);

        $writer->activate($product, $variant, $recipe);

        return $this->saved($request, $product, $user, 'recipe', 'Recipe "'.$recipe->name.'" berhasil diaktifkan. Status siap jual sudah diperbarui.');
    }

    public function deactivate(Request $request, Product $product, ProductVariant $variant, Recipe $recipe, RecipeWriter $writer)
    {
        $user = $this->authorizeRecipeWork($request, $product, $variant, $recipe, $writer);

        $writer->deactivateInWorkspace($product, $variant, $recipe);

        return $this->saved($request, $product, $user, 'recipe', 'Recipe "'.$recipe->name.'" berhasil dinonaktifkan. Variant tidak dapat dijual sampai ada Recipe aktif.');
    }

    // ---- Guards -----------------------------------------------------------------------------------------

    private function authorizeRecipeWork(Request $request, Product $product, ProductVariant $variant, ?Recipe $recipe, RecipeWriter $writer): User
    {
        // Product roles (owner, admin_pusat, admin_outlet) are all Recipe page roles too.
        $user = $this->authorizeProduct($request, $product);

        abort_unless(RecipeWriter::owns($product, $variant, $recipe), 404);

        $status = app(RecipeAccessPolicy::class)->mutationStatus($user, $variant);
        abort_unless($status['allowed'], 403, $status['message'] ?? 'Recipe ini tidak dapat diubah dari konteks ini.');

        if ($writer->isAmbiguous($variant)) {
            abort(409, RecipeWriter::AMBIGUOUS_MESSAGE.' Recipe ini tidak dapat diubah dari Product Workspace.');
        }

        return $user;
    }

    // ---- Drawer form ------------------------------------------------------------------------------------

    private function form(Request $request, User $user, Product $product, ProductVariant $variant, ?Recipe $recipe, RecipeWriter $writer)
    {
        $variant->load(['outlets' => fn ($query) => $query->orderBy('name')]);
        $required = $variant->outlets->pluck('id')->map(fn ($id) => (int) $id);
        $items = $recipe
            ? $recipe->items()->with(['ingredient.outlets:id', 'ingredient.category:id,name'])->orderBy('id')->get()
            : collect();
        $ingredientCounts = $items->countBy('ingredient_id');

        return response()->json([
            'ok' => true,
            'html' => view('backoffice.products.workspace._recipe-form', [
                'product' => $product,
                'variant' => $variant,
                'recipe' => $recipe,
                'workspaceReturnTo' => BackofficeReturnUrl::fromRequest($request),
                'defaultName' => RecipeWriter::defaultName($variant->setRelation('product', $product)),
                'variantOutlets' => $variant->outlets->pluck('name')->all(),
                'items' => $items->map(fn (RecipeItem $item) => $this->itemRow($item, $required, $variant->outlets, $ingredientCounts))->all(),
                'ingredientOptions' => $this->ingredientOptionList($writer->selectableIngredients($variant, $items->pluck('ingredient_id'))),
                'optionsUrl' => route('backoffice.products.workspace.recipes.ingredient-options', array_filter([$product, $variant, 'recipe' => $recipe?->id]), false),
                'ingredientCreateUrl' => IngredientWriter::hasIngredientRole($user)
                    ? route('backoffice.products.workspace.ingredients.create-form', [$product, 'return_section' => 'recipe'], false)
                    : null,
                'classicUrl' => $recipe ? route('backoffice.recipes.edit', [$recipe->id], false) : null,
            ])->render(),
        ]);
    }

    /** One stored item as the drawer shows it: values exactly as stored, plus warnings (never fixes). */
    private function itemRow(RecipeItem $item, Collection $required, Collection $variantOutlets, Collection $ingredientCounts): array
    {
        $ingredient = $item->ingredient;
        $missing = $ingredient ? $required->diff($ingredient->outlets->pluck('id')->map(fn ($id) => (int) $id)) : collect();

        return [
            'id' => (int) $item->id,
            'ingredient' => $ingredient?->name ?? 'Ingredient tidak valid',
            'category' => $ingredient?->category?->name,
            // Raw stored value (e.g. "46084.00"): shown and submitted back untouched unless edited.
            'qty' => (string) $item->getRawOriginal('qty'),
            'unit' => $item->unit,
            'ingredient_unit' => $ingredient?->unit,
            'warnings' => array_values(array_filter([
                ! $ingredient ? 'Ingredient tidak ditemukan.' : null,
                $ingredient && ! $ingredient->is_active ? 'Ingredient nonaktif.' : null,
                $missing->isNotEmpty() ? 'Belum tersedia di: '.$variantOutlets->whereIn('id', $missing->all())->pluck('name')->implode(', ').'.' : null,
                ($ingredientCounts[$item->ingredient_id] ?? 0) > 1 ? 'Ingredient ini muncul lebih dari sekali di Recipe (data lama, tidak digabung otomatis).' : null,
                $ingredient && $item->unit !== null && $ingredient->unit !== null && $item->unit !== $ingredient->unit
                    ? 'Unit tersimpan "'.$item->unit.'" berbeda dengan unit Ingredient "'.$ingredient->unit.'" (tidak dikonversi).' : null,
            ])),
        ];
    }

    private function ingredientOptionList(Collection $ingredients): array
    {
        return $ingredients->map(fn (Ingredient $ingredient) => [
            'id' => (int) $ingredient->id,
            'name' => $ingredient->name,
            'unit' => $ingredient->unit,
            'label' => $ingredient->name.' - '.$ingredient->unit.' - '.($ingredient->category->name ?? '-').' - ['.strtoupper($ingredient->ingredientTypeLabel()).']',
        ])->values()->all();
    }
}
