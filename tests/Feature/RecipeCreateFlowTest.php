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
use App\Services\RecipeWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Tambah Recipe": pick a Menu (Product), then one of its Variants; the Recipe name is generated
 * server-side as "<Product> - <Variant>". The cascading dropdown itself is JavaScript (covered by the
 * browser QA); here we pin down what the server offers, accepts and stores.
 */
class RecipeCreateFlowTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $bazaar;

    private Brand $brand;

    private ProductCategory $category;

    private Product $milk;

    private ProductVariant $milkRc;

    private ProductVariant $milkL;

    private ProductVariant $milk1L;

    private Product $remedy;

    private ProductVariant $remedyRegular;

    /** BXC + Bazaar. */
    private User $adminPusat;

    /** BXC only. */
    private User $bxcAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar TikTok', 'code' => 'BZR', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->milk = $this->makeProduct('Brown Sugar Fresh Milk', 'BSFM', [$this->bxc]);
        $this->milkRc = $this->makeVariant($this->milk, 'RC', [$this->bxc]);
        $this->milkL = $this->makeVariant($this->milk, 'L', [$this->bxc]);
        $this->milk1L = $this->makeVariant($this->milk, '1L', [$this->bxc]);

        $this->remedy = $this->makeProduct("Ah-Ma's Remedy", 'REMEDY', [$this->bxc, $this->bazaar]);
        $this->remedyRegular = $this->makeVariant($this->remedy, 'Regular', [$this->bxc, $this->bazaar]);

        $this->adminPusat = $this->makeUser('admin-pusat', 'admin_pusat', [$this->bxc, $this->bazaar]);
        $this->bxcAdmin = $this->makeUser('admin-bxc', 'admin_outlet', [$this->bxc]);
    }

    // ---- What the form offers ---------------------------------------------------------------------

    public function test_product_dropdown_lists_eligible_products_with_only_their_own_variants(): void
    {
        $response = $this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->assertOk();
        $menus = collect($response->viewData('menuOptions'))->keyBy('id');

        $this->assertEqualsCanonicalizing([$this->milk->id, $this->remedy->id], $menus->keys()->all());
        $this->assertSame('Brown Sugar Fresh Milk', $menus[$this->milk->id]['name']);

        $this->assertEqualsCanonicalizing(
            [$this->milkRc->id, $this->milkL->id, $this->milk1L->id],
            collect($menus[$this->milk->id]['variants'])->pluck('id')->all(),
        );
        $this->assertSame([$this->remedyRegular->id], collect($menus[$this->remedy->id]['variants'])->pluck('id')->all());

        // The page ships the form's three controls and no hand-typed Recipe Name input.
        $response->assertSee('Menu / Product')->assertSee('Variant / Size')->assertSee('Nama Recipe Otomatis')
            ->assertSee('name="product_id"', false)->assertSee('name="product_variant_id"', false)
            ->assertDontSee('name="name"', false);
    }

    public function test_option_carries_the_server_generated_name(): void
    {
        $menus = collect($this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->viewData('menuOptions'))->keyBy('id');
        $variants = collect($menus[$this->milk->id]['variants'])->keyBy('id');

        $this->assertSame('Brown Sugar Fresh Milk - 1L', $variants[$this->milk1L->id]['recipe_name']);
        $this->assertSame("Ah-Ma's Remedy - Regular", $menus[$this->remedy->id]['variants'][0]['recipe_name']);
    }

    public function test_limited_user_only_sees_menus_and_variants_inside_their_outlets(): void
    {
        // Regular lives at BXC + Bazaar: a BXC-only user may not change a Recipe that also reaches Bazaar.
        $menus = collect($this->actingAs($this->bxcAdmin)->get(route('backoffice.recipes.create'))->viewData('menuOptions'));

        $this->assertSame([$this->milk->id], $menus->pluck('id')->all());
    }

    public function test_deleted_products_and_variants_are_not_offered(): void
    {
        $this->milkL->delete();
        $this->remedy->delete();

        $menus = collect($this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->viewData('menuOptions'));

        $this->assertSame([$this->milk->id], $menus->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$this->milkRc->id, $this->milk1L->id],
            collect($menus[0]['variants'])->pluck('id')->all(),
        );
    }

    public function test_a_variant_that_already_has_a_recipe_is_flagged_with_a_link_to_it(): void
    {
        $recipe = $this->makeRecipe($this->milk1L, 'Legacy 1L Name');

        $menus = collect($this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->viewData('menuOptions'))->keyBy('id');
        $variants = collect($menus[$this->milk->id]['variants'])->keyBy('id');

        $this->assertSame(route('backoffice.recipes.edit', $recipe), $variants[$this->milk1L->id]['existing']['url']);
        $this->assertNull($variants[$this->milkRc->id]['existing']);
    }

    public function test_product_with_all_variants_taken_is_still_listed_so_the_form_can_explain_it(): void
    {
        $this->makeRecipe($this->remedyRegular, 'x');

        $menus = collect($this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->viewData('menuOptions'))->keyBy('id');

        $this->assertNotNull($menus[$this->remedy->id]['variants'][0]['existing']);
    }

    public function test_when_every_variant_is_taken_all_stay_listed_and_nothing_can_be_created(): void
    {
        foreach ([$this->milkRc, $this->milkL, $this->milk1L] as $variant) {
            $this->makeRecipe($variant, 'Legacy '.$variant->name)->update(['is_active' => $variant->id % 2 === 0]);
        }

        $page = $this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->assertOk();
        $variants = collect(collect($page->viewData('menuOptions'))->keyBy('id')[$this->milk->id]['variants']);

        // All three remain visible, each pointing at its existing Recipe (disabled options in the form).
        $this->assertCount(3, $variants);
        $this->assertSame([], $variants->whereNull('existing')->all());
        $this->assertSame(
            route('backoffice.recipes.edit', Recipe::where('product_variant_id', $this->milkRc->id)->first()),
            $variants->firstWhere('id', $this->milkRc->id)['existing']['url'],
        );

        // The form keeps the select reachable by keyboard and blocks submit via a required placeholder.
        $page->assertSee('Semua Variant sudah punya Recipe')->assertSee('setCustomValidity', false)->assertSee('Sudah ada Recipe');

        // A hand-made request cannot create a second Recipe for any of them.
        foreach ([$this->milkRc, $this->milkL, $this->milk1L] as $variant) {
            $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $variant->id, 'is_active' => 1])
                ->assertSessionHasErrors('product_variant_id');
        }
        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => '', 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertSame(3, Recipe::count());
    }

    // ---- Long names ----------------------------------------------------------------------------------

    public function test_names_that_fit_are_never_altered(): void
    {
        $this->assertSame('Brown Sugar Fresh Milk - 1L', RecipeWriter::defaultName($this->milk1L->load('product')));

        $exact = $this->nameFor(str_repeat('P', 255 - 3 - 4), 'Size');
        $this->assertSame(255, mb_strlen($exact));
        $this->assertSame(str_repeat('P', 248).' - Size', $exact);
    }

    public function test_long_product_name_is_cut_first_and_the_variant_suffix_survives(): void
    {
        $name = $this->nameFor(str_repeat('A', 400), '1L');

        $this->assertSame(255, mb_strlen($name));
        $this->assertStringEndsWith('… - 1L', $name);
        $this->assertStringStartsWith(str_repeat('A', 100), $name);
    }

    public function test_long_variant_name_is_shortened_with_an_ellipsis_and_the_product_keeps_its_room(): void
    {
        $name = $this->nameFor('Matcha', str_repeat('V', 400));

        $this->assertLessThanOrEqual(255, mb_strlen($name));
        $this->assertSame('Matcha - '.str_repeat('V', 99).'…', $name);

        // Product and Variant both far too long: Variant capped at 100, Product takes the rest, total exactly 255.
        $both = $this->nameFor(str_repeat('P', 500), str_repeat('V', 500));
        $this->assertSame(255, mb_strlen($both));
        $this->assertStringEndsWith(' - '.str_repeat('V', 99).'…', $both);
        $this->assertStringStartsWith(str_repeat('P', 100), $both);
    }

    public function test_names_are_cut_by_character_never_in_the_middle_of_a_multibyte_character(): void
    {
        foreach ([str_repeat('抹茶', 200), str_repeat('Café Crème ', 60), str_repeat("\u{1F375}", 300)] as $product) {
            foreach (['1L', str_repeat('大', 300), 'Regülär'] as $variant) {
                $name = $this->nameFor($product, $variant);

                $this->assertLessThanOrEqual(255, mb_strlen($name));
                $this->assertTrue(mb_check_encoding($name, 'UTF-8'));
                $this->assertNotSame(false, json_encode($name), 'valid UTF-8 survives encoding');
            }
        }

        $this->assertStringEndsWith(' - Regülär', $this->nameFor(str_repeat('抹茶', 200), 'Regülär'));
    }

    public function test_a_long_generated_name_is_stored_and_is_never_over_255(): void
    {
        $product = $this->makeProduct(str_repeat('Brown Sugar ', 40), 'LONG', [$this->bxc]);
        $variant = $this->makeVariant($product, '1L', [$this->bxc]);

        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $product->id, 'product_variant_id' => $variant->id, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $stored = Recipe::where('product_variant_id', $variant->id)->value('name');
        $this->assertLessThanOrEqual(255, mb_strlen($stored));
        $this->assertStringEndsWith('… - 1L', $stored);

        // The preview shown on the form is the very same string.
        $menus = collect($this->get(route('backoffice.recipes.create'))->viewData('menuOptions'))->keyBy('id');
        $this->assertSame($stored, $menus[$product->id]['variants'][0]['recipe_name']);
    }

    public function test_product_workspace_prefill_uses_the_same_name_and_is_accepted_by_its_own_store(): void
    {
        $product = $this->makeProduct(str_repeat('Waspffle ', 60), 'WORK', [$this->bxc]);
        $variant = $this->makeVariant($product, 'Regular', [$this->bxc]);
        $this->actingAs($this->adminPusat);

        $html = $this->getJson(route('backoffice.products.workspace.recipes.create-form', [$product, $variant]))
            ->assertOk()->assertJsonPath('ok', true)->json('html');

        $this->assertSame(1, preg_match('/id="pw-recipe-name"[^>]*value="([^"]*)"/', $html, $m));
        $prefill = html_entity_decode($m[1], ENT_QUOTES);
        $this->assertSame(RecipeWriter::defaultName($variant->fresh('product')), $prefill);
        $this->assertLessThanOrEqual(255, mb_strlen($prefill));
        $this->assertStringEndsWith('… - Regular', $prefill);

        $this->postJson(route('backoffice.products.workspace.recipes.store', [$product, $variant]), ['name' => $prefill])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame($prefill, Recipe::where('product_variant_id', $variant->id)->value('name'));
    }

    // ---- Saving -----------------------------------------------------------------------------------

    public function test_recipe_is_created_without_typing_a_name(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1])
            ->assertSessionHasNoErrors()->assertRedirect();

        $recipe = Recipe::where('product_variant_id', $this->milk1L->id)->firstOrFail();
        $this->assertSame('Brown Sugar Fresh Milk - 1L', $recipe->name);
        $this->assertSame($this->milk->id, $recipe->product_id);
        $this->assertTrue($recipe->is_active);
        $this->assertSame(1, Recipe::count());
    }

    public function test_apostrophe_names_are_stored_verbatim(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->remedy->id, 'product_variant_id' => $this->remedyRegular->id, 'is_active' => 0])
            ->assertSessionHasNoErrors();

        $recipe = Recipe::where('product_variant_id', $this->remedyRegular->id)->firstOrFail();
        $this->assertSame("Ah-Ma's Remedy - Regular", $recipe->name);
        $this->assertFalse($recipe->is_active);
    }

    public function test_a_client_submitted_name_is_never_trusted(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), [
                'name' => 'Totally Different',
                'product_id' => $this->milk->id,
                'product_variant_id' => $this->milkRc->id,
                'is_active' => 1,
            ])->assertSessionHasNoErrors();

        $this->assertSame('Brown Sugar Fresh Milk - RC', Recipe::where('product_variant_id', $this->milkRc->id)->value('name'));
    }

    public function test_older_callers_that_only_send_the_variant_still_work(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_variant_id' => $this->milkL->id, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('Brown Sugar Fresh Milk - L', Recipe::where('product_variant_id', $this->milkL->id)->value('name'));
    }

    public function test_status_is_required_and_validated_as_before(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milkRc->id])
            ->assertSessionHasErrors('is_active');

        $this->assertSame(0, Recipe::count());
    }

    // ---- Rejections ---------------------------------------------------------------------------------

    public function test_duplicate_recipe_for_the_same_variant_is_rejected(): void
    {
        $existing = $this->makeRecipe($this->milk1L, 'Legacy 1L Name');

        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1])
            ->assertSessionHasErrors(['product_variant_id' => 'Variant ini sudah memiliki Recipe. Buka Recipe yang ada, jangan buat yang baru.']);

        $this->assertSame(1, Recipe::count());
        $this->assertSame('Legacy 1L Name', $existing->fresh()->name);
    }

    public function test_a_variant_whose_recipe_is_inactive_is_just_as_taken(): void
    {
        $recipe = $this->makeRecipe($this->milk1L, 'Inactive Legacy');
        $recipe->update(['is_active' => false]);

        $menus = collect($this->actingAs($this->adminPusat)->get(route('backoffice.recipes.create'))->viewData('menuOptions'))->keyBy('id');
        $this->assertNotNull(collect($menus[$this->milk->id]['variants'])->firstWhere('id', $this->milk1L->id)['existing']);

        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');
        $this->assertSame(1, Recipe::count());
        $this->assertFalse($recipe->fresh()->is_active);
    }

    public function test_variant_that_does_not_belong_to_the_selected_product_is_rejected(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->remedy->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1])
            ->assertSessionHasErrors(['product_variant_id' => 'Variant tidak sesuai dengan Menu / Product yang dipilih.']);

        $this->assertSame(0, Recipe::count());
    }

    public function test_malformed_or_unknown_ids_are_rejected(): void
    {
        $this->actingAs($this->adminPusat);

        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');
        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => 'abc', 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');
        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => 99999, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');
        $this->post(route('backoffice.recipes.store'), ['product_id' => 99999, 'product_variant_id' => $this->milkRc->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_id');
        $this->post(route('backoffice.recipes.store'), ['product_id' => ['x'], 'product_variant_id' => [1], 'is_active' => 1])
            ->assertSessionHasErrors(['product_id', 'product_variant_id']);

        $this->assertSame(0, Recipe::count());
    }

    public function test_variant_of_a_deleted_product_or_a_deleted_variant_is_rejected(): void
    {
        $this->milkL->delete();
        $this->remedy->delete();

        $this->actingAs($this->adminPusat);
        $this->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milkL->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');
        // The Variant row itself is alive; only its Product is deleted.
        $this->post(route('backoffice.recipes.store'), ['product_variant_id' => $this->remedyRegular->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, Recipe::count());
    }

    public function test_limited_user_cannot_use_a_variant_outside_their_outlets(): void
    {
        // Regular reaches Bazaar as well: out of reach for a BXC-only user, even with a hand-made request.
        $this->actingAs($this->bxcAdmin)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->remedy->id, 'product_variant_id' => $this->remedyRegular->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertSame(0, Recipe::count());

        // A Variant of a Bazaar-only Product is not even in their scope.
        $bazaarOnly = $this->makeVariant($this->makeProduct('Bazaar Only', 'BZR-ONLY', [$this->bazaar]), 'Std', [$this->bazaar]);
        $this->post(route('backoffice.recipes.store'), ['product_variant_id' => $bazaarOnly->id, 'is_active' => 1])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertSame(0, Recipe::count());
    }

    public function test_role_without_recipe_access_is_forbidden(): void
    {
        $cashier = $this->makeUser('kasir', 'kasir', [$this->bxc]);

        $this->actingAs($cashier)->get(route('backoffice.recipes.create'))->assertForbidden();
        $this->actingAs($cashier)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milkRc->id, 'is_active' => 1])
            ->assertForbidden();
    }

    // ---- Form state & backward compatibility --------------------------------------------------------

    public function test_failed_submit_keeps_the_selected_menu_variant_and_status(): void
    {
        $this->actingAs($this->adminPusat)
            ->from(route('backoffice.recipes.create'))
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milkL->id, 'is_active' => 'nope'])
            ->assertRedirect(route('backoffice.recipes.create'))
            ->assertSessionHasErrors('is_active');

        $page = $this->get(route('backoffice.recipes.create'))->assertOk();
        $page->assertSee('var oldProduct = "'.$this->milk->id.'"', false);
        $page->assertSee('var oldVariant = "'.$this->milkL->id.'"', false);
    }

    public function test_creating_a_recipe_leaves_existing_recipes_untouched(): void
    {
        $legacy = $this->makeRecipe($this->milkRc, 'Es Susu Gula Aren (resep lama)');
        $item = RecipeItem::create(['recipe_id' => $legacy->id, 'ingredient_id' => $this->makeIngredient('Susu')->id, 'qty' => 25, 'unit' => 'gram']);

        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1])
            ->assertSessionHasNoErrors();

        $this->assertSame('Es Susu Gula Aren (resep lama)', $legacy->fresh()->name);
        $this->assertSame(1, $legacy->items()->count());
        $this->assertSame(25.0, (float) $item->fresh()->qty);
        $this->assertSame(2, Recipe::count());
    }

    public function test_new_recipe_opens_in_the_editor_and_ingredients_can_be_managed(): void
    {
        $this->actingAs($this->adminPusat)
            ->post(route('backoffice.recipes.store'), ['product_id' => $this->milk->id, 'product_variant_id' => $this->milk1L->id, 'is_active' => 1]);

        $recipe = Recipe::where('product_variant_id', $this->milk1L->id)->firstOrFail();
        $sugar = $this->makeIngredient('Gula Aren');

        $this->get(route('backoffice.recipes.edit', $recipe))->assertOk()->assertSee('Brown Sugar Fresh Milk - 1L');

        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $sugar->id, 'qty' => 12.5])->assertSessionHasNoErrors();
        $item = $recipe->items()->firstOrFail();
        $this->assertSame(12.5, (float) $item->qty);

        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 20])->assertSessionHasNoErrors();
        $this->assertSame(20.0, (float) $item->fresh()->qty);

        // The editor still lets a user rename the Recipe by hand afterwards (only creation is automatic).
        $this->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $this->milk1L->id, 'name' => 'Custom Name', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('Custom Name', $recipe->fresh()->name);

        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $item]))->assertSessionHasNoErrors();
        $this->assertSame(0, $recipe->items()->count());
    }

    // ---- helpers -------------------------------------------------------------------------------------

    /** defaultName() for an unsaved Product + Variant pair. */
    private function nameFor(string $product, string $variant): string
    {
        return RecipeWriter::defaultName(
            (new ProductVariant(['name' => $variant]))->setRelation('product', new Product(['name' => $product]))
        );
    }

    private function makeProduct(string $name, string $code, array $outlets): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->category->id,
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $product;
    }

    private function makeVariant(Product $product, string $name, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $name,
            'code' => $product->code.'-'.strtoupper($name),
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function makeIngredient(string $name): Ingredient
    {
        $category = IngredientCategory::firstOrCreate(['code' => 'POWDER'], ['name' => 'Powder', 'is_active' => true]);
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => 'gram',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->bxc->id, $this->bazaar->id]);

        return $ingredient;
    }

    private function makeRecipe(ProductVariant $variant, string $name): Recipe
    {
        return Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $username, string $roleCode, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleCode])->id,
            'outlet_id' => $outlets[0]->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
