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
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Requirement #07: the classic Recipe Editor (standalone page and the Recipes list drawer, which is an iframe of
 * the same page) is compact: the item list gets the width, Qty and Action stay on screen.
 *
 * Presentation only. Pinned here: every item and its information, the exact quantity formatting, the existing
 * forms (routes, methods, input names, confirm text, return_to), read-only enforcement, validation, that viewing
 * writes nothing, and that the compact CSS is scoped and responsive. Widths and clipping are measured in a real
 * browser during QA; here we check what the HTML and CSS promise.
 */
class RecipeEditorCompactLayoutTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outletA;

    private Outlet $outletB;

    private User $owner;

    private User $limited;

    private Recipe $recipe;

    private Recipe $emptyRecipe;

    private Recipe $sharedRecipe;

    private Ingredient $susu;

    private Ingredient $kopi;

    private Ingredient $sirup;

    private Ingredient $es;

    private Ingredient $long;

    private Ingredient $extra;

    /** @var array<string, RecipeItem> */
    private array $items = [];

    private const LONG_NAME = 'Bubuk Cokelat Premium Single Origin Dengan Nama Sangat Panjang Sekali Untuk Menguji Wrap Teks';

    protected function setUp(): void
    {
        parent::setUp();

        $this->outletA = Outlet::create(['name' => 'Outlet A', 'code' => 'OA', 'is_active' => true]);
        $this->outletB = Outlet::create(['name' => 'Outlet B', 'code' => 'OB', 'is_active' => true]);

        $owner = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $admin = Role::create(['name' => 'Admin Outlet', 'code' => 'admin_outlet']);
        $this->owner = $this->makeUser('owner', $owner, [$this->outletA, $this->outletB]);
        $this->limited = $this->makeUser('limited', $admin, [$this->outletA]);

        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $raw = IngredientCategory::create(['name' => 'Bahan Baku', 'code' => 'RAW', 'is_active' => true]);
        $base = IngredientCategory::create(['name' => 'Sirup & Base', 'code' => 'SYR', 'is_active' => true]);

        $this->susu = $this->ingredient('Susu Segar', 'ml', Ingredient::TYPE_RAW, $raw);
        $this->kopi = $this->ingredient('Kopi Arabika', 'gram', Ingredient::TYPE_RAW, $raw);
        $this->sirup = $this->ingredient('Sirup Gula Aren (Semi)', 'ml', Ingredient::TYPE_SEMI_FINISHED, $base);
        $this->es = $this->ingredient('Es Batu', 'gram', Ingredient::TYPE_RAW, $raw);
        $this->long = $this->ingredient(self::LONG_NAME, 'gram', Ingredient::TYPE_RAW, $raw);
        $this->extra = $this->ingredient('Krimer Nabati', 'gram', Ingredient::TYPE_RAW, $raw);

        // Outlet A only: both users may change it.
        [$product, $variant] = $this->productWithVariant($brand, $category, 'Es Kopi Susu', 'Regular', [$this->outletA]);
        $this->recipe = Recipe::create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Recipe - Es Kopi Susu Regular', 'is_active' => true]);
        foreach ([['susu', $this->susu, 0.01], ['kopi', $this->kopi, 12.50], ['sirup', $this->sirup, 0.25], ['es', $this->es, 1000.00], ['long', $this->long, 1]] as [$key, $ingredient, $qty]) {
            $this->items[$key] = RecipeItem::create(['recipe_id' => $this->recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => $qty, 'unit' => $ingredient->unit]);
        }

        // No items at all.
        [$product2, $variant2] = $this->productWithVariant($brand, $category, 'Matcha Latte', 'Regular', [$this->outletA]);
        $this->emptyRecipe = Recipe::create(['product_id' => $product2->id, 'product_variant_id' => $variant2->id, 'name' => 'Recipe - Matcha Latte Regular', 'is_active' => false]);

        // Used at A and B: read-only for the user who only has A.
        [$product3, $variant3] = $this->productWithVariant($brand, $category, 'Choco Fresh', 'Large', [$this->outletA, $this->outletB]);
        $this->sharedRecipe = Recipe::create(['product_id' => $product3->id, 'product_variant_id' => $variant3->id, 'name' => 'Recipe - Choco Fresh Large', 'is_active' => true]);
        $this->items['shared'] = RecipeItem::create(['recipe_id' => $this->sharedRecipe->id, 'ingredient_id' => $this->susu->id, 'qty' => 150, 'unit' => 'ml']);
    }

    // ---- Items, information and formatting -------------------------------------------------------

    public function test_every_item_is_listed_with_its_row_anchor_and_all_its_information(): void
    {
        $xpath = $this->editor($this->recipe);

        $this->assertSame(5, $xpath->query('//table[contains(@class,"items-table")]//tbody/tr')->length);

        $expected = [
            'susu' => ['Susu Segar', 'Bahan Baku', 'Mentah', 'ml'],
            'kopi' => ['Kopi Arabika', 'Bahan Baku', 'Mentah', 'gram'],
            'sirup' => ['Sirup Gula Aren (Semi)', 'Sirup & Base', 'Setengah Jadi', 'ml'],
            'es' => ['Es Batu', 'Bahan Baku', 'Mentah', 'gram'],
            'long' => [self::LONG_NAME, 'Bahan Baku', 'Mentah', 'gram'],
        ];

        foreach ($expected as $key => [$name, $category, $type, $unit]) {
            $row = $xpath->query('//tr[@id="recipe-item-'.$this->items[$key]->id.'"]')->item(0);
            $this->assertNotNull($row, $name);

            $ingredientCell = $xpath->query('.//td[contains(@class,"c-ing")]', $row)->item(0);
            $this->assertSame($name, trim($xpath->query('.//*[contains(@class,"ing-name")]', $ingredientCell)->item(0)->textContent));
            // Category and type are kept, as secondary information under the name.
            $this->assertSame($category, trim($xpath->query('.//*[contains(@class,"ing-cat")]', $ingredientCell)->item(0)->textContent));
            $this->assertSame($type, trim($xpath->query('.//*[contains(@class,"badge")]', $ingredientCell)->item(0)->textContent));
            $this->assertSame($unit, trim($xpath->query('.//td[contains(@class,"c-unit")]', $row)->item(0)->textContent));
        }

        // Semi-finished and raw keep their own badge colour class.
        $this->assertSame(1, $xpath->query('//*[contains(@class,"badge-semi")]')->length);
        $this->assertSame(4, $xpath->query('//*[contains(@class,"badge-raw")]')->length);
    }

    public function test_quantity_formatting_is_unchanged_for_the_example_quantities(): void
    {
        $xpath = $this->editor($this->recipe);

        $shown = [];
        foreach (['susu', 'kopi', 'sirup', 'es', 'long'] as $key) {
            $shown[$key] = trim($xpath->query('//tr[@id="recipe-item-'.$this->items[$key]->id.'"]//*[contains(@class,"qty-text")]')->item(0)->textContent);
        }

        $this->assertSame(['susu' => '0,01', 'kopi' => '12,50', 'sirup' => '0,25', 'es' => '1.000,00', 'long' => '1,00'], $shown);
    }

    public function test_the_table_has_the_four_compact_columns_and_the_header_row_stays(): void
    {
        $xpath = $this->editor($this->recipe);

        $headers = [];
        foreach ($xpath->query('//table[contains(@class,"items-table")]//thead//th') as $th) {
            $headers[] = trim($th->textContent);
        }

        $this->assertSame(['Ingredient', 'Unit', 'Qty', 'Action'], $headers);
        $this->assertSame('Daftar Recipe Items', trim($xpath->query('//*[@id="recipe-items"]//*[contains(@class,"section-title")]')->item(0)->textContent));
    }

    public function test_an_empty_recipe_shows_the_empty_state_and_still_offers_the_add_form(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->emptyRecipe))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertStringContainsString('Recipe ini belum punya bahan sama sekali.', $html);
        $this->assertSame(0, $xpath->query('//table[contains(@class,"items-table")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="recipe-add-item"]//form')->length);
        $this->assertSame(1, $xpath->query('//*[contains(@class,"recipe-header-card")]//form')->length);
    }

    // ---- Existing forms ---------------------------------------------------------------------------

    public function test_the_recipe_header_form_is_intact_and_still_saves(): void
    {
        $xpath = $this->editor($this->recipe, ['return_to' => '/backoffice/recipes?status=active']);

        $form = $xpath->query('//*[contains(@class,"recipe-header-card")]//form')->item(0);
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(route('backoffice.recipes.update', $this->recipe), $form->getAttribute('action'));
        $this->assertSame('PUT', $xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame('/backoffice/recipes?status=active', $xpath->query('.//input[@name="return_to"]', $form)->item(0)->getAttribute('value'));

        foreach (['product_variant_id', 'name', 'is_active'] as $field) {
            $this->assertSame(1, $xpath->query('.//*[@name="'.$field.'"]', $form)->length, $field);
        }
        $this->assertSame('Update Header', trim($xpath->query('.//button[@type="submit"]', $form)->item(0)->textContent));

        // The three fields sit in the grouped container that lets them share a row on a wide card.
        $this->assertSame(3, $xpath->query('.//*[contains(@class,"recipe-fields")]/*[contains(@class,"field")]', $form)->length);

        $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.update', $this->recipe), [
                'product_variant_id' => $this->recipe->product_variant_id,
                'name' => 'Es Kopi Susu Baru',
                'is_active' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('Es Kopi Susu Baru', $this->recipe->fresh()->name);
    }

    public function test_the_quantity_edit_form_keeps_its_route_method_fields_and_controls(): void
    {
        $xpath = $this->editor($this->recipe, ['return_to' => '/backoffice/recipes']);
        $row = $xpath->query('//tr[@id="recipe-item-'.$this->items['kopi']->id.'"]')->item(0);

        $form = $xpath->query('.//form[contains(@class,"qty-form")]', $row)->item(0);
        $this->assertSame(route('backoffice.recipes.items.update', [$this->recipe, $this->items['kopi']]), $form->getAttribute('action'));
        $this->assertSame('PUT', $xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $form)->length);
        $this->assertSame('/backoffice/recipes', $xpath->query('.//input[@name="return_to"]', $form)->item(0)->getAttribute('value'));

        $input = $xpath->query('.//input[@name="qty"]', $form)->item(0);
        $this->assertSame('number', $input->getAttribute('type'));
        $this->assertSame('0.01', $input->getAttribute('step'));
        $this->assertSame('12.50', $input->getAttribute('value'));
        $this->assertStringContainsString('qty-input', $input->getAttribute('class'));

        // ✓ Simpan submits the form; Batal is a plain button that calls closeQty.
        $this->assertStringContainsString('Simpan', $xpath->query('.//button[contains(@class,"btn-primary")]', $form)->item(0)->textContent);
        $cancel = $xpath->query('.//button[@type="button"]', $form)->item(0);
        $this->assertStringContainsString('Batal', $cancel->textContent);
        $this->assertSame('closeQty(this)', $cancel->getAttribute('onclick'));

        // Edit opens it (and carries the class the script uses to hide it while editing).
        $edit = $xpath->query('.//button[contains(@class,"btn-edit-trigger")]', $row)->item(0);
        $this->assertSame('openQty(this)', $edit->getAttribute('onclick'));
        $this->assertSame('button', $edit->getAttribute('type'));
    }

    public function test_a_quantity_update_changes_only_that_item_and_keeps_return_to(): void
    {
        $before = $this->itemQuantities();

        $response = $this->actingAs($this->owner)
            ->put(route('backoffice.recipes.items.update', [$this->recipe, $this->items['kopi']]), ['qty' => '13.75', 'return_to' => '/backoffice/recipes?status=active']);

        $response->assertSessionHasNoErrors();
        $location = urldecode($response->headers->get('Location'));
        $this->assertStringContainsString('#recipe-item-'.$this->items['kopi']->id, $location);
        $this->assertStringContainsString('return_to=/backoffice/recipes?status=active', $location);

        $after = $this->itemQuantities();
        $this->assertSame(13.75, $after[$this->items['kopi']->id]);
        unset($before[$this->items['kopi']->id], $after[$this->items['kopi']->id]);
        $this->assertSame($before, $after, 'every other quantity is untouched');
    }

    public function test_quantity_validation_is_unchanged(): void
    {
        $this->actingAs($this->owner);
        $before = $this->itemQuantities();
        $url = route('backoffice.recipes.items.update', [$this->recipe, $this->items['susu']]);

        $this->from(route('backoffice.recipes.edit', $this->recipe))->put($url, ['qty' => '0'])->assertSessionHasErrors('qty');
        $this->from(route('backoffice.recipes.edit', $this->recipe))->put($url, ['qty' => '-1'])->assertSessionHasErrors('qty');
        $this->from(route('backoffice.recipes.edit', $this->recipe))->put($url, ['qty' => 'abc'])->assertSessionHasErrors('qty');
        $this->from(route('backoffice.recipes.edit', $this->recipe))->put($url, [])->assertSessionHasErrors('qty');

        $this->assertSame($before, $this->itemQuantities());
    }

    public function test_the_delete_form_keeps_its_route_method_and_confirmation(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe, ['return_to' => '/backoffice/recipes']))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $row = $xpath->query('//tr[@id="recipe-item-'.$this->items['es']->id.'"]')->item(0);
        $form = $xpath->query('.//form[contains(@action,"/items/'.$this->items['es']->id.'")][not(contains(@class,"qty-form"))]', $row)->item(0);

        $this->assertNotNull($form);
        $this->assertSame(route('backoffice.recipes.items.destroy', [$this->recipe, $this->items['es']]), $form->getAttribute('action'));
        $this->assertSame('DELETE', $xpath->query('.//input[@name="_method"]', $form)->item(0)->getAttribute('value'));
        $this->assertSame("return confirm('Yakin mau hapus recipe item ini?')", $form->getAttribute('onsubmit'));
        $this->assertStringContainsString('Hapus', $xpath->query('.//button', $form)->item(0)->textContent);

        $this->actingAs($this->owner)->delete(route('backoffice.recipes.items.destroy', [$this->recipe, $this->items['es']]))->assertSessionHasNoErrors();
        $this->assertNull(RecipeItem::find($this->items['es']->id));
        $this->assertSame(4, $this->recipe->items()->count());
    }

    public function test_the_add_ingredient_form_is_intact_and_still_adds(): void
    {
        $xpath = $this->editor($this->recipe, ['return_to' => '/backoffice/recipes']);
        $form = $xpath->query('//*[@id="recipe-add-item"]//form')->item(0);

        $this->assertSame(route('backoffice.recipes.items.store', $this->recipe), $form->getAttribute('action'));
        $this->assertSame(0, $xpath->query('.//input[@name="_method"]', $form)->length, 'plain POST');
        $this->assertSame('/backoffice/recipes', $xpath->query('.//input[@name="return_to"]', $form)->item(0)->getAttribute('value'));

        $select = $xpath->query('.//select[@name="ingredient_id"]', $form)->item(0);
        $this->assertSame('required', $select->getAttributeNode('required')?->name);
        $options = [];
        foreach ($xpath->query('.//option', $select) as $option) {
            $options[] = $option->getAttribute('value');
        }
        $this->assertContains((string) $this->extra->id, $options, 'an ingredient not yet in the recipe is offered');
        $this->assertNotContains((string) $this->susu->id, $options, 'one already in the recipe is not');

        $qty = $xpath->query('.//input[@name="qty"]', $form)->item(0);
        $this->assertSame(['0.01', '0.01'], [$qty->getAttribute('min'), $qty->getAttribute('step')]);
        $this->assertSame('Tambah Recipe Item', trim($xpath->query('.//button[@type="submit"]', $form)->item(0)->textContent));

        $this->actingAs($this->owner)
            ->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->extra->id, 'qty' => '2.5'])
            ->assertSessionHasNoErrors();
        $added = RecipeItem::where('recipe_id', $this->recipe->id)->where('ingredient_id', $this->extra->id)->firstOrFail();
        $this->assertSame(2.5, (float) $added->qty);
        $this->assertSame('gram', $added->unit);
    }

    public function test_add_ingredient_validation_is_unchanged(): void
    {
        $this->actingAs($this->owner);
        $count = $this->recipe->items()->count();
        $from = route('backoffice.recipes.edit', $this->recipe);

        $this->from($from)->post(route('backoffice.recipes.items.store', $this->recipe), ['qty' => '1'])->assertSessionHasErrors('ingredient_id');
        $this->from($from)->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->extra->id, 'qty' => '0'])->assertSessionHasErrors('qty');
        $this->from($from)->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->extra->id])->assertSessionHasErrors('qty');
        // Already in the recipe: refused with the existing message, nothing added.
        $this->from($from)->post(route('backoffice.recipes.items.store', $this->recipe), ['ingredient_id' => $this->susu->id, 'qty' => '1'])
            ->assertSessionHas('error', \App\Services\RecipeWriter::DUPLICATE_ITEM_MESSAGE);

        $this->assertSame($count, $this->recipe->items()->count());
    }

    public function test_the_page_anchors_the_redirects_rely_on_are_kept(): void
    {
        $xpath = $this->editor($this->recipe);

        foreach (['recipe-items', 'recipe-add-item', 'recipe-item-'.$this->items['susu']->id] as $id) {
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length, $id);
        }
    }

    // ---- Read-only ---------------------------------------------------------------------------------

    public function test_read_only_mode_offers_no_mutation_and_still_lists_the_items(): void
    {
        $html = $this->actingAs($this->limited)->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//*[@id="recipe-readonly-notice"]')->length);
        $this->assertSame(1, $xpath->query('//table[contains(@class,"items-table")]//tbody/tr')->length);
        $this->assertSame('Susu Segar', trim($xpath->query('//*[contains(@class,"ing-name")]')->item(0)->textContent));
        $this->assertSame('Read-only', trim($xpath->query('//td[contains(@class,"c-act")]')->item(0)->textContent));

        $this->assertSame(0, $xpath->query('//form[contains(@class,"qty-form")]')->length);
        $this->assertSame(0, $xpath->query('//*[contains(@class,"btn-edit-trigger")]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="recipe-add-item"]//form')->length);
        $this->assertSame(0, $xpath->query('//input[@name="qty"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="_method"][@value="DELETE"]')->length);
        $this->assertStringNotContainsString('Yakin mau hapus recipe item', $html);
        $this->assertStringNotContainsString('Update Header', $html);
        $this->assertStringNotContainsString('Tambah Recipe Item', $html);
        $this->assertStringContainsString('Recipe read-only, bahan tidak bisa ditambah dari konteks ini.', $html);
        $this->assertSame('disabled', $xpath->query('//*[contains(@class,"recipe-header-card")]//fieldset')->item(0)->getAttributeNode('disabled')?->name);
    }

    public function test_read_only_users_still_cannot_change_anything_by_posting_directly(): void
    {
        $before = $this->itemQuantities();
        $this->actingAs($this->limited);

        $this->put(route('backoffice.recipes.items.update', [$this->sharedRecipe, $this->items['shared']]), ['qty' => '999'])->assertForbidden();
        $this->delete(route('backoffice.recipes.items.destroy', [$this->sharedRecipe, $this->items['shared']]))->assertForbidden();
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $this->extra->id, 'qty' => '1'])->assertForbidden();

        $this->assertSame($before, $this->itemQuantities());
        $this->assertSame(1, $this->sharedRecipe->items()->count());
    }

    public function test_a_limited_user_can_still_edit_a_recipe_used_only_in_their_outlet(): void
    {
        $xpath = $this->xpath($this->actingAs($this->limited)->get(route('backoffice.recipes.edit', $this->recipe))->assertOk()->getContent());

        $this->assertSame(0, $xpath->query('//*[@id="recipe-readonly-notice"]')->length);
        $this->assertSame(5, $xpath->query('//*[contains(@class,"btn-edit-trigger")]')->length);
        $this->assertSame(5, $xpath->query('//form[contains(@class,"qty-form")]')->length);
    }

    // ---- Nothing is written by looking ---------------------------------------------------------------

    public function test_viewing_the_editor_changes_no_recipe_or_ingredient_data(): void
    {
        $snapshot = fn () => json_encode([
            DB::table('recipes')->orderBy('id')->get(),
            DB::table('recipe_items')->orderBy('id')->get(),
            DB::table('ingredients')->orderBy('id')->get(),
            DB::table('ingredient_outlet')->orderBy('id')->get(),
            DB::table('stock_movements')->count(),
        ]);
        $before = $snapshot();

        foreach ([$this->recipe, $this->emptyRecipe, $this->sharedRecipe] as $recipe) {
            $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $recipe))->assertOk();
            $this->actingAs($this->limited)->get(route('backoffice.recipes.edit', $recipe))->assertOk();
        }
        $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->assertOk();

        $this->assertSame($before, $snapshot());
    }

    // ---- CSS and markup contracts ----------------------------------------------------------------------

    public function test_compact_styles_are_scoped_to_the_recipe_editor_item_list_and_forms(): void
    {
        $block = $this->compactBlock($this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe))->assertOk()->getContent());

        // Flatten @media wrappers so every rule's selector can be checked.
        $flat = preg_replace('/@media[^{]*\{/', '', $block);
        preg_match_all('/([^{}]+)\{[^{}]*\}/', $flat, $rules, PREG_SET_ORDER);
        $this->assertGreaterThan(20, count($rules));

        foreach ($rules as [, $selectorList]) {
            foreach (explode(',', $selectorList) as $selector) {
                $this->assertMatchesRegularExpression(
                    '/^(\.items-table|\.recipe-fields|\.add-row|#recipe-items)\b/',
                    trim($selector),
                    'unscoped selector in the compact block: '.trim($selector)
                );
            }
        }

        $this->assertDoesNotMatchRegularExpression('/(^|\})\s*(table|th|td|tr|body)\s*[,{]/', $flat);
    }

    public function test_responsive_rules_are_present_and_the_old_fixed_760px_width_is_gone(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe))->assertOk()->getContent();
        $block = $this->compactBlock($html);

        // The list no longer forces a width wider than its column.
        $this->assertDoesNotMatchRegularExpression('/min-width:\s*760px/', $html);
        $this->assertMatchesRegularExpression('/\.items-table\s*\{[^}]*min-width:\s*0[^}]*table-layout:\s*fixed/s', $block);

        // Desktop: narrow left column for header + add-form, the list takes the rest.
        $this->assertMatchesRegularExpression('/\.grid-2\s*\{[^}]*grid-template-columns:\s*minmax\(280px, 320px\) minmax\(0, 1fr\)/s', $html);
        $this->assertStringContainsString('grid-template-areas: "head items" "add items"', $html);
        // Below 1140px: header, add form, then the full-width list.
        $this->assertMatchesRegularExpression('/@media \(max-width: 1139px\)\s*\{\s*\.grid-2\s*\{[^}]*grid-template-areas:\s*"head" "add" "items"/s', $html);
        // Phone: every item turns into a card, the header row stays available to screen readers.
        $this->assertStringContainsString('@media (max-width: 720px)', $block);
        $this->assertMatchesRegularExpression('/\.items-table tr\s*\{[^}]*display:\s*grid/s', $block);
        $this->assertMatchesRegularExpression('/\.items-table thead\s*\{[^}]*position:\s*absolute[^}]*clip:/s', $block);
        $this->assertStringContainsString('content: attr(data-label)', $block);

        // Cells carry the labels the card layout prints.
        $xpath = $this->xpath($html);
        $this->assertSame('Unit', $xpath->query('//tr[@id="recipe-item-'.$this->items['susu']->id.'"]/td[contains(@class,"c-unit")]')->item(0)->getAttribute('data-label'));
        $this->assertSame('Qty', $xpath->query('//tr[@id="recipe-item-'.$this->items['susu']->id.'"]/td[contains(@class,"c-qty")]')->item(0)->getAttribute('data-label'));
        $this->assertSame('Action', $xpath->query('//tr[@id="recipe-item-'.$this->items['susu']->id.'"]/td[contains(@class,"c-act")]')->item(0)->getAttribute('data-label'));
    }

    public function test_standalone_and_drawer_share_one_layout_and_the_drawer_only_drops_the_page_chrome(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe))->assertOk()->getContent();

        // The embedded detection that adds .is-embedded is still there.
        $this->assertStringContainsString("if (window.self !== window.top) { document.documentElement.classList.add('is-embedded'); }", $html);
        $this->assertStringContainsString('.is-embedded .topbar { display: none; }', $html);
        $this->assertStringContainsString('.is-embedded .recipe-header-card > .info { display: none; }', $html);

        // No separate embedded grid any more: the same areas serve both, so the frame width alone decides.
        $this->assertStringNotContainsString('.is-embedded .grid-2', $html);
        $this->assertStringNotContainsString('.is-embedded .right-stack', $html);

        // The Recipes list still opens this very page inside the drawer iframe.
        $list = $this->actingAs($this->owner)->get(route('backoffice.recipes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<iframe class="rcp-drawer-frame" id="rcp-drawer-frame"', $list);
        $this->assertStringContainsString('data-recipe-edit', $list);
    }

    public function test_the_quantity_script_keeps_open_and_close_and_adds_the_editing_row_state(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.recipes.edit', $this->recipe))->assertOk()->getContent();

        $this->assertStringContainsString('function openQty(button)', $html);
        $this->assertStringContainsString('function closeQty(button)', $html);
        $this->assertMatchesRegularExpression("/function openQty.*?container\\.classList\\.add\\('editing'\\);.*?row\\.classList\\.add\\('is-editing'\\);.*?input\\.focus\\(\\);.*?input\\.select\\(\\);/s", $html);
        $this->assertMatchesRegularExpression("/function closeQty.*?container\\.classList\\.remove\\('editing'\\);.*?row\\.classList\\.remove\\('is-editing'\\);/s", $html);
        // Hapus stays visible while editing: only the Edit trigger is hidden.
        $this->assertMatchesRegularExpression('/\.items-table tr\.is-editing \.btn-edit-trigger\s*\{\s*display:\s*none;/s', $html);
        $this->assertStringNotContainsString('tr.is-editing .btn-danger', $html);
    }

    // ---- helpers -----------------------------------------------------------------------------------------

    private function editor(Recipe $recipe, array $query = []): DOMXPath
    {
        return $this->xpath($this->actingAs($this->owner)->get(route('backoffice.recipes.edit', ['recipe' => $recipe] + $query))->assertOk()->getContent());
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    /** The CSS rules of the RECIPE_EDITOR_COMPACT block (comments removed). */
    private function compactBlock(string $html): string
    {
        $start = strpos($html, 'RECIPE_EDITOR_COMPACT');
        $this->assertNotFalse($start);
        $end = strpos($html, '</style>', $start);
        $afterComment = strpos($html, '*/', $start) + 2;

        return preg_replace('#/\*.*?\*/#s', '', substr($html, $afterComment, $end - $afterComment));
    }

    /** @return array<int, float> recipe item id => qty */
    private function itemQuantities(): array
    {
        return RecipeItem::orderBy('id')->get()->mapWithKeys(fn ($item) => [$item->id => (float) $item->qty])->all();
    }

    private function makeUser(string $username, Role $role, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => $role->id,
            'outlet_id' => $outlets[0]->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    private function ingredient(string $name, string $unit, string $type, IngredientCategory $category): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(substr(md5($name), 0, 10)),
            'unit' => $unit,
            'ingredient_type' => $type,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outletA->id, $this->outletB->id]);

        return $ingredient;
    }

    /** @return array{0: Product, 1: ProductVariant} */
    private function productWithVariant(Brand $brand, ProductCategory $category, string $name, string $variantName, array $outlets): array
    {
        $ids = collect($outlets)->pluck('id')->all();

        $product = Product::create([
            'brand_id' => $brand->id,
            'product_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '-', $name)),
            'is_active' => true,
        ]);
        $product->outlets()->sync($ids);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $variantName,
            'code' => $product->code.'-'.strtoupper($variantName),
            'price' => 25000,
            'price_dine_in' => 25000,
            'price_delivery' => 27000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync($ids);

        return [$product, $variant];
    }
}
