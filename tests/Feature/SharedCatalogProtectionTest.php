<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Feedback #04 Phase B.1: a Product / Variant is one global row, so its global attributes may only be changed by
 * Owner / Admin Pusat once the item is also assigned to an outlet the user cannot access. Admin Outlet keeps full
 * control of items whose outlets are all within its access, and of outlet assignment inside its access.
 * Also: renaming a Product into a look-alike asks for confirmation.
 */
class SharedCatalogProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private Brand $brand;

    private Brand $otherBrand;

    private ProductCategory $category;

    private ProductCategory $snack;

    /** Ube Latte at A, B, C: shared with outlets a limited user at A / A+B cannot see. */
    private Product $shared;

    private ProductVariant $sharedVariant;

    private ProductVariant $exclusiveVariant;

    /** Mocha at A only. */
    private Product $own;

    private ProductVariant $ownVariant;

    private User $owner;

    private User $pusat;

    private User $limitedA;

    private User $limitedAB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'OA', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'OB', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'OC', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->otherBrand = Brand::create(['name' => 'Other', 'code' => 'OTH', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->snack = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Snack', 'code' => 'SNACK', 'is_active' => true]);

        $this->shared = $this->makeProduct('Ube Latte', 'UBE', [$this->a, $this->b, $this->c]);
        $this->sharedVariant = $this->makeVariant($this->shared, 'Regular', 'UBE-R', 20000, 22000, [$this->a, $this->b, $this->c]);
        $this->exclusiveVariant = $this->makeVariant($this->shared, 'Mini', 'UBE-M', 10000, 11000, [$this->a]);

        $this->own = $this->makeProduct('Mocha', 'MOCHA', [$this->a]);
        $this->ownVariant = $this->makeVariant($this->own, 'Regular', 'MOCHA-R', 18000, 19000, [$this->a]);

        $this->owner = $this->makeUser('owner', [$this->a, $this->b, $this->c]);
        $this->pusat = $this->makeUser('admin_pusat', [$this->a]);
        $this->limitedA = $this->makeUser('admin_outlet', [$this->a]);
        $this->limitedAB = $this->makeUser('admin_outlet', [$this->a, $this->b]);
    }

    // ================================================================================================
    // Variant: workspace editor
    // ================================================================================================

    public function test_admin_outlet_can_edit_a_variant_that_only_its_own_outlets_use(): void
    {
        $this->putVariant($this->limitedA, $this->own, $this->ownVariant, ['name' => 'Reguler', 'price_dine_in' => '19000', 'price_delivery' => '20000'])->assertOk();

        $variant = $this->ownVariant->fresh();
        $this->assertSame('Reguler', $variant->name);
        $this->assertSame(19000.0, (float) $variant->price_dine_in);
    }

    public function test_admin_outlet_can_edit_an_exclusive_variant_of_a_shared_product(): void
    {
        // Mini is only at Alpha even though its Product is also at Bravo and Charlie.
        $this->putVariant($this->limitedA, $this->shared, $this->exclusiveVariant, ['price_dine_in' => '12000', 'price_delivery' => '13000'])->assertOk();

        $this->assertSame(12000.0, (float) $this->exclusiveVariant->fresh()->price_dine_in);
    }

    public function test_admin_outlet_covering_every_outlet_of_the_variant_can_edit_it(): void
    {
        $duo = $this->makeProduct('Duo', 'DUO', [$this->a, $this->b]);
        $variant = $this->makeVariant($duo, 'Regular', 'DUO-R', 15000, 16000, [$this->a, $this->b]);

        $this->putVariant($this->limitedAB, $duo, $variant, ['price_dine_in' => '17000', 'price_delivery' => '18000'])->assertOk();

        $this->assertSame(17000.0, (float) $variant->fresh()->price_dine_in);
    }

    public function test_admin_outlet_cannot_change_the_price_of_a_shared_variant(): void
    {
        $before = $this->snapshot();

        $response = $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, ['price_dine_in' => '15000']);

        $response->assertStatus(422)->assertJsonValidationErrors(['price_dine_in']);
        $this->assertStringContainsString('Charlie', $response->json('errors.price_dine_in.0'));
        $this->assertStringContainsString('Owner/Admin Pusat', $response->json('errors.price_dine_in.0'));
        $this->assertSame($before, $this->snapshot(), 'the rejected write changed nothing');
        $this->assertSame(20000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
    }

    public function test_admin_outlet_cannot_change_delivery_price_name_code_or_status_of_a_shared_variant(): void
    {
        $before = $this->snapshot();

        foreach ([
            ['price_delivery' => '23000', 'field' => 'price_delivery'],
            ['name' => 'Renamed', 'field' => 'name'],
            ['code' => 'UBE-X', 'field' => 'code'],
            ['is_active' => 0, 'field' => 'is_active'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);

            $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, $case)->assertStatus(422)->assertJsonValidationErrors([$field]);
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_refused_variant_write_does_not_report_success_or_touch_outlets(): void
    {
        // Price changes AND the outlet list changes in one request: nothing at all is saved.
        $before = $this->snapshot();

        $response = $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, ['price_dine_in' => '15000', 'outlet_ids' => [$this->a->id]]);

        $response->assertStatus(422);
        $this->assertNotTrue($response->json('ok'));
        $this->assertSame($before, $this->snapshot());
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id, $this->c->id], $this->sharedVariant->outlets()->pluck('outlets.id')->all());
    }

    public function test_admin_outlet_can_still_change_assignment_of_a_shared_variant_inside_its_access(): void
    {
        $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, ['outlet_ids' => [$this->a->id]])->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->sharedVariant->outlets()->pluck('outlets.id')->all(), 'Bravo removed, Charlie (outside its access) kept');
        $this->assertSame(20000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
    }

    public function test_the_same_values_resubmitted_by_admin_outlet_are_not_a_permission_error(): void
    {
        $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, ['name' => ' Regular ', 'code' => 'ube-r', 'price_dine_in' => 'Rp. 20.000', 'price_delivery' => 'Rp. 22.000', 'outlet_ids' => [$this->a->id, $this->b->id]])->assertOk();

        $this->assertSame('UBE-R', $this->sharedVariant->fresh()->code);
    }

    public function test_owner_and_admin_pusat_can_edit_shared_variants(): void
    {
        $this->putVariant($this->owner, $this->shared, $this->sharedVariant, ['price_dine_in' => '21000', 'price_delivery' => '23000', 'name' => 'Regular+'])->assertOk();
        $this->assertSame(21000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
        $this->assertSame('Regular+', $this->sharedVariant->fresh()->name);

        $this->putVariant($this->pusat, $this->shared, $this->sharedVariant, ['price_dine_in' => '24000', 'price_delivery' => '25000', 'name' => 'Regular+'])->assertOk();
        $this->assertSame(24000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
    }

    public function test_admin_outlet_cannot_deactivate_a_shared_variant_but_can_deactivate_its_own(): void
    {
        $this->actingAs($this->limitedAB)->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->shared, $this->sharedVariant]))->assertStatus(422);
        $this->assertTrue((bool) $this->sharedVariant->fresh()->is_active);

        $this->actingAs($this->limitedAB)->delete(route('backoffice.variants.destroy', $this->sharedVariant))->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $this->sharedVariant->fresh()->is_active);

        $this->actingAs($this->limitedA)->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->own, $this->ownVariant]))->assertOk();
        $this->assertFalse((bool) $this->ownVariant->fresh()->is_active);

        $this->actingAs($this->owner)->patchJson(route('backoffice.products.workspace.variants.deactivate', [$this->shared, $this->sharedVariant]))->assertOk();
        $this->assertFalse((bool) $this->sharedVariant->fresh()->is_active);
    }

    // ================================================================================================
    // Variant: classic group editor
    // ================================================================================================

    public function test_classic_group_editor_with_unchanged_shared_fields_saves_fine(): void
    {
        $this->actingAs($this->limitedAB)->put(route('backoffice.variants.update', $this->sharedVariant), [
            'product_id' => $this->shared->id,
            'variants' => [
                $this->groupRow($this->sharedVariant, ['outlet_ids' => [$this->a->id]]),
                $this->groupRow($this->exclusiveVariant, ['price_dine_in' => 10500]),
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->sharedVariant->outlets()->pluck('outlets.id')->all());
        $this->assertSame(10500.0, (float) $this->exclusiveVariant->fresh()->price_dine_in, 'the exclusive row was edited');
        $this->assertSame(20000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
    }

    public function test_classic_group_editor_refuses_a_shared_price_change_and_saves_no_row_at_all(): void
    {
        $before = $this->snapshot();

        $this->actingAs($this->limitedAB)->from(route('backoffice.variants.edit', $this->sharedVariant))->put(route('backoffice.variants.update', $this->sharedVariant), [
            'product_id' => $this->shared->id,
            'variants' => [
                $this->groupRow($this->exclusiveVariant, ['price_dine_in' => 99000]),   // a row it may edit...
                $this->groupRow($this->sharedVariant, ['price_dine_in' => 15000, 'outlet_ids' => [$this->a->id, $this->b->id]]),   // ...and one it may not
            ],
        ])->assertSessionHasErrors(['variants.1.price_dine_in'])->assertRedirect(route('backoffice.variants.edit', $this->sharedVariant));

        $this->assertSame($before, $this->snapshot(), 'one refused row leaves the whole group untouched');
    }

    public function test_classic_group_editor_cannot_move_a_shared_variant_to_another_product(): void
    {
        $this->own->outlets()->sync([$this->a->id, $this->b->id]);
        $before = $this->snapshot();

        $this->actingAs($this->limitedAB)->put(route('backoffice.variants.update', $this->sharedVariant), [
            'product_id' => $this->own->id,
            'variants' => [
                $this->groupRow($this->sharedVariant, ['outlet_ids' => [$this->a->id]]),
                $this->groupRow($this->exclusiveVariant, ['outlet_ids' => [$this->a->id]]),
            ],
        ])->assertSessionHasErrors(['variants.0.product_id']);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($this->shared->id, $this->sharedVariant->fresh()->product_id);
    }

    public function test_classic_group_editor_allows_the_owner_to_change_shared_prices(): void
    {
        $this->actingAs($this->owner)->put(route('backoffice.variants.update', $this->sharedVariant), [
            'product_id' => $this->shared->id,
            'variants' => [
                $this->groupRow($this->sharedVariant, ['price_dine_in' => 21000]),
                $this->groupRow($this->exclusiveVariant),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(21000.0, (float) $this->sharedVariant->fresh()->price_dine_in);
    }

    public function test_a_forged_request_cannot_bypass_the_rule_with_extra_fields(): void
    {
        $before = $this->snapshot();

        // Forged legacy outlet_id / outlet_ids pretending the Variant is only at Alpha does not change what is stored.
        $this->actingAs($this->limitedAB)->put(route('backoffice.variants.update', $this->sharedVariant), [
            'product_id' => $this->shared->id,
            'variants' => [
                $this->groupRow($this->sharedVariant, ['price_dine_in' => 1, 'outlet_id' => $this->a->id, 'outlet_ids' => [$this->a->id]]),
                $this->groupRow($this->exclusiveVariant),
            ],
        ])->assertSessionHasErrors(['variants.0.price_dine_in']);

        $this->putVariant($this->limitedAB, $this->shared, $this->sharedVariant, ['price_dine_in' => '1', 'outlet_id' => $this->a->id])->assertStatus(422);

        // An omitted is_active is "active" for the writer; on an inactive shared Variant that would be a status change.
        $this->sharedVariant->update(['is_active' => false]);
        $before = $this->snapshot();
        $this->actingAs($this->limitedAB)->putJson(route('backoffice.products.workspace.variants.update', [$this->shared, $this->sharedVariant]), [
            'name' => 'Regular', 'price_dine_in' => '20000', 'price_delivery' => '22000', 'outlet_ids' => [$this->a->id],
        ])->assertStatus(422)->assertJsonValidationErrors(['is_active']);

        $this->assertSame($before, $this->snapshot());
    }

    // ================================================================================================
    // Product: general fields
    // ================================================================================================

    public function test_admin_outlet_can_edit_a_product_that_only_its_own_outlets_use(): void
    {
        $this->putGeneral($this->limitedA, $this->own, ['name' => 'Mocha Ice', 'description' => 'Cold', 'is_active' => 0])->assertOk();

        $own = $this->own->fresh();
        $this->assertSame('Mocha Ice', $own->name);
        $this->assertFalse((bool) $own->is_active);
    }

    public function test_admin_outlet_cannot_change_global_fields_of_a_shared_product(): void
    {
        $before = $this->snapshot();

        foreach ([
            ['name' => 'Renamed', 'field' => 'name'],
            ['code' => 'UBE-9', 'field' => 'code'],
            ['description' => 'New text', 'field' => 'description'],
            ['is_active' => 0, 'field' => 'is_active'],
            ['product_category_id' => $this->snack->id, 'field' => 'product_category_id'],
            ['brand_id' => $this->otherBrand->id, 'field' => 'brand_id'],
        ] as $case) {
            $field = $case['field'];
            unset($case['field']);

            $response = $this->putGeneral($this->limitedAB, $this->shared, $case);
            $response->assertStatus(422)->assertJsonValidationErrors([$field]);
            $this->assertNotTrue($response->json('ok'));
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_admin_outlet_can_resubmit_unchanged_general_fields_of_a_shared_product(): void
    {
        $this->putGeneral($this->limitedAB, $this->shared, [])->assertOk();
    }

    public function test_classic_product_update_refuses_global_changes_but_keeps_outlet_changes_possible(): void
    {
        $before = $this->snapshot();
        $payload = fn (array $override = []) => $override + [
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => 1,
            'outlet_ids' => [$this->a->id, $this->b->id],
        ];

        $this->actingAs($this->limitedAB)->put(route('backoffice.products.update', $this->shared), $payload(['name' => 'Hijacked', 'outlet_ids' => [$this->a->id]]))
            ->assertSessionHasErrors('name');
        $this->assertSame($before, $this->snapshot(), 'the outlet part of a refused request is not applied either');

        $this->actingAs($this->limitedAB)->put(route('backoffice.products.update', $this->shared), $payload(['outlet_ids' => [$this->a->id]]))->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->shared->outlets()->pluck('outlets.id')->all());
        $this->assertSame('Ube Latte', $this->shared->fresh()->name);
    }

    public function test_deactivating_a_shared_product_needs_owner_or_admin_pusat(): void
    {
        $this->actingAs($this->limitedAB)->delete(route('backoffice.products.destroy', $this->shared))->assertSessionHasErrors('is_active');
        $this->assertTrue((bool) $this->shared->fresh()->is_active);

        $this->actingAs($this->limitedA)->delete(route('backoffice.products.destroy', $this->own))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->own->fresh()->is_active);

        $this->actingAs($this->pusat)->delete(route('backoffice.products.destroy', $this->shared))->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->shared->fresh()->is_active);
    }

    public function test_owner_and_admin_pusat_can_edit_shared_products(): void
    {
        $this->putGeneral($this->owner, $this->shared, ['name' => 'Ube Latte Deluxe', 'description' => 'x'])->assertOk();
        $this->assertSame('Ube Latte Deluxe', $this->shared->fresh()->name);

        $this->putGeneral($this->pusat, $this->shared, ['name' => 'Ube Latte Royal', 'is_active' => 0])->assertOk();
        $this->assertSame('Ube Latte Royal', $this->shared->fresh()->name);
        $this->assertFalse((bool) $this->shared->fresh()->is_active);
    }

    public function test_the_workspace_is_still_authorized_by_outlet_scope(): void
    {
        $elsewhere = $this->makeProduct('Elsewhere', 'ELSE', [$this->c]);

        $this->putGeneral($this->limitedAB, $elsewhere, ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->limitedAB)->get(route('backoffice.products.edit', $this->shared))->assertOk();
        $this->actingAs($this->limitedAB)->get(route('backoffice.products.edit', $elsewhere))->assertForbidden();
    }

    public function test_assignment_operations_of_admin_outlet_inside_its_access_still_work_on_shared_products(): void
    {
        $this->actingAs($this->limitedAB)->putJson(route('backoffice.products.workspace.outlets', $this->shared), ['outlet_ids' => [$this->a->id]])->assertOk();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->c->id], $this->shared->outlets()->pluck('outlets.id')->all());
        $this->assertSame('Ube Latte', $this->shared->fresh()->name);
    }

    // ================================================================================================
    // Imports stay assignment-only
    // ================================================================================================

    public function test_imports_by_admin_outlet_stay_assignment_only_and_keep_existing_assignments(): void
    {
        $before = $this->snapshot();
        $productCsv = "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Renamed,UBE,Hijack,0\n";
        $variantCsv = "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nUBE,OA,Regular,UBE-R,1,1,0\n";

        $this->actingAs($this->limitedAB)->post(route('backoffice.products.import.store'), ['outlet_id' => $this->a->id, 'file' => UploadedFile::fake()->createWithContent('p.csv', $productCsv)])->assertSessionHasNoErrors();
        $this->actingAs($this->limitedAB)->post(route('backoffice.variants.import.store'), ['file' => UploadedFile::fake()->createWithContent('v.csv', $variantCsv)])->assertSessionHasNoErrors();

        $this->assertSame($before, $this->snapshot());
        $this->assertStringContainsString('harga global', collect(session('import_errors'))->implode(' '));
    }

    // ================================================================================================
    // Rename into a look-alike
    // ================================================================================================

    public function test_renaming_a_product_into_a_look_alike_asks_for_confirmation_and_saves_nothing(): void
    {
        $before = $this->snapshot();

        $response = $this->putGeneral($this->owner, $this->own, ['name' => '  ube  LATTE! ']);

        $response->assertStatus(409)->assertJsonPath('needs_confirmation', 'similar_product')->assertJsonPath('ok', false);
        $this->assertStringContainsString('kode UBE', $response->json('message'));
        $this->assertStringContainsString('Alpha, Bravo, Charlie', $response->json('message'));
        $this->assertSame($this->shared->id, $response->json('similar_products.0.id'));
        $this->assertSame('Mocha', $this->own->fresh()->name);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_confirmed_rename_is_saved_and_nothing_is_merged_or_deleted(): void
    {
        $this->putGeneral($this->owner, $this->own, ['name' => 'Ube Latte', 'confirm_similar' => 1])->assertOk();

        $this->assertSame('Ube Latte', $this->own->fresh()->name);
        $this->assertSame(2, Product::where('name', 'Ube Latte')->count());
        $this->assertSame(2, Product::count());
        $this->assertSame(3, ProductVariant::count());
        $this->assertSame($this->shared->id, Product::where('code', 'UBE')->value('id'));
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id, $this->c->id], $this->shared->outlets()->pluck('outlets.id')->all());
    }

    public function test_the_product_is_never_a_look_alike_of_itself_and_untouched_twins_do_not_warn(): void
    {
        // Same normalized name (case / punctuation only): the Product itself is excluded.
        $this->putGeneral($this->owner, $this->shared, ['name' => 'UBE latte'])->assertOk();

        // A pre-existing twin does not warn when the name / brand / category are not being changed.
        $twin = $this->makeProduct('UBE latte', 'UBE-TWIN', [$this->a]);
        $this->putGeneral($this->owner, $twin, ['description' => 'Only the description changes'])->assertOk();
        $this->assertSame('Only the description changes', $twin->fresh()->description);
    }

    public function test_the_same_name_in_another_brand_or_category_does_not_warn(): void
    {
        $this->putGeneral($this->owner, $this->own, ['name' => 'Ube Latte', 'brand_id' => $this->otherBrand->id, 'product_category_id' => ProductCategory::create(['brand_id' => $this->otherBrand->id, 'name' => 'Cold', 'code' => 'COLD', 'is_active' => true])->id])->assertOk();
        $this->assertSame('Ube Latte', $this->own->fresh()->name);
    }

    public function test_changing_category_into_a_look_alike_combination_also_asks(): void
    {
        $twin = $this->makeProduct('Ube Latte', 'UBE-S', [$this->a], $this->snack);

        $this->putGeneral($this->owner, $twin, ['product_category_id' => $this->category->id])->assertStatus(409);
        $this->assertSame($this->snack->id, $twin->fresh()->product_category_id);
    }

    public function test_the_permission_check_comes_before_the_duplicate_warning(): void
    {
        $this->putGeneral($this->limitedAB, $this->shared, ['name' => 'Mocha', 'brand_id' => $this->brand->id])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    public function test_classic_update_warns_on_a_look_alike_rename_and_keeps_the_form_values(): void
    {
        $payload = [
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'MOCHA', 'is_active' => 1,
            'outlet_ids' => [$this->a->id],
        ];

        $this->actingAs($this->owner)->put(route('backoffice.products.update', $this->own), $payload)
            ->assertSessionHas('similar_products')->assertSessionHasInput('name', 'Ube Latte');
        $this->assertSame('Mocha', $this->own->fresh()->name);

        $this->actingAs($this->owner)->put(route('backoffice.products.update', $this->own), $payload + ['confirm_similar' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Ube Latte', $this->own->fresh()->name);
    }

    public function test_the_workspace_general_form_shows_the_confirmation_box_without_javascript(): void
    {
        $page = $this->followingRedirects()->actingAs($this->owner)->from(route('backoffice.products.edit', [$this->own, 'section' => 'general']))
            ->put(route('backoffice.products.workspace.general', $this->own), [
                'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'MOCHA', 'is_active' => 1,
            ])->getContent();

        $this->assertStringContainsString('data-pw-similar-product="'.$this->shared->id.'"', $page);
        $this->assertStringContainsString('name="confirm_similar"', $page);
        $this->assertSame('Mocha', $this->own->fresh()->name);
    }

    public function test_validation_failures_still_work_on_general_save(): void
    {
        $this->putGeneral($this->owner, $this->own, ['code' => 'UBE'])->assertStatus(422)->assertJsonValidationErrors(['code']);
        $this->putGeneral($this->owner, $this->own, ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name']);
    }

    // ================================================================================================
    // helpers
    // ================================================================================================

    /** Every row and pivot that a refused write must leave exactly as it was. */
    private function snapshot(): array
    {
        return [
            'products' => DB::table('products')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'variants' => DB::table('product_variants')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'product_outlet' => DB::table('product_outlet')->orderBy('product_id')->orderBy('outlet_id')->get(['product_id', 'outlet_id'])->map(fn ($row) => (array) $row)->all(),
            'variant_outlet' => DB::table('product_variant_outlet')->orderBy('product_variant_id')->orderBy('outlet_id')->get(['product_variant_id', 'outlet_id'])->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function putVariant(User $user, Product $product, ProductVariant $variant, array $override = [])
    {
        return $this->actingAs($user)->putJson(route('backoffice.products.workspace.variants.update', [$product, $variant]), $override + [
            'name' => $variant->name,
            'price_dine_in' => (string) (int) $variant->price_dine_in,
            'price_delivery' => (string) (int) $variant->price_delivery,
            'outlet_ids' => $variant->outlets()->pluck('outlets.id')->all(),
            'is_active' => $variant->is_active ? 1 : 0,
        ]);
    }

    private function putGeneral(User $user, Product $product, array $override = [])
    {
        return $this->actingAs($user)->putJson(route('backoffice.products.workspace.general', $product), $override + [
            'brand_id' => $product->brand_id, 'product_category_id' => $product->product_category_id, 'name' => $product->name,
            'code' => $product->code, 'description' => $product->description, 'is_active' => $product->is_active ? 1 : 0,
        ]);
    }

    private function groupRow(ProductVariant $variant, array $override = []): array
    {
        return $override + [
            'id' => $variant->id, 'name' => $variant->name, 'code' => $variant->code,
            'price_dine_in' => (int) $variant->price_dine_in, 'price_delivery' => (int) $variant->price_delivery,
            'outlet_ids' => $variant->outlets()->pluck('outlets.id')->all(), 'is_active' => $variant->is_active ? 1 : 0,
        ];
    }

    private function makeProduct(string $name, string $code, array $outlets, ?ProductCategory $category = null): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => ($category ?? $this->category)->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $product;
    }

    private function makeVariant(Product $product, string $name, string $code, float $dineIn, float $delivery, array $outlets): ProductVariant
    {
        $variant = ProductVariant::create(['product_id' => $product->id, 'name' => $name, 'code' => $code, 'price' => $dineIn, 'price_dine_in' => $dineIn, 'price_delivery' => $delivery, 'is_active' => true]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
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
