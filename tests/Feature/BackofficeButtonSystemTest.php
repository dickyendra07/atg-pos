<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the Back Office UI polish: the compact Recipe list (no "Recipe Items" column), the semantic button
 * variants (Hapus Permanen is danger, Nonaktifkan is warning) and the shared button stylesheet.
 */
class BackofficeButtonSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backoffice.destructive_delete_enabled' => true]);

        $outlet = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);

        $this->owner = User::create([
            'name' => 'owner', 'username' => 'owner', 'email' => 'owner@example.test', 'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => 'owner'], ['name' => 'owner'])->id,
            'outlet_id' => $outlet->id, 'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$outlet->id]);

        $product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'name' => 'Es Kopi', 'code' => 'ES-KOPI', 'is_active' => true]);
        $product->outlets()->sync([$outlet->id]);
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => 'Regular', 'code' => 'ES-KOPI-REG', 'price' => 10000, 'is_active' => true]);
        $variant->outlets()->sync([$outlet->id]);

        $ingredient = Ingredient::create([
            'ingredient_category_id' => $ingredientCategory->id, 'name' => 'Susu Segar', 'code' => 'SUSU', 'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$outlet->id]);

        $this->recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Recipe - Es Kopi Regular', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $this->recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 5, 'unit' => 'ml']);
    }

    public function test_recipe_list_has_no_recipe_items_column_but_keeps_the_other_columns(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('<th>Recipe Items</th>', $html);
        $this->assertStringNotContainsString('recipe-items-scroll', $html);
        $this->assertStringNotContainsString('Susu Segar - 5', $html, 'item pills must not be listed in the table');

        foreach (['Recipe Name', 'Ingredient Type', 'Status', 'Action'] as $heading) {
            $this->assertStringContainsString('<th>'.$heading.'</th>', $html);
        }
        $this->assertStringContainsString('Es Kopi Regular', $html);
    }

    public function test_recipe_items_are_still_visible_in_the_recipe_editor(): void
    {
        $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe))
            ->assertOk()->assertSee('Susu Segar');
    }

    public function test_recipe_row_actions_use_semantic_variants(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#class="btn btn-secondary btn-sm"[^>]*>Edit</a>#', $html);
        $this->assertMatchesRegularExpression('#<button type="submit" class="btn btn-warning btn-sm">\s*Nonaktifkan#', $html);
        $this->assertMatchesRegularExpression('#class="btn btn-danger btn-sm"[^>]*data-cleanup-delete#', $html);
        $this->assertStringNotContainsString('background: linear-gradient(135deg, #b91c1c', $html, 'no inline colour on Nonaktifkan');
    }

    public function test_shared_button_stylesheet_is_on_the_layout_and_defines_every_variant_and_state(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->assertOk()->getContent();

        foreach (['.btn.btn-secondary', '.btn.btn-success', '.btn.btn-warning', '.btn.btn-danger', '.btn.btn-sm',
            '.btn:focus-visible', '.btn:disabled', '.btn.is-loading'] as $selector) {
            $this->assertStringContainsString('body '.$selector, $html);
        }
    }
}
