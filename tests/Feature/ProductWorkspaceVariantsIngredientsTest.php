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
use App\Services\VariantWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UX-D (Variants & Pricing in the workspace) and UX-E (Ingredient workflow from the workspace).
 */
class ProductWorkspaceVariantsIngredientsTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private Brand $brand;

    private Product $product;

    private ProductVariant $regular;

    private IngredientCategory $dairy;

    private Ingredient $milk;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'B', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'C', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $category->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => true]);
        $this->product->outlets()->sync([$this->a->id, $this->b->id, $this->c->id]);

        $this->regular = $this->variant($this->product, 'Regular', 'UBE-R', 20000, 22000, [$this->a, $this->b, $this->c]);

        $this->dairy = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = $this->ingredient('Fresh Milk', [$this->a, $this->b, $this->c]);

        $this->owner = $this->user('owner', [$this->a, $this->b, $this->c]);
    }

    // ================================================================================================
    // UX-D: Variants & Pricing
    // ================================================================================================

    public function test_create_variant_in_the_workspace_with_rupiah_prices_and_outlets(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.variants.store', $this->product), [
                'name' => 'Large', 'code' => 'ube-l', 'price_dine_in' => 'Rp. 25.000', 'price_delivery' => 'Rp. 27.500',
                'outlet_ids' => [$this->a->id, $this->b->id], 'is_active' => 1,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'variants', 'message' => 'Variant "Large" berhasil ditambahkan.']);

        $variant = ProductVariant::where('code', 'UBE-L')->firstOrFail();
        $this->assertSame($this->product->id, (int) $variant->product_id);
        $this->assertSame('25000.00', $variant->price_dine_in);
        $this->assertSame('27500.00', $variant->price_delivery);
        $this->assertSame('25000.00', $variant->price, 'legacy price follows dine-in');
        $this->assertTrue($variant->is_active);
        $this->assertNull($variant->outlet_id);
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $variant->outlets()->pluck('outlets.id')->all());
        $this->assertStringContainsString('data-pw-variant="'.$variant->id.'"', $response->json('sections.variants'));
    }

    public static function rupiahProvider(): array
    {
        return [
            'formatted' => ['Rp. 12.000', '12000'],
            'plain digits' => ['12000', '12000'],
            'stored decimal with dot' => ['12000.00', '12000'],
            'stored decimal with comma' => ['12000,00', '12000'],
            'thousands only' => ['12.000', '12000'],
            'spaces' => ['  Rp 1.250.000 ', '1250000'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('rupiahProvider')]
    public function test_rupiah_normalisation_matches_the_variant_editor(string $input, string $expected): void
    {
        $this->assertSame($expected, VariantWriter::normalizeRupiah($input));
    }

    public function test_update_variant_changes_identity_prices_status_and_outlets(): void
    {
        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), [
                'name' => 'Regular Ice', 'code' => 'UBE-RI', 'price_dine_in' => 'Rp. 21.500', 'price_delivery' => '23500',
                'outlet_ids' => [$this->b->id], 'is_active' => 0,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'variants']);

        $variant = $this->regular->fresh();
        $this->assertSame('Regular Ice', $variant->name);
        $this->assertSame('UBE-RI', $variant->code);
        $this->assertSame('21500.00', $variant->price_dine_in);
        $this->assertSame('23500.00', $variant->price_delivery);
        $this->assertSame('21500.00', $variant->price);
        $this->assertFalse($variant->is_active);
        $this->assertSame([$this->b->id], $variant->outlets()->pluck('outlets.id')->all());
    }

    // ---- Variant Code is internal: not in the forms, generated for new Variants, kept for existing ones --------

    public function test_variant_code_is_not_part_of_the_workspace_drawer_or_the_classic_forms(): void
    {
        $this->actingAs($this->owner);

        foreach ([
            route('backoffice.products.workspace.variants.create-form', $this->product),
            route('backoffice.products.workspace.variants.edit-form', [$this->product, $this->regular]),
        ] as $url) {
            $html = $this->getJson($url)->assertOk()->json('html');

            $this->assertStringContainsString('name="name"', $html);
            $this->assertStringNotContainsString('name="code"', $html);
            $this->assertStringNotContainsString('Kode Variant', $html);
        }

        foreach ([route('backoffice.variants.create'), route('backoffice.variants.edit', $this->regular)] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('Kode Variant', $html);
            $this->assertStringNotContainsString('][code]', $html);
            $this->assertStringNotContainsString('data-name="code"', $html);
        }
    }

    public function test_a_new_variant_without_a_code_gets_a_generated_unique_internal_code(): void
    {
        $this->actingAs($this->owner);
        $payload = $this->variantPayload(['name' => 'Large Ice']);
        unset($payload['code']);

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $payload)->assertOk();
        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $payload)->assertOk();
        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $payload + [])->assertOk();

        $codes = ProductVariant::where('product_id', $this->product->id)->where('name', 'Large Ice')->orderBy('id')->pluck('code')->all();
        $this->assertSame(['UBE-LARGE-ICE', 'UBE-LARGE-ICE-2', 'UBE-LARGE-ICE-3'], $codes);
    }

    public function test_generated_codes_are_clean_bounded_and_never_collide(): void
    {
        $product = $this->product;

        $this->assertSame('UBE-V', VariantWriter::generateCode($product, ''));
        $this->assertSame('UBE-V', VariantWriter::generateCode($product, '***'));
        $this->assertSame('UBE-ES-KOPI-SUSU-GULA-AREN', VariantWriter::generateCode($product, '*Es Kopi (Susu) Gula   Aren!'));
        $this->assertSame('UBE-CAFE-LATTE', VariantWriter::generateCode($product, 'Café Latté'));
        // an existing code (any case) is reserved
        $this->assertSame('UBE-R-2', VariantWriter::generateCode($product, 'R', ['UBE-R' => true]));

        $long = VariantWriter::generateCode($product, str_repeat('Waspffle Salty Cheesy ', 6));
        $this->assertLessThanOrEqual(50, strlen($long));
        $this->assertDoesNotMatchRegularExpression('/-$|--/', $long);
        $taken = VariantWriter::generateCode($product, str_repeat('Waspffle Salty Cheesy ', 6), [$long => true]);
        $this->assertLessThanOrEqual(50, strlen($taken));
        $this->assertNotSame($long, $taken);
        $this->assertStringEndsWith('-2', $taken);
    }

    public function test_editing_a_variant_never_changes_its_code(): void
    {
        $this->regular->update(['code' => 'legacy-Code_1']);   // a historical, odd-looking code stays exactly as stored

        $payload = $this->variantPayload(['name' => 'Completely Renamed', 'price_dine_in' => '30000', 'outlet_ids' => [$this->b->id]]);
        unset($payload['code']);

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $payload)
            ->assertOk();

        $variant = $this->regular->fresh();
        $this->assertSame('Completely Renamed', $variant->name);
        $this->assertSame('30000.00', $variant->price_dine_in);
        $this->assertSame('legacy-Code_1', $variant->code);
    }

    public function test_the_classic_group_editor_keeps_existing_codes_and_generates_one_for_a_new_row(): void
    {
        $large = $this->variant($this->product, 'Large', 'ube-large-old', 1, 1, [$this->a]);
        $row = fn (ProductVariant $variant, array $extra = []) => array_merge([
            'id' => $variant->id, 'name' => $variant->name, 'outlet_ids' => [$this->a->id],
            'price_dine_in' => '20000', 'price_delivery' => '22000', 'is_active' => 1,
        ], $extra);

        $this->actingAs($this->owner)
            ->put(route('backoffice.variants.update', $this->regular), [
                'product_id' => $this->product->id,
                'variants' => [
                    $row($this->regular, ['name' => 'Regular Renamed']),
                    $row($large),
                    ['name' => 'Jumbo', 'outlet_ids' => [$this->a->id], 'price_dine_in' => '30000', 'price_delivery' => '32000', 'is_active' => 1],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('UBE-R', $this->regular->fresh()->code);
        $this->assertSame('Regular Renamed', $this->regular->fresh()->name);
        $this->assertSame('ube-large-old', $large->fresh()->code, 'existing code untouched, not even upper-cased');
        $this->assertSame('UBE-JUMBO', ProductVariant::where('product_id', $this->product->id)->where('name', 'Jumbo')->value('code'));
    }

    public function test_the_classic_create_form_generates_codes_for_every_new_row(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.variants.store'), [
                'product_id' => $this->product->id,
                'variants' => [
                    ['name' => 'Tall', 'outlet_ids' => [$this->a->id], 'price_dine_in' => '1000', 'price_delivery' => '1000', 'is_active' => 1],
                    ['name' => 'Tall', 'outlet_ids' => [$this->a->id], 'price_dine_in' => '1000', 'price_delivery' => '1000', 'is_active' => 1],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['UBE-TALL', 'UBE-TALL-2'], ProductVariant::where('product_id', $this->product->id)->where('name', 'Tall')->orderBy('id')->pluck('code')->all());
    }

    public function test_the_code_constraint_is_unique_per_product_and_the_generator_follows_exactly_that(): void
    {
        // the database's final authority: one unique index on (product_id, code), no global uniqueness
        $unique = collect(Schema::getIndexes('product_variants'))->where('unique', true)->where('primary', false)->pluck('columns')->all();
        $this->assertSame([['product_id', 'code']], $unique);

        // the same code under two different Products is therefore legal ...
        $other = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->product->product_category_id, 'name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $other->outlets()->sync([$this->a->id]);
        $this->variant($other, 'Regular', 'UBE-R', 1, 1, [$this->a]);   // UBE-R already exists under Ube Latte
        $this->assertSame(2, ProductVariant::where('code', 'UBE-R')->count());

        // ... so the generator only avoids collisions inside the Product it is generating for
        $this->assertSame('UBE-R-2', VariantWriter::generateCode($this->product, 'R', ['UBE-R' => true]));
        $this->assertSame('OTH-R', VariantWriter::generateCode($other, 'R'));

        // through the writer: a code taken (any case) by a sibling gets the deterministic suffix, never a DB error
        $this->regular->update(['code' => 'ube-sibling']);
        $payload = $this->variantPayload(['name' => 'Sibling']);
        unset($payload['code']);
        $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.variants.store', $this->product), $payload)->assertOk();
        $this->assertSame('UBE-SIBLING-2', ProductVariant::where('product_id', $this->product->id)->where('name', 'Sibling')->value('code'));
        $this->actingAs($this->owner)->postJson(route('backoffice.products.workspace.variants.store', $this->product), $payload)->assertOk();
        $this->assertSame(['UBE-SIBLING-2', 'UBE-SIBLING-3'], ProductVariant::where('product_id', $this->product->id)->where('name', 'Sibling')->orderBy('id')->pluck('code')->all());
        $this->assertSame('ube-sibling', $this->regular->fresh()->code, 'the existing code is untouched');
    }

    public function test_an_explicitly_submitted_code_is_still_honoured_and_still_unique_per_product(): void
    {
        $this->actingAs($this->owner);

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['name' => 'Medium', 'code' => 'imp-m']))->assertOk();
        $this->assertSame('IMP-M', ProductVariant::where('name', 'Medium')->value('code'));

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['name' => 'Medium 2', 'code' => 'IMP-M']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code' => 'Kode variant sudah dipakai pada product ini: IMP-M']);
    }

    public function test_variant_validation_errors(): void
    {
        $this->variant($this->product, 'Large', 'UBE-L', 1, 1, [$this->a]);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.variants.store', $this->product), ['outlet_ids' => [$this->a->id]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'price_dine_in', 'price_delivery']);

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['code' => 'ube-l']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['code' => 'Kode variant sudah dipakai pada product ini: UBE-L']);

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['price_dine_in' => 'gratis']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_dine_in']);

        $this->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['outlet_ids' => []]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_ids']);

        $this->assertSame(2, ProductVariant::count());
    }

    public function test_variant_outlets_must_be_a_subset_of_the_products_saved_outlets(): void
    {
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['outlet_ids' => [$this->a->id, $this->c->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_ids' => 'Outlet variant harus merupakan subset outlet Product. Outlet tidak valid: Charlie']);

        // The drawer only offers the Product's CURRENT outlets.
        $html = $this->getJson(route('backoffice.products.workspace.variants.create-form', $this->product))->assertOk()->json('html');
        $this->assertStringContainsString('value="'.$this->a->id.'"', $html);
        $this->assertStringContainsString('value="'.$this->b->id.'"', $html);
        $this->assertStringNotContainsString('name="outlet_ids[]" value="'.$this->c->id.'"', $html);
    }

    public function test_limited_user_keeps_variant_outlets_outside_their_access_in_the_workspace(): void
    {
        // Variant at A, B, C; the user can access A and B only; saves A checked, B unchecked.
        $limited = $this->user('admin_outlet', [$this->a, $this->b]);

        $form = $this->actingAs($limited)->getJson(route('backoffice.products.workspace.variants.edit-form', [$this->product, $this->regular]))->assertOk()->json('html');
        $this->assertStringNotContainsString('name="outlet_ids[]" value="'.$this->c->id.'"', $form);
        $this->assertStringContainsString('pw-chip-locked">Charlie', $form);

        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload(['name' => 'Regular', 'code' => 'UBE-R', 'outlet_ids' => [$this->a->id]]))
            ->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->regular->outlets()->pluck('outlets.id')->all());

        // ...and cannot add an outlet outside their access.
        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload(['name' => 'Regular', 'code' => 'UBE-R', 'outlet_ids' => [$this->a->id, $this->c->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outlet_ids']);
    }

    public function test_regression_classic_variant_group_editor_also_keeps_inaccessible_outlets(): void
    {
        $limited = $this->user('admin_outlet', [$this->a, $this->b]);

        $this->actingAs($limited)
            ->put(route('backoffice.variants.update', $this->regular), [
                'product_id' => $this->product->id,
                'variants' => [[
                    'id' => $this->regular->id, 'name' => 'Regular', 'code' => 'UBE-R',
                    'outlet_ids' => [$this->a->id], 'price_dine_in' => 20000, 'price_delivery' => 22000, 'is_active' => 1,
                ]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->regular->outlets()->pluck('outlets.id')->all());
    }

    public function test_preserved_outlets_never_leave_the_products_outlets(): void
    {
        // Legacy data: the Variant still has C although the Product no longer does.
        $this->product->outlets()->sync([$this->a->id, $this->b->id]);
        $limited = $this->user('admin_outlet', [$this->a, $this->b]);

        $this->actingAs($limited)
            ->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload(['name' => 'Regular', 'code' => 'UBE-R', 'outlet_ids' => [$this->a->id]]))
            ->assertOk();

        $this->assertSame([$this->a->id], $this->regular->outlets()->pluck('outlets.id')->all());
    }

    public function test_classic_variant_controller_uses_the_same_writer_rules(): void
    {
        // A code clash used to slip past the controller (legacy outlet_id filter) into a database error.
        $this->regular->forceFill(['outlet_id' => $this->a->id])->save();

        $this->actingAs($this->owner)
            ->post(route('backoffice.variants.store'), [
                'product_id' => $this->product->id,
                'variants' => [['name' => 'Again', 'code' => 'ube-r', 'outlet_ids' => [$this->a->id], 'price_dine_in' => 1, 'price_delivery' => 1]],
            ])
            ->assertSessionHasErrors(['variants' => 'Kode variant sudah dipakai pada product ini: UBE-R']);

        $this->assertSame(1, ProductVariant::count());
    }

    public function test_deactivate_variant_keeps_outlets_and_recipe_and_can_be_reactivated(): void
    {
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->regular->id, 'name' => 'R', 'is_active' => true]);

        $this->actingAs($this->owner)
            ->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->product, $this->regular]))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'variants']);

        $this->assertFalse($this->regular->fresh()->is_active);
        $this->assertCount(3, $this->regular->outlets()->get());
        $this->assertTrue(Recipe::whereKey($recipe->id)->exists());
        $this->assertSame(1, ProductVariant::count(), 'never deleted');

        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload(['name' => 'Regular', 'code' => 'UBE-R', 'outlet_ids' => [$this->a->id, $this->b->id, $this->c->id], 'is_active' => 1]))
            ->assertOk();
        $this->assertTrue($this->regular->fresh()->is_active);
    }

    public function test_no_variant_delete_is_exposed_in_the_workspace(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with((string) $route->getName(), 'backoffice.products.workspace.')) {
                $this->assertNotContains('DELETE', $route->methods(), $route->getName());
            }
        }

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'variants']))->getContent();
        $this->assertStringNotContainsString('Hapus', $this->between($html, 'data-pw-panel="variants"', 'data-pw-panel="recipe"'));
    }

    public function test_variant_from_another_product_is_rejected_on_every_nested_endpoint(): void
    {
        $other = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->product->product_category_id, 'name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);
        $other->outlets()->sync([$this->a->id]);
        $foreign = $this->variant($other, 'Foreign', 'OTHER-R', 1, 1, [$this->a]);

        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.variants.edit-form', [$this->product, $foreign]))->assertNotFound();
        $this->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $foreign]), $this->variantPayload())->assertNotFound();
        $this->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->product, $foreign]))->assertNotFound();

        $this->assertTrue($foreign->fresh()->is_active);
        $this->assertSame('Foreign', $foreign->fresh()->name);
    }

    public function test_variant_writes_cannot_move_a_variant_to_another_product(): void
    {
        $other = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->product->product_category_id, 'name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload(['name' => 'Regular', 'code' => 'UBE-R', 'product_id' => $other->id]))
            ->assertOk();

        $this->assertSame($this->product->id, (int) $this->regular->fresh()->product_id);
    }

    public function test_variant_endpoints_respect_product_scope_and_roles(): void
    {
        $outOfScope = $this->user('admin_outlet', [Outlet::create(['name' => 'Delta', 'code' => 'D', 'is_active' => true])]);
        $staff = $this->user('staff_gudang', [$this->a]);

        foreach ([$outOfScope, $staff] as $user) {
            $this->actingAs($user)->getJson(route('backoffice.products.workspace.variants.create-form', $this->product))->assertForbidden();
            $this->actingAs($user)->postJson(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload())->assertForbidden();
            $this->actingAs($user)->putJson(route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]), $this->variantPayload())->assertForbidden();
            $this->actingAs($user)->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->product, $this->regular]))->assertForbidden();
        }

        $this->assertSame(1, ProductVariant::count());
        $this->assertTrue($this->regular->fresh()->is_active);
    }

    public function test_variant_save_without_js_returns_to_the_variants_section(): void
    {
        $returnTo = '/backoffice/products?search=ube';

        $this->actingAs($this->owner)
            ->post(route('backoffice.products.workspace.variants.store', $this->product), $this->variantPayload(['return_to' => $returnTo]))
            ->assertRedirect(url(ProductWorkspace::url($this->product, 'variants', $returnTo)))
            ->assertSessionHas('success');

        $this->patch(route('backoffice.products.workspace.variants.deactivate', [$this->product, $this->regular]), ['return_to' => $returnTo])
            ->assertRedirect(url(ProductWorkspace::url($this->product, 'variants', $returnTo)));
    }

    public function test_variants_section_offers_drawer_actions_with_classic_fallback_links(): void
    {
        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'variants']))->getContent();
        $back = ProductWorkspace::url($this->product, 'variants', null);

        $this->assertStringContainsString('data-pw-form-url="'.e(route('backoffice.products.workspace.variants.create-form', $this->product, false)).'"', $html);
        $this->assertStringContainsString('data-pw-form-url="'.e(route('backoffice.products.workspace.variants.edit-form', [$this->product, $this->regular], false)).'"', $html);
        $this->assertStringContainsString('href="'.e(route('backoffice.variants.edit', [$this->regular->id, 'return_to' => $back], false)).'"', $html);
        $this->assertStringContainsString('action="'.route('backoffice.products.workspace.variants.deactivate', [$this->product, $this->regular], false).'"', $html);
        $this->assertStringContainsString('data-pw-drawer="variant"', $html);
    }

    public function test_variant_drawer_prefills_formatted_prices(): void
    {
        $html = $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.variants.edit-form', [$this->product, $this->regular]))->json('html');

        $this->assertStringContainsString('value="Rp. 20.000"', $html);
        $this->assertStringContainsString('value="Rp. 22.000"', $html);
        $this->assertStringContainsString('action="'.route('backoffice.products.workspace.variants.update', [$this->product, $this->regular]).'"', $html);
    }

    // ================================================================================================
    // UX-E: Ingredient workflow
    // ================================================================================================

    public function test_create_ingredient_from_the_workspace(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['name' => 'Ube Paste']))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'stock', 'message' => 'Ingredient "Ube Paste" berhasil ditambahkan.']);

        $ingredient = Ingredient::where('name', 'Ube Paste')->firstOrFail();
        $this->assertSame('UBE_PASTE', $ingredient->code);
        $this->assertSame($this->dairy->id, (int) $ingredient->ingredient_category_id);
        $this->assertSame('gram', $ingredient->unit);
        $this->assertSame('semi_finished', $ingredient->ingredient_type);
        $this->assertSame('250.00', $ingredient->minimum_stock);
        $this->assertSame('15.50', $ingredient->cost_per_unit);
        $this->assertTrue($ingredient->is_active);
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $ingredient->outlets()->pluck('outlets.id')->all());
        $this->assertArrayHasKey('stock', $response->json('sections'));
    }

    public function test_update_ingredient_keeps_recipe_items_untouched(): void
    {
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->regular->id, 'name' => 'R', 'is_active' => true]);
        $item = RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 150, 'unit' => 'ml']);
        $itemsBefore = RecipeItem::orderBy('id')->get()->toArray();
        $recipeBefore = $recipe->fresh()->toArray();

        $this->actingAs($this->owner)
            ->putJson(route('backoffice.products.workspace.ingredients.update', [$this->product, $this->milk]), $this->ingredientPayload([
                'name' => 'Fresh Milk UHT', 'unit' => 'ml', 'ingredient_type' => 'raw', 'minimum_stock' => 1000, 'is_active' => 0,
                'outlet_ids' => [$this->a->id], 'return_section' => 'recipe',
            ]))
            ->assertOk()
            ->assertJson(['ok' => true, 'section' => 'recipe']);

        $milk = $this->milk->fresh();
        $this->assertSame('Fresh Milk UHT', $milk->name);
        $this->assertSame('FRESH_MILK_UHT', $milk->code);
        $this->assertSame('1000.00', $milk->minimum_stock);
        $this->assertFalse($milk->is_active);
        $this->assertSame([$this->a->id], $milk->outlets()->pluck('outlets.id')->all());

        // Recipe and its items are exactly as before.
        $this->assertSame($itemsBefore, RecipeItem::orderBy('id')->get()->toArray());
        $this->assertSame($recipeBefore, $recipe->fresh()->toArray());
        $this->assertSame('150.00', $item->fresh()->qty);
    }

    public function test_ingredient_validation_errors(): void
    {
        $inactiveCategory = IngredientCategory::create(['name' => 'Old', 'code' => 'OLD', 'is_active' => false]);

        $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['name' => 'Fresh Milk']))
            ->assertStatus(422)->assertJsonValidationErrors(['name']);

        $this->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['ingredient_category_id' => $inactiveCategory->id]))
            ->assertStatus(422)->assertJsonValidationErrors(['ingredient_category_id']);

        $this->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['unit' => 'kg']))
            ->assertStatus(422)->assertJsonValidationErrors(['unit']);

        $this->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['minimum_stock' => -1]))
            ->assertStatus(422)->assertJsonValidationErrors(['minimum_stock']);

        $this->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['outlet_ids' => []]))
            ->assertStatus(422)->assertJsonValidationErrors(['outlet_ids']);

        $this->assertSame(1, Ingredient::count());
    }

    public function test_limited_user_keeps_ingredient_outlets_outside_their_access(): void
    {
        $limited = $this->user('admin_outlet', [$this->a, $this->b]);

        $form = $this->actingAs($limited)->getJson(route('backoffice.products.workspace.ingredients.edit-form', [$this->product, $this->milk]))->assertOk()->json('html');
        $this->assertStringNotContainsString('name="outlet_ids[]" value="'.$this->c->id.'"', $form);
        $this->assertStringContainsString('pw-chip-locked">Charlie', $form);

        $this->putJson(route('backoffice.products.workspace.ingredients.update', [$this->product, $this->milk]), $this->ingredientPayload(['name' => 'Fresh Milk', 'outlet_ids' => [$this->a->id]]))
            ->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->milk->outlets()->pluck('outlets.id')->all());

        $this->putJson(route('backoffice.products.workspace.ingredients.update', [$this->product, $this->milk]), $this->ingredientPayload(['name' => 'Fresh Milk', 'outlet_ids' => [$this->c->id]]))
            ->assertStatus(422)->assertJsonValidationErrors(['outlet_ids']);
    }

    public function test_ingredient_authorization(): void
    {
        $delta = Outlet::create(['name' => 'Delta', 'code' => 'D', 'is_active' => true]);
        $elsewhere = $this->ingredient('Elsewhere Syrup', [$delta]);
        $limited = $this->user('admin_outlet', [$this->a]);
        $staff = $this->user('staff_gudang', [$this->a]);

        // Ingredient outside the limited user's outlets: not editable from their workspace.
        $this->actingAs($limited)->getJson(route('backoffice.products.workspace.ingredients.edit-form', [$this->product, $elsewhere]))->assertForbidden();
        $this->actingAs($limited)->putJson(route('backoffice.products.workspace.ingredients.update', [$this->product, $elsewhere]), $this->ingredientPayload(['name' => 'Hacked', 'outlet_ids' => [$this->a->id]]))->assertForbidden();

        // No Product access -> no workspace Ingredient actions (the Ingredient page itself is unchanged).
        $this->actingAs($staff)->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload())->assertForbidden();
        $this->actingAs($staff)->postJson(route('backoffice.products.workspace.ingredient-categories.store', $this->product), ['name' => 'X', 'is_active' => 1])->assertForbidden();

        $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.ingredients.edit-form', [$this->product, $elsewhere]))->assertOk();

        $this->assertSame('Elsewhere Syrup', $elsewhere->fresh()->name);
    }

    public function test_inline_ingredient_category_uses_the_category_rules(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('backoffice.products.workspace.ingredient-categories.store', $this->product), ['name' => '  Syrup  Base ', 'is_active' => 1])
            ->assertOk()
            ->assertJson(['ok' => true, 'category' => ['name' => 'Syrup Base', 'is_active' => true]]);

        $category = IngredientCategory::findOrFail($response->json('category.id'));
        $this->assertSame('SYRUP_BASE', $category->code);

        $this->postJson(route('backoffice.products.workspace.ingredient-categories.store', $this->product), ['name' => 'dairy', 'is_active' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Category dengan nama tersebut sudah ada (huruf besar/kecil dan spasi dianggap sama).']);

        // Immediately selectable in the Ingredient drawer and usable.
        $this->getJson(route('backoffice.products.workspace.ingredients.create-form', $this->product))->assertSee('<option value=\"'.$category->id.'\"', false);
        $this->postJson(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['ingredient_category_id' => $category->id]))->assertOk();
    }

    public function test_ingredient_create_form_defaults_to_the_products_outlets(): void
    {
        $this->product->outlets()->sync([$this->b->id]);

        $html = $this->actingAs($this->owner)->getJson(route('backoffice.products.workspace.ingredients.create-form', [$this->product, 'return_section' => 'stock']))->json('html');

        $this->assertMatchesRegularExpression('/value="'.$this->b->id.'"\s+checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="'.$this->a->id.'"\s+checked/', $html);
        $this->assertStringContainsString('name="return_section" value="stock"', $html);
        $this->assertStringNotContainsString('DELETE', $html);
    }

    public function test_ingredient_save_without_js_returns_to_its_section(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.products.workspace.ingredients.store', $this->product), $this->ingredientPayload(['return_section' => 'recipe', 'return_to' => '/backoffice/products']))
            ->assertRedirect(url(ProductWorkspace::url($this->product, 'recipe', '/backoffice/products')));

        $this->put(route('backoffice.products.workspace.ingredients.update', [$this->product, $this->milk]), $this->ingredientPayload(['name' => 'Fresh Milk', 'return_section' => 'promo']))
            ->assertRedirect(url(ProductWorkspace::url($this->product, 'stock', null)));
    }

    public function test_no_ingredient_hard_delete_is_exposed_from_the_workspace(): void
    {
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->regular->id, 'name' => 'R', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 150, 'unit' => 'ml']);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->getContent();

        $this->assertStringNotContainsString(route('backoffice.ingredients.destroy', $this->milk), $html);
        $this->assertStringNotContainsString('ingredients/'.$this->milk->id.'" method', $html);
        $this->assertFalse(Route::has('backoffice.products.workspace.ingredients.destroy'));
    }

    public function test_ingredient_entry_points_in_stock_and_recipe(): void
    {
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $this->regular->id, 'name' => 'R', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 150, 'unit' => 'ml']);

        $html = $this->actingAs($this->owner)->get(route('backoffice.products.edit', $this->product))->getContent();

        $this->assertStringContainsString('data-pw-open-drawer="ingredient"', $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.ingredients.create-form', $this->product, false)), $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.ingredients.edit-form', [$this->product, $this->milk, 'return_section' => 'stock'], false)), $html);
        $this->assertStringContainsString(e(route('backoffice.products.workspace.ingredients.edit-form', [$this->product, $this->milk, 'return_section' => 'recipe'], false)), $html);

        // UX-F: Recipe changes go through the workspace Recipe drawer / explicit actions only - never the
        // classic per-item routes, and never a Recipe delete.
        $recipePanel = $this->between($html, 'data-pw-panel="recipe"', 'data-pw-panel="stock"');
        $this->assertStringNotContainsString('/items', $recipePanel);
        $this->assertStringNotContainsString('value="DELETE"', $recipePanel);
        $this->assertStringContainsString('Fresh Milk', $recipePanel);
    }

    public function test_classic_ingredient_routes_still_work_through_the_shared_writer(): void
    {
        $limited = $this->user('admin_outlet', [$this->a, $this->b]);

        $this->actingAs($this->owner)
            ->post(route('backoffice.ingredients.store'), $this->ingredientPayload(['name' => 'Classic Ingredient']))
            ->assertRedirect()
            ->assertSessionHas('success', 'Ingredient berhasil ditambahkan.');
        $this->assertSame('CLASSIC_INGREDIENT', Ingredient::where('name', 'Classic Ingredient')->value('code'));

        $this->actingAs($limited)
            ->put(route('backoffice.ingredients.update', $this->milk), $this->ingredientPayload(['name' => 'Fresh Milk', 'outlet_ids' => [$this->b->id]]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Ingredient berhasil diperbarui.');
        $this->assertEqualsCanonicalizing([$this->b->id, $this->c->id], $this->milk->outlets()->pluck('outlets.id')->all());
    }

    // ---- helpers -------------------------------------------------------------------------------------

    private function variantPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Medium', 'code' => 'UBE-M', 'price_dine_in' => 'Rp. 18.000', 'price_delivery' => 'Rp. 19.000',
            'outlet_ids' => [$this->a->id], 'is_active' => 1,
        ], $overrides);
    }

    private function ingredientPayload(array $overrides = []): array
    {
        return array_merge([
            'ingredient_category_id' => $this->dairy->id, 'name' => 'Ube Paste', 'unit' => 'gram', 'ingredient_type' => 'semi_finished',
            'minimum_stock' => 250, 'cost_per_unit' => 15.5, 'is_active' => 1, 'outlet_ids' => [$this->a->id, $this->b->id],
        ], $overrides);
    }

    private function variant(Product $product, string $name, string $code, float $dineIn, float $delivery, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => $name, 'code' => $code,
            'price' => $dineIn, 'price_dine_in' => $dineIn, 'price_delivery' => $delivery, 'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function ingredient(string $name, array $outlets): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->dairy->id, 'name' => $name, 'code' => strtoupper(str_replace(' ', '_', $name)), 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 100, 'cost_per_unit' => 10, 'is_active' => true,
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
