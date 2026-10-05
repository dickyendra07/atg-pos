<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Backoffice\Concerns\RespondsAsProductWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\BackofficeOutletContext;
use App\Services\CategoryWriter;
use App\Services\IngredientWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;

/**
 * Ingredient management reachable from the Product Workspace (Stock & Readiness, Recipe context).
 *
 * Ingredients are GLOBAL: the {product} in the URL only supplies the workspace context (and its
 * access check); it never owns the Ingredient. Writes go through IngredientWriter, the same rules as
 * the Ingredient pages. There is no delete here - only Active / Inactive.
 */
class ProductWorkspaceIngredientController extends Controller
{
    use RespondsAsProductWorkspace;

    private const RETURN_SECTIONS = ['stock', 'recipe'];

    public function createForm(Request $request, Product $product)
    {
        $user = $this->authorizeIngredientWork($request, $product);

        return $this->form($request, $user, $product, null);
    }

    public function editForm(Request $request, Product $product, Ingredient $ingredient, IngredientWriter $writer)
    {
        $user = $this->authorizeIngredientWork($request, $product);
        $this->authorizeIngredient($user, $ingredient, $writer);

        return $this->form($request, $user, $product, $ingredient);
    }

    public function store(Request $request, Product $product, IngredientWriter $writer)
    {
        $user = $this->authorizeIngredientWork($request, $product);

        $ingredient = $writer->create($user, $request->validate($writer->rules()));

        return $this->saved($request, $product, $user, $this->returnSection($request), 'Ingredient "'.$ingredient->name.'" berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product, Ingredient $ingredient, IngredientWriter $writer)
    {
        $user = $this->authorizeIngredientWork($request, $product);
        $this->authorizeIngredient($user, $ingredient, $writer);

        $writer->update($user, $ingredient, $request->validate($writer->rules($ingredient)));

        return $this->saved($request, $product, $user, $this->returnSection($request), 'Ingredient "'.$ingredient->fresh()->name.'" berhasil disimpan.');
    }

    /** Ingredient Category created from the Ingredient drawer, with the Category pages' own rules. */
    public function storeCategory(Request $request, Product $product, CategoryWriter $writer)
    {
        $user = $this->authorizeIngredientWork($request, $product);
        abort_unless(CategoryWriter::canManage($user->loadMissing('roles')), 403, CategoryWriter::DENIED_MESSAGE);

        $category = $writer->create(IngredientCategory::class, $writer->validated($request, IngredientCategory::class, false));

        if (! $request->expectsJson()) {
            return redirect(\App\Services\ProductWorkspace::url($product, 'stock', BackofficeReturnUrl::fromRequest($request)))
                ->with('success', 'Kategori berhasil ditambahkan.');
        }

        return response()->json([
            'ok' => true,
            'message' => $category->is_active
                ? 'Ingredient Category berhasil ditambahkan dan dipilih.'
                : 'Ingredient Category berhasil ditambahkan, tetapi nonaktif sehingga tidak dapat dipilih.',
            'category' => ['id' => (int) $category->id, 'name' => $category->name, 'is_active' => (bool) $category->is_active],
        ]);
    }

    /** Product workspace access AND the Ingredient pages' own role rule (never broader than either). */
    private function authorizeIngredientWork(Request $request, Product $product): User
    {
        $user = $this->authorizeProduct($request, $product);
        abort_unless(IngredientWriter::hasIngredientRole($user), 403, 'Role kamu tidak punya akses ke halaman Ingredients.');

        return $user;
    }

    private function authorizeIngredient(User $user, Ingredient $ingredient, IngredientWriter $writer): void
    {
        abort_unless($writer->canEdit($user, $ingredient), 403, 'Ingredient ini tidak tersedia di outlet yang dapat kamu akses.');
    }

    private function returnSection(Request $request): string
    {
        $section = $request->input('return_section');

        return is_string($section) && in_array($section, self::RETURN_SECTIONS, true) ? $section : 'stock';
    }

    private function form(Request $request, User $user, Product $product, ?Ingredient $ingredient)
    {
        $accessible = app(BackofficeOutletContext::class)->accessibleOutlets($user);
        $accessibleIds = $accessible->pluck('id')->map(fn ($id) => (int) $id);

        // A new Ingredient starts in the outlets where this Product is sold (and the user has access).
        $checked = $ingredient
            ? $ingredient->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)
            : $product->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id);

        return response()->json([
            'ok' => true,
            'html' => view('backoffice.products.workspace._ingredient-form', [
                'product' => $product,
                'ingredient' => $ingredient,
                'workspaceReturnTo' => BackofficeReturnUrl::fromRequest($request),
                'returnSection' => $this->returnSection($request),
                'categories' => IngredientCategory::where('is_active', true)
                    ->when($ingredient, fn ($query) => $query->orWhere('id', $ingredient->ingredient_category_id))
                    ->orderBy('name')->get(),
                'unitOptions' => Ingredient::unitOptions($ingredient?->unit),
                'typeOptions' => Ingredient::ingredientTypeOptions(),
                'outletChoices' => $accessible->map(fn ($outlet) => [
                    'id' => (int) $outlet->id,
                    'name' => $outlet->name,
                    'checked' => $checked->contains((int) $outlet->id),
                ])->values()->all(),
                'lockedOutlets' => $ingredient
                    ? $ingredient->outlets()->whereNotIn('outlets.id', $accessibleIds->all())->orderBy('name')->pluck('name')->all()
                    : [],
                'canCreateCategory' => CategoryWriter::canManage($user->loadMissing('roles')),
            ])->render(),
        ]);
    }
}
