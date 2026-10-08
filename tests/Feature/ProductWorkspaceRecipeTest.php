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
use App\Services\ProductWorkspace;
use App\Services\RecipeWriter;
use App\Services\SaleEligibilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * UX-F: inline Recipe workflow in the Product Workspace (RecipeWriter shared with the classic pages).
 */
class ProductWorkspaceRecipeTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private IngredientCategory $dairy;

    private Product $product;

    private Product $other;

    private ProductVariant $regular;

    private ProductVariant $large;

    private ProductVariant $otherVariant;

    private Ingredient $milk;

    private Ingredient $sugar;

    private Ingredient $ice;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'B', 'is_active' => true]);
        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => true]);
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $this->other = Product::create(['brand_id' => $brand->id, 'product_category_id' => $category->id, 'name' => 'Matcha', 'code' => 'MAT', 'is_active' => true]);
        $this->other->outlets()->sync([$this->a->id, $this->b->id]);

        $this->regular = $this->variant($this->product, 'Regular', 'UBE-R', [$this->a, $this->b]);
        $this->large = $this->variant($this->product, 'Large', 'UBE-L', [$this->a, $this->b]);
        $this->otherVariant = $this->variant($this->other, 'Regular', 'MAT-R', [$this->a, $this->b]);

        $this->dairy = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = $this->ingredient('Fresh Milk', 'ml', [$this->a, $this->b]);
        $this->sugar = $this->ingredient('Liquid Sugar', 'ml', [$this->a, $this->b]);
        $this->ice = $this->ingredient('Ice Cube', 'gram', [$this->a, $this->b]);

        $this->owner = $this->user('owner', [$this->a, $this->b]);
    }

    // ================================================================================================
    // NO RECIPE
    // ================================================================================================

    public function test_no_recipe_offers_an_explicit_create_and_opening_creates_nothing(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('data-pw-recipe-variant="'.$this->regular->id.'" data-pw-recipe-status="none"', $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.create-form', [$this->product, $this->regular], false)), $html);
        $this->assertStringContainsString('+ Buat Recipe', $html);
        $this->assertStringContainsString('Recipe tidak dibuat otomatis', $html);

        $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.create-form', [$this->product, $this->regular]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(0, Recipe::count(), 'opening the workspace or the create drawer never creates a Recipe');
    }

    public function test_manual_create_makes_an_inactive_recipe_with_items_that_copy_the_ingredient_unit(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->regular]), [
                'name' => 'Ube Latte - Regular',
                'new_items' => [
                    0 => ['ingredient_id' => $this->milk->id, 'qty' => '150'],
                    3 => ['ingredient_id' => $this->ice->id, 'qty' => '80.5'],
                    4 => ['ingredient_id' => '', 'qty' => ''],      // empty row: ignored
                ],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'recipe']);

        $recipe = Recipe::sole();
        $this->assertSame($this->product->id, (int) $recipe->product_id);
        $this->assertSame($this->regular->id, (int) $recipe->product_variant_id);
        $this->assertFalse($recipe->is_active, 'a new Recipe is never activated automatically');
        $this->assertSame(['ml', 'gram'], $recipe->items()->orderBy('id')->pluck('unit')->all());
        $this->assertSame(['150.00', '80.50'], $recipe->items()->orderBy('id')->get()->pluck('qty')->all());
        $this->assertStringContainsString('data-pw-recipe-status="inactive"', $response->json('sections.recipe'));
        $this->assertStringContainsString('dibuat sebagai nonaktif', $response->json('message'));
    }

    public function test_create_is_refused_when_the_variant_already_has_any_recipe(): void
    {
        $this->recipe($this->regular, false, [[$this->milk, 100]]);

        $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.create-form', [$this->product, $this->regular]))
            ->assertStatus(409);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->regular]), ['name' => 'Second'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['recipe']);

        $this->assertSame(1, Recipe::count());
    }

    public function test_create_validates_rows_and_saves_nothing_on_error(): void
    {
        $inactive = $this->ingredient('Old Syrup', 'ml', [$this->a, $this->b], false);
        $onlyA = $this->ingredient('Alpha Only', 'ml', [$this->a]);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->regular]), [
                'name' => '',
                'new_items' => [
                    0 => ['ingredient_id' => $this->milk->id, 'qty' => '0'],
                    1 => ['ingredient_id' => '', 'qty' => '10'],
                    2 => ['ingredient_id' => $this->milk->id, 'qty' => 'abc'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'new_items.0.qty', 'new_items.1.ingredient_id', 'new_items.2.qty']);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->regular]), [
                'name' => 'R',
                'new_items' => [
                    0 => ['ingredient_id' => $inactive->id, 'qty' => '10'],
                    1 => ['ingredient_id' => $onlyA->id, 'qty' => '10'],
                    2 => ['ingredient_id' => $this->milk->id, 'qty' => '10'],
                    5 => ['ingredient_id' => $this->milk->id, 'qty' => '12'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['new_items.0.ingredient_id', 'new_items.1.ingredient_id', 'new_items.5.ingredient_id'])
            ->assertJsonPath('errors', fn ($errors) => ! isset($errors['new_items.2.ingredient_id'])
                && $errors['new_items.0.ingredient_id'][0] === RecipeWriter::INACTIVE_INGREDIENT_MESSAGE
                && $errors['new_items.1.ingredient_id'][0] === RecipeWriter::MISSING_OUTLET_MESSAGE
                && $errors['new_items.5.ingredient_id'][0] === RecipeWriter::DUPLICATE_ITEM_MESSAGE);

        $this->assertSame(0, Recipe::count());
        $this->assertSame(0, RecipeItem::count());
    }

    // ================================================================================================
    // ONE ACTIVE RECIPE
    // ================================================================================================

    public function test_edit_form_loads_the_recipe_with_stored_values(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 46084], [$this->sugar, 12.5, 'gr']]);
        [$milkItem, $sugarItem] = $recipe->items()->orderBy('id')->get()->all();

        $html = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe]))
            ->assertOk()
            ->json('html');

        $milkRaw = (string) DB::table('recipe_items')->where('id', $milkItem->id)->value('qty');
        $this->assertStringContainsString('name="items['.$milkItem->id.'][qty]" value="'.$milkRaw.'"', $html);
        $this->assertStringContainsString('name="items['.$milkItem->id.'][original_qty]" value="'.$milkRaw.'"', $html);
        $this->assertStringContainsString('Edit Recipe #'.$recipe->id, $html);
        $this->assertStringContainsString('Ube Latte', $html);
        $this->assertStringContainsString('Unit tersimpan &quot;gr&quot; berbeda dengan unit Ingredient &quot;ml&quot; (tidak dikonversi).', $html);
        $this->assertStringContainsString('data-pw-recipe-item="'.$sugarItem->id.'"', $html);
        // Ingredients already in the Recipe are not offered again.
        $this->assertStringNotContainsString('<option value="'.$this->milk->id.'"', $html);
        $this->assertStringContainsString('<option value="'.$this->ice->id.'"', $html);
        $this->assertStringNotContainsString('value="DELETE"', $html);
    }

    public function test_save_adds_changes_and_removes_rows_and_leaves_untouched_rows_exactly_as_stored(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150], [$this->sugar, 20], [$this->ice, 46084, 'gr']]);
        [$milk, $sugar, $ice] = $recipe->items()->orderBy('id')->get()->all();
        $iceBefore = (array) DB::table('recipe_items')->where('id', $ice->id)->first();

        Carbon::setTestNow(now()->addHour());

        $response = $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), [
                'name' => $recipe->name,
                'items' => [
                    $milk->id => ['qty' => '175.5', 'original_qty' => '150.00', 'remove' => '0'],
                    $sugar->id => ['qty' => '20.00', 'original_qty' => '20.00', 'remove' => '1'],
                    $ice->id => ['qty' => $iceBefore['qty'], 'original_qty' => $iceBefore['qty'], 'remove' => '0'],
                ],
                'new_items' => [7 => ['ingredient_id' => $this->sugar->id, 'qty' => '25']],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'recipe']);

        $this->assertSame('175.50', $milk->fresh()->qty);
        $this->assertSame('ml', $milk->fresh()->unit);
        $this->assertNull(RecipeItem::find($sugar->id));
        $readded = RecipeItem::where('recipe_id', $recipe->id)->where('ingredient_id', $this->sugar->id)->sole();
        $this->assertSame('25.00', $readded->qty);
        $this->assertSame('ml', $readded->unit);
        $this->assertEquals($iceBefore, (array) DB::table('recipe_items')->where('id', $ice->id)->first(), 'untouched row: same qty, unit and updated_at');
        $this->assertTrue($recipe->fresh()->is_active, 'saving items never changes the Recipe status');
        $this->assertStringContainsString('data-pw-recipe-status="valid"', $response->json('sections.recipe'));
        $this->assertArrayHasKey('stock', $response->json('sections'));
    }

    public function test_untouched_suspicious_quantities_and_legacy_units_are_never_rewritten(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 1179, 'gr'], [$this->sugar, 46084], [$this->ice, 46154, 'ml']]);
        $other = $this->recipe($this->large, true, [[$this->milk, 46145, 'pcs']]);
        $before = DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $recipesBefore = DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        Carbon::setTestNow(now()->addDay());

        $items = [];
        foreach ($recipe->items as $item) {
            $raw = (string) DB::table('recipe_items')->where('id', $item->id)->value('qty');
            $items[$item->id] = ['qty' => $raw, 'original_qty' => $raw, 'remove' => '0'];
        }

        // Save with nothing changed (and the formatted variants of the same value).
        $items[$recipe->items[0]->id]['qty'] = '1179.00';

        $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), ['name' => $recipe->name, 'items' => $items])
            ->assertOk()
            ->assertJsonPath('message', 'Tidak ada perubahan pada Recipe "'.$recipe->name.'".');

        $this->assertEquals($before, DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertEquals($recipesBefore, DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertSame(['1179.00', '46084.00', '46154.00'], $recipe->items()->orderBy('id')->pluck('qty')->all());
        $this->assertSame(['gr', 'ml', 'ml'], $recipe->items()->orderBy('id')->pluck('unit')->all());
        $this->assertSame('46145.00', $other->items()->value('qty'));
        $this->assertSame('pcs', $other->items()->value('unit'));
    }

    public function test_renaming_only_writes_the_name(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 46084, 'gr']]);
        $itemBefore = (array) DB::table('recipe_items')->first();
        Carbon::setTestNow(now()->addHour());

        $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), [
                'name' => 'Renamed',
                'items' => [$itemBefore['id'] => ['qty' => (string) $itemBefore['qty'], 'original_qty' => (string) $itemBefore['qty']]],
            ])
            ->assertOk();

        $this->assertSame('Renamed', $recipe->fresh()->name);
        $this->assertEquals($itemBefore, (array) DB::table('recipe_items')->first());
    }

    public function test_a_qty_changed_elsewhere_since_opening_is_not_overwritten(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $item = $recipe->items->first();
        $item->update(['qty' => 160]);   // someone else, after the drawer was opened with 150

        $errors = $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), ['name' => $recipe->name, 'items' => [$item->id => ['qty' => '170', 'original_qty' => '150.00']]])
            ->assertStatus(422)
            ->json('errors');

        $this->assertSame(RecipeWriter::STALE_QTY_MESSAGE, $errors['items.'.$item->id.'.qty'][0]);

        $this->assertSame('160.00', $item->fresh()->qty);
    }

    public function test_edited_qty_follows_the_recipe_qty_rule(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $item = $recipe->items->first();

        foreach (['0', '-5', 'abc', '', '12,5'] as $bad) {
            $this->actingAs($this->owner)
                ->putJson($this->updateUrl($recipe), ['name' => $recipe->name, 'items' => [$item->id => ['qty' => $bad, 'original_qty' => '150.00']]])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['items.'.$item->id.'.qty']);
        }

        $this->assertSame('150.00', $item->fresh()->qty);
    }

    public function test_historical_duplicate_rows_are_preserved_and_no_new_duplicate_is_added(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 100], [$this->milk, 50]]);
        $before = DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $html = $this->page();
        $this->assertStringContainsString('Ingredient muncul lebih dari sekali', $html);

        $errors = $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), ['name' => $recipe->name, 'new_items' => [0 => ['ingredient_id' => $this->milk->id, 'qty' => '10']]])
            ->assertStatus(422)
            ->json('errors');

        $this->assertSame(RecipeWriter::DUPLICATE_ITEM_MESSAGE, $errors['new_items.0.ingredient_id'][0]);

        $this->assertEquals($before, DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), 'not merged, not deleted');
    }

    public function test_forged_item_ids_from_another_recipe_are_rejected(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $foreign = $this->recipe($this->otherVariant, true, [[$this->milk, 999]]);
        $foreignItem = $foreign->items->first();

        $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), ['name' => $recipe->name, 'items' => [$foreignItem->id => ['qty' => '1', 'original_qty' => '999.00', 'remove' => '1']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.'.$foreignItem->id.'.qty']);

        $this->assertSame('999.00', $foreignItem->fresh()->qty);
        $this->assertSame(1, $recipe->items()->count());
    }

    // ================================================================================================
    // INACTIVE
    // ================================================================================================

    public function test_one_inactive_recipe_can_be_edited_and_is_only_activated_explicitly(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $html = $this->page();

        $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $recipe], false)), $html);

        // Saving (even with is_active in the payload) does not activate.
        $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), ['name' => 'Edited', 'is_active' => 1])
            ->assertOk();
        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertFalse($this->eligible($this->regular));

        $response = $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $recipe]))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'recipe']);

        $this->assertTrue($recipe->fresh()->is_active);
        $this->assertTrue($this->eligible($this->regular));
        $this->assertStringContainsString('data-pw-recipe-variant="'.$this->regular->id.'" data-pw-recipe-status="valid"', $response->json('sections.recipe'));
        $this->assertStringContainsString('2/2', $response->json('header'));
    }

    public function test_multiple_inactive_recipes_are_listed_and_none_is_picked(): void
    {
        $first = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $second = $this->recipe($this->regular, false, [[$this->milk, 160]]);
        $before = DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        $html = $this->page();

        $this->assertStringContainsString('data-pw-recipe-status="inactive"', $html);
        $this->assertStringContainsString('Ada 2 Recipe nonaktif', $html);
        foreach ([$first, $second] as $recipe) {
            $this->assertStringContainsString('data-pw-recipe="'.$recipe->id.'"', $html);
            $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe], false)), $html);
        }
        $this->assertStringNotContainsString(e(route('backoffice.products.workspace.recipes.create-form', [$this->product, $this->regular], false)), $html, 'no extra Recipe is offered');
        $this->assertEquals($before, DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());

        // The user picks the second one explicitly.
        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $second]))
            ->assertOk();
        $this->assertFalse($first->fresh()->is_active);
        $this->assertTrue($second->fresh()->is_active);
    }

    // ================================================================================================
    // AMBIGUOUS
    // ================================================================================================

    public function test_ambiguous_variant_shows_the_warning_and_ids_and_offers_no_inline_mutation(): void
    {
        [$one, $two] = $this->ambiguous();
        $third = $this->recipe($this->regular, false, [[$this->milk, 1]]);

        $html = $this->page();
        // Variants are listed by name: Large, then Regular (the last row of the panel).
        $row = $this->between($html, 'data-pw-recipe-variant="'.$this->regular->id.'"', 'data-pw-panel="stock"');

        $this->assertStringContainsString('data-pw-recipe-status="ambiguous"', $row);
        $this->assertStringContainsString('Lebih dari satu Recipe aktif. Perlu review data terlebih dahulu.', $row);
        $this->assertStringContainsString('Recipe aktif: #'.$one->id.', #'.$two->id.'.', $row);
        $this->assertStringNotContainsString('data-pw-open-drawer="recipe"', $row);
        $this->assertStringNotContainsString('/workspace/variants/'.$this->regular->id.'/recipes', $row);
        $this->assertStringNotContainsString('<form', $row);
        // Every Recipe is listed, view-only, and nothing links to a second editor.
        foreach ([$one, $two, $third] as $recipe) {
            $this->assertStringContainsString('data-pw-recipe="'.$recipe->id.'"', $row);
            $this->assertStringNotContainsString(e(route('backoffice.recipes.edit', $recipe->id, false)), $row);
        }
        $this->assertSame(3, substr_count($row, 'data-pw-recipe-readonly'));
    }

    public function test_every_workspace_recipe_endpoint_refuses_an_ambiguous_variant_and_nothing_changes(): void
    {
        [$one, $two] = $this->ambiguous();
        $third = $this->recipe($this->regular, false, [[$this->milk, 1]]);
        $item = $one->items->first();
        $snapshot = fn () => [
            DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
        $before = $snapshot();

        $calls = [
            ['getJson', route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $one]), []],
            ['getJson', route('backoffice.products.workspace.recipes.create-form', [$this->product, $this->regular]), []],
            ['getJson', route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->regular]), []],
            ['postJson', route('backoffice.products.workspace.recipes.store', [$this->product, $this->regular]), ['name' => 'X']],
            ['putJson', $this->updateUrl($one), ['name' => 'X', 'items' => [$item->id => ['qty' => '5', 'original_qty' => '100.00']]]],
            ['putJson', $this->updateUrl($third), ['name' => 'X', 'new_items' => [['ingredient_id' => $this->sugar->id, 'qty' => '1']]]],
            ['patchJson', route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $third]), []],
            ['patchJson', route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $one]), []],
            ['patchJson', route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $two]), []],
        ];

        foreach ($calls as [$method, $url, $payload]) {
            $this->actingAs($this->owner)->{$method}($url, $payload)
                ->assertStatus(409)
                ->assertJsonPath('message', RecipeWriter::AMBIGUOUS_MESSAGE.' Recipe ini tidak dapat diubah dari Product Workspace.');
        }

        $this->assertEquals($before, $snapshot(), 'no Recipe disabled, picked, merged or edited');
        $this->assertTrue($one->fresh()->is_active);
        $this->assertTrue($two->fresh()->is_active);
    }

    public function test_the_writer_itself_refuses_ambiguous_variants_under_lock(): void
    {
        [$one] = $this->ambiguous();
        $writer = app(RecipeWriter::class);

        foreach ([
            fn () => $writer->saveFromWorkspace($this->product, $this->regular, $one, ['name' => 'X']),
            fn () => $writer->deactivateInWorkspace($this->product, $this->regular, $one),
            fn () => $writer->activate($this->product, $this->regular, $this->recipe($this->regular, false, [[$this->milk, 1]])),
        ] as $call) {
            try {
                $call();
                $this->fail('ambiguous Variant was mutated');
            } catch (ValidationException $e) {
                $this->assertStringStartsWith(RecipeWriter::AMBIGUOUS_MESSAGE, $e->errors()['recipe'][0]);
            }
        }

        $this->assertSame(2, Recipe::where('product_variant_id', $this->regular->id)->where('is_active', true)->count());
    }

    // ================================================================================================
    // ACTIVATION
    // ================================================================================================

    public function test_activation_is_blocked_while_another_recipe_is_active_and_the_other_is_not_disabled(): void
    {
        $active = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $inactive = $this->recipe($this->regular, false, [[$this->milk, 160]]);

        $html = $this->page();
        $this->assertStringNotContainsString(e(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $inactive], false)), $html);
        $this->assertStringContainsString('Tidak dapat diaktifkan selama Recipe #'.$active->id.' aktif', $html);

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $inactive]))
            ->assertStatus(422)
            ->assertJsonPath('errors.recipe.0', fn ($message) => str_contains($message, RecipeWriter::OTHER_ACTIVE_MESSAGE));

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_activation_rules_empty_invalid_qty_inactive_ingredient_and_missing_outlet(): void
    {
        $inactiveIngredient = $this->ingredient('Old Syrup', 'ml', [$this->a, $this->b], false);
        $onlyA = $this->ingredient('Alpha Only', 'ml', [$this->a]);

        $cases = [
            'empty' => [[], 'belum memiliki bahan'],
            'zero qty' => [[[$this->milk, 0]], 'harus lebih dari 0'],
            'inactive ingredient' => [[[$inactiveIngredient, 10]], '"Old Syrup" tidak aktif'],
            'missing outlet' => [[[$onlyA, 10]], '"Alpha Only" belum tersedia di seluruh outlet'],
        ];

        foreach ($cases as $label => [$items, $expected]) {
            Recipe::query()->delete();
            $recipe = $this->recipe($this->regular, false, $items);

            $this->actingAs($this->owner)
                ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $recipe]))
                ->assertStatus(422)
                ->assertJsonPath('errors.recipe.0', fn ($message) => str_contains($message, $expected));

            $this->assertFalse($recipe->fresh()->is_active, $label);
        }
    }

    public function test_activation_already_active_is_refused(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $recipe]))
            ->assertStatus(422)
            ->assertJsonPath('errors.recipe.0', 'Recipe ini sudah aktif.');
    }

    public function test_recipe_access_policy_is_honoured_for_every_write(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $limited = $this->user('admin_outlet', [$this->a]);   // the Variant is also sold at Bravo

        $html = $this->actingAs($limited)->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe']))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-pw-open-drawer="recipe"', $html);
        $this->assertStringContainsString('Recipe ini digunakan di outlet lain di luar akses Anda', $html);
        $this->assertStringContainsString('Fresh Milk', $html, 'read-only Recipe context is still shown');

        $this->assertWritesForbidden($limited, $recipe);

        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertSame('150.00', $recipe->items()->value('qty'));
    }

    public function test_classic_header_update_can_no_longer_create_a_second_active_recipe(): void
    {
        $active = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $inactive = $this->recipe($this->regular, false, [[$this->milk, 160]]);

        $this->actingAs($this->owner)
            ->from(route('backoffice.recipes.edit', $inactive->id))
            ->put(route('backoffice.recipes.update', $inactive->id), [
                'product_variant_id' => $this->regular->id, 'name' => 'Second', 'is_active' => 1,
            ])
            ->assertSessionHasErrors('product_variant_id');   // classic unique rule still answers first

        $this->assertFalse($inactive->fresh()->is_active);

        // Through the writer the guard holds even without the classic unique rule.
        try {
            app(RecipeWriter::class)->updateHeader($inactive, $this->regular, 'Second', true);
            $this->fail('second active Recipe was created');
        } catch (ValidationException $e) {
            $this->assertSame(RecipeWriter::OTHER_ACTIVE_MESSAGE, $e->errors()['is_active'][0]);
        }

        $this->assertTrue($active->fresh()->is_active);
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_classic_header_can_still_rename_or_deactivate_an_active_recipe_of_an_ambiguous_variant(): void
    {
        [$one, $two] = $this->ambiguous();
        $writer = app(RecipeWriter::class);

        $writer->updateHeader($one, $this->regular, 'Renamed', true);
        $this->assertSame('Renamed', $one->fresh()->name);
        $this->assertTrue($one->fresh()->is_active);

        $writer->updateHeader($two, $this->regular, $two->name, false);
        $this->assertFalse($two->fresh()->is_active);
    }

    // ================================================================================================
    // DEACTIVATION
    // ================================================================================================

    public function test_deactivation_is_explicit_and_refreshes_status_and_readiness(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $this->assertTrue($this->eligible($this->regular));

        $html = $this->page();
        $this->assertStringContainsString('Variant ini dapat menjadi tidak dapat dijual karena tidak memiliki Recipe aktif.', $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $recipe], false)), $html);

        $response = $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $recipe]))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'recipe']);

        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertSame(1, $recipe->items()->count(), 'items are kept');
        $this->assertFalse($this->eligible($this->regular));
        $this->assertStringContainsString('data-pw-recipe-variant="'.$this->regular->id.'" data-pw-recipe-status="inactive"', $response->json('sections.recipe'));
        $this->assertStringContainsString('Recipe sedang nonaktif', $response->json('sections.stock'));

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $recipe]))
            ->assertStatus(422);
    }

    public function test_row_actions_without_js_redirect_back_to_the_recipe_section(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $back = ProductWorkspace::url($this->product, 'recipe', '/backoffice/products');

        $this->actingAs($this->owner)
            ->patch(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $recipe]), ['return_to' => '/backoffice/products'])
            ->assertRedirect(url($back))
            ->assertSessionHas('success');

        $this->assertTrue($recipe->fresh()->is_active);
    }

    // ================================================================================================
    // SECURITY
    // ================================================================================================

    public function test_recipe_variant_and_product_mismatches_are_not_found(): void
    {
        $regularRecipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $largeRecipe = $this->recipe($this->large, false, [[$this->milk, 150]]);
        $otherRecipe = $this->recipe($this->otherVariant, false, [[$this->milk, 150]]);

        $urls = [
            // Recipe of another Variant of the same Product.
            route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $largeRecipe]),
            // Variant of another Product.
            route('backoffice.products.workspace.recipes.activate', [$this->product, $this->otherVariant, $otherRecipe]),
            // Recipe + Variant of another Product under this Product.
            route('backoffice.products.workspace.recipes.activate', [$this->product, $this->regular, $otherRecipe]),
        ];

        foreach ($urls as $url) {
            $this->actingAs($this->owner)->patchJson($url)->assertNotFound();
        }

        $this->actingAs($this->owner)->putJson($this->updateUrl($largeRecipe, $this->regular), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $otherRecipe]))->assertNotFound();
        $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->otherVariant]), ['name' => 'X'])->assertNotFound();
        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->regular, 'recipe' => $largeRecipe->id]))->assertNotFound();

        $this->assertFalse($regularRecipe->fresh()->is_active || $largeRecipe->fresh()->is_active || $otherRecipe->fresh()->is_active);
        $this->assertSame(3, Recipe::count());
    }

    public function test_ids_in_the_payload_are_never_trusted(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);

        $this->actingAs($this->owner)
            ->putJson($this->updateUrl($recipe), [
                'name' => 'Same',
                'product_id' => $this->other->id,
                'product_variant_id' => $this->otherVariant->id,
                'recipe_id' => 999,
                'is_active' => 1,
            ])
            ->assertOk();

        $fresh = $recipe->fresh();
        $this->assertSame($this->product->id, (int) $fresh->product_id);
        $this->assertSame($this->regular->id, (int) $fresh->product_variant_id);
        $this->assertFalse($fresh->is_active);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.recipes.store', [$this->product, $this->large]), [
                'name' => 'New', 'product_variant_id' => $this->otherVariant->id, 'product_id' => $this->other->id, 'is_active' => 1,
            ])
            ->assertOk();

        $created = Recipe::where('name', 'New')->sole();
        $this->assertSame($this->large->id, (int) $created->product_variant_id);
        $this->assertSame($this->product->id, (int) $created->product_id);
        $this->assertFalse($created->is_active);
    }

    public function test_recipe_recorded_on_another_product_is_read_only_here(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        DB::table('recipes')->where('id', $recipe->id)->update(['product_id' => $this->other->id]);   // historical anomaly

        $html = $this->page();
        $this->assertStringContainsString('tercatat pada Product lain', $html);
        $this->assertStringNotContainsString(e(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe], false)), $html);

        $this->actingAs($this->owner)->patchJson(route('backoffice.products.workspace.recipes.deactivate', [$this->product, $this->regular, $recipe]))->assertNotFound();
        $this->assertSame($this->other->id, (int) $recipe->fresh()->product_id, 'not repaired');
        $this->assertTrue($recipe->fresh()->is_active);
    }

    public function test_roles_without_product_access_and_out_of_scope_products_are_rejected(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);
        $gudang = $this->user('staff_gudang', [$this->a, $this->b]);
        $kasir = $this->user('kasir', [$this->a, $this->b]);
        $outsider = $this->user('admin_outlet', [Outlet::create(['name' => 'Zulu', 'code' => 'Z', 'is_active' => true])]);

        foreach ([$gudang, $kasir, $outsider] as $user) {
            $this->assertWritesForbidden($user, $recipe);
        }

        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertSame(1, Recipe::count());
    }

    public function test_recipe_routes_keep_the_web_middleware_stack_with_csrf(): void
    {
        foreach (['create-form', 'ingredient-options', 'store', 'edit-form', 'update', 'activate', 'deactivate'] as $name) {
            $route = Route::getRoutes()->getByName('backoffice.products.workspace.recipes.'.$name);
            $this->assertNotNull($route, $name);
            $this->assertContains('web', $route->gatherMiddleware(), $name);
            $this->assertContains('auth', $route->gatherMiddleware(), $name);
        }

        $this->assertFalse(Route::has('backoffice.products.workspace.recipes.destroy'), 'no Recipe delete in the workspace');
    }

    // ================================================================================================
    // DATA SAFETY / INGREDIENT DRAWER / CLASSIC
    // ================================================================================================

    public function test_opening_the_workspace_and_every_form_with_historical_anomalies_changes_nothing(): void
    {
        $this->ambiguous();
        $this->recipe($this->large, true, []);                                         // empty active
        $this->recipe($this->otherVariant, false, [[$this->milk, 0], [$this->sugar, 1179, 'gr']]);
        $inactiveOnly = $this->recipe($this->large, false, [[$this->milk, 46154, 'pcs']]);
        $snapshot = fn () => [
            DB::table('recipes')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            DB::table('recipe_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
        $before = $snapshot();

        $this->page();
        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->large, $inactiveOnly]))->assertOk();
        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->large]))->assertOk();

        $this->assertEquals($before, $snapshot());
    }

    public function test_ingredient_options_follow_the_recipe_rules_for_the_refresh_after_an_inline_ingredient(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $onlyA = $this->ingredient('Alpha Only', 'ml', [$this->a]);

        // Ingredient created from the Recipe drawer (UX-E drawer, return_section=recipe).
        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), [
                'ingredient_category_id' => $this->dairy->id, 'name' => 'Oat Milk', 'unit' => 'ml', 'ingredient_type' => 'raw',
                'minimum_stock' => 0, 'cost_per_unit' => 0, 'is_active' => 1, 'outlet_ids' => [$this->a->id, $this->b->id],
                'return_section' => 'recipe',
            ])
            ->assertOk()
            ->assertJson(['section' => 'recipe']);
        $oat = Ingredient::where('name', 'Oat Milk')->sole();

        $ids = collect($this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->regular, 'recipe' => $recipe->id]))
            ->assertOk()
            ->json('ingredients'))->pluck('id')->all();

        $this->assertContains($oat->id, $ids);
        $this->assertNotContains($this->milk->id, $ids, 'already in the Recipe');
        $this->assertNotContains($onlyA->id, $ids, 'not in every Variant outlet');
        $this->assertSame(1, $recipe->items()->count(), 'the new Ingredient is not inserted automatically');
    }

    public function test_the_recipe_drawer_offers_the_existing_ingredient_drawer(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);

        $html = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe]))
            ->json('html');

        $this->assertStringContainsString('data-pw-open-drawer="ingredient"', $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.ingredients.create-form', [$this->product, 'return_section' => 'recipe'], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->regular, 'recipe' => $recipe->id], false)), $html);

        $page = $this->page();
        $this->assertStringContainsString('data-pw-drawer="recipe"', $page);
        $this->assertStringContainsString('data-pw-drawer="ingredient"', $page);
    }

    public function test_classic_recipe_routes_still_work_through_the_shared_writer(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.store'), ['product_variant_id' => $this->regular->id, 'name' => 'Classic', 'is_active' => 1])
            ->assertRedirect()
            ->assertSessionHas('success', 'Recipe berhasil ditambahkan.');
        $recipe = Recipe::sole();
        $this->assertTrue($recipe->is_active);

        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $recipe->id), ['ingredient_id' => $this->ice->id, 'qty' => '30'])
            ->assertSessionHas('success');
        $item = $recipe->items()->sole();
        $this->assertSame('gram', $item->unit);

        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $recipe->id), ['ingredient_id' => $this->ice->id, 'qty' => '31'])
            ->assertSessionHas('error', RecipeWriter::DUPLICATE_ITEM_MESSAGE);

        $item->update(['unit' => 'gr']);   // legacy unit
        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.items.update', [$recipe->id, $item->id]), ['qty' => '32.5'])
            ->assertSessionHas('success');
        $this->assertSame('32.50', $item->fresh()->qty);
        $this->assertSame('gr', $item->fresh()->unit, 'qty edit keeps the stored unit');

        $this->actingAs($this->owner)
            ->delete(route('backoffice.recipes.destroy', $recipe->id))
            ->assertSessionHas('success', 'Recipe berhasil dinonaktifkan.');
        $this->assertFalse($recipe->fresh()->is_active);
        $this->assertSame(1, Recipe::count(), 'classic "delete" only deactivates');

        $this->actingAs($this->owner)
            ->delete(route('backoffice.recipes.items.destroy', [$recipe->id, $item->id]))
            ->assertSessionHas('success');
        $this->assertSame(0, $recipe->items()->count());
    }

    // ---- helpers ------------------------------------------------------------------------------------

    // ================================================================================================
    // RECIPE MANAGEMENT: the standalone Recipe editor is the normal place; the old Workspace deep link still works
    // ================================================================================================

    public function test_the_recipe_edit_url_opens_the_standalone_editor_for_the_right_recipe_and_the_old_deep_link_still_works(): void
    {
        $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $large = $this->recipe($this->large, true, [[$this->milk, 200], [$this->sugar, 30]]);
        $this->recipe($this->otherVariant, true, [[$this->milk, 10]]);
        $returnTo = '/backoffice/recipes?status=active&search=Ube';

        // Standalone editor: no redirect, the Product Workspace is not entered
        $standalone = $this->actingAs($this->owner)
            ->get(route('backoffice.recipes.edit', [$large, 'return_to' => $returnTo]))
            ->assertOk()
            ->assertViewIs('backoffice.recipes.edit');
        $this->assertSame($large->id, $standalone->viewData('recipe')->id, 'the Large Recipe, not Regular or another Product');
        $this->assertTrue($standalone->viewData('canMutate'));
        $this->assertStringNotContainsString('data-product-workspace', $standalone->getContent());
        $this->assertStringNotContainsString('data-pw-open-recipe-url', $standalone->getContent());
        $this->assertStringContainsString('Liquid Sugar', $standalone->getContent());

        // The old Workspace deep link (bookmarks) keeps rendering and opening the right drawer
        $page = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe', 'recipe' => $large->id, 'return_to' => $returnTo]))->assertOk();
        $html = $page->getContent();

        // the Large Recipe's own drawer is the one to open, not Regular's or another Product's
        $this->assertStringContainsString('data-pw-open-recipe-url="'.e(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->large, $large], false)).'"', $html);
        $this->assertSame(1, substr_count($html, 'data-pw-open-recipe-url='));
        $this->assertStringContainsString('data-pw-section="recipe"', $html);
        $page->assertViewHas('closeUrl', $returnTo)->assertViewHas('closeLabel', 'Kembali ke Recipes');
        $this->assertStringContainsString('Kembali ke Recipes</a>', $html);

        // opened from the Products list, "back" still means the Products list
        $fromProducts = $this->get(route('backoffice.products.edit', [$this->product, 'return_to' => '/backoffice/products?search=ube']));
        $fromProducts->assertViewHas('closeUrl', '/backoffice/products?search=ube#product-'.$this->product->id)->assertViewHas('closeLabel', 'Kembali ke Products');

        // and that URL really serves Large's items
        $form = $this->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->large, $large]))->assertOk()->json('html');
        $this->assertStringContainsString('Liquid Sugar', $form);
    }

    public function test_the_recipe_deep_link_forces_the_recipe_section_but_only_for_a_drawer_the_user_may_open(): void
    {
        $recipe = $this->recipe($this->regular, false, [[$this->milk, 150]]);   // inactive Recipes open too
        $other = $this->recipe($this->otherVariant, true, [[$this->milk, 10]]);
        $this->actingAs($this->owner);

        $html = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'general', 'recipe' => $recipe->id]))->assertOk()->getContent();
        $this->assertStringContainsString('data-pw-section="recipe"', $html);
        $this->assertStringContainsString('data-pw-open-recipe-url=', $html);

        foreach ([$other->id, 999999, 'abc', '1 OR 1=1', '', '-1', ['x']] as $bad) {
            $html = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock', 'recipe' => $bad]))->assertOk()->getContent();

            $this->assertStringNotContainsString('data-pw-open-recipe-url=', $html, json_encode($bad));
            $this->assertStringContainsString('data-pw-section="stock"', $html, 'the requested section is kept');
        }
    }

    public function test_a_recipe_of_a_variant_with_several_active_recipes_opens_standalone_and_is_never_picked(): void
    {
        [$one, $two] = $this->ambiguous();
        $before = $this->recipeFingerprint();
        $this->actingAs($this->owner);

        foreach ([$one, $two] as $recipe) {
            // the standalone editor opens exactly this Recipe (nothing is chosen for the user) and writes nothing
            $standalone = $this->get(route('backoffice.recipes.edit', [$recipe, 'return_to' => '/backoffice/recipes']))
                ->assertOk()->assertViewIs('backoffice.recipes.edit');
            $this->assertSame($recipe->id, $standalone->viewData('recipe')->id);
            $this->assertSame($before, $this->recipeFingerprint(), 'opening the standalone editor writes nothing');

            $page = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'general', 'recipe' => $recipe->id]))->assertOk();
            $html = $page->getContent();

            // lands on the Recipe section and brings that Recipe into view, but opens nothing and offers no control
            $this->assertStringContainsString('data-pw-section="recipe"', $html);
            $this->assertStringContainsString('data-pw-focus-recipe="'.$recipe->id.'"', $html);
            $this->assertStringNotContainsString('data-pw-open-recipe-url=', $html);
            $this->assertStringContainsString('data-pw-recipe-ambiguous', $html);
            $this->assertStringContainsString('Recipe aktif: #'.$one->id.', #'.$two->id.'.', $html);
        }

        $this->assertSame($before, $this->recipeFingerprint(), 'nothing activated, deactivated or edited');
        $this->assertTrue($one->fresh()->is_active);
        $this->assertTrue($two->fresh()->is_active);
    }

    public function test_opening_the_edit_url_or_the_old_deep_link_writes_nothing(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 46084.0], [$this->sugar, 0.6]]);
        $before = $this->recipeFingerprint();
        $this->actingAs($this->owner);

        $this->get(route('backoffice.recipes.edit', [$recipe, 'return_to' => '/backoffice/recipes']))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe', 'recipe' => $recipe->id]))->assertOk();
        $this->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe]))->assertOk();

        $this->assertSame($before, $this->recipeFingerprint());
    }

    public function test_the_edit_url_keeps_its_authorization_and_view_only_recipes_open_read_only(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);

        // not signed in
        $this->get(route('backoffice.recipes.edit', $recipe))->assertRedirect();

        // a limited user may view a Recipe used at outlets beyond their access: it opens READ-ONLY in the standalone editor
        $limited = $this->user('admin_outlet', [$this->a]);
        $readOnly = $this->actingAs($limited)->get(route('backoffice.recipes.edit', $recipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->assertFalse($readOnly->viewData('canMutate'));
        $readOnly->assertSee('id="recipe-readonly-notice"', false)->assertDontSee('Update Header')->assertDontSee('Tambah Recipe Item');

        // the old Workspace deep link still renders it read-only as well
        $html = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe', 'recipe' => $recipe->id]))->assertOk()->getContent();
        $this->assertStringContainsString('data-pw-focus-recipe="'.$recipe->id.'"', $html);
        $this->assertStringNotContainsString('data-pw-open-recipe-url=', $html);
        $this->assertStringNotContainsString('data-pw-open-drawer="recipe"', $html);
        $this->assertStringContainsString('data-pw-recipe-readonly', $html);
        $this->assertStringContainsString('Recipe ini digunakan di outlet lain di luar akses Anda', $html);
        $this->assertWritesForbidden($limited, $recipe);

        // a Recipe used only at an outlet nobody here can access is not viewable at all
        $foreign = $this->variant($this->other, 'Foreign', 'MAT-F', [$this->b]);
        $foreignRecipe = $this->recipe($foreign, true, [[$this->milk, 5]]);
        $this->actingAs($limited)->get(route('backoffice.recipes.edit', $foreignRecipe))->assertForbidden();

        // Warehouse staff have no Product pages: unchanged, they open the same standalone editor
        $warehouse = $this->user('staff_gudang', [$this->a, $this->b]);
        $this->actingAs($warehouse)->get(route('backoffice.recipes.edit', $recipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->get(route('backoffice.products.edit', $this->product))->assertForbidden();

        // roles without Recipe access stay out
        $cashier = $this->user('kasir', [$this->a]);
        $this->actingAs($cashier)->get(route('backoffice.recipes.edit', $recipe))->assertForbidden();
    }

    public function test_a_recipe_recorded_on_another_product_fails_safely_for_owner_and_never_opens_the_classic_editor(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $recipe->update(['product_id' => $this->other->id]);   // legacy data: not repaired, not edited
        $before = $this->recipeFingerprint();

        foreach (['owner' => $this->owner, 'admin_pusat' => $this->user('admin_pusat', [$this->a, $this->b])] as $user) {
            $this->actingAs($user)
                ->get(route('backoffice.recipes.edit', [$recipe, 'return_to' => '/backoffice/recipes?status=active']))
                ->assertRedirect(url('/backoffice/recipes?status=active#recipe-'.$recipe->id))
                ->assertSessionHas('error', fn ($message) => str_contains($message, 'Product yang berbeda') && str_contains($message, 'tidak ada data yang diubah'));
        }

        // without return_to: the Recipes list
        $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $recipe))->assertRedirect(url('/backoffice/recipes'));

        $this->assertSame($before, $this->recipeFingerprint(), 'zero writes');
    }

    public function test_a_recipe_with_many_ingredients_opens_complete(): void
    {
        $items = [];
        foreach (range(1, 25) as $n) {
            $items[] = [$this->ingredient('Bahan '.str_pad((string) $n, 2, '0', STR_PAD_LEFT), 'gram', [$this->a, $this->b]), $n + 0.25];
        }
        $recipe = $this->recipe($this->regular, true, $items);

        $form = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe]))
            ->assertOk()->json('html');

        $this->assertSame(25, preg_match_all('/name="items\[\d+\]\[qty\]"/', $form));
        $this->assertStringContainsString('Bahan 01', $form);
        $this->assertStringContainsString('Bahan 25', $form);
    }

    public function test_the_recipe_drawer_no_longer_links_to_a_second_editor(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);

        $form = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->regular, $recipe]))
            ->json('html');

        $this->assertStringNotContainsString('Halaman Recipe lama', $form);
        $this->assertStringNotContainsString(route('backoffice.recipes.edit', $recipe->id, false), $form);
    }

    public function test_owner_and_admin_pusat_manage_recipes_in_the_standalone_editor_while_the_workspace_tab_stays_hidden(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $listUrl = '/backoffice/recipes?status=active';

        foreach (['owner' => $this->owner, 'admin_pusat' => $this->user('admin_pusat', [$this->a, $this->b])] as $role => $user) {
            $this->actingAs($user);

            // Recipes list -> Edit: the standalone editor, not a redirect into the Product Workspace
            $this->assertStringContainsString(e(route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $listUrl])), $this->get($listUrl)->assertOk()->getContent());
            $edit = $this->get(route('backoffice.recipes.edit', [$recipe, 'return_to' => $listUrl]))->assertOk()->assertViewIs('backoffice.recipes.edit');
            $this->assertTrue($edit->viewData('canMutate'), $role);
            $edit->assertSee('Update Header', false);

            // Create is the standalone form too
            $this->get(route('backoffice.recipes.create', ['return_to' => $listUrl]))->assertOk()->assertViewIs('backoffice.recipes.create');
        }

        // write flow: save / update return to the standalone Recipes list (the given return_to), never the Workspace
        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $this->regular->id, 'name' => 'Regular v2', 'is_active' => 1, 'return_to' => $listUrl])
            ->assertRedirect(url($listUrl.'#recipe-'.$recipe->id));
        $this->assertSame('Regular v2', $recipe->fresh()->name);

        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $this->regular->id, 'name' => 'Regular v3', 'is_active' => 1])
            ->assertRedirect(route('backoffice.recipes.index'));

        // the Product Workspace rail still has no Recipe tab (and no Stock / Promo tab) ...
        $page = $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'general']))->assertOk()->getContent();
        $this->assertSame(1, preg_match('#<nav class="pw-nav".*?</nav>#s', $page, $rail));
        foreach (['recipe', 'stock', 'promo'] as $hidden) {
            $this->assertStringNotContainsString('data-pw-nav="'.$hidden.'"', $rail[0], $hidden);
        }

        // ... and the old deep link still renders safely
        $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe', 'recipe' => $recipe->id]))->assertOk()->assertSee('data-pw-section="recipe"', false);
    }

    public function test_the_recipes_list_edit_link_leads_to_the_standalone_editor(): void
    {
        $recipe = $this->recipe($this->regular, true, [[$this->milk, 150]]);
        $listUrl = '/backoffice/recipes?status=active';

        $html = $this->actingAs($this->owner)->get($listUrl)->assertOk()->getContent();
        $editUrl = route('backoffice.recipes.edit', [$recipe->id, 'return_to' => $listUrl]);

        $this->assertStringContainsString(e($editUrl), $html);

        // the Edit links open the standalone editor in a side panel of the list itself (no page change); the href stays
        // a real URL, so open-in-new-tab and no-JS keep working
        $this->assertStringContainsString('data-recipe-edit', $html);
        // "Tambah Recipe" opens the create form in the same panel; the href stays the real create URL
        $this->assertMatchesRegularExpression('#<a href="[^"]*recipes/create[^"]*" class="btn btn-green" data-recipe-edit data-recipe-title="Tambah Recipe">#', $html);
        $this->assertStringContainsString('id="rcp-drawer"', $html);
        // the layout's .shell has a backdrop-filter (it would become the reference of position:fixed): the panel is moved to <body>
        $this->assertStringContainsString('document.body.appendChild(drawer)', $html);
        $this->assertStringContainsString('<iframe class="rcp-drawer-frame" id="rcp-drawer-frame"', $html);

        // no redirect: the standalone editor renders and the Product Workspace is not entered
        $standalone = $this->get($editUrl)->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->assertSame($recipe->id, $standalone->viewData('recipe')->id);
        $this->assertStringNotContainsString('data-product-workspace', $standalone->getContent());
        $this->assertStringNotContainsString('section=recipe', $html);
    }

    private function recipeFingerprint(): array
    {
        return json_decode(json_encode([
            DB::table('recipes')->orderBy('id')->get(),
            DB::table('recipe_items')->orderBy('id')->get(),
        ]), true);
    }

    private function assertWritesForbidden(User $user, Recipe $recipe): void
    {
        $variant = $recipe->variant;
        $calls = [
            ['getJson', route('backoffice.products.workspace.recipes.edit-form', [$this->product, $variant, $recipe]), []],
            ['putJson', $this->updateUrl($recipe), ['name' => 'Hacked']],
            ['patchJson', route('backoffice.products.workspace.recipes.activate', [$this->product, $variant, $recipe]), []],
            ['patchJson', route('backoffice.products.workspace.recipes.deactivate', [$this->product, $variant, $recipe]), []],
            ['postJson', route('backoffice.products.workspace.recipes.store', [$this->product, $this->large]), ['name' => 'Hacked']],
        ];

        foreach ($calls as [$method, $url, $payload]) {
            $this->actingAs($user)->{$method}($url, $payload)->assertForbidden();
        }

        $this->assertSame(0, Recipe::where('name', 'Hacked')->count());
    }

    /** Two active Recipes for Regular. */
    private function ambiguous(): array
    {
        return [
            $this->recipe($this->regular, true, [[$this->milk, 100]]),
            $this->recipe($this->regular, true, [[$this->milk, 120]]),
        ];
    }

    private function page(): string
    {
        return $this->actingAs($this->owner)
            ->get(route('backoffice.products.edit', [$this->product, 'section' => 'recipe']))
            ->assertOk()
            ->getContent();
    }

    private function updateUrl(Recipe $recipe, ?ProductVariant $variant = null): string
    {
        return route('backoffice.products.workspace.recipes.update', [$this->product, $variant ?? $recipe->variant, $recipe]);
    }

    private function eligible(ProductVariant $variant): bool
    {
        return app(SaleEligibilityService::class)->variantStatuses([$variant->id], $this->a->id)[$variant->id]['eligible'];
    }

    /** @param  array<int, array{0: Ingredient, 1: float|int, 2?: string}>  $items */
    private function recipe(ProductVariant $variant, bool $active, array $items): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $variant->product_id, 'product_variant_id' => $variant->id,
            'name' => $variant->product->name.' - '.$variant->name, 'is_active' => $active,
        ]);

        foreach ($items as $item) {
            RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $item[0]->id, 'qty' => $item[1], 'unit' => $item[2] ?? $item[0]->unit]);
        }

        return $recipe->load('items');
    }

    private function variant(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code,
            'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function ingredient(string $name, string $unit, array $outlets, bool $active = true): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->dairy->id, 'name' => $name, 'code' => strtoupper(str_replace(' ', '_', $name)), 'unit' => $unit,
            'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 10, 'is_active' => $active,
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

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $to = strpos($html, $end, $from);
        $this->assertNotFalse($from);
        $this->assertNotFalse($to);

        return substr($html, $from, $to - $from);
    }
}
