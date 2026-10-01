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
use App\Support\BackofficeReturnUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Batch 2: return_to (list context) after create/update/delete, the shared open-redirect guard,
 * and the global toast. Active Outlet / Recipe access rules (Batch 1) must stay intact.
 */
class BackofficeReturnContextTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $bazaar;

    private Brand $brand;

    private ProductCategory $menuCategory;

    private IngredientCategory $packagingCategory;

    private Product $product;

    private ProductVariant $variant;

    private Ingredient $cup;

    private Ingredient $sugar;

    private Recipe $recipe;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar TikTok', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->menuCategory = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->packagingCategory = IngredientCategory::create(['name' => 'Packaging', 'code' => 'PACKAGING', 'is_active' => true]);

        $this->product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => 'Kafei Susu',
            'code' => 'KAFEI-SUSU',
            'is_active' => true,
        ]);
        $this->product->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'name' => 'Regular',
            'code' => 'KAFEI-REG',
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $this->variant->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        $this->cup = $this->makeIngredient('Cup 16oz', [$this->bxc, $this->bazaar]);
        $this->sugar = $this->makeIngredient('Sugar', [$this->bxc, $this->bazaar]);

        $this->recipe = Recipe::create([
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'name' => 'Recipe Kafei Susu',
            'is_active' => true,
        ]);
        RecipeItem::create(['recipe_id' => $this->recipe->id, 'ingredient_id' => $this->cup->id, 'qty' => 1, 'unit' => 'pcs']);

        $this->owner = $this->makeUser('owner', 'owner', $this->bxc, [$this->bxc, $this->bazaar]);
    }

    // ---- shared guard: BackofficeReturnUrl --------------------------------------------------------

    #[DataProvider('unsafeReturnTo')]
    public function test_unsafe_return_to_values_are_rejected(mixed $value): void
    {
        $this->assertNull(BackofficeReturnUrl::sanitize($value));
    }

    public static function unsafeReturnTo(): array
    {
        return [
            'external url' => ['https://evil.example'],
            'external url with backoffice path' => ['https://evil.example/backoffice/products'],
            'protocol-relative' => ['//evil.example'],
            'protocol-relative with backoffice path' => ['//evil.example/backoffice/products'],
            'javascript scheme' => ['javascript:alert(1)'],
            'encoded scheme (as submitted)' => ['https:%2F%2Fevil.example'],
            'encoded protocol-relative leading slashes' => ['/%2Fevil.example'],
            'backslash host trick' => ['/\\evil.example'],
            'double backslash' => ['\\\\evil.example'],
            'tab inside' => ["/backoffice/products\t//evil.example"],
            'newline (header injection)' => ["/backoffice/products\r\nSet-Cookie: a=b"],
            'space' => ['/backoffice/products evil'],
            'other app path' => ['/login'],
            'prefix lookalike' => ['/backofficeevil/products'],
            'empty slashes inside' => ['/backoffice//evil.example'],
            'traversal' => ['/backoffice/../login'],
            'encoded traversal' => ['/backoffice/%2e%2e/login'],
            'relative path' => ['backoffice/products'],
            'empty' => [''],
            'null' => [null],
            'array' => [['/backoffice/products']],
            'too long' => ['/backoffice/products?search='.str_repeat('a', 3000)],
        ];
    }

    public function test_valid_internal_backoffice_urls_are_accepted(): void
    {
        $url = '/backoffice/products?search=milk&category_id=4&status=active&page=3';

        $this->assertSame($url, BackofficeReturnUrl::sanitize($url));
        $this->assertSame('/backoffice', BackofficeReturnUrl::sanitize('/backoffice'));
        $this->assertSame('/backoffice/products', BackofficeReturnUrl::sanitize('/backoffice/products#product-1'), 'fragment is dropped');
        $this->assertSame('/backoffice/products?search=a%20b', BackofficeReturnUrl::sanitize('/backoffice/products?search=a%20b'));
    }

    public function test_anchor_is_only_appended_when_it_is_a_plain_dom_id(): void
    {
        $this->assertSame('/backoffice/products?page=2#product-9', BackofficeReturnUrl::withAnchor('/backoffice/products?page=2', 'product-9'));
        $this->assertSame('/backoffice/products?page=2', BackofficeReturnUrl::withAnchor('/backoffice/products?page=2', 'x" onclick="evil'));
        $this->assertSame('/backoffice/products?page=2', BackofficeReturnUrl::withAnchor('/backoffice/products?page=2', null));
    }

    // ---- Product ----------------------------------------------------------------------------------

    public function test_product_update_returns_to_list_context_with_anchor_and_success_flash(): void
    {
        $listUrl = '/backoffice/products?search=kafei&category_id='.$this->menuCategory->id.'&page=3';

        $this->actingAs($this->owner)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload(['return_to' => $listUrl]))
            ->assertRedirect(url($listUrl.'#product-'.$this->product->id))
            ->assertSessionHas('success', 'Product berhasil diperbarui.');

        $this->assertSame('Kafei Susu Baru', $this->product->fresh()->name);
    }

    public function test_product_create_returns_to_list_context_anchored_at_the_new_record(): void
    {
        $listUrl = '/backoffice/products?search=kafei&page=2';

        $this->actingAs($this->owner)
            ->post(route('backoffice.products.store'), $this->productPayload([
                'name' => 'Kafei Baru',
                'code' => 'KAFEI-BARU',
                'return_to' => $listUrl,
            ]))
            ->assertRedirect(url($listUrl.'#product-'.Product::where('code', 'KAFEI-BARU')->value('id')))
            ->assertSessionHas('success', 'Product berhasil ditambahkan.');
    }

    public function test_product_inactivate_returns_to_list_context_and_keeps_the_row_anchor(): void
    {
        $listUrl = '/backoffice/products?category_id='.$this->menuCategory->id;

        $this->actingAs($this->owner)
            ->delete(route('backoffice.products.destroy', $this->product), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl.'#product-'.$this->product->id))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'dinonaktifkan'));

        $this->assertFalse((bool) $this->product->fresh()->is_active);
    }

    public function test_product_index_hands_its_own_url_to_edit_links_and_row_actions(): void
    {
        $query = '?search=kafei&category_id='.$this->menuCategory->id;
        $returnTo = '/backoffice/products'.$query;

        $html = $this->actingAs($this->owner)->get('/backoffice/products'.$query)->assertOk()->getContent();

        $this->assertStringContainsString('id="product-'.$this->product->id.'"', $html);
        $this->assertStringContainsString(
            e(route('backoffice.products.edit', [$this->product->id, 'return_to' => $returnTo])),
            $html
        );
        $this->assertStringContainsString('<input type="hidden" name="return_to" value="'.e($returnTo).'">', $html);
    }

    public function test_product_edit_form_carries_return_to_and_a_cancel_link_back_to_the_record(): void
    {
        $returnTo = '/backoffice/products?search=kafei&page=3';

        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'return_to' => $returnTo]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<input type="hidden" name="return_to" value="'.e($returnTo).'">', $html);
        $this->assertStringContainsString('href="'.e($returnTo.'#product-'.$this->product->id).'"', $html);
    }

    public function test_product_edit_ignores_a_hostile_return_to_in_the_form_and_links(): void
    {
        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'return_to' => 'https://evil.example/backoffice']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="return_to"', $html);
    }

    // ---- Ingredient -------------------------------------------------------------------------------

    public function test_ingredient_update_returns_to_packaging_search_with_anchor(): void
    {
        $listUrl = '/backoffice/ingredients?search=Packaging&ingredient_type=raw';

        $this->actingAs($this->owner)
            ->put(route('backoffice.ingredients.update', $this->cup), $this->ingredientPayload(['return_to' => $listUrl]))
            ->assertRedirect(url($listUrl.'#ingredient-'.$this->cup->id))
            ->assertSessionHas('success', 'Ingredient berhasil diperbarui.');

        $this->assertSame('Cup 16oz XL', $this->cup->fresh()->name);
    }

    public function test_ingredient_delete_returns_to_list_context_without_a_dead_anchor(): void
    {
        $listUrl = '/backoffice/ingredients?search=Sugar';

        $this->actingAs($this->owner)
            ->delete(route('backoffice.ingredients.destroy', $this->sugar), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl))
            ->assertSessionHas('success', 'Ingredient berhasil dihapus.');
    }

    public function test_ingredient_index_lists_rows_with_stable_ids_and_return_to_links(): void
    {
        $returnTo = '/backoffice/ingredients?search=Cup';

        $html = $this->actingAs($this->owner)->get($returnTo)->assertOk()->getContent();

        $this->assertStringContainsString('id="ingredient-'.$this->cup->id.'"', $html);
        $this->assertStringContainsString(e(route('backoffice.ingredients.edit', [$this->cup->id, 'return_to' => $returnTo])), $html);
    }

    // ---- Variant ----------------------------------------------------------------------------------

    public function test_variant_update_and_inactivate_return_to_the_product_group_in_the_list_context(): void
    {
        $listUrl = '/backoffice/variants?search=kafei&category_id='.$this->menuCategory->id;
        $anchor = '#variant-group-'.$this->product->id;

        $this->actingAs($this->owner)
            ->put(route('backoffice.variants.update', $this->variant), [
                'product_id' => $this->product->id,
                'return_to' => $listUrl,
                'variants' => [[
                    'id' => $this->variant->id,
                    'name' => 'Regular Plus',
                    'code' => 'KAFEI-REG',
                    'outlet_ids' => [$this->bxc->id],
                    'price_dine_in' => 21000,
                    'price_delivery' => 23000,
                    'is_active' => 1,
                ]],
            ])
            ->assertRedirect(url($listUrl.$anchor))
            ->assertSessionHas('success', 'Variant berhasil diperbarui.');

        $this->actingAs($this->owner)
            ->delete(route('backoffice.variants.destroy', $this->variant), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl.$anchor));

        $html = $this->actingAs($this->owner)->get($listUrl)->assertOk()->getContent();
        $this->assertStringContainsString('id="variant-group-'.$this->product->id.'"', $html);
    }

    // ---- Categories -------------------------------------------------------------------------------

    public function test_menu_and_ingredient_category_update_return_to_context(): void
    {
        $menuUrl = '/backoffice/menu-categories?search=Kaf';

        $this->actingAs($this->owner)
            ->put(route('backoffice.menu-categories.update', $this->menuCategory->id), ['brand_id' => $this->brand->id, 'name' => 'Kafei Panas', 'is_active' => 1, 'return_to' => $menuUrl])
            ->assertRedirect(url($menuUrl.'#category-'.$this->menuCategory->id))
            ->assertSessionHas('success', 'Kategori berhasil diperbarui.');

        $ingredientUrl = '/backoffice/ingredient-categories?search=Pack';

        $this->actingAs($this->owner)
            ->put(route('backoffice.ingredient-categories.update', $this->packagingCategory->id), ['name' => 'Packaging Baru', 'is_active' => 1, 'return_to' => $ingredientUrl])
            ->assertRedirect(url($ingredientUrl.'#category-'.$this->packagingCategory->id));
    }

    public function test_category_delete_goes_back_without_anchor_but_a_blocked_delete_keeps_the_row_anchor(): void
    {
        $listUrl = '/backoffice/menu-categories?search=Kaf';

        // Kafei is used by a product: delete is refused, the row is still there.
        $this->actingAs($this->owner)
            ->delete(route('backoffice.menu-categories.destroy', $this->menuCategory->id), ['return_to' => $listUrl])
            ->assertRedirect(url($listUrl.'#category-'.$this->menuCategory->id))
            ->assertSessionHas('error');

        $unused = IngredientCategory::create(['name' => 'Unused', 'code' => 'UNUSED', 'is_active' => true]);

        $this->actingAs($this->owner)
            ->delete(route('backoffice.ingredient-categories.destroy', $unused->id), ['return_to' => '/backoffice/ingredient-categories?search=Un'])
            ->assertRedirect(url('/backoffice/ingredient-categories?search=Un'))
            ->assertSessionHas('success', 'Kategori berhasil dihapus.');
    }

    // ---- Recipe + Recipe items --------------------------------------------------------------------

    public function test_recipe_update_respects_return_to_and_leaves_the_active_outlet_alone(): void
    {
        $listUrl = '/backoffice/recipes?search=Kafei&status=active';

        // A Recipe used at BXC only is editable while BXC is the Active Outlet (Batch 1 rule).
        $bxcOnly = $this->makeBxcOnlyRecipe();

        $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->put(route('backoffice.recipes.update', $bxcOnly), [
                'product_variant_id' => $bxcOnly->product_variant_id,
                'name' => 'Recipe BXC v2',
                'is_active' => 1,
                'return_to' => $listUrl,
            ])
            ->assertRedirect(url($listUrl.'#recipe-'.$bxcOnly->id))
            ->assertSessionHas('success')
            ->assertSessionHas('active_backoffice_outlet_id', $this->bxc->id);

        $this->assertSame('Recipe BXC v2', $bxcOnly->fresh()->name);
    }

    public function test_recipe_shared_across_outlets_still_needs_all_outlets_context_even_with_return_to(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->put(route('backoffice.recipes.update', $this->recipe), $this->recipePayload(['name' => 'Nope', 'return_to' => '/backoffice/recipes']))
            ->assertForbidden();

        $this->assertSame('Recipe Kafei Susu', $this->recipe->fresh()->name);
    }

    public function test_recipe_return_to_does_not_bypass_the_recipe_access_policy(): void
    {
        // Admin limited to BXC; this Recipe's Variant is also sold at Bazaar, which they cannot access.
        $bxcAdmin = $this->makeUser('admin-bxc', 'admin_outlet', $this->bxc, [$this->bxc]);

        $this->actingAs($bxcAdmin)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->put(route('backoffice.recipes.update', $this->recipe), $this->recipePayload(['name' => 'Hijack', 'return_to' => '/backoffice/recipes']))
            ->assertForbidden();

        $this->actingAs($bxcAdmin)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 2, 'return_to' => '/backoffice/recipes'])
            ->assertForbidden();

        $this->assertSame('Recipe Kafei Susu', $this->recipe->fresh()->name);
        $this->assertSame(1, $this->recipe->items()->count());
    }

    public function test_recipe_item_add_update_and_delete_stay_on_the_recipe_edit_page_with_flash_and_return_to(): void
    {
        $listUrl = '/backoffice/recipes?search=Kafei&status=active';
        $editBase = route('backoffice.recipes.edit', $this->recipe, false);
        $queryReturn = '?'.http_build_query(['return_to' => $listUrl]);

        // add
        $response = $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 5, 'return_to' => $listUrl])
            ->assertSessionHas('success', 'Bahan Recipe berhasil ditambahkan.');
        $newItem = RecipeItem::where('ingredient_id', $this->sugar->id)->firstOrFail();
        $response->assertRedirect(url($editBase.$queryReturn.'#recipe-item-'.$newItem->id));

        // update qty
        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.items.update', [$this->recipe, $newItem]), ['qty' => 7, 'return_to' => $listUrl])
            ->assertRedirect(url($editBase.$queryReturn.'#recipe-item-'.$newItem->id))
            ->assertSessionHas('success', 'Jumlah bahan berhasil diperbarui.');
        $this->assertEquals(7, $newItem->fresh()->qty);

        // duplicate add -> error flash, still on the edit page
        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 1, 'return_to' => $listUrl])
            ->assertRedirect(url($editBase.$queryReturn.'#recipe-add-item'))
            ->assertSessionHas('error');

        // delete
        $this->actingAs($this->owner)
            ->delete(route('backoffice.recipes.items.destroy', [$this->recipe, $newItem]), ['return_to' => $listUrl])
            ->assertRedirect(url($editBase.$queryReturn.'#recipe-items'))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('recipe_items', ['id' => $newItem->id]);
    }

    public function test_recipe_item_actions_without_return_to_still_land_on_the_recipe_edit_page(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 5])
            ->assertRedirect(url(route('backoffice.recipes.edit', $this->recipe, false).'#recipe-item-'.RecipeItem::where('ingredient_id', $this->sugar->id)->value('id')));
    }

    public function test_recipe_item_hostile_return_to_is_not_carried_forward(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 5, 'return_to' => '//evil.example'])
            ->assertRedirect(url(route('backoffice.recipes.edit', $this->recipe, false).'#recipe-item-'.RecipeItem::where('ingredient_id', $this->sugar->id)->value('id')))
            ->assertSessionHas('success');
    }

    public function test_recipe_edit_page_threads_return_to_through_every_form_and_back_link(): void
    {
        $returnTo = '/backoffice/recipes?search=Kafei';

        $html = $this->actingAs($this->owner)
            ->get(route('backoffice.recipes.edit', [$this->recipe, 'return_to' => $returnTo]))
            ->assertOk()
            ->getContent();

        $hidden = '<input type="hidden" name="return_to" value="'.e($returnTo).'">';
        // header form + qty form + delete form + add-item form
        $this->assertSame(4, substr_count($html, $hidden));
        $this->assertStringContainsString('id="recipe-item-'.$this->recipe->items()->value('id').'"', $html);
        $this->assertStringContainsString('href="'.e($returnTo.'#recipe-'.$this->recipe->id).'"', $html);
    }

    public function test_recipe_index_hands_its_own_url_to_edit_links(): void
    {
        $returnTo = '/backoffice/recipes?search=Kafei&status=active';

        $html = $this->actingAs($this->owner)->get($returnTo)->assertOk()->getContent();

        $this->assertStringContainsString('id="recipe-'.$this->recipe->id.'"', $html);
        $this->assertStringContainsString(e(route('backoffice.recipes.edit', [$this->recipe->id, 'return_to' => $returnTo])), $html);
    }

    // ---- Open redirect through real endpoints -----------------------------------------------------

    #[DataProvider('hostileReturnToOnRequests')]
    public function test_hostile_return_to_falls_back_to_the_normal_index(string $returnTo): void
    {
        $this->actingAs($this->owner)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload(['return_to' => $returnTo]))
            ->assertRedirect(route('backoffice.products.index'));

        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.update', $this->recipe), $this->recipePayload(['return_to' => $returnTo]))
            ->assertRedirect(route('backoffice.recipes.index'));
    }

    public static function hostileReturnToOnRequests(): array
    {
        return [
            'external' => ['https://evil.example'],
            'protocol-relative' => ['//evil.example'],
            'javascript' => ['javascript:alert(1)'],
            'encoded external' => ['https:%2F%2Fevil.example'],
            'backslash' => ['/\\evil.example'],
            'other app path' => ['/login'],
            'traversal' => ['/backoffice/../login'],
        ];
    }

    public function test_return_to_can_not_be_smuggled_through_a_non_string_value(): void
    {
        $this->actingAs($this->owner)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload(['return_to' => ['/backoffice/products']]))
            ->assertRedirect(route('backoffice.products.index'));
    }

    // ---- fallbacks without return_to --------------------------------------------------------------

    public function test_direct_edit_urls_without_return_to_use_the_normal_index_fallbacks(): void
    {
        $this->actingAs($this->owner)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload())
            ->assertRedirect(route('backoffice.products.index'));

        $this->actingAs($this->owner)
            ->put(route('backoffice.ingredients.update', $this->cup), $this->ingredientPayload())
            ->assertRedirect(route('backoffice.ingredients.index'));

        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.update', $this->recipe), $this->recipePayload())
            ->assertRedirect(route('backoffice.recipes.index'));

        $this->actingAs($this->owner)
            ->put(route('backoffice.ingredient-categories.update', $this->packagingCategory->id), ['name' => 'Packaging 2', 'is_active' => 1])
            ->assertRedirect(route('backoffice.ingredient-categories.index'));

        // and the pages themselves open fine without return_to
        foreach ([
            route('backoffice.products.edit', $this->product),
            route('backoffice.ingredients.edit', $this->cup),
            route('backoffice.recipes.edit', $this->recipe),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    // ---- validation failures ----------------------------------------------------------------------

    public function test_failed_validation_stays_on_the_edit_form_with_errors_old_input_and_return_to(): void
    {
        $returnTo = '/backoffice/products?search=kafei&page=3';
        $editUrl = route('backoffice.products.edit', [$this->product, 'return_to' => $returnTo]);

        $this->actingAs($this->owner)
            ->from($editUrl)
            ->put(route('backoffice.products.update', $this->product), $this->productPayload(['name' => '', 'description' => 'keep me', 'return_to' => $returnTo]))
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('name')
            ->assertSessionHasInput('return_to', $returnTo)
            ->assertSessionHasInput('description', 'keep me');

        $this->assertSame('Kafei Susu', $this->product->fresh()->name);
    }

    public function test_edit_form_redisplayed_after_failed_validation_keeps_return_to_and_old_input(): void
    {
        $returnTo = '/backoffice/products?search=kafei&page=3';

        $html = $this->actingAs($this->owner)
            ->withSession(['_old_input' => ['description' => 'keep me', 'return_to' => $returnTo]])
            ->get(route('backoffice.products.edit', $this->product))
            ->assertOk()
            ->getContent();

        // return_to survives even though the redisplayed URL lost its query string
        $this->assertStringContainsString('<input type="hidden" name="return_to" value="'.e($returnTo).'">', $html);
        $this->assertStringContainsString('keep me', $html);
    }

    public function test_validation_failures_produce_a_single_summary_toast_not_one_per_error(): void
    {
        $view = $this->withViewErrors([
            'name' => 'Nama wajib diisi.',
            'code' => 'Kode wajib diisi.',
            'brand_id' => 'Brand wajib diisi.',
        ])->view('backoffice.partials.feedback');

        $view->assertSee('Ada data yang perlu diperbaiki.');
        $this->assertSame(1, substr_count((string) $view, 'data-bo-toast-type="error"'));
        $this->assertStringNotContainsString('Nama wajib diisi.', (string) $view, 'field messages stay inline, not in the toast');
    }

    public function test_recipe_item_validation_failure_stays_on_the_recipe_edit_page(): void
    {
        $editUrl = route('backoffice.recipes.edit', [$this->recipe, 'return_to' => '/backoffice/recipes?search=Kafei']);

        $this->actingAs($this->owner)
            ->from($editUrl)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->sugar->id, 'qty' => 0, 'return_to' => '/backoffice/recipes?search=Kafei'])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors('qty');
    }

    // ---- toast ------------------------------------------------------------------------------------

    public function test_global_toast_renders_each_flash_type_once_on_layout_pages(): void
    {
        $html = $this->actingAs($this->owner)
            ->withSession(['success' => 'Product berhasil diperbarui.', 'error' => 'Gagal.', 'warning' => 'Hati-hati.', 'info' => 'Info saja.'])
            ->get(route('backoffice.products.index'))
            ->assertOk()
            ->getContent();

        foreach (['success' => 'Product berhasil diperbarui.', 'error' => 'Gagal.', 'warning' => 'Hati-hati.', 'info' => 'Info saja.'] as $type => $message) {
            $this->assertSame(1, substr_count($html, 'data-bo-toast-type="'.$type.'"'), $type.' toast');
            $this->assertSame(1, substr_count($html, $message), $message.' must not also appear as an inline banner');
        }

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('aria-label="Tutup notifikasi"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    public function test_global_toast_is_also_on_standalone_backoffice_pages(): void
    {
        $this->actingAs($this->owner)
            ->withSession(['success' => 'Recipe berhasil diperbarui.'])
            ->get(route('backoffice.recipes.edit', $this->recipe))
            ->assertOk()
            ->assertSee('data-bo-toast-type="success"', false)
            ->assertSee('Recipe berhasil diperbarui.');

        $this->actingAs($this->owner)
            ->withSession(['error' => 'Tidak bisa.'])
            ->get(route('backoffice.outlets.create'))
            ->assertOk()
            ->assertSee('data-bo-toast-type="error"', false);
    }

    public function test_toast_is_absent_when_there_is_nothing_to_report(): void
    {
        $this->actingAs($this->owner)
            ->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertDontSee('<div class="bo-toast ', false);
    }

    public function test_successful_update_leaves_a_success_flash_that_the_list_renders_as_a_toast(): void
    {
        $listUrl = '/backoffice/products?search=kafei';

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->put(route('backoffice.products.update', $this->product), $this->productPayload(['return_to' => $listUrl]))
            ->assertOk()
            ->assertSee('data-bo-toast-type="success"', false)
            ->assertSee('Product berhasil diperbarui.');
    }

    // ---- helpers ----------------------------------------------------------------------------------

    private function productPayload(array $overrides = []): array
    {
        return array_merge([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => 'Kafei Susu Baru',
            'code' => 'KAFEI-SUSU',
            'description' => null,
            'is_active' => 1,
            'outlet_ids' => [$this->bxc->id, $this->bazaar->id],
        ], $overrides);
    }

    private function ingredientPayload(array $overrides = []): array
    {
        return array_merge([
            'ingredient_category_id' => $this->packagingCategory->id,
            'name' => 'Cup 16oz XL',
            'unit' => 'pcs',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => 1,
            'outlet_ids' => [$this->bxc->id, $this->bazaar->id],
        ], $overrides);
    }

    private function recipePayload(array $overrides = []): array
    {
        return array_merge([
            'product_variant_id' => $this->variant->id,
            'name' => 'Recipe Kafei Susu v2',
            'is_active' => 1,
        ], $overrides);
    }

    private function makeBxcOnlyRecipe(): Recipe
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => 'Kafei BXC',
            'code' => 'KAFEI-BXC',
            'is_active' => true,
        ]);
        $product->outlets()->sync([$this->bxc->id]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'BXC Regular',
            'code' => 'KAFEI-BXC-REG',
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync([$this->bxc->id]);

        return Recipe::create([
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'name' => 'Recipe BXC',
            'is_active' => true,
        ]);
    }

    private function makeIngredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->packagingCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => 'pcs',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function makeUser(string $username, string $roleCode, Outlet $homeOutlet, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $homeOutlet->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
