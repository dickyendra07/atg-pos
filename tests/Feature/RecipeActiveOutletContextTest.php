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
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RecipeActiveOutletContextTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $bazaar;

    private Brand $brand;

    private ProductCategory $menuCategory;

    private IngredientCategory $ingredientCategory;

    /** Available at BXC + Bazaar. */
    private ProductVariant $sharedVariant;

    private Recipe $sharedRecipe;

    /** Available at Bazaar only. */
    private ProductVariant $bazaarVariant;

    private Recipe $bazaarRecipe;

    private Ingredient $milk;

    private User $adminPusat;

    private User $bxcAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar TikTok', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->menuCategory = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Powder', 'code' => 'POWDER', 'is_active' => true]);

        $this->sharedVariant = $this->makeVariant('Matcha', 'MATCHA', [$this->bxc, $this->bazaar]);
        $this->bazaarVariant = $this->makeVariant('Bazaar Only', 'BZR-ONLY', [$this->bazaar]);

        $this->milk = $this->makeIngredient('Milk', [$this->bxc, $this->bazaar]);

        $this->sharedRecipe = $this->makeRecipe($this->sharedVariant, $this->milk);
        $this->bazaarRecipe = $this->makeRecipe($this->bazaarVariant, $this->makeIngredient('Bazaar Syrup', [$this->bazaar]));

        // Admin pusat whose HOME outlet (users.outlet_id) is Bazaar TikTok but who can access every outlet.
        $this->adminPusat = $this->makeUser('admin-pusat', 'Admin Pusat', 'admin_pusat', $this->bazaar, [$this->bxc, $this->bazaar]);
        $this->bxcAdmin = $this->makeUser('admin-bxc', 'Admin Outlet', 'admin_outlet', $this->bxc, [$this->bxc]);
    }

    // ---- A / B: outlet label follows the Active Outlet, not users.outlet_id -----------------------

    public function test_admin_pusat_with_bazaar_home_outlet_sees_active_outlet_on_recipe_pages(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        foreach ([
            route('backoffice.recipes.create'),
            route('backoffice.recipes.edit', $this->sharedRecipe),
            route('backoffice.recipes.import'),
            route('backoffice.ingredients.import'),
        ] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('<strong>Active Outlet:</strong> BXC', false)
                ->assertDontSee('<strong>Active Outlet:</strong> Bazaar TikTok', false);
        }

        // Sidebar footer (shared layout) uses the same source.
        $this->assertSame('BXC', $this->sidebarFooterOutlet($this->get(route('backoffice.menu-categories.index'))->getContent()));
    }

    public function test_admin_pusat_with_all_outlets_context_sees_semua_outlet_not_home_outlet(): void
    {
        $this->actingAs($this->adminPusat);

        foreach ([
            route('backoffice.recipes.create'),
            route('backoffice.recipes.edit', $this->sharedRecipe),
            route('backoffice.recipes.import'),
            route('backoffice.ingredients.import'),
        ] as $url) {
            $response = $this->get($url)->assertOk();

            $response->assertSee('<strong>Active Outlet:</strong> Semua Outlet', false)
                ->assertDontSee('<strong>Active Outlet:</strong> Bazaar TikTok', false);
        }

        $this->assertSame('Semua Outlet', $this->sidebarFooterOutlet($this->get(route('backoffice.menu-categories.index'))->getContent()));
    }

    public function test_limited_user_with_all_context_sees_allowed_outlets_label(): void
    {
        $this->actingAs($this->bxcAdmin)->get(route('backoffice.recipes.create'))->assertOk()
            ->assertSee('<strong>Active Outlet:</strong> Semua Outlet yang Diizinkan', false);

        $this->assertSame('Semua Outlet yang Diizinkan', $this->sidebarFooterOutlet($this->get(route('backoffice.menu-categories.index'))->getContent()));
    }

    // ---- C / D: no way around the Active Outlet / outlet access, by URL or otherwise -------------

    public function test_limited_user_cannot_open_or_change_recipe_of_a_foreign_outlet(): void
    {
        $this->actingAs($this->bxcAdmin);
        $recipe = $this->bazaarRecipe;
        $item = $recipe->items()->first();

        $this->get(route('backoffice.recipes.edit', $recipe))->assertForbidden();
        $this->put(route('backoffice.recipes.update', $recipe), [
            'product_variant_id' => $this->sharedVariant->id,
            'name' => 'Hijacked',
            'is_active' => 0,
        ])->assertForbidden();
        $this->delete(route('backoffice.recipes.destroy', $recipe))->assertForbidden();
        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->milk->id, 'qty' => 5])->assertForbidden();
        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 999])->assertForbidden();
        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $item]))->assertForbidden();

        $this->assertRecipeUntouched($recipe->fresh(), $this->bazaarVariant, $item);
    }

    public function test_limited_user_index_and_export_only_contain_accessible_outlets(): void
    {
        $this->actingAs($this->bxcAdmin);

        $this->get(route('backoffice.recipes.index'))->assertOk()
            ->assertSee('Matcha')->assertDontSee('Bazaar Only');

        $csv = $this->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $csv);
        $this->assertStringNotContainsString('BZR-ONLY', $csv);
    }

    public function test_active_outlet_blocks_manual_url_to_recipe_of_another_outlet_even_for_admin_pusat(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);
        $recipe = $this->bazaarRecipe;
        $item = $recipe->items()->first();

        $this->get(route('backoffice.recipes.edit', $recipe))->assertForbidden();
        $this->put(route('backoffice.recipes.update', $recipe), [
            'product_variant_id' => $this->sharedVariant->id,
            'name' => 'Hijacked',
            'is_active' => 0,
        ])->assertForbidden();
        $this->delete(route('backoffice.recipes.destroy', $recipe))->assertForbidden();
        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->milk->id, 'qty' => 5])->assertForbidden();
        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 999])->assertForbidden();
        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $item]))->assertForbidden();

        $this->assertRecipeUntouched($recipe->fresh(), $this->bazaarVariant, $item);

        // The same recipe is reachable once the Active Outlet includes it, and in "Semua Outlet".
        $this->withSession(['active_backoffice_outlet_id' => $this->bazaar->id])
            ->get(route('backoffice.recipes.edit', $recipe))->assertOk();
        $this->withSession(['active_backoffice_outlet_id' => null])
            ->get(route('backoffice.recipes.edit', $recipe))->assertOk();
    }

    public function test_recipe_can_only_be_created_for_a_variant_inside_the_outlet_scope(): void
    {
        $payload = ['name' => 'Foreign', 'is_active' => 1];
        $this->bazaarRecipe->delete();

        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->post(route('backoffice.recipes.store'), $payload + ['product_variant_id' => $this->bazaarVariant->id])
            ->assertSessionHasErrors('product_variant_id');

        $this->actingAs($this->bxcAdmin)->withSession(['active_backoffice_outlet_id' => null])
            ->post(route('backoffice.recipes.store'), $payload + ['product_variant_id' => $this->bazaarVariant->id])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseMissing('recipes', ['product_variant_id' => $this->bazaarVariant->id]);
    }

    // ---- E: the Active Outlet must never re-point a Recipe to another Variant --------------------

    public function test_active_outlet_cannot_silently_repoint_recipe_variant(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        // The current Variant is offered and pre-selected in the edit form.
        $response = $this->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk();
        $response->assertSee('value="'.$this->sharedVariant->id.'" selected', false);
        $this->assertSame([$this->sharedVariant->id], $response->viewData('variants')->pluck('id')->all());

        // Saving with the unchanged Variant works and keeps it.
        $this->put(route('backoffice.recipes.update', $this->sharedRecipe), [
            'product_variant_id' => $this->sharedVariant->id,
            'name' => 'Matcha renamed',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->sharedVariant->id, $this->sharedRecipe->fresh()->product_variant_id);
        $this->assertSame($this->sharedVariant->product_id, $this->sharedRecipe->fresh()->product_id);

        // A forged Variant from outside the Active Outlet is rejected and nothing moves.
        $this->put(route('backoffice.recipes.update', $this->sharedRecipe), [
            'product_variant_id' => $this->bazaarVariant->id,
            'name' => 'Matcha renamed',
            'is_active' => 1,
        ])->assertSessionHasErrors('product_variant_id');
        $this->assertSame($this->sharedVariant->id, $this->sharedRecipe->fresh()->product_variant_id);
    }

    // ---- F: ingredient dropdown and validation use the same rule ---------------------------------

    public function test_ingredient_options_match_what_the_server_accepts(): void
    {
        $bxcOnly = $this->makeIngredient('BXC Only Powder', [$this->bxc]);
        $inactive = $this->makeIngredient('Retired Syrup', [$this->bxc, $this->bazaar], false);
        $both = $this->makeIngredient('Shared Cream', [$this->bxc, $this->bazaar]);

        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        // Recipe Variant sells at BXC + Bazaar, so an ingredient that only exists at BXC is not offered
        // even though the Active Outlet is BXC; neither is an inactive one or one already in the Recipe.
        $response = $this->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk();
        $offered = $response->viewData('ingredients')->pluck('id')->all();

        $this->assertSame([$both->id], $offered);
        $response->assertSee('Shared Cream')->assertDontSee('BXC Only Powder')->assertDontSee('Retired Syrup');
        $response->assertSee('Variant ini tersedia di: BXC, Bazaar TikTok');

        // Everything not offered is rejected by the server ...
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $bxcOnly->id, 'qty' => 1])
            ->assertSessionHasErrors('ingredient_id');
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $inactive->id, 'qty' => 1])
            ->assertSessionHasErrors('ingredient_id');
        $this->assertSame(1, $this->sharedRecipe->items()->count());

        // ... and everything offered is accepted, without touching ingredient outlet availability.
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $both->id, 'qty' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $this->sharedRecipe->items()->count());
        $this->assertSame([$this->bxc->id], $bxcOnly->outlets()->pluck('outlets.id')->all());
    }

    // ---- Export / import follow the same scope ----------------------------------------------------

    public function test_export_follows_active_outlet_and_stays_global_for_all_outlets(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);
        $scoped = $this->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $scoped);
        $this->assertStringNotContainsString('BZR-ONLY', $scoped);

        $all = $this->withSession(['active_backoffice_outlet_id' => null])->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $all);
        $this->assertStringContainsString('BZR-ONLY', $all);
    }

    public function test_import_skips_variants_outside_the_active_outlet_without_touching_their_recipes(): void
    {
        $csv = "variant_code,ingredient_name,qty,is_active\nBZR-ONLY,Milk,77,1\nMATCHA,Milk,9,1\n";
        $bazaarItems = $this->bazaarRecipe->items()->count();

        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->post(route('backoffice.recipes.import.store'), ['file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv)])
            ->assertRedirect(route('backoffice.recipes.index'))
            ->assertSessionHas('import_errors', fn ($errors) => count($errors) === 1 && str_contains($errors[0], 'BZR-ONLY'));

        $this->assertSame($bazaarItems, $this->bazaarRecipe->items()->count());
        $this->assertSame(9.0, (float) $this->sharedRecipe->items()->where('ingredient_id', $this->milk->id)->value('qty'));
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function assertRecipeUntouched(Recipe $recipe, ProductVariant $variant, RecipeItem $item): void
    {
        $this->assertTrue($recipe->is_active);
        $this->assertSame($variant->id, $recipe->product_variant_id);
        $this->assertSame('Recipe - '.$variant->name, $recipe->name);
        $this->assertSame(1, $recipe->items()->count());
        $this->assertSame((float) $item->qty, (float) $item->fresh()->qty);
    }

    private function sidebarFooterOutlet(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="sidebar-footer">(.*?)<\/div>/s', $html, $m));
        $lines = preg_split('/<br\s*\/?>/i', $m[1]);

        return trim($lines[1] ?? '');
    }

    private function makeVariant(string $name, string $code, array $outlets): ProductVariant
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => $name.' Product',
            'code' => $code.'-P',
            'is_active' => true,
        ]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $name,
            'code' => $code,
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function makeIngredient(string $name, array $outlets, bool $active = true): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => 'gram',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => $active,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function makeRecipe(ProductVariant $variant, Ingredient $ingredient): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => 'Recipe - '.$variant->name,
            'is_active' => true,
        ]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 10, 'unit' => 'gram']);

        return $recipe;
    }

    private function makeUser(string $username, string $roleName, string $roleCode, Outlet $homeOutlet, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleName])->id,
            'outlet_id' => $homeOutlet->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
