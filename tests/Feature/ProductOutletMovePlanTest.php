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
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * PR #32 review: one resolved plan per Variant when a Product's outlets change.
 *
 * FINAL Variant outlets = (Variant outlets that stay in the Product) + (newly added Product outlets, only for an
 * ACTIVE Variant and only with the opt-in). A Variant is deactivated from that FINAL set, never from an
 * intermediate one, so "move A -> B with the option" keeps an A-only Variant active at B. Inactive Variants are
 * never activated and never gain an outlet. Preview and save come from the same plan; the Workspace and the
 * classic update behave alike.
 */
class ProductOutletMovePlanTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private Brand $brand;

    private ProductCategory $category;

    private Ingredient $milk;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'OA', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'OB', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'OC', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $ingredientCategory = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = Ingredient::create(['ingredient_category_id' => $ingredientCategory->id, 'name' => 'Milk', 'code' => 'MILK', 'unit' => 'ml', 'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true]);
        $this->milk->outlets()->sync([$this->a->id, $this->b->id, $this->c->id]);

        $this->owner = $this->makeUser('owner', [$this->a, $this->b, $this->c]);
    }

    public static function paths(): array
    {
        return ['workspace' => ['workspace'], 'classic' => ['classic']];
    }

    // ---- 1 / 2: A -> B ----------------------------------------------------------------------------

    #[DataProvider('paths')]
    public function test_moving_a_to_b_with_the_option_keeps_an_a_only_variant_active_and_moves_it(string $path): void
    {
        [$product, $onlyA] = $this->productAt([$this->a], ['Regular' => [$this->a]]);
        $recipe = $this->recipe($onlyA);
        $before = $onlyA->only(['id', 'name', 'code', 'price', 'price_dine_in', 'price_delivery', 'product_id']);

        $this->save($path, $this->owner, $product, [$this->b], true)->assertSessionHasNoErrors();

        $onlyA = $onlyA->fresh();
        $this->assertTrue((bool) $onlyA->is_active, 'still active');
        $this->assertSame([$this->b->id], $this->outletsOf($onlyA));
        $this->assertSame([$this->b->id], $this->outletsOf($product));
        $this->assertSame($before, $onlyA->only(['id', 'name', 'code', 'price', 'price_dine_in', 'price_delivery', 'product_id']), 'ID, prices and codes untouched');
        $this->assertSame($onlyA->id, $recipe->fresh()->product_variant_id);
        $this->assertSame(1, ProductVariant::count(), 'no duplicate Variant row');
    }

    #[DataProvider('paths')]
    public function test_moving_a_to_b_without_the_option_keeps_the_existing_removal_and_deactivation(string $path): void
    {
        [$product, $onlyA] = $this->productAt([$this->a], ['Regular' => [$this->a]]);

        $this->save($path, $this->owner, $product, [$this->b], false)->assertSessionHasNoErrors();

        $onlyA = $onlyA->fresh();
        $this->assertFalse((bool) $onlyA->is_active, 'left with no outlet: deactivated, as before');
        $this->assertSame([], $this->outletsOf($onlyA), 'B is not silently assigned');
        $this->assertSame([$this->b->id], $this->outletsOf($product));
        $this->assertSame(1, ProductVariant::count());
    }

    // ---- 3 / 4: A+B -> A+C ------------------------------------------------------------------------

    #[DataProvider('paths')]
    public function test_a_b_to_a_c_with_the_option_moves_a_b_only_variant_to_c_and_keeps_it_active(string $path): void
    {
        [$product, $variants] = $this->productAt([$this->a, $this->b], ['OnlyA' => [$this->a], 'OnlyB' => [$this->b], 'Both' => [$this->a, $this->b]]);

        $this->save($path, $this->owner, $product, [$this->a, $this->c], true)->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->outletsOf($product));
        $this->assertTrue((bool) $variants['OnlyB']->fresh()->is_active);
        $this->assertSame([$this->c->id], $this->outletsOf($variants['OnlyB']), 'B-only Variant is now at C');
        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->outletsOf($variants['Both']));
        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->outletsOf($variants['OnlyA']), 'an active Variant also gains the newly added outlet');
        $this->assertSame(3, ProductVariant::count());
        $this->assertSame(3, ProductVariant::where('is_active', true)->count());
    }

    #[DataProvider('paths')]
    public function test_a_b_to_a_c_without_the_option_never_assigns_c(string $path): void
    {
        [$product, $variants] = $this->productAt([$this->a, $this->b], ['OnlyA' => [$this->a], 'OnlyB' => [$this->b], 'Both' => [$this->a, $this->b]]);

        $this->save($path, $this->owner, $product, [$this->a, $this->c], false)->assertSessionHasNoErrors();

        foreach ($variants as $name => $variant) {
            $this->assertNotContains($this->c->id, $this->outletsOf($variant), $name.' must not silently get Charlie');
        }
        $this->assertFalse((bool) $variants['OnlyB']->fresh()->is_active, 'B-only Variant lost its only outlet: deactivated, as before');
        $this->assertSame([$this->a->id], $this->outletsOf($variants['Both']));
        $this->assertSame([$this->a->id], $this->outletsOf($variants['OnlyA']));
    }

    // ---- 5: inactive Variants ---------------------------------------------------------------------

    #[DataProvider('paths')]
    public function test_inactive_variants_stay_inactive_and_never_gain_outlets(string $path): void
    {
        [$product, $variants] = $this->productAt([$this->a, $this->b], ['Old' => [$this->a, $this->b], 'OldA' => [$this->a], 'Live' => [$this->a, $this->b]]);
        $variants['Old']->update(['is_active' => false]);
        $variants['OldA']->update(['is_active' => false]);

        $this->save($path, $this->owner, $product, [$this->a, $this->c], true)->assertSessionHasNoErrors();

        foreach (['Old', 'OldA'] as $name) {
            $this->assertFalse((bool) $variants[$name]->fresh()->is_active, $name.' is not activated');
            $this->assertNotContains($this->c->id, $this->outletsOf($variants[$name]), $name.' gains no new outlet');
        }
        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->outletsOf($variants['Live']));

        // A -> B with the option: the inactive A-only Variant is neither moved nor activated.
        $this->save($path, $this->owner, $product, [$this->b], true)->assertSessionHasNoErrors();
        $this->assertFalse((bool) $variants['OldA']->fresh()->is_active);
        $this->assertSame([], $this->outletsOf($variants['OldA']));
        $this->assertSame(3, ProductVariant::count());
    }

    // ---- 6 / 7: preview == save -------------------------------------------------------------------

    public function test_the_preview_reports_the_resolved_plan_without_contradictions(): void
    {
        [$product, $onlyA] = $this->productAt([$this->a], ['Regular' => [$this->a]]);

        $on = $this->preview($product, [$this->b], true)->assertOk();
        $on->assertJsonPath('deactivated_variant_ids', [])->assertJsonPath('assigned_variant_ids', [$onlyA->id])->assertJsonPath('has_consequences', true);
        $this->assertStringContainsString('data-pw-preview-assign="'.$onlyA->id.'"', $on->json('html'));
        $this->assertStringNotContainsString('Variant akan dinonaktifkan', $on->json('html'), 'it does not announce a deactivation that will not happen');
        $this->assertStringContainsString('kehilangan outlet Alpha', $on->json('html'));

        $off = $this->preview($product, [$this->b], false)->assertOk();
        $off->assertJsonPath('deactivated_variant_ids', [$onlyA->id])->assertJsonPath('assigned_variant_ids', []);
        $this->assertStringContainsString('Variant akan dinonaktifkan', $off->json('html'));
        $this->assertStringNotContainsString('data-pw-preview-assign=', $off->json('html'));

        $this->assertSame([$this->a->id], $this->outletsOf($onlyA), 'previews wrote nothing');
        $this->assertTrue((bool) $onlyA->fresh()->is_active);
    }

    public function test_an_already_inactive_variant_is_not_announced_as_a_deactivation(): void
    {
        [$product, $variants] = $this->productAt([$this->a], ['Live' => [$this->a], 'Old' => [$this->a]]);
        $variants['Old']->update(['is_active' => false]);

        $on = $this->preview($product, [$this->b], true)->assertOk();
        $on->assertJsonPath('deactivated_variant_ids', [])->assertJsonPath('assigned_variant_ids', [$variants['Live']->id]);
        $this->assertStringContainsString('Variant tetap nonaktif', $on->json('html'));

        $off = $this->preview($product, [$this->b], false)->assertOk();
        $off->assertJsonPath('deactivated_variant_ids', [$variants['Live']->id]);   // Old is inactive already: not counted
    }

    public function test_the_saved_result_matches_the_preview_in_every_scenario(): void
    {
        $scenarios = [
            'a->b on' => [[$this->a], [$this->b], true],
            'a->b off' => [[$this->a], [$this->b], false],
            'ab->ac on' => [[$this->a, $this->b], [$this->a, $this->c], true],
            'ab->ac off' => [[$this->a, $this->b], [$this->a, $this->c], false],
            'ab->a on' => [[$this->a, $this->b], [$this->a], true],
            'a->ab on' => [[$this->a], [$this->a, $this->b], true],
        ];

        foreach ($scenarios as $label => [$from, $to, $assign]) {
            [$product, $variants] = $this->productAt($from, ['One' => [$from[0]], 'Two' => $from, 'Gone' => [end($from)]], $label);
            $variants['Gone']->update(['is_active' => false]);

            $preview = $this->preview($product, $to, $assign)->assertOk();
            $wasActive = collect($variants)->mapWithKeys(fn ($variant, $name) => [$variant->id => (bool) $variant->fresh()->is_active]);
            $before = collect($variants)->mapWithKeys(fn ($variant) => [$variant->id => $this->outletsOf($variant)]);

            $this->save('workspace', $this->owner, $product, $to, $assign)->assertSessionHasNoErrors();

            $deactivated = collect($variants)->filter(fn ($variant) => $wasActive[$variant->id] && ! $variant->fresh()->is_active)->pluck('id')->sort()->values()->all();
            $assigned = collect($variants)->filter(fn ($variant) => array_diff($this->outletsOf($variant), $before[$variant->id]) !== [])->pluck('id')->sort()->values()->all();
            $previewDeactivated = collect($preview->json('deactivated_variant_ids'))->sort()->values()->all();

            $this->assertSame($previewDeactivated, $deactivated, $label.': deactivations in the preview == saved');
            $this->assertSame(collect($preview->json('assigned_variant_ids'))->sort()->values()->all(), $assigned, $label.': assignments in the preview == saved');
        }
    }

    // ---- 9: Cashier ---------------------------------------------------------------------------------

    public function test_cashier_eligibility_follows_the_resolved_plan(): void
    {
        [$product, $onlyA] = $this->productAt([$this->a], ['Regular' => [$this->a]]);
        $this->recipe($onlyA);
        $service = app(SaleEligibilityService::class);

        $this->save('workspace', $this->owner, $product, [$this->b], true)->assertSessionHasNoErrors();

        $this->assertTrue($service->variantStatuses([$onlyA->id], $this->b->id)[$onlyA->id]['eligible']);
        $this->assertSame('product_not_at_outlet', $service->variantStatuses([$onlyA->id], $this->a->id)[$onlyA->id]['reason']);

        // Without the option the Variant is not sellable at B: it was deactivated, never silently assigned.
        [$product2, $other] = $this->productAt([$this->a], ['Regular' => [$this->a]], 'second');
        $this->recipe($other);
        $this->save('workspace', $this->owner, $product2, [$this->b], false)->assertSessionHasNoErrors();
        $this->assertSame('variant_inactive', $service->variantStatuses([$other->id], $this->b->id)[$other->id]['reason']);

        // Recipe readiness is still enforced after an assignment.
        [$product3, $third] = $this->productAt([$this->a], ['Regular' => [$this->a]], 'third');
        $this->recipe($third);
        $this->milk->outlets()->sync([$this->a->id]);
        $this->save('workspace', $this->owner, $product3, [$this->b], true)->assertSessionHasNoErrors();
        $this->assertSame('ingredient_not_at_outlet', $service->variantStatuses([$third->id], $this->b->id)[$third->id]['reason']);
    }

    // ---- 10: Admin Outlet --------------------------------------------------------------------------

    public function test_admin_outlet_moves_only_inside_its_access_and_keeps_inaccessible_assignments(): void
    {
        $limited = $this->makeUser('admin_outlet', [$this->a, $this->b]);
        [$product, $variants] = $this->productAt([$this->a, $this->c], ['OnlyA' => [$this->a], 'AC' => [$this->a, $this->c]]);

        $this->save('workspace', $limited, $product, [$this->b], true)->assertOk();

        $this->assertEqualsCanonicalizing([$this->b->id, $this->c->id], $this->outletsOf($product), 'Charlie, outside its access, is kept');
        $this->assertSame([$this->b->id], $this->outletsOf($variants['OnlyA']));
        $this->assertTrue((bool) $variants['OnlyA']->fresh()->is_active);
        $this->assertEqualsCanonicalizing([$this->b->id, $this->c->id], $this->outletsOf($variants['AC']), 'Charlie kept, Bravo added');
    }

    public function test_admin_outlet_cannot_use_the_option_to_reach_an_inaccessible_outlet(): void
    {
        $limited = $this->makeUser('admin_outlet', [$this->a]);
        [$product, $variant] = $this->productAt([$this->a], ['Regular' => [$this->a]]);

        $this->save('workspace', $limited, $product, [$this->a, $this->c], true)->assertStatus(422);

        $this->assertSame([$this->a->id], $this->outletsOf($product));
        $this->assertSame([$this->a->id], $this->outletsOf($variant));
    }

    // ---- 11: classic == workspace -----------------------------------------------------------------

    public function test_classic_and_workspace_updates_produce_the_same_result(): void
    {
        $fixtures = [];
        foreach (['workspace', 'classic'] as $path) {
            [$product, $variants] = $this->productAt([$this->a, $this->b], ['OnlyA' => [$this->a], 'OnlyB' => [$this->b], 'Both' => [$this->a, $this->b], 'Old' => [$this->b]], $path);
            $variants['Old']->update(['is_active' => false]);
            $this->save($path, $this->owner, $product, [$this->a, $this->c], true)->assertSessionHasNoErrors();

            $fixtures[$path] = collect($variants)->map(fn ($variant) => [$this->outletsOf($variant), (bool) $variant->fresh()->is_active])->all();
        }

        $this->assertSame($fixtures['workspace'], $fixtures['classic']);
    }

    // ================================================================================================
    // helpers
    // ================================================================================================

    /**
     * @param  Outlet[]  $outlets
     * @param  array<string, Outlet[]>  $variants  name => outlets
     * @return array{0: Product, 1: ProductVariant|array<string, ProductVariant>} a single Variant when only one was asked for
     */
    private function productAt(array $outlets, array $variants, string $tag = 'p'): array
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube '.$tag, 'code' => 'UBE-'.strtoupper(preg_replace('/\W+/', '-', $tag)), 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        $made = [];
        foreach ($variants as $name => $variantOutlets) {
            $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => strtoupper($name).'-'.$product->id, 'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true]);
            $variant->outlets()->sync(collect($variantOutlets)->pluck('id')->all());
            $made[$name] = $variant;
        }

        return [$product, count($made) === 1 ? array_values($made)[0] : $made];
    }

    private function recipe(ProductVariant $variant): Recipe
    {
        $recipe = Recipe::create(['product_id' => $variant->product_id, 'product_variant_id' => $variant->id, 'name' => 'Recipe '.$variant->id, 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 10, 'unit' => 'ml']);

        return $recipe;
    }

    /** @param  Outlet[]  $outlets */
    private function save(string $path, User $user, Product $product, array $outlets, bool $assign)
    {
        $ids = collect($outlets)->pluck('id')->all();

        if ($path === 'workspace') {
            return $this->actingAs($user)->putJson(route('backoffice.products.workspace.outlets', $product), array_filter(['outlet_ids' => $ids, 'assign_variants' => $assign ? 1 : null]));
        }

        return $this->actingAs($user)->put(route('backoffice.products.update', $product), array_filter([
            'brand_id' => $product->brand_id, 'product_category_id' => $product->product_category_id, 'name' => $product->name, 'code' => $product->code,
            'is_active' => 1, 'outlet_ids' => $ids, 'assign_variants' => $assign ? 1 : null,
        ], fn ($value) => $value !== null));
    }

    /** @param  Outlet[]  $outlets */
    private function preview(Product $product, array $outlets, bool $assign)
    {
        return $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.outlets.preview', $product), array_filter([
            'outlet_ids' => collect($outlets)->pluck('id')->all(), 'assign_variants' => $assign ? 1 : null,
        ]));
    }

    private function outletsOf($model): array
    {
        return $model->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
    }

    private function makeUser(string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $roleCode, 'username' => $roleCode.'-'.uniqid(), 'email' => $roleCode.uniqid().'@example.test', 'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id, 'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
