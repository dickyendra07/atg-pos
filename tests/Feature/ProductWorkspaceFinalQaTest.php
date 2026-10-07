<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\PromoRequirement;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\User;
use App\Services\ProductWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UX-H: final end-to-end review of the Product Workspace. Direct routes must enforce what the hidden
 * buttons only suggest; reads must not write; query counts must not grow with the data; contextual links
 * must come back to their own section.
 */
class ProductWorkspaceFinalQaTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'B', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
    }

    // ================================================================================================
    // Direct routes: a hidden button is not authorization
    // ================================================================================================

    /**
     * @return array<string, array{0: callable, 1: ?callable}> label => [request(fixtures) => [method, uri, payload], damaged(fixtures) => bool]
     */
    private function directRoutes(): array
    {
        return [
            'product deactivate' => [
                fn ($f) => ['DELETE', route('backoffice.products.destroy', $f['product'])],
                fn ($f) => ! $f['product']->fresh()->is_active,
            ],
            'variant classic edit page' => [fn ($f) => ['GET', route('backoffice.variants.edit', $f['variant'])], null],
            'variant classic update' => [
                fn ($f) => ['PUT', route('backoffice.variants.update', $f['variant']), ['product_id' => $f['product']->id, 'variants' => [['id' => $f['variant']->id, 'name' => 'HACKED', 'code' => $f['variant']->code, 'price_dine_in' => 5000, 'price_delivery' => 5000, 'outlet_ids' => [$f['outlet']->id], 'is_active' => 1]]]],
                fn ($f) => $f['variant']->fresh()->name === 'HACKED',
            ],
            'variant classic deactivate' => [
                fn ($f) => ['DELETE', route('backoffice.variants.destroy', $f['variant'])],
                fn ($f) => ! $f['variant']->fresh()->is_active,
            ],
            'ingredient classic edit page' => [fn ($f) => ['GET', route('backoffice.ingredients.edit', $f['ingredient'])], null],
            'ingredient classic update' => [
                fn ($f) => ['PUT', route('backoffice.ingredients.update', $f['ingredient']), ['ingredient_category_id' => $this->ingredientCategory->id, 'name' => 'HACKED', 'unit' => 'ml', 'ingredient_type' => 'raw', 'minimum_stock' => 1, 'cost_per_unit' => 1, 'is_active' => 1, 'outlet_ids' => [$f['outlet']->id]]],
                fn ($f) => $f['ingredient']->fresh()->name === 'HACKED',
            ],
            'ingredient classic delete' => [
                fn ($f) => ['DELETE', route('backoffice.ingredients.destroy', $f['ingredient'])],
                fn ($f) => Ingredient::find($f['ingredient']->id) === null,
            ],
            'promo edit page' => [fn ($f) => ['GET', route('backoffice.promos.edit', $f['promo'])], null],
            'promo update' => [
                fn ($f) => ['PUT', route('backoffice.promos.update', $f['promo']), ['name' => 'HACKED', 'requirement_logic' => 'and', 'status' => 'draft', 'outlet_ids' => [$f['outlet']->id]]],
                fn ($f) => $f['promo']->fresh()->name === 'HACKED',
            ],
            'promo delete' => [
                fn ($f) => ['DELETE', route('backoffice.promos.destroy', $f['promo'])],
                fn ($f) => Promo::find($f['promo']->id) === null,
            ],
        ];
    }

    public function test_limited_roles_cannot_reach_resources_that_exist_only_at_other_outlets_by_direct_route(): void
    {
        foreach (['admin_outlet', 'staff_gudang'] as $role) {
            foreach ($this->directRoutes() as $label => [$request, $damaged]) {
                // Everything below lives at Bravo only; the user only has Alpha.
                $f = $this->fixtures($this->b);
                $user = $this->user($role, [$this->a]);
                [$method, $uri, $payload] = array_pad($request($f), 3, []);

                $response = $this->actingAs($user)->call($method, $uri, $payload);

                // A role without access to a page at all is turned away elsewhere (redirect / 403);
                // what matters: nothing is served for a GET and nothing changes for a write.
                $this->assertNotSame(200, $response->getStatusCode(), $role.' '.$label.' must not be served');

                if ($damaged) {
                    $this->assertFalse((bool) $damaged($f), $role.' '.$label.' must not change data');
                }
            }
        }
    }

    public function test_the_same_direct_routes_still_work_for_the_users_own_outlets_and_for_full_access_roles(): void
    {
        // A limited user acting on resources AT their outlet (Alpha).
        $admin = $this->user('admin_outlet', [$this->a]);
        $f = $this->fixtures($this->a);
        $this->actingAs($admin)->get(route('backoffice.variants.edit', $f['variant']))->assertOk();
        $this->get(route('backoffice.ingredients.edit', $f['ingredient']))->assertOk();
        $this->delete(route('backoffice.variants.destroy', $f['variant']))->assertRedirect();
        $this->assertFalse((bool) $f['variant']->fresh()->is_active);
        $this->delete(route('backoffice.products.destroy', $f['product']))->assertRedirect();
        $this->assertFalse((bool) $f['product']->fresh()->is_active);

        // Staff gudang on a Promo that lives entirely inside their outlets, and on one without outlets.
        $warehouse = $this->user('staff_gudang', [$this->a]);
        $own = $this->fixtures($this->a);
        $this->actingAs($warehouse)->get(route('backoffice.promos.edit', $own['promo']))->assertOk();
        $unassigned = Promo::create(['name' => 'Idea', 'requirement_logic' => 'and', 'status' => 'draft', 'is_active' => false, 'active_days' => []]);
        $this->get(route('backoffice.promos.edit', $unassigned))->assertOk();

        // Owner / admin pusat: unrestricted, as before.
        foreach (['owner', 'admin_pusat'] as $role) {
            $user = $this->user($role, [$this->a]);
            $far = $this->fixtures($this->b);

            $this->actingAs($user)->get(route('backoffice.variants.edit', $far['variant']))->assertOk();
            $this->get(route('backoffice.ingredients.edit', $far['ingredient']))->assertOk();
            $this->get(route('backoffice.promos.edit', $far['promo']))->assertOk();
            $this->delete(route('backoffice.promos.destroy', $far['promo']))->assertRedirect();
            $this->assertNull(Promo::find($far['promo']->id));
        }
    }

    public function test_a_promo_that_also_covers_other_outlets_is_closed_to_a_limited_role_because_saving_would_drop_them(): void
    {
        $warehouse = $this->user('staff_gudang', [$this->a]);
        $f = $this->fixtures($this->a);
        $f['promo']->outlets()->sync([$this->a->id, $this->b->id]);
        $name = $f['promo']->name;

        $this->actingAs($warehouse)->get(route('backoffice.promos.edit', $f['promo']))->assertForbidden();
        $this->put(route('backoffice.promos.update', $f['promo']), ['name' => 'Trim', 'requirement_logic' => 'and', 'status' => 'draft', 'outlet_ids' => [$this->a->id]])->assertForbidden();
        $this->delete(route('backoffice.promos.destroy', $f['promo']))->assertForbidden();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $f['promo']->outlets()->pluck('outlets.id')->all());
        $this->assertSame($name, $f['promo']->fresh()->name);
    }

    public function test_the_ingredient_list_only_offers_edit_and_delete_for_rows_the_user_may_change(): void
    {
        $mine = $this->fixtures($this->a)['ingredient'];
        $theirs = $this->fixtures($this->b)['ingredient'];
        $limited = $this->user('admin_outlet', [$this->a]);

        $html = $this->actingAs($limited)->get(route('backoffice.ingredients.index'))->assertOk()->getContent();

        $this->assertStringContainsString('ingredients/'.$mine->id.'/edit', $html);
        $this->assertStringNotContainsString('ingredients/'.$theirs->id.'/edit', $html);
        $this->assertStringNotContainsString('action="'.route('backoffice.ingredients.destroy', $theirs).'"', $html);
        $this->assertStringContainsString('Hanya lihat', $html);

        $owner = $this->user('owner', [$this->a]);
        $ownerHtml = $this->actingAs($owner)->get(route('backoffice.ingredients.index'))->getContent();
        $this->assertStringContainsString('ingredients/'.$theirs->id.'/edit', $ownerHtml);
        $this->assertStringNotContainsString('Hanya lihat', $ownerHtml);
    }

    public function test_workspace_drawer_endpoints_keep_refusing_out_of_scope_data(): void
    {
        $user = $this->user('admin_outlet', [$this->a]);
        $f = $this->fixtures($this->b);
        $recipe = Recipe::where('product_variant_id', $f['variant']->id)->first();

        $this->actingAs($user);
        $this->getJson(route('backoffice.products.workspace.variants.edit-form', [$f['product'], $f['variant']]))->assertForbidden();
        $this->getJson(route('backoffice.products.workspace.recipes.edit-form', [$f['product'], $f['variant'], $recipe]))->assertForbidden();
        $this->patchJson(route('backoffice.products.workspace.recipes.deactivate', [$f['product'], $f['variant'], $recipe]))->assertForbidden();
        $this->getJson(route('backoffice.products.workspace.ingredients.edit-form', [$f['product'], $f['ingredient']]))->assertForbidden();
        $this->assertTrue((bool) $recipe->fresh()->is_active);
    }

    // ================================================================================================
    // Reading never writes (all sections, drawer forms, linked pages)
    // ================================================================================================

    public function test_opening_every_section_drawer_form_and_linked_page_writes_nothing(): void
    {
        $f = $this->fixtures($this->a);
        $user = $this->user('owner', [$this->a]);
        $recipe = Recipe::where('product_variant_id', $f['variant']->id)->first();
        StockBalance::create(['ingredient_id' => $f['ingredient']->id, 'location_type' => 'outlet', 'location_id' => $this->a->id, 'qty_on_hand' => -4]);
        $before = $this->fingerprint();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($user);
        foreach (array_keys(ProductWorkspace::SECTIONS) as $section) {
            $this->get(route('backoffice.products.edit', [$f['product'], 'section' => $section]))->assertOk();
        }
        $this->getJson(route('backoffice.products.workspace.variants.create-form', $f['product']))->assertOk();
        $this->getJson(route('backoffice.products.workspace.variants.edit-form', [$f['product'], $f['variant']]))->assertOk();
        $this->getJson(route('backoffice.products.workspace.recipes.edit-form', [$f['product'], $f['variant'], $recipe]))->assertOk();
        $this->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$f['product'], $f['variant']]))->assertOk();
        $this->getJson(route('backoffice.products.workspace.ingredients.create-form', $f['product']))->assertOk();
        $this->getJson(route('backoffice.products.workspace.ingredients.edit-form', [$f['product'], $f['ingredient']]))->assertOk();
        $this->postJson(route('backoffice.products.workspace.outlets.preview', $f['product']), ['outlet_ids' => [$this->a->id]])->assertOk();
        $this->get(route('backoffice.stock-balances.index', ['ingredient_id' => $f['ingredient']->id]))->assertOk();
        $this->get(route('backoffice.stock-movements.index', ['ingredient_id' => $f['ingredient']->id]))->assertOk();
        $this->get(route('backoffice.promos.create', ['variant_id' => $f['variant']->id]))->assertOk();
        $this->get(route('backoffice.promos.edit', $f['promo']))->assertOk();
        $this->get(route('backoffice.products.index'))->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame($before, $this->fingerprint());
    }

    // ================================================================================================
    // Query counts stay flat as the data grows
    // ================================================================================================

    public function test_workspace_endpoints_do_not_query_per_variant_recipe_ingredient_or_promo(): void
    {
        $f = $this->fixtures($this->a);
        $product = $f['product'];
        $f['variant']->outlets()->sync([$this->a->id, $this->b->id]);
        $product->outlets()->sync([$this->a->id, $this->b->id]);

        foreach (['owner', 'admin_outlet'] as $role) {
            $user = $this->user($role, [$this->a, $this->b]);
            $recipe = Recipe::where('product_variant_id', $f['variant']->id)->first();
            $endpoints = [
                'page' => fn () => $this->actingAs($user)->get(route('backoffice.products.edit', $product)),
                'save (re-renders every section)' => fn () => $this->actingAs($user)->putJson(route('backoffice.products.workspace.general', $product), ['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $product->name, 'code' => $product->code, 'is_active' => 1]),
                'variant form' => fn () => $this->actingAs($user)->getJson(route('backoffice.products.workspace.variants.edit-form', [$product, $f['variant']])),
                'recipe form' => fn () => $this->actingAs($user)->getJson(route('backoffice.products.workspace.recipes.edit-form', [$product, $f['variant'], $recipe])),
                'recipe options' => fn () => $this->actingAs($user)->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$product, $f['variant']])),
            ];
            $measure = function () use ($endpoints) {
                $counts = [];
                foreach ($endpoints as $label => $call) {
                    $call();   // warm any one-off framework queries
                    DB::flushQueryLog();
                    DB::enableQueryLog();
                    $call();
                    $counts[$label] = count(DB::getQueryLog());
                    DB::disableQueryLog();
                }

                return $counts;
            };

            // Some queries only exist once there is other data to read (a one-off, not per row), so the
            // baseline is taken with a few Variants already in place and then grown again.
            for ($i = 0; $i < 2; $i++) {
                $this->addVariantWithRecipeAndPromo($product, [$this->a, $this->b]);
            }

            $small = $measure();

            for ($i = 0; $i < 6; $i++) {
                $this->addVariantWithRecipeAndPromo($product, [$this->a, $this->b]);
            }

            $this->assertSame($small, $measure(), $role.': query counts must not grow with Variants / Recipes / Ingredients / Balances / Promos');
        }
    }

    // ================================================================================================
    // Contextual links come back to their own section
    // ================================================================================================

    public function test_every_contextual_link_in_each_section_returns_to_that_section(): void
    {
        $f = $this->fixtures($this->a);
        $user = $this->user('owner', [$this->a]);
        $html = $this->actingAs($user)->get(route('backoffice.products.edit', [$f['product'], 'section' => 'general']))->assertOk()->getContent();

        foreach (['variants', 'recipe', 'stock', 'promo'] as $section) {
            $panel = $this->panel($html, $section);
            preg_match_all('/href="([^"]*return_to=[^"]*)"/', $panel, $matches);
            $links = array_map('html_entity_decode', $matches[1]);

            $this->assertNotEmpty($links, $section.' has contextual links');

            foreach ($links as $link) {
                parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
                $this->assertSame(ProductWorkspace::url($f['product'], $section, null), $query['return_to'], $section.' link '.$link);
            }
        }

        // The list context the user came from survives inside the links too.
        $listUrl = '/backoffice/products?search=x';
        $withList = $this->get(route('backoffice.products.edit', [$f['product'], 'section' => 'promo', 'return_to' => $listUrl]))->getContent();
        $this->assertStringContainsString(urlencode(ProductWorkspace::url($f['product'], 'promo', $listUrl)), html_entity_decode($this->panel($withList, 'promo')));
    }

    public function test_hostile_and_malformed_return_to_values_are_dropped_everywhere_a_page_reads_them(): void
    {
        $f = $this->fixtures($this->a);
        $user = $this->user('owner', [$this->a]);
        $bad = ['https://evil.example/x', '//evil.example', '/backoffice/../admin', '/backoffice\\evil', 'javascript:alert(1)', '/other-app/path', str_repeat('/backoffice/a', 400)];
        $pages = [
            'workspace' => fn ($v) => route('backoffice.products.edit', [$f['product'], 'section' => 'stock', 'return_to' => $v]),
            'stock balances' => fn ($v) => route('backoffice.stock-balances.index', ['return_to' => $v]),
            'stock movements' => fn ($v) => route('backoffice.stock-movements.index', ['return_to' => $v]),
            'promo create' => fn ($v) => route('backoffice.promos.create', ['return_to' => $v]),
        ];

        $this->actingAs($user);

        foreach ($pages as $label => $url) {
            foreach ($bad as $value) {
                $response = $this->get($url($value));
                $this->assertSame(200, $response->getStatusCode(), $label);
                $html = $response->getContent();

                $this->assertStringNotContainsString('data-return-to-back', $html, $label.' '.substr($value, 0, 30));
                $this->assertStringNotContainsString('<input type="hidden" name="return_to"', $html, $label.' '.substr($value, 0, 30));
            }
        }
    }

    // ================================================================================================
    // Accessibility markup
    // ================================================================================================

    public function test_recipe_row_actions_and_unsaved_markers_have_accessible_names(): void
    {
        $f = $this->fixtures($this->a);
        $user = $this->user('owner', [$this->a]);
        $recipe = Recipe::where('product_variant_id', $f['variant']->id)->first();

        $form = $this->actingAs($user)->getJson(route('backoffice.products.workspace.recipes.edit-form', [$f['product'], $f['variant'], $recipe]))->json('html');
        $this->assertStringContainsString('aria-label="Hapus '.$f['ingredient']->name.' dari Recipe"', $form);

        $page = $this->get(route('backoffice.products.edit', $f['product']))->getContent();
        $this->assertSame(6, substr_count($page, 'role="img" aria-label="Belum disimpan"'));
        $this->assertStringContainsString('aria-live="polite"', $page);
        $this->assertStringContainsString('aria-modal="true"', $page);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $page);
    }

    // ================================================================================================
    // helpers
    // ================================================================================================

    /** Product + Variant + Ingredient + active Recipe + Promo, all at $outlet only. */
    private function fixtures(Outlet $outlet): array
    {
        $this->n++;
        $n = $this->n;

        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Product '.$n, 'code' => 'P'.$n, 'is_active' => true]);
        $product->outlets()->sync([$outlet->id]);
        $variant = $this->variant($product, 'Variant '.$n, 'V'.$n, [$outlet]);
        $ingredient = $this->ingredient('Ingredient '.$n, [$outlet]);
        $recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Recipe '.$n, 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 5, 'unit' => 'ml']);
        $promo = Promo::create(['name' => 'Promo '.$n, 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => true, 'active_days' => []]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);
        $promo->outlets()->sync([$outlet->id]);

        return compact('product', 'variant', 'ingredient', 'promo', 'outlet');
    }

    private function addVariantWithRecipeAndPromo(Product $product, array $outlets): void
    {
        $this->n++;
        $variant = $this->variant($product, 'Extra '.$this->n, 'X'.$this->n, $outlets);
        $recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Recipe X'.$this->n, 'is_active' => true]);

        foreach ([1, 2, 3] as $k) {
            $ingredient = $this->ingredient('Extra Ingredient '.$this->n.'-'.$k, $outlets);
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 5, 'unit' => 'ml']);

            foreach ($outlets as $outlet) {
                StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => 'outlet', 'location_id' => $outlet->id, 'qty_on_hand' => $k * 10 - 15]);
            }
        }

        $promo = Promo::create(['name' => 'Promo X'.$this->n, 'requirement_logic' => 'and', 'status' => 'active', 'is_active' => true, 'active_days' => []]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $variant->id, 'qty' => 1]);
        $promo->outlets()->sync(collect($outlets)->pluck('id')->all());
    }

    private function fingerprint(): array
    {
        $tables = ['products', 'product_variants', 'product_outlet', 'product_variant_outlet', 'ingredients', 'ingredient_outlet', 'recipes', 'recipe_items', 'stock_balances', 'stock_movements', 'promos', 'promo_outlet', 'promo_requirements', 'promo_rewards'];

        return collect($tables)->mapWithKeys(fn ($table) => [$table => md5(json_encode(DB::table($table)->orderBy(DB::raw('1'))->get()))])->all();
    }

    private function panel(string $html, string $section): string
    {
        $from = strpos($html, 'id="pw-section-'.$section.'"');
        $this->assertNotFalse($from, 'panel '.$section);

        return substr($html, $from, strpos($html, '</section>', $from) - $from);
    }

    private function variant(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'price' => 1, 'price_dine_in' => 1, 'price_delivery' => 1, 'is_active' => true]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function ingredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id, 'name' => $name, 'code' => strtoupper(str_replace([' ', '-'], '_', $name)), 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 5, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function user(string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $roleCode, 'username' => $roleCode.'-'.uniqid(), 'email' => $roleCode.uniqid().'@example.test',
            'password' => 'password', 'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
