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
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\CleanupDeletionService;
use App\Services\VariantWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Final hardening: Product / Variant / Ingredient are NEVER physically deleted by any application path.
 * Every legacy destructive URL either goes through CleanupDeletionService (flag, role, impact, typed
 * confirmation, tombstone) or only deactivates. Recipe is the one entity that is really deleted.
 */
class CleanupLegacyPathsTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Brand $brand;

    private ProductCategory $category;

    private IngredientCategory $ingredientCategory;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['backoffice.destructive_delete_enabled' => true]);

        $this->outlet = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->owner = $this->makeUser('owner', 'owner');
    }

    // ---- 1. legacy Product permanent-delete route ----------------------------------------------------------

    public function test_legacy_permanent_route_is_rejected_when_the_flag_is_off(): void
    {
        config(['backoffice.destructive_delete_enabled' => false]);
        [$product] = $this->productWithRecipe('Kopi');
        $before = $this->counts();

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy-permanent', $product->id), ['confirmation' => $product->name])->assertForbidden();

        $this->assertSame($before, $this->counts());
        $this->assertNull(Product::find($product->id)->deleted_at);
    }

    public function test_legacy_permanent_route_rejects_every_limited_role(): void
    {
        [$product] = $this->productWithRecipe('Kopi');
        $before = $this->counts();

        foreach (['admin_outlet', 'staff_gudang', 'kasir'] as $role) {
            $this->actingAs($this->makeUser($role, $role))
                ->delete(route('backoffice.products.destroy-permanent', $product->id), ['confirmation' => $product->name])
                ->assertForbidden();
        }

        // even for an id that does not exist: the role is checked before anything is looked up
        $this->actingAs($this->makeUser('ao9', 'admin_outlet'))->delete(route('backoffice.products.destroy-permanent', 99999), ['confirmation' => 'x'])->assertForbidden();

        $this->assertSame($before, $this->counts());
        $this->assertNull(Product::find($product->id)->deleted_at);
    }

    public function test_legacy_permanent_route_needs_the_typed_confirmation(): void
    {
        [$product] = $this->productWithRecipe('Kopi');
        $before = $this->counts();

        foreach ([null, '', 'kopi', 'Kopi '.'x'] as $typed) {
            $this->actingAs($this->owner)
                ->delete(route('backoffice.products.destroy-permanent', $product->id), $typed === null ? [] : ['confirmation' => $typed])
                ->assertSessionHas('error');
        }

        $this->assertSame($before, $this->counts());
        $this->assertNull(Product::find($product->id)->deleted_at);
    }

    public function test_legacy_permanent_route_with_approval_tombstones_and_never_physically_deletes(): void
    {
        foreach (['owner', 'admin_pusat'] as $role) {
            $user = $role === 'owner' ? $this->owner : $this->makeUser($role, $role);
            [$product, $variant, $recipe] = $this->productWithRecipe('Kopi '.$role);   // unused: this is what used to be hard-deleted
            $sold = $this->productWithRecipe('Terjual '.$role);
            $this->sale($sold[0], $sold[1]);
            $history = $this->historyFingerprint();

            foreach ([[$product, $variant, $recipe], $sold] as [$p, $v, $r]) {
                $this->actingAs($user)
                    ->delete(route('backoffice.products.destroy-permanent', $p->id), ['confirmation' => $p->name])
                    ->assertSessionHas('success');

                // the row is still there, tombstoned (not removed)
                $this->assertSame(1, DB::table('products')->where('id', $p->id)->count(), 'physical row kept');
                $this->assertNotNull(DB::table('products')->where('id', $p->id)->value('deleted_at'));
                $this->assertSame(1, DB::table('product_variants')->where('id', $v->id)->count());
                $this->assertNotNull(DB::table('product_variants')->where('id', $v->id)->value('deleted_at'));
                $this->assertNull(Recipe::find($r->id), 'recipes are the one thing really deleted');
                $this->assertSame(0, RecipeItem::where('recipe_id', $r->id)->count());
            }

            $this->assertSame($history, $this->historyFingerprint());
        }
    }

    // ---- other legacy destructive routes ---------------------------------------------------------------------

    public function test_legacy_ingredient_route_is_gated_and_tombstones(): void
    {
        $ingredient = $this->ingredient('Gula');

        config(['backoffice.destructive_delete_enabled' => false]);
        $this->actingAs($this->owner)->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => 'Gula'])->assertForbidden();
        config(['backoffice.destructive_delete_enabled' => true]);

        $this->actingAs($this->makeUser('ao', 'admin_outlet'))->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => 'Gula'])->assertForbidden();
        $this->actingAs($this->owner)->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => 'salah'])->assertSessionHas('error');
        $this->assertNull(DB::table('ingredients')->where('id', $ingredient->id)->value('deleted_at'));

        $this->actingAs($this->owner)->delete(route('backoffice.ingredients.destroy', $ingredient), ['confirmation' => 'Gula'])->assertSessionHas('success');
        $this->assertSame(1, DB::table('ingredients')->where('id', $ingredient->id)->count());
        $this->assertNotNull(DB::table('ingredients')->where('id', $ingredient->id)->value('deleted_at'));
    }

    public function test_legacy_deactivate_routes_never_delete_or_tombstone(): void
    {
        [$product, $variant] = $this->productWithRecipe('Kopi');
        $before = $this->counts();

        $this->actingAs($this->owner)->delete(route('backoffice.products.destroy', $product->id))->assertSessionHas('success');
        $this->actingAs($this->owner)->delete(route('backoffice.variants.destroy', $variant->id))->assertSessionHas('success');

        $this->assertSame($before, $this->counts(), 'nothing removed');
        $this->assertNull(DB::table('products')->where('id', $product->id)->value('deleted_at'));
        $this->assertNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));
        $this->assertFalse((bool) $product->fresh()->is_active);
        $this->assertFalse((bool) $variant->fresh()->is_active);
    }

    public function test_old_product_index_buttons_are_gone_and_the_legacy_url_is_a_distinct_route(): void
    {
        $this->productWithRecipe('Kopi');

        foreach ([true, false] as $flag) {
            config(['backoffice.destructive_delete_enabled' => $flag]);
            $html = $this->actingAs($this->owner)->get(route('backoffice.products.index'))->assertOk()->getContent();
            $this->assertStringNotContainsString('destroy-permanent', $html);
            $this->assertStringNotContainsString('/permanent', $html);
            $this->assertStringNotContainsString('data-bo-confirm-label="Hapus Permanen"', $html);
        }

        $this->assertSame('/backoffice/products/5/permanent', route('backoffice.products.destroy-permanent', 5, false));
        $this->actingAs($this->owner)->delete('/backoffice/products/abc/permanent')->assertNotFound();
    }

    // ---- 2. Variant group editor ---------------------------------------------------------------------------

    public function test_group_edit_omitting_an_existing_variant_is_refused_and_deletes_nothing(): void
    {
        $product = $this->product('Teh');
        $hot = $this->variant($product, 'Hot');
        $ice = $this->variant($product, 'Ice');   // unused: this is the case that used to be hard-deleted
        $before = $this->counts();

        foreach (['owner' => $this->owner, 'admin_outlet' => $this->makeUser('ao', 'admin_outlet')] as $label => $user) {
            $response = $this->actingAs($user)->put(route('backoffice.variants.update', $hot), [
                'product_id' => $product->id,
                'variants' => [$this->row($hot)],   // $ice left out
            ]);

            $response->assertSessionHasErrors('variants');
            $message = session('errors')->first('variants');
            $this->assertStringContainsString('Ice', $message, $label);
            $this->assertStringContainsString('Hapus dari Sistem', $message, $label);
        }

        $this->assertSame($before, $this->counts());
        $this->assertNull(DB::table('product_variants')->where('id', $ice->id)->value('deleted_at'));
        $this->assertSame(2, ProductVariant::withTrashed()->where('product_id', $product->id)->count());
    }

    public function test_group_edit_still_updates_and_adds_variants_and_touches_no_sibling(): void
    {
        $product = $this->product('Teh');
        $hot = $this->variant($product, 'Hot');
        $ice = $this->variant($product, 'Ice');
        $iceBefore = DB::table('product_variants')->where('id', $ice->id)->first();

        $this->actingAs($this->owner)->put(route('backoffice.variants.update', $hot), [
            'product_id' => $product->id,
            'variants' => [
                array_merge($this->row($hot), ['name' => 'Hot Besar', 'price_dine_in' => 25000]),
                $this->row($ice),
                ['id' => '', 'name' => 'Jumbo', 'price_dine_in' => 30000, 'price_delivery' => 32000, 'outlet_ids' => [$this->outlet->id], 'is_active' => 1],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('Hot Besar', $hot->fresh()->name);
        $this->assertSame(3, ProductVariant::where('product_id', $product->id)->count());
        $this->assertSame('Ice', $ice->fresh()->name);
        $this->assertSame(20000.0, (float) $ice->fresh()->price_dine_in, 'sibling prices untouched');
        $this->assertSame($iceBefore->code, $ice->fresh()->code);
        $this->assertStringContainsString('Hapus dari Sistem', VariantWriter::REMOVAL_MESSAGE);
    }

    public function test_group_edit_omission_is_refused_even_for_a_variant_with_history_or_a_promo(): void
    {
        $product = $this->product('Teh');
        $hot = $this->variant($product, 'Hot');
        $ice = $this->variant($product, 'Ice');
        $this->sale($product, $ice);
        $promo = Promo::create(['name' => 'P', 'requirement_logic' => 'and', 'requirement_qty' => 1, 'reward_type' => 'percent', 'reward_value' => 10, 'reward_qty' => 0, 'status' => 'active', 'is_active' => true]);
        PromoRequirement::create(['promo_id' => $promo->id, 'product_variant_id' => $ice->id, 'qty' => 1]);
        $before = $this->counts();
        $history = $this->historyFingerprint();

        $this->actingAs($this->owner)->put(route('backoffice.variants.update', $hot), ['product_id' => $product->id, 'variants' => [$this->row($hot)]])->assertSessionHasErrors('variants');

        $this->assertSame($before, $this->counts());
        $this->assertSame($history, $this->historyFingerprint());
        $this->assertSame(1, PromoRequirement::where('promo_id', $promo->id)->count());
    }

    // ---- 3-5. nothing physically deletes the three masters; Recipe is the only hard delete --------------------

    public function test_no_application_code_can_physically_delete_a_product_variant_or_ingredient(): void
    {
        $forbidden = [
            '/->forceDelete\(/', '/forceDeleteQuietly/', '/forceDestroy\(/', '/->deleteQuietly\(/', '/->forceRestore/',
            '/\b(Product|ProductVariant|Ingredient)::destroy\(/',
            '/DB::table\(\s*[\'"](products|product_variants|ingredients)[\'"]\s*\)[^;]*->(delete|truncate)\(/s',
            '/DB::(statement|delete|unprepared)\([^;]*delete\s+from\s+[`"]?(products|product_variants|ingredients)\b/is',
        ];

        // the only tools allowed to physically wipe tables: the CLI reset command (not reachable from any route)
        $allowed = [str_replace('\\', '/', app_path('Console/Commands/ResetMasterDataCommand.php'))];
        $hits = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            if (! str_ends_with($path, '.php') || in_array($path, $allowed, true)) {
                continue;
            }

            $code = file_get_contents($path);
            // comments may describe the old behaviour; only code counts
            $code = preg_replace('~/\*.*?\*/|//[^\n]*|#[^\n]*~s', '', $code);

            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $code)) {
                    $hits[] = basename($path).' ~ '.$pattern;
                }
            }
        }

        $this->assertSame([], $hits, 'physical delete of Product / Variant / Ingredient found');
    }

    public function test_the_reset_command_is_not_reachable_from_any_route_or_the_scheduler(): void
    {
        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('ResetMasterData', $route->getActionName());
        }

        $this->assertStringNotContainsString('reset-master-data', file_get_contents(base_path('routes/console.php')));
        $this->assertStringNotContainsString('ResetMasterData', file_get_contents(base_path('routes/web.php')));
    }

    public function test_every_delete_route_for_the_three_masters_leaves_the_physical_rows_in_place(): void
    {
        [$product, $variant, , $susu] = $this->productWithRecipe('Kopi');
        $free = $this->ingredient('Bebas');

        $physical = fn () => [
            DB::table('products')->count(), DB::table('product_variants')->count(), DB::table('ingredients')->count(),
        ];
        $before = $physical();

        $calls = [
            route('backoffice.products.destroy', $product->id),
            route('backoffice.products.destroy-permanent', $product->id),
            route('backoffice.variants.destroy', $variant->id),
            route('backoffice.ingredients.destroy', $free->id),
            route('backoffice.cleanup.destroy', ['ingredient', $susu->id]),
            route('backoffice.cleanup.destroy', ['variant', $variant->id]),
            route('backoffice.cleanup.destroy', ['product', $product->id]),
        ];

        foreach ($calls as $url) {
            foreach ([$product->name, $variant->name, $free->name, $susu->name, ''] as $typed) {
                $this->actingAs($this->owner)->delete($url, ['confirmation' => $typed]);
            }
        }

        $this->assertSame($before, $physical(), 'rows were only tombstoned or untouched, never removed');
        $this->assertNotNull(DB::table('products')->where('id', $product->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('ingredients')->where('id', $free->id)->value('deleted_at'));
    }

    public function test_cleanup_always_tombstones_and_recipe_is_the_only_physical_delete(): void
    {
        [$product, $variant, $recipe] = $this->productWithRecipe('Kopi');
        $other = $this->productWithRecipe('Lain');
        $gula = $this->ingredient('Gula');
        $this->sale($product, $variant);
        $history = $this->historyFingerprint();
        $tables = ['products', 'product_variants', 'ingredients', 'recipes', 'recipe_items'];
        $count = fn () => array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));
        $before = $count();

        $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['product', $product->id]), ['confirmation' => $product->name])->assertSessionHas('success');
        $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['ingredient', $gula->id]), ['confirmation' => 'Gula'])->assertSessionHas('success');

        $after = $count();
        $this->assertSame($before['products'], $after['products'], 'Product row kept');
        $this->assertSame($before['product_variants'], $after['product_variants'], 'Variant row kept');
        $this->assertSame($before['ingredients'], $after['ingredients'], 'Ingredient row kept');
        $this->assertSame($before['recipes'] - 1, $after['recipes'], 'exactly the Product\'s one Recipe is gone');
        $this->assertSame($before['recipe_items'] - 1, $after['recipe_items']);
        $this->assertNotNull(Recipe::find($other[2]->id));
        $this->assertNotNull(DB::table('products')->where('id', $product->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('product_variants')->where('id', $variant->id)->value('deleted_at'));
        $this->assertNotNull(DB::table('ingredients')->where('id', $gula->id)->value('deleted_at'));
        $this->assertSame($history, $this->historyFingerprint(), 'sales, items and payment values preserved');

        // and a direct Recipe cleanup is the one genuinely physical delete
        $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['recipe', $other[2]->id]), ['confirmation' => $other[2]->name])->assertSessionHas('success');
        $this->assertSame(0, DB::table('recipes')->where('id', $other[2]->id)->count());
    }

    // ---- hazards a tombstone would otherwise reopen -------------------------------------------------------------

    public function test_a_category_whose_only_products_or_ingredients_are_tombstoned_cannot_be_deleted(): void
    {
        // the FK cascades category -> products / ingredients; a tombstoned row is still a row
        [$product] = $this->productWithRecipe('Kopi');
        $ingredient = $this->ingredient('Gula');
        $this->sale($product, $product->variants()->first());
        $history = $this->historyFingerprint();

        $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['product', $product->id]), ['confirmation' => $product->name]);
        $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['ingredient', $ingredient->id]), ['confirmation' => 'Gula']);

        $this->actingAs($this->owner)->delete(route('backoffice.menu-categories.destroy', $this->category->id))->assertSessionHas('error');
        $this->actingAs($this->owner)->delete(route('backoffice.ingredient-categories.destroy', $this->ingredientCategory->id))->assertSessionHas('error');

        $this->assertSame(1, DB::table('product_categories')->where('id', $this->category->id)->count());
        $this->assertSame(1, DB::table('ingredient_categories')->where('id', $this->ingredientCategory->id)->count());
        $this->assertSame(1, DB::table('products')->where('id', $product->id)->count());
        $this->assertSame(1, DB::table('ingredients')->where('id', $ingredient->id)->count());
        $this->assertSame($history, $this->historyFingerprint());

        // a category that never had anything is still deletable
        $empty = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kosong', 'code' => 'KOSONG', 'is_active' => true]);
        $this->actingAs($this->owner)->delete(route('backoffice.menu-categories.destroy', $empty->id))->assertSessionHas('success');
        $this->assertSame(0, DB::table('product_categories')->where('id', $empty->id)->count());
    }

    public function test_a_recipe_that_points_across_products_blocks_a_product_cleanup(): void
    {
        [$product, $variant] = $this->productWithRecipe('Pemilik Variant');
        [$other, , $otherRecipe] = $this->productWithRecipe('Pemilik Recipe');

        // anomaly: the other Product's Recipe is attached to this Product's Variant
        $otherRecipe->update(['product_variant_id' => $variant->id]);
        $snapshot = DB::table('recipes')->where('id', $otherRecipe->id)->first();

        foreach ([$product, $other] as $p) {
            $impact = $this->actingAs($this->owner)->getJson(route('backoffice.cleanup.impact', ['product', $p->id]))->json('impact');
            $this->assertFalse($impact['can_delete']);
            $this->assertSame('recipe_inconsistent', $impact['blockers'][0]['code']);

            $this->actingAs($this->owner)->delete(route('backoffice.cleanup.destroy', ['product', $p->id]), ['confirmation' => $p->name])->assertSessionHas('error');
            $this->assertNull(DB::table('products')->where('id', $p->id)->value('deleted_at'));
        }

        $this->assertEquals($snapshot, DB::table('recipes')->where('id', $otherRecipe->id)->first(), 'the foreign Recipe was neither deleted nor detached');
    }

    public function test_every_foreign_key_into_the_masters_is_known_to_the_cleanup_service(): void
    {
        $found = [];

        foreach (DB::select("select name from sqlite_master where type = 'table'") as $table) {
            foreach (DB::select('pragma foreign_key_list('.$table->name.')') as $fk) {
                if (in_array($fk->table, ['products', 'product_variants', 'ingredients', 'recipes'], true)) {
                    $found[] = $table->name.'.'.$fk->from.' -> '.$fk->table.' ('.$fk->on_delete.')';
                }
            }
        }

        sort($found);

        // Each of these is handled in CleanupDeletionService (blocked, tombstoned-in-place or removed as
        // configuration) or is history that a tombstone leaves alone. A NEW reference must be considered there
        // first - this failing is the reminder.
        $this->assertSame([
            'ingredient_outlet.ingredient_id -> ingredients (CASCADE)',
            'ingredient_production_items.ingredient_id -> ingredients (CASCADE)',
            'ingredient_production_recipe_items.input_ingredient_id -> ingredients (CASCADE)',
            'ingredient_production_recipes.output_ingredient_id -> ingredients (CASCADE)',
            'ingredient_productions.output_ingredient_id -> ingredients (CASCADE)',
            'product_outlet.product_id -> products (CASCADE)',
            'product_variant_outlet.product_variant_id -> product_variants (CASCADE)',
            'product_variants.product_id -> products (CASCADE)',
            'promo_requirements.product_variant_id -> product_variants (CASCADE)',
            'promo_rewards.product_variant_id -> product_variants (SET NULL)',
            'promos.requirement_product_variant_id -> product_variants (SET NULL)',
            'promos.reward_product_variant_id -> product_variants (SET NULL)',
            'purchase_receipt_items.ingredient_id -> ingredients (RESTRICT)',
            'recipe_items.ingredient_id -> ingredients (CASCADE)',
            'recipe_items.recipe_id -> recipes (CASCADE)',
            'recipes.product_id -> products (CASCADE)',
            'recipes.product_variant_id -> product_variants (SET NULL)',
            'sales_transaction_items.product_id -> products (SET NULL)',
            'sales_transaction_items.product_variant_id -> product_variants (SET NULL)',
            'stock_adjustment_items.ingredient_id -> ingredients (CASCADE)',
            'stock_balances.ingredient_id -> ingredients (CASCADE)',
            'stock_movements.ingredient_id -> ingredients (CASCADE)',
        ], $found);

        $this->assertTrue(CleanupDeletionService::enabled());
    }

    // ---- helpers -------------------------------------------------------------------------------------------------

    private function counts(): array
    {
        $tables = ['products', 'product_variants', 'ingredients', 'recipes', 'recipe_items', 'product_outlet', 'product_variant_outlet', 'ingredient_outlet'];

        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all()
            + ['deleted' => DB::table('products')->whereNotNull('deleted_at')->count() + DB::table('product_variants')->whereNotNull('deleted_at')->count() + DB::table('ingredients')->whereNotNull('deleted_at')->count()];
    }

    private function historyFingerprint(): array
    {
        $tables = ['sales_transactions', 'sales_transaction_items', 'stock_movements', 'stock_transfers', 'stock_balances', 'stock_adjustments', 'purchase_receipts'];

        return collect($tables)->mapWithKeys(fn ($table) => [$table => md5(json_encode(DB::table($table)->orderBy('id')->get()))])->all();
    }

    private function row(ProductVariant $variant): array
    {
        return [
            'id' => $variant->id, 'name' => $variant->name, 'code' => $variant->code,
            'price_dine_in' => 20000, 'price_delivery' => 22000, 'outlet_ids' => [$this->outlet->id], 'is_active' => 1,
        ];
    }

    /** @return array{0: Product, 1: ProductVariant, 2: Recipe, 3: Ingredient} */
    private function productWithRecipe(string $name): array
    {
        $product = $this->product($name);
        $variant = $this->variant($product, 'Reg');
        $susu = Ingredient::where('name', 'Susu')->first() ?? $this->ingredient('Susu');
        $recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Resep '.$name, 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $susu->id, 'qty' => 10, 'unit' => 'ml']);

        return [$product, $variant, $recipe, $susu];
    }

    private function product(string $name): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name, 'code' => strtoupper(str_replace(' ', '-', $name)).'-'.uniqid(), 'is_active' => true]);
        $product->outlets()->sync([$this->outlet->id]);

        return $product;
    }

    private function variant(Product $product, string $name): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => strtoupper($product->code.'-'.$name), 'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true]);
        $variant->outlets()->sync([$this->outlet->id]);

        return $variant;
    }

    private function ingredient(string $name): Ingredient
    {
        $ingredient = Ingredient::create(['ingredient_category_id' => $this->ingredientCategory->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => 'ml', 'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true]);
        $ingredient->outlets()->sync([$this->outlet->id]);

        return $ingredient;
    }

    private function sale(Product $product, ProductVariant $variant): SalesTransaction
    {
        $transaction = SalesTransaction::create(['transaction_number' => 'TRX-'.uniqid(), 'user_id' => $this->owner->id, 'outlet_id' => $this->outlet->id, 'subtotal' => 20000, 'grand_total' => 20000, 'status' => 'completed', 'payment_method' => 'cash', 'payment_status' => 'paid', 'amount_paid' => 20000, 'change_amount' => 0]);
        $transaction->items()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'product_name' => $product->name, 'variant_name' => $variant->name, 'qty' => 1, 'price' => 20000, 'line_total' => 20000]);

        return $transaction;
    }

    private function makeUser(string $username, string $roleCode): User
    {
        $user = User::create(['name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id, 'outlet_id' => $this->outlet->id, 'is_active' => true]);
        $user->outlets()->sync([$this->outlet->id]);

        return $user;
    }
}
