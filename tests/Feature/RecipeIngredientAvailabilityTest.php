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
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\RecipeWriter;
use App\Services\SaleEligibilityService;
use App\Services\StockDeductionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Feedback #09: Recipe Ingredient availability. A Recipe is GLOBAL per Variant, so an Ingredient must be
 * active and available at every SELLABLE outlet of the Variant (active Variant outlets AND active Product
 * outlets). Ineligible Ingredients are shown with the reason instead of vanishing, and both Recipe imports
 * obey the same rules as the editor, all-or-nothing per Variant.
 */
class RecipeIngredientAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $tb;

    private Outlet $bx;

    private Outlet $ta;

    private Outlet $closed;

    private Product $product;

    private ProductVariant $variant;

    private IngredientCategory $category;

    private Ingredient $milk;

    private Ingredient $syrup;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tb = Outlet::create(['name' => 'Tanjung Barat', 'code' => 'TB', 'is_active' => true]);
        $this->bx = Outlet::create(['name' => 'Bintaro Xchange', 'code' => 'BX', 'is_active' => true]);
        $this->ta = Outlet::create(['name' => 'Taman Anggrek', 'code' => 'TA', 'is_active' => true]);
        $this->closed = Outlet::create(['name' => 'Closed Outlet', 'code' => 'OLD', 'is_active' => false]);

        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $productCategory = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'K', 'is_active' => true]);
        $this->category = IngredientCategory::create(['name' => 'Syrup', 'code' => 'SY', 'is_active' => true]);

        $this->product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $productCategory->id, 'name' => 'Italian Soda', 'code' => 'IS', 'is_active' => true]);
        $this->product->outlets()->sync([$this->tb->id, $this->bx->id, $this->ta->id]);
        $this->variant = $this->variant($this->product, 'Regular', 'IS-R', [$this->tb, $this->bx, $this->ta]);

        $this->milk = $this->ingredient('Fresh Milk', [$this->tb, $this->bx, $this->ta]);
        $this->syrup = $this->ingredient('Syrup Peach', [$this->tb, $this->bx]);   // missing at Taman Anggrek

        $this->owner = $this->user('owner', [$this->tb, $this->bx, $this->ta]);
    }

    // ================================================================================================
    // Required outlets + selectable / unavailable split
    // ================================================================================================

    public function test_active_ingredient_at_all_required_outlets_is_selectable(): void
    {
        $choices = app(RecipeWriter::class)->ingredientChoices($this->variant);

        $this->assertContains($this->milk->id, $choices['eligible']->pluck('id')->all());
        $this->assertNotContains($this->milk->id, $choices['unavailable']->pluck('ingredient.id')->all());
        $this->assertEqualsCanonicalizing(['Bintaro Xchange', 'Tanjung Barat', 'Taman Anggrek'], $choices['required_names']);

        $recipe = $this->recipe([]);
        app(RecipeWriter::class)->addItem($recipe, $this->milk, 100);
        $this->assertSame(1, $recipe->items()->count());
    }

    public function test_ingredient_missing_from_ta_is_visible_with_a_clear_warning_in_every_editor(): void
    {
        $recipe = $this->recipe([$this->milk]);

        // Writer: not selectable, but listed with the reason naming the outlet.
        $choices = app(RecipeWriter::class)->ingredientChoices($this->variant, [$this->milk->id]);
        $this->assertNotContains($this->syrup->id, $choices['eligible']->pluck('id')->all());
        $row = $choices['unavailable']->firstWhere('ingredient.id', $this->syrup->id);
        $this->assertSame('belum tersedia di Taman Anggrek', $row['reason']);

        // Add Ingredient: actionable message (name, missing outlet, required scope, who can fix it).
        try {
            app(RecipeWriter::class)->addItem($recipe, $this->syrup, 10);
            $this->fail('expected a refusal');
        } catch (ValidationException $e) {
            $message = $e->errors()['ingredient_id'][0];
            $this->assertStringContainsString('"Syrup Peach" belum tersedia di Taman Anggrek', $message);
            $this->assertStringContainsString('Bintaro Xchange, Taman Anggrek, Tanjung Barat', $message);
            $this->assertStringContainsString('Owner/Admin Pusat', $message);
        }
        $this->assertSame(1, $recipe->items()->count());

        // Workspace options endpoint: eligible unchanged, unavailable carries the reason.
        $json = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->variant, 'recipe' => $recipe->id]))
            ->assertOk()->json();
        $this->assertNotContains($this->syrup->id, collect($json['ingredients'])->pluck('id')->all());
        $this->assertSame('belum tersedia di Taman Anggrek', collect($json['unavailable'])->firstWhere('id', $this->syrup->id)['reason']);

        // Workspace drawer: disabled option in an "unavailable" group.
        $html = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->variant, $recipe]))
            ->assertOk()->json('html');
        $this->assertStringContainsString('data-pw-recipe-unavailable', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$this->syrup->id.'" disabled>[^<]*belum tersedia di Taman Anggrek/', $html);

        // Classic editor: same.
        $page = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $recipe))->assertOk();
        $page->assertSee('Tidak tersedia (tidak bisa dipilih)');
        $this->assertMatchesRegularExpression('/<option value="'.$this->syrup->id.'" disabled>\s*Syrup Peach[^<]*belum tersedia di Taman Anggrek/', $page->getContent());

        // A forced POST for the hidden-or-disabled option is still refused by the server.
        $this->actingAs($this->owner)->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->syrup->id, 'qty' => 5])
            ->assertSessionHasErrors('ingredient_id');
        $this->assertSame(1, $recipe->items()->count());
    }

    public function test_inactive_ingredient_is_not_selectable_for_a_new_item_and_says_why(): void
    {
        $inactive = $this->ingredient('Syrup Dead', [$this->tb, $this->bx, $this->ta], false);

        $choices = app(RecipeWriter::class)->ingredientChoices($this->variant);
        $this->assertNotContains($inactive->id, $choices['eligible']->pluck('id')->all());
        $this->assertSame('Ingredient nonaktif', $choices['unavailable']->firstWhere('ingredient.id', $inactive->id)['reason']);

        $this->expectException(ValidationException::class);
        app(RecipeWriter::class)->addItem($this->recipe([]), $inactive, 5);
    }

    public function test_inactive_outlet_on_the_variant_does_not_block_recipe_editing(): void
    {
        $this->variant->outlets()->attach($this->closed->id);   // stale pivot row on a closed outlet
        $writer = app(RecipeWriter::class);

        $this->assertEqualsCanonicalizing([$this->tb->id, $this->bx->id, $this->ta->id], $writer->requiredOutletIds($this->variant)->all());
        $this->assertContains($this->milk->id, $writer->selectableIngredients($this->variant)->pluck('id')->all());

        $recipe = $this->recipe([]);
        $writer->addItem($recipe, $this->milk, 10);
        $this->assertSame([], $writer->activationProblems($recipe->fresh(), $this->variant));
        $this->assertTrue($this->eligibility($this->tb)['eligible']);
    }

    public function test_variant_outlets_outside_the_product_or_inactive_are_excluded_from_the_required_scope(): void
    {
        $this->product->outlets()->detach($this->ta->id);              // Variant still lists TA
        $writer = app(RecipeWriter::class);

        $this->assertEqualsCanonicalizing([$this->tb->id, $this->bx->id], $writer->requiredOutletIds($this->variant->fresh())->all());
        // Syrup (TB + BX) is now fine; no data was changed to get there.
        $this->assertContains($this->syrup->id, $writer->selectableIngredients($this->variant->fresh())->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$this->tb->id, $this->bx->id, $this->ta->id], $this->variant->outlets()->pluck('outlets.id')->all());

        // An inactive PRODUCT outlet is excluded as well.
        $this->bx->update(['is_active' => false]);
        $this->assertSame([$this->tb->id], $writer->requiredOutletIds($this->variant->fresh())->all());
    }

    public function test_no_sellable_outlet_never_reads_as_ready(): void
    {
        $this->product->outlets()->sync([]);
        $recipe = $this->recipe([$this->milk], false);

        $this->assertTrue(app(RecipeWriter::class)->requiredOutletIds($this->variant->fresh())->isEmpty());
        $problems = app(RecipeWriter::class)->activationProblems($recipe, $this->variant->fresh());
        $this->assertContains(RecipeWriter::NO_SELLABLE_OUTLET_MESSAGE, $problems);

        $html = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->variant, $recipe]))
            ->assertOk()->json('html');
        $this->assertStringContainsString('belum tersedia di outlet aktif manapun', $html);
    }

    public function test_existing_invalid_item_stays_visible_and_its_qty_remains_editable(): void
    {
        $recipe = $this->recipe([$this->milk, $this->syrup]);   // Syrup is not at TA: invalid, but stored
        $item = $recipe->items()->where('ingredient_id', $this->syrup->id)->first();

        $html = $this->actingAs($this->owner)
            ->getJson(route('backoffice.products.workspace.recipes.edit-form', [$this->product, $this->variant, $recipe]))
            ->assertOk()->json('html');
        $this->assertStringContainsString('Syrup Peach', $html);
        $this->assertStringContainsString('Belum tersedia di: Taman Anggrek', $html);
        $this->assertStringContainsString('Owner/Admin Pusat', $html);

        $page = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $recipe))->assertOk();
        $page->assertSee('data-recipe-item-warning', false);
        $page->assertSee('Belum tersedia di Taman Anggrek', false);
        $this->assertStringContainsString('Syrup Peach', $page->getContent());

        $this->actingAs($this->owner)->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 45])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertSame(45.0, (float) $item->qty);
        $this->assertSame($this->syrup->id, (int) $item->ingredient_id);                 // never replaced / merged / removed
        $this->assertSame(2, $recipe->items()->count());
    }

    public function test_workspace_save_with_a_forged_unavailable_ingredient_is_refused_with_the_clear_message(): void
    {
        $recipe = $this->recipe([$this->milk]);

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.recipes.update', [$this->product, $this->variant, $recipe]), [
                'name' => $recipe->name,
                'new_items' => [0 => ['ingredient_id' => $this->syrup->id, 'qty' => '10']],
            ])
            ->assertStatus(422)
            ->assertJsonPath('errors', fn ($errors) => str_contains($errors['new_items.0.ingredient_id'][0], 'belum tersedia di Taman Anggrek'));

        $this->assertSame(1, $recipe->items()->count());
    }

    public function test_activation_message_names_the_missing_outlet_and_guidance(): void
    {
        $recipe = $this->recipe([$this->milk, $this->syrup], false);

        $problems = implode(' ', app(RecipeWriter::class)->activationProblems($recipe, $this->variant));
        $this->assertStringContainsString('"Syrup Peach" belum tersedia di seluruh outlet Variant ini (kurang: Taman Anggrek)', $problems);
        $this->assertStringContainsString('Owner/Admin Pusat', $problems);

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->variant, $recipe]))
            ->assertStatus(422);
        $this->assertFalse($recipe->fresh()->is_active);
    }

    // ================================================================================================
    // CSV import
    // ================================================================================================

    public function test_csv_import_rejects_inactive_ingredients_and_leaves_the_variant_untouched(): void
    {
        $inactive = $this->ingredient('Syrup Dead', [$this->tb, $this->bx, $this->ta], false);
        $recipe = $this->recipe([$this->milk]);

        $errors = $this->importCsv("IS-R,Fresh Milk,200,1\nIS-R,Syrup Dead,10,1");

        $this->assertStringContainsString('Baris 3', implode("\n", $errors));
        $this->assertStringContainsString('"Syrup Dead" tidak aktif', implode("\n", $errors));
        $this->assertSame(100.0, (float) $recipe->items()->first()->qty);               // the valid Milk row was NOT applied either
        $this->assertSame(1, $recipe->items()->count());
        $this->assertNull(RecipeItem::where('ingredient_id', $inactive->id)->first());
    }

    public function test_csv_import_rejects_ingredients_missing_at_a_required_outlet(): void
    {
        $errors = $this->importCsv("IS-R,Fresh Milk,200,1\nIS-R,Syrup Peach,30,1");

        $this->assertStringContainsString('Baris 3: Ingredient "Syrup Peach" belum tersedia di Taman Anggrek', implode("\n", $errors));
        $this->assertSame(0, Recipe::count(), 'no Recipe is created when one of its rows is rejected');
        $this->assertSame(0, RecipeItem::count());
    }

    public function test_csv_mixed_rows_never_leave_a_partially_mutated_active_recipe(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $other = $this->ingredient('Sugar', [$this->tb, $this->bx, $this->ta]);

        $this->importCsv("IS-R,Sugar,5,1\nIS-R,Fresh Milk,250,1\nIS-R,Syrup Peach,30,1\nIS-R,Ghost Ingredient,1,1\nIS-R,Fresh Milk,abc,1");

        $recipe->refresh();
        $this->assertTrue($recipe->is_active);
        $this->assertSame(['Fresh Milk' => 100.0], $recipe->items()->with('ingredient')->get()->mapWithKeys(fn ($i) => [$i->ingredient->name => (float) $i->qty])->all());
        $this->assertNull(RecipeItem::where('ingredient_id', $other->id)->first());
    }

    public function test_csv_valid_import_reuses_the_existing_recipe_and_ids(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $itemId = $recipe->items()->value('id');
        $sugar = $this->ingredient('Sugar', [$this->tb, $this->bx, $this->ta]);

        $this->importCsv("IS-R,Fresh Milk,150,1\nIS-R,Sugar,8,1");

        $this->assertSame(1, Recipe::count());
        $this->assertSame($recipe->id, Recipe::value('id'));
        $this->assertSame($itemId, (int) $recipe->items()->where('ingredient_id', $this->milk->id)->value('id'));
        $this->assertSame(150.0, (float) $recipe->items()->where('ingredient_id', $this->milk->id)->value('qty'));
        $this->assertSame(8.0, (float) $recipe->items()->where('ingredient_id', $sugar->id)->value('qty'));
        $this->assertTrue($recipe->fresh()->is_active);
        $this->assertSame(1, Recipe::where('product_variant_id', $this->variant->id)->where('is_active', true)->count());
    }

    public function test_csv_new_valid_recipe_is_created_and_activated_only_when_complete(): void
    {
        $this->importCsv("IS-R,Fresh Milk,120,1");

        $recipe = Recipe::sole();
        $this->assertTrue($recipe->is_active);
        $this->assertTrue($this->eligibility($this->ta)['eligible']);

        Recipe::query()->delete();
        $this->importCsv("IS-R,Fresh Milk,120,0");
        $this->assertFalse(Recipe::sole()->is_active, 'an explicit is_active=0 stays inactive');
    }

    public function test_import_never_touches_a_variant_with_several_active_recipes(): void
    {
        $first = $this->recipe([$this->milk]);
        $second = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'name' => 'Duplicate', 'is_active' => true]);
        $second->items()->create(['ingredient_id' => $this->milk->id, 'qty' => 7, 'unit' => 'ml']);

        $errors = $this->importCsv("IS-R,Fresh Milk,999,1");

        $this->assertStringContainsString('Lebih dari satu Recipe aktif', implode("\n", $errors));
        $this->assertSame(100.0, (float) $first->items()->value('qty'));
        $this->assertSame(7.0, (float) $second->items()->value('qty'));
        $this->assertSame(2, Recipe::count());
    }

    // ================================================================================================
    // Excel import
    // ================================================================================================

    public function test_excel_import_follows_the_same_validation(): void
    {
        $inactive = $this->ingredient('Syrup Dead', [$this->tb, $this->bx, $this->ta], false);
        $recipe = $this->recipe([$this->milk]);

        $errors = $this->importExcel([
            ['*Italian Soda', 'Regular', '', '', ''],
            ['', '', 'Fresh Milk', 250, 'ml'],
            ['', '', 'Syrup Peach', 30, 'ml'],
            ['', '', 'Syrup Dead', 10, 'ml'],
        ]);

        $report = implode("\n", $errors);
        $this->assertStringContainsString('"Syrup Peach" belum tersedia di Taman Anggrek', $report);
        $this->assertStringContainsString('"Syrup Dead" tidak aktif', $report);
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));
        $this->assertSame(1, $recipe->items()->count());
        $this->assertNull(RecipeItem::where('ingredient_id', $inactive->id)->first());
    }

    public function test_excel_import_valid_rows_apply_and_activate_a_complete_recipe(): void
    {
        $this->importExcel([
            ['*Italian Soda', 'Regular', '', '', ''],
            ['', '', 'Fresh Milk', 120, 'ml'],
        ]);

        $recipe = Recipe::sole();
        $this->assertTrue($recipe->is_active);
        $this->assertSame(120.0, (float) $recipe->items()->value('qty'));
    }

    public function test_excel_import_never_blindly_activates_an_incomplete_recipe(): void
    {
        // An existing INACTIVE Recipe that already holds an Ingredient missing at TA.
        $recipe = $this->recipe([$this->milk, $this->syrup], false);

        $errors = $this->importExcel([
            ['*Italian Soda', 'Regular', '', '', ''],
            ['', '', 'Fresh Milk', 130, 'ml'],
        ]);

        $recipe->refresh();
        $this->assertFalse($recipe->is_active, 'activation is a request that must pass the activation rules');
        $this->assertStringContainsString('TIDAK diaktifkan', implode("\n", $errors));
        $this->assertStringContainsString('"Syrup Peach" belum tersedia', implode("\n", $errors));
        $this->assertSame(130.0, (float) $recipe->items()->where('ingredient_id', $this->milk->id)->value('qty'));   // the valid row itself is fine

        // And a Variant with no sellable outlet gets an inactive Recipe, not an active one.
        $this->product->outlets()->sync([]);
        Recipe::query()->delete();
        $this->importExcel([
            ['*Italian Soda', 'Regular', '', '', ''],
            ['', '', 'Fresh Milk', 130, 'ml'],
        ]);
        $this->assertFalse(Recipe::sole()->is_active);
    }

    // ================================================================================================
    // Authorization
    // ================================================================================================

    public function test_admin_outlet_cannot_import_or_edit_a_recipe_shared_with_outlets_outside_their_access(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $adminTa = $this->user('admin_outlet', [$this->ta]);

        $errors = $this->importCsv("IS-R,Fresh Milk,999,1", $adminTa);
        $this->assertStringContainsString('di luar akses', implode("\n", $errors));
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));

        $this->actingAs($adminTa)
            ->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->variant]))
            ->assertForbidden();
        $this->actingAs($adminTa)->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->milk->id, 'qty' => 1])->assertForbidden();
        $this->assertSame(1, $recipe->items()->count());
    }

    public function test_admin_outlet_with_access_to_every_outlet_of_the_variant_can_work(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $sugar = $this->ingredient('Sugar', [$this->tb, $this->bx, $this->ta]);
        $admin = $this->user('admin_outlet', [$this->tb, $this->bx, $this->ta]);

        $this->importCsv("IS-R,Sugar,5,1", $admin);

        $this->assertSame(5.0, (float) $recipe->items()->where('ingredient_id', $sugar->id)->value('qty'));
        $this->actingAs($admin)->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->variant]))->assertOk();
    }

    public function test_owner_can_fix_the_assignment_and_the_ingredient_becomes_selectable_and_sellable(): void
    {
        $recipe = $this->recipe([$this->milk], false);
        $this->assertNotContains($this->syrup->id, app(RecipeWriter::class)->selectableIngredients($this->variant)->pluck('id')->all());

        // Existing Ingredient management flow (not an automatic action).
        $this->actingAs($this->owner)->put(route('backoffice.ingredients.update', $this->syrup), [
            'ingredient_category_id' => $this->category->id, 'name' => 'Syrup Peach', 'unit' => 'ml', 'ingredient_type' => 'raw',
            'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => 1,
            'outlet_ids' => [$this->tb->id, $this->bx->id, $this->ta->id],
        ])->assertSessionHasNoErrors();

        $this->assertContains($this->syrup->id, app(RecipeWriter::class)->selectableIngredients($this->variant)->pluck('id')->all());
        app(RecipeWriter::class)->addItem($recipe, $this->syrup, 30);

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.recipes.activate', [$this->product, $this->variant, $recipe->fresh()]))
            ->assertOk();
        $this->assertTrue($recipe->fresh()->is_active);
        foreach ([$this->tb, $this->bx, $this->ta] as $outlet) {
            $this->assertTrue($this->eligibility($outlet)['eligible'], $outlet->name);
        }
    }

    // ================================================================================================
    // Cashier / stock invariants
    // ================================================================================================

    public function test_cashier_still_rejects_missing_and_inactive_ingredients_with_distinct_reasons(): void
    {
        $this->recipe([$this->milk, $this->syrup]);

        $this->assertTrue($this->eligibility($this->tb)['eligible'], 'the Syrup is valid at TB: the cashier only judges the outlet it is sold at');
        $ta = $this->eligibility($this->ta);
        $this->assertSame('ingredient_not_at_outlet', $ta['reason']);
        $this->assertSame('Ingredient Syrup Peach belum tersedia di Taman Anggrek.', $ta['message']);

        $this->syrup->update(['is_active' => false]);
        $this->assertSame('ingredient_inactive', $this->eligibility($this->tb)['reason']);
    }

    public function test_zero_and_insufficient_stock_do_not_change_eligibility_and_deduction_is_unchanged(): void
    {
        $recipe = $this->recipe([$this->milk]);

        $this->assertTrue($this->eligibility($this->tb)['eligible'], 'no stock row at all');
        $balance = StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->tb->id, 'qty_on_hand' => 0]);
        $this->assertTrue($this->eligibility($this->tb)['eligible'], 'zero stock');
        $balance->update(['qty_on_hand' => 40]);
        $this->assertTrue($this->eligibility($this->tb)['eligible'], 'less stock than the recipe needs (100)');

        $transaction = SalesTransaction::create([
            'transaction_number' => 'TRX-AVAIL-1', 'user_id' => $this->owner->id, 'outlet_id' => $this->tb->id,
            'subtotal' => 20000, 'grand_total' => 20000, 'status' => 'completed',
        ]);
        $transaction->items()->create([
            'product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'product_name' => 'Italian Soda',
            'variant_name' => 'Regular', 'qty' => 2, 'price' => 10000, 'line_total' => 20000,
        ]);

        app(StockDeductionService::class)->deductFromTransaction($transaction);

        $this->assertSame(40.0 - 200.0, (float) $balance->fresh()->qty_on_hand, 'stock still goes negative and is fully recorded');
        $this->assertSame(200.0, (float) StockMovement::where('movement_type', 'sales_usage')->where('reference_id', $transaction->id)->sum('qty_out'));
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));
    }

    public function test_nothing_in_the_editor_or_imports_assigns_ingredients_to_outlets_or_touches_stock(): void
    {
        $this->recipe([$this->milk]);
        StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => 'outlet', 'location_id' => $this->tb->id, 'qty_on_hand' => 12]);
        $before = [
            DB::table('ingredient_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('stock_balances')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('stock_movements')->count(),
            DB::table('product_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('product_variant_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];

        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.recipes.ingredient-options', [$this->product, $this->variant]));
        $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', Recipe::first()));
        $this->importCsv("IS-R,Syrup Peach,30,1\nIS-R,Fresh Milk,1,1");
        $this->importExcel([['*Italian Soda', 'Regular', '', '', ''], ['', '', 'Syrup Peach', 30, 'ml']]);
        try {
            app(RecipeWriter::class)->addItem(Recipe::first(), $this->syrup, 5);
        } catch (ValidationException) {
        }

        $after = [
            DB::table('ingredient_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('stock_balances')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('stock_movements')->count(),
            DB::table('product_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            DB::table('product_variant_outlet')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
        $this->assertSame($before, $after);
    }

    // ================================================================================================
    // Import contract corrections: malformed rows, qty parity, atomic groups, honest counters
    // ================================================================================================

    public function test_csv_row_with_missing_columns_for_a_resolvable_variant_rejects_the_whole_variant(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $itemId = $recipe->items()->value('id');

        // Third line has only 3 columns (is_active missing) but its Variant code is readable.
        $errors = $this->importCsv("IS-R,Fresh Milk,150,1\nIS-R,Syrup Peach,30");

        $this->assertStringContainsString('Baris 3: jumlah kolom kurang dari 4', implode("\n", $errors));
        $this->assertStringContainsString("Variant 'IS-R' tidak diimport sama sekali", implode("\n", $errors));
        $recipe->refresh();
        $this->assertSame(1, Recipe::count());
        $this->assertSame($recipe->id, (int) Recipe::value('id'));
        $this->assertTrue($recipe->is_active);
        $this->assertSame(1, $recipe->items()->count());
        $this->assertSame($itemId, (int) $recipe->items()->value('id'));
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'), 'the valid first row was not applied either');
    }

    public function test_csv_malformed_row_for_an_unidentifiable_variant_is_only_reported_and_other_variants_still_import(): void
    {
        $large = $this->variant($this->product, 'Large', 'IS-L', [$this->tb, $this->bx, $this->ta]);
        $recipe = $this->recipe([$this->milk]);

        $errors = $this->importCsv("NOPE-CODE,Fresh Milk\n,,\nIS-R,Fresh Milk,150,1\nIS-L,Fresh Milk,90,1");

        $report = implode("\n", $errors);
        $this->assertStringContainsString('Baris 2: jumlah kolom kurang dari 4', $report);   // unresolved: just reported
        $this->assertSame(150.0, (float) $recipe->items()->value('qty'), 'unrelated Variant IS-R still imported');
        $this->assertSame(90.0, (float) Recipe::where('product_variant_id', $large->id)->firstOrFail()->items()->value('qty'), 'unrelated Variant IS-L still imported');
    }

    public function test_one_malformed_row_leaves_only_its_own_variant_untouched(): void
    {
        $large = $this->variant($this->product, 'Large', 'IS-L', [$this->tb, $this->bx, $this->ta]);
        $recipe = $this->recipe([$this->milk]);

        $this->importCsv("IS-R,Fresh Milk,150,1\nIS-L,Fresh Milk,90,1\nIS-R,Syrup Peach");

        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));
        $this->assertSame(90.0, (float) Recipe::where('product_variant_id', $large->id)->firstOrFail()->items()->value('qty'));
    }

    public function test_csv_rejects_a_qty_below_the_editor_minimum_and_accepts_the_minimum(): void
    {
        $recipe = $this->recipe([$this->milk]);

        $errors = $this->importCsv("IS-R,Fresh Milk,0.001,1");
        $this->assertStringContainsString('qty untuk ingredient', implode("\n", $errors));
        $this->assertStringContainsString('minimal 0.01', implode("\n", $errors));
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'), '0.001 is below RecipeWriter::qtyRule() and is rejected');

        $this->importCsv("IS-R,Fresh Milk,0.01,1");
        $this->assertSame(0.01, (float) $recipe->items()->value('qty'), '0.01 is exactly the editor minimum and is accepted');
    }

    public function test_excel_rejects_a_qty_below_the_editor_minimum_and_accepts_the_minimum(): void
    {
        $recipe = $this->recipe([$this->milk]);

        $errors = $this->importExcel([['*Italian Soda', 'Regular', '', '', ''], ['', '', 'Fresh Milk', 0.001, 'ml']]);
        $this->assertStringContainsString('minimal 0.01', implode("\n", $errors));
        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));

        $this->importExcel([['*Italian Soda', 'Regular', '', '', ''], ['', '', 'Fresh Milk', 0.01, 'ml']]);
        $this->assertSame(0.01, (float) $recipe->items()->value('qty'));
    }

    public function test_the_import_qty_minimum_matches_the_editor_rule(): void
    {
        $this->assertSame('required|numeric|min:0.01', RecipeWriter::qtyRule());   // the import mirrors this value (0.01)
    }

    public function test_interleaved_rows_of_one_variant_form_one_atomic_group(): void
    {
        $large = $this->variant($this->product, 'Large', 'IS-L', [$this->tb, $this->bx, $this->ta]);
        $sugar = $this->ingredient('Sugar', [$this->tb, $this->bx, $this->ta]);
        $recipe = $this->recipe([$this->milk]);

        // IS-R rows are split around IS-L rows; the LAST IS-R row is invalid, so ALL IS-R rows are dropped.
        $errors = $this->importCsv("IS-R,Fresh Milk,150,1\nIS-L,Fresh Milk,90,1\nIS-R,Sugar,5,1\nIS-L,Sugar,4,1\nIS-R,Syrup Peach,30,1");

        $this->assertSame(100.0, (float) $recipe->items()->value('qty'));
        $this->assertNull($recipe->items()->where('ingredient_id', $sugar->id)->first());
        $this->assertStringContainsString("Variant 'IS-R' tidak diimport sama sekali (3 baris)", implode("\n", $errors));

        $largeRecipe = Recipe::where('product_variant_id', $large->id)->firstOrFail();
        $this->assertSame(2, $largeRecipe->items()->count(), 'IS-L (interleaved but valid) imported fully');
    }

    public function test_duplicate_ingredient_rows_for_one_variant_keep_the_last_qty_and_count_once(): void
    {
        $this->importCsv("IS-R,Fresh Milk,10,1\nIS-R,Fresh Milk,20,1");

        $recipe = Recipe::sole();
        $this->assertSame(1, $recipe->items()->count());
        $this->assertSame(20.0, (float) $recipe->items()->value('qty'), 'documented behaviour: the last qty wins');
        $this->assertStringContainsString('Data masuk: 1. Data update: 0.', session('success'));
    }

    public function test_a_rolled_back_variant_does_not_inflate_the_success_counters(): void
    {
        $large = $this->variant($this->product, 'Large', 'IS-L', [$this->tb, $this->bx, $this->ta]);

        // Activation blows up AFTER the items were written inside IS-R's transaction.
        $this->app->bind(RecipeWriter::class, fn () => new class extends RecipeWriter
        {
            public function activationProblems(Recipe $recipe, ProductVariant $variant): array
            {
                throw new \RuntimeException('forced failure after the items were written');
            }
        });

        $errors = $this->importCsv("IS-R,Fresh Milk,150,1\nIS-L,Fresh Milk,90,0");

        $this->assertSame(0, Recipe::where('product_variant_id', $this->variant->id)->count(), 'IS-R rolled back completely');
        $this->assertSame(0, RecipeItem::whereHas('recipe', fn ($q) => $q->where('product_variant_id', $this->variant->id))->count());
        $this->assertStringContainsString("Variant 'IS-R' tidak diimport: terjadi kesalahan", implode("\n", $errors));
        // Only the committed Variant (IS-L, one new item) is counted; the rolled-back one is "dilewati".
        $this->assertSame('Import recipes CSV selesai. Data masuk: 1. Data update: 0. Data dilewati: 1.', session('success'));
        $this->assertSame(1, Recipe::where('product_variant_id', $large->id)->count());
    }

    public function test_existing_active_recipe_stays_intact_when_any_row_of_its_variant_is_bad(): void
    {
        $recipe = $this->recipe([$this->milk]);
        $originalItemId = $recipe->items()->value('id');

        foreach (["IS-R,Fresh Milk,150,1\nIS-R,Syrup Peach,30,1", "IS-R,Fresh Milk,150,1\nIS-R,Fresh Milk,0.001,1", "IS-R,Fresh Milk,150,1\nIS-R,Ghost,3,1", "IS-R,Fresh Milk,150,0\nIS-R,Fresh Milk"] as $csv) {
            $this->importCsv($csv);

            $fresh = Recipe::sole();
            $this->assertSame($recipe->id, $fresh->id);
            $this->assertTrue($fresh->is_active, 'a bad import never switches an active Recipe off');
            $this->assertSame($originalItemId, (int) $fresh->items()->value('id'));
            $this->assertSame(100.0, (float) $fresh->items()->value('qty'));
            $this->assertSame(1, $fresh->items()->count());
        }
    }

    // ---- helpers ---------------------------------------------------------------------------------

    private function importCsv(string $rows, ?User $as = null): array
    {
        $csv = "variant_code,ingredient_name,qty,is_active\n".$rows."\n";

        $this->actingAs($as ?? $this->owner)
            ->post(route('backoffice.recipes.import.store'), ['file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv)])
            ->assertRedirect(route('backoffice.recipes.index'));

        return (array) session('import_errors');
    }

    private function importExcel(array $rows, ?User $as = null): array
    {
        $sheet = new Spreadsheet();
        $sheet->getActiveSheet()->fromArray(array_merge([['Produk', 'Variant', 'Bahan', 'Qty', 'Unit']], $rows), null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'recipe').'.xlsx';
        (new Xlsx($sheet))->save($path);

        try {
            $this->actingAs($as ?? $this->owner)
                ->post(route('backoffice.recipes.import.store'), ['file' => new UploadedFile($path, 'recipes.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)])
                ->assertRedirect(route('backoffice.recipes.index'));
        } finally {
            @unlink($path);
        }

        return (array) session('import_errors');
    }

    private function eligibility(Outlet $outlet): array
    {
        return app(SaleEligibilityService::class)->variantStatuses([$this->variant->id], $outlet->id)[$this->variant->id];
    }

    private function recipe(array $ingredients, bool $active = true): Recipe
    {
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->variant->id, 'name' => 'Italian Soda - Regular', 'is_active' => $active]);

        foreach ($ingredients as $ingredient) {
            $recipe->items()->create(['ingredient_id' => $ingredient->id, 'qty' => 100, 'unit' => 'ml']);
        }

        return $recipe;
    }

    private function variant(Product $product, string $name, string $code, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'price' => 20000, 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => true]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function ingredient(string $name, array $outlets, bool $active = true): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->category->id, 'name' => $name, 'code' => strtoupper(str_replace(' ', '_', $name)), 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => $active,
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
