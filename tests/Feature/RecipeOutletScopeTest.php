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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Recipe outlet scope. The Back Office has no selectable Active Outlet any more: every page works on
 * "all outlets the user may access", and a Recipe (global per Variant) may only be changed by a user who
 * can access EVERY outlet its Variant is used at. Editable Recipes are edited in the Product Workspace;
 * the classic page only serves what the workspace does not edit (view-only Recipes, ambiguous Variants).
 */
class RecipeOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $bazaar;

    private Outlet $anggrek;

    private Brand $brand;

    private ProductCategory $menuCategory;

    private IngredientCategory $ingredientCategory;

    /** Available at BXC + Bazaar. */
    private ProductVariant $sharedVariant;

    private Recipe $sharedRecipe;

    /** Available at Bazaar only. */
    private ProductVariant $bazaarVariant;

    private Recipe $bazaarRecipe;

    /** Available at BXC + Bazaar + Taman Anggrek. */
    private ProductVariant $tripleVariant;

    private Recipe $tripleRecipe;

    /** Available at BXC only. */
    private ProductVariant $bxcVariant;

    private Recipe $bxcRecipe;

    /** No Recipe yet: single outlet (BXC) and multi outlet (BXC + Bazaar + Taman Anggrek). */
    private ProductVariant $bxcSpareVariant;

    private ProductVariant $tripleSpareVariant;

    private Ingredient $milk;

    /** Available everywhere, not in any Recipe: safe to add to any Recipe. */
    private Ingredient $spare;

    private User $adminPusat;

    private User $bxcAdmin;

    private User $bxcBazaarAdmin;

    /** No Product pages: the only role the classic Recipe page still serves. */
    private User $warehouse;

    private User $bxcWarehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC', 'code' => 'BXC', 'is_active' => true]);
        $this->bazaar = Outlet::create(['name' => 'Bazaar TikTok', 'code' => 'BZR', 'is_active' => true]);
        $this->anggrek = Outlet::create(['name' => 'Taman Anggrek', 'code' => 'TA', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->menuCategory = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->ingredientCategory = IngredientCategory::create(['name' => 'Powder', 'code' => 'POWDER', 'is_active' => true]);

        $this->sharedVariant = $this->makeVariant('Matcha', 'MATCHA', [$this->bxc, $this->bazaar]);
        $this->bazaarVariant = $this->makeVariant('Bazaar Only', 'BZR-ONLY', [$this->bazaar]);

        $this->tripleVariant = $this->makeVariant('Triple', 'TRIPLE', [$this->bxc, $this->bazaar, $this->anggrek]);
        $this->bxcVariant = $this->makeVariant('Bxc Only', 'BXC-ONLY', [$this->bxc]);
        $this->bxcSpareVariant = $this->makeVariant('Bxc Spare', 'BXC-SPARE', [$this->bxc]);
        $this->tripleSpareVariant = $this->makeVariant('Triple Spare', 'TRIPLE-SPARE', [$this->bxc, $this->bazaar, $this->anggrek]);

        $this->milk = $this->makeIngredient('Milk', [$this->bxc, $this->bazaar, $this->anggrek]);
        $this->spare = $this->makeIngredient('Spare Sugar', [$this->bxc, $this->bazaar, $this->anggrek]);

        $this->sharedRecipe = $this->makeRecipe($this->sharedVariant, $this->milk);
        $this->bazaarRecipe = $this->makeRecipe($this->bazaarVariant, $this->makeIngredient('Bazaar Syrup', [$this->bazaar]));
        $this->tripleRecipe = $this->makeRecipe($this->tripleVariant, $this->milk);
        $this->bxcRecipe = $this->makeRecipe($this->bxcVariant, $this->milk);

        // Admin pusat whose HOME outlet (users.outlet_id) is Bazaar TikTok but who can access every outlet.
        $this->adminPusat = $this->makeUser('admin-pusat', 'Admin Pusat', 'admin_pusat', $this->bazaar, [$this->bxc, $this->bazaar]);
        $this->bxcAdmin = $this->makeUser('admin-bxc', 'Admin Outlet', 'admin_outlet', $this->bxc, [$this->bxc]);
        $this->bxcBazaarAdmin = $this->makeUser('admin-bxc-bzr', 'Admin Outlet', 'admin_outlet', $this->bxc, [$this->bxc, $this->bazaar]);
        $this->warehouse = $this->makeUser('gudang', 'Staff Gudang', 'staff_gudang', $this->bxc, [$this->bxc, $this->bazaar, $this->anggrek]);
        $this->bxcWarehouse = $this->makeUser('gudang-bxc', 'Staff Gudang', 'staff_gudang', $this->bxc, [$this->bxc]);
    }

    // ---- A / B: the outlet label is the Back Office scope, never users.outlet_id --------------------

    public function test_admin_pusat_with_bazaar_home_outlet_sees_semua_outlet_not_home_outlet_even_with_a_leftover_selection(): void
    {
        // The leftover selection is what the removed selector used to store; it must not matter any more.
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        foreach ([
            route('backoffice.recipes.create'),
            route('backoffice.recipes.import'),
            route('backoffice.ingredients.import'),
        ] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('<strong>Outlet:</strong> Semua Outlet', false)
                ->assertDontSee('<strong>Outlet:</strong> Bazaar TikTok', false)
                ->assertDontSee('<strong>Outlet:</strong> BXC', false);
        }

        $this->assertSame('Semua Outlet', $this->sidebarFooterOutlet($this->get(route('backoffice.menu-categories.index'))->getContent()));
    }

    public function test_limited_user_sees_the_allowed_outlets_label(): void
    {
        $this->actingAs($this->bxcAdmin)->get(route('backoffice.recipes.create'))->assertOk()
            ->assertSee('<strong>Outlet:</strong> Semua Outlet yang Diizinkan', false);

        // The classic page (warehouse staff only) shows the same label.
        $this->actingAs($this->bxcWarehouse)->get(route('backoffice.recipes.edit', $this->tripleRecipe))->assertOk()
            ->assertSee('<strong>Outlet:</strong> Semua Outlet yang Diizinkan', false);

        $this->assertSame('Semua Outlet yang Diizinkan', $this->sidebarFooterOutlet($this->actingAs($this->bxcAdmin)->get(route('backoffice.menu-categories.index'))->getContent()));
    }

    public function test_the_backoffice_header_has_no_outlet_selector(): void
    {
        foreach ([$this->adminPusat, $this->bxcAdmin] as $user) {
            $this->actingAs($user)->get(route('backoffice.recipes.index'))->assertOk()
                ->assertDontSee('id="active-backoffice-outlet"', false)
                ->assertDontSee('backoffice-context-bar', false)
                ->assertDontSee('Semua Outlet yang Diizinkan</option>', false);
        }
    }

    // ---- C / D: no way around the Active Outlet / outlet access, by URL or otherwise -------------

    public function test_limited_user_cannot_open_or_change_recipe_of_a_foreign_outlet(): void
    {
        $this->actingAs($this->bxcAdmin);
        $recipe = $this->bazaarRecipe;
        $item = $recipe->items()->first();

        $this->get(route('backoffice.recipes.edit', $recipe))->assertForbidden();
        $this->put(route('backoffice.recipes.update', $recipe), [
            'product_variant_id' => $this->sharedVariant->id,
            'name' => 'Hijacked',
            'is_active' => 0,
        ])->assertForbidden();
        $this->delete(route('backoffice.recipes.destroy', $recipe))->assertForbidden();
        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->milk->id, 'qty' => 5])->assertForbidden();
        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 999])->assertForbidden();
        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $item]))->assertForbidden();

        $this->assertRecipeUntouched($recipe->fresh(), $this->bazaarVariant, $item);
    }

    public function test_limited_user_index_and_export_only_contain_accessible_outlets(): void
    {
        $this->actingAs($this->bxcAdmin);

        $this->get(route('backoffice.recipes.index'))->assertOk()
            ->assertSee('Matcha')->assertDontSee('Bazaar Only');

        $csv = $this->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $csv);
        $this->assertStringNotContainsString('BZR-ONLY', $csv);
    }

    public function test_admin_pusat_reaches_the_recipe_of_any_outlet_and_a_leftover_selection_does_not_narrow_it(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        // A Recipe of any outlet opens in the standalone editor (no hand-over to the Product Workspace); nothing is
        // hidden by the old selection.
        $edit = $this->get(route('backoffice.recipes.edit', $this->bazaarRecipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->assertTrue($edit->viewData('canMutate'));
        $this->get(route('backoffice.recipes.index'))->assertOk()->assertSee('Matcha')->assertSee('Bazaar Only');
    }

    public function test_recipe_can_only_be_created_for_a_variant_inside_the_outlet_scope(): void
    {
        $payload = ['name' => 'Foreign', 'is_active' => 1];
        $this->bazaarRecipe->delete();

        $this->actingAs($this->bxcAdmin)
            ->post(route('backoffice.recipes.store'), $payload + ['product_variant_id' => $this->bazaarVariant->id])
            ->assertSessionHasErrors('product_variant_id');

        $this->assertDatabaseMissing('recipes', ['product_variant_id' => $this->bazaarVariant->id]);
    }

    // ---- E: saving must never silently re-point a Recipe to another Variant -----------------------

    public function test_a_recipe_cannot_be_silently_repointed_to_a_variant_outside_the_users_scope(): void
    {
        $this->actingAs($this->bxcAdmin);

        // Saving with the unchanged Variant keeps it.
        $this->put(route('backoffice.recipes.update', $this->bxcRecipe), [
            'product_variant_id' => $this->bxcVariant->id,
            'name' => 'Renamed',
            'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->bxcVariant->id, $this->bxcRecipe->fresh()->product_variant_id);

        // A forged Variant outside this user's outlets is rejected and nothing moves.
        foreach ([$this->bazaarVariant, $this->tripleSpareVariant] as $forged) {
            $this->put(route('backoffice.recipes.update', $this->bxcRecipe), [
                'product_variant_id' => $forged->id,
                'name' => 'Renamed',
                'is_active' => 1,
            ])->assertSessionHasErrors('product_variant_id');
            $this->assertSame($this->bxcVariant->id, $this->bxcRecipe->fresh()->product_variant_id);
        }
    }

    // ---- F: ingredient dropdown and validation use the same rule ---------------------------------

    public function test_ingredient_options_match_what_the_server_accepts(): void
    {
        $bxcOnly = $this->makeIngredient('BXC Only Powder', [$this->bxc]);
        $inactive = $this->makeIngredient('Retired Syrup', [$this->bxc, $this->bazaar], false);
        $both = $this->makeIngredient('Shared Cream', [$this->bxc, $this->bazaar]);

        // The classic page is only served to warehouse staff; its dropdown and the server rule are unchanged.
        $this->actingAs($this->warehouse);

        // Recipe Variant sells at BXC + Bazaar, so an ingredient that only exists at BXC is not offered;
        // neither is an inactive one or one already in the Recipe.
        $response = $this->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk();
        $offered = $response->viewData('ingredients')->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$both->id, $this->spare->id], $offered);
        $response->assertSee('Shared Cream')->assertDontSee('BXC Only Powder')->assertDontSee('Retired Syrup');
        $response->assertSee('Variant ini tersedia di: BXC, Bazaar TikTok');

        // Everything not offered is rejected by the server ...
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $bxcOnly->id, 'qty' => 1])
            ->assertSessionHasErrors('ingredient_id');
        $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $inactive->id, 'qty' => 1])
            ->assertSessionHasErrors('ingredient_id');
        $this->assertSame(1, $this->sharedRecipe->items()->count());

        // ... and everything offered is accepted, without touching ingredient outlet availability.
        foreach ([$both, $this->spare] as $offeredIngredient) {
            $this->post(route('backoffice.recipes.items.store', $this->sharedRecipe), ['ingredient_id' => $offeredIngredient->id, 'qty' => 2])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(3, $this->sharedRecipe->items()->count());
        $this->assertSame([$this->bxc->id], $bxcOnly->outlets()->pluck('outlets.id')->all());
    }

    // ---- Export / import follow the same scope ----------------------------------------------------

    public function test_export_covers_every_accessible_outlet_and_ignores_a_leftover_selection(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);
        $all = $this->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $all);
        $this->assertStringContainsString('BZR-ONLY', $all);

        $limited = $this->actingAs($this->bxcAdmin)->get(route('backoffice.recipes.export.csv'))->streamedContent();
        $this->assertStringContainsString('MATCHA', $limited);
        $this->assertStringNotContainsString('BZR-ONLY', $limited);
    }

    // ---- MUTATION POLICY (Recipe is global per Variant) -------------------------------------------

    // A. Variant BXC+Bazaar+TA, limited user only has BXC, "Semua Outlet yang Diizinkan".
    public function test_limited_user_can_view_but_not_mutate_recipe_used_by_outlets_outside_access(): void
    {
        $this->actingAs($this->bxcAdmin);

        $this->get(route('backoffice.recipes.index'))->assertOk()
            ->assertSee('Triple')->assertSee('Lihat (read-only)');

        // The standalone editor opens it read-only: the reason is shown and there is no mutation control
        $classic = $this->get(route('backoffice.recipes.edit', $this->tripleRecipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->assertFalse($classic->viewData('canMutate'));
        $classic->assertSee('id="recipe-readonly-notice"', false)
            ->assertDontSee('Update Header')->assertDontSee('Tambah Recipe Item');

        // The old Product Workspace deep link still renders read-only too (bookmarks keep working)
        $workspace = $this->get(route('backoffice.products.edit', [$this->tripleVariant->product_id, 'section' => 'recipe', 'recipe' => $this->tripleRecipe->id]))->assertOk();
        $workspace->assertSee('Recipe ini digunakan di outlet lain di luar akses Anda. Anda dapat melihat Recipe ini, tetapi tidak dapat mengubahnya.')
            ->assertSee('data-pw-recipe-readonly', false)
            ->assertDontSee('data-pw-open-recipe-url=', false)
            ->assertDontSee('data-pw-open-drawer="recipe"', false);

        // Warehouse staff (no Product pages) keep the classic page, also read-only for this Recipe
        $edit = $this->actingAs($this->bxcWarehouse)->get(route('backoffice.recipes.edit', $this->tripleRecipe))->assertOk();
        $edit->assertSee('id="recipe-readonly-notice"', false)
            ->assertSee('Recipe ini digunakan di outlet lain di luar akses Anda. Anda dapat melihat Recipe ini, tetapi tidak dapat mengubahnya.')
            ->assertDontSee('Update Header')->assertDontSee('Tambah Recipe Item')->assertDontSee('Yakin mau hapus recipe item');
        $this->assertFalse($edit->viewData('canMutate'));

        $this->actingAs($this->bxcAdmin);

        $this->assertMutationDenied($this->tripleRecipe, $this->bxcSpareVariant);
    }

    // B. Variant BXC+Bazaar, limited user has BXC+Bazaar: every outlet of the Variant is theirs.
    public function test_limited_user_owning_every_outlet_of_the_variant_can_mutate_it(): void
    {
        $this->actingAs($this->bxcBazaarAdmin);

        $edit = $this->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
        $this->assertTrue($edit->viewData('canMutate'));

        $this->assertMutationAllowed($this->sharedRecipe);
    }

    // C. The same user with a selection left over from the removed selector: nothing narrows.
    public function test_a_leftover_single_outlet_selection_does_not_make_a_shared_recipe_read_only(): void
    {
        $this->actingAs($this->bxcBazaarAdmin)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        $this->get(route('backoffice.recipes.index'))->assertOk()->assertSee('Matcha');
        $this->assertTrue($this->get(route('backoffice.recipes.edit', $this->sharedRecipe))->assertOk()->viewData('canMutate'));

        $this->assertMutationAllowed($this->sharedRecipe);
    }

    // D. Variant only in BXC: editable by anyone who can access BXC.
    public function test_single_outlet_recipe_is_editable_by_whoever_can_access_that_outlet(): void
    {
        foreach ([$this->bxcAdmin, $this->adminPusat] as $user) {
            $edit = $this->actingAs($user)->get(route('backoffice.recipes.edit', $this->bxcRecipe))->assertOk()->assertViewIs('backoffice.recipes.edit');
            $this->assertTrue($edit->viewData('canMutate'), $user->username);
        }

        $this->assertMutationAllowed($this->bxcRecipe);
    }

    // F. Owner/admin pusat, Variant BXC+Bazaar+TA: they can access all three.
    public function test_admin_pusat_can_mutate_a_multi_outlet_recipe(): void
    {
        $this->actingAs($this->adminPusat)->withSession(['active_backoffice_outlet_id' => $this->bxc->id]);

        $this->assertTrue($this->get(route('backoffice.recipes.edit', $this->tripleRecipe))->assertOk()->viewData('canMutate'));

        $this->assertMutationAllowed($this->tripleRecipe);
    }

    // G. Reassign X -> Y: source AND target Variant must both pass the mutation rule.
    public function test_reassign_authorizes_source_and_target_variant(): void
    {
        // Limited to BXC: source (BXC only) is fine, a target with outlets outside access is not.
        $this->actingAs($this->bxcAdmin);
        $this->put(route('backoffice.recipes.update', $this->bxcRecipe), [
            'product_variant_id' => $this->tripleSpareVariant->id, 'name' => 'x', 'is_active' => 1,
        ])->assertSessionHasErrors(['product_variant_id' => 'Recipe ini digunakan di outlet lain di luar akses Anda. Anda dapat melihat Recipe ini, tetapi tidak dapat mengubahnya.']);
        $this->assertSame($this->bxcVariant->id, $this->bxcRecipe->fresh()->product_variant_id);

        // ... while a source that is multi-outlet beyond their access cannot be moved at all.
        $this->put(route('backoffice.recipes.update', $this->tripleRecipe), [
            'product_variant_id' => $this->bxcSpareVariant->id, 'name' => 'x', 'is_active' => 1,
        ])->assertForbidden();
        $this->assertSame($this->tripleVariant->id, $this->tripleRecipe->fresh()->product_variant_id);

        // Both ends inside the user's outlets: allowed, and the old Variant simply has no Recipe now.
        $this->put(route('backoffice.recipes.update', $this->bxcRecipe), [
            'product_variant_id' => $this->bxcSpareVariant->id, 'name' => 'moved', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($this->bxcSpareVariant->id, $this->bxcRecipe->fresh()->product_variant_id);
        $this->assertSame(1, Recipe::count() - Recipe::whereIn('id', [$this->sharedRecipe->id, $this->bazaarRecipe->id, $this->tripleRecipe->id])->count());
    }

    public function test_create_only_offers_and_accepts_variants_the_user_may_mutate(): void
    {
        $payload = ['name' => 'Fresh', 'is_active' => 1];

        // Full access: the multi-outlet Variant is offered and accepted.
        $this->actingAs($this->adminPusat);
        $offered = $this->get(route('backoffice.recipes.create'))->assertOk()->viewData('variants')->pluck('id')->all();
        $this->assertContains($this->bxcSpareVariant->id, $offered);
        $this->assertContains($this->tripleSpareVariant->id, $offered);
        $this->post(route('backoffice.recipes.store'), $payload + ['product_variant_id' => $this->tripleSpareVariant->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recipes', ['product_variant_id' => $this->tripleSpareVariant->id]);

        // Limited to BXC only: a Variant that also lives outside BXC is neither offered nor accepted.
        $this->actingAs($this->bxcAdmin);
        $offered = $this->get(route('backoffice.recipes.create'))->viewData('variants')->pluck('id')->all();
        $this->assertContains($this->bxcSpareVariant->id, $offered);
        $this->assertNotContains($this->tripleVariant->id, $offered);
        $this->tripleRecipe->delete();
        $this->post(route('backoffice.recipes.store'), $payload + ['product_variant_id' => $this->tripleVariant->id])
            ->assertSessionHasErrors('product_variant_id');
        $this->assertDatabaseMissing('recipes', ['product_variant_id' => $this->tripleVariant->id]);
    }

    // H. Import: every row goes through the mutation rule; rejected rows are skipped and reported.
    public function test_import_skips_variants_the_user_may_not_mutate_and_still_processes_valid_rows(): void
    {
        $csv = "variant_code,ingredient_name,qty,is_active\n"
            ."BXC-ONLY,Milk,7,1\n"      // BXC only            -> allowed for a BXC-only user
            ."MATCHA,Milk,8,1\n"        // BXC + Bazaar        -> Bazaar is outside access, skipped
            ."TRIPLE,Milk,9,1\n"        // BXC + Bazaar + TA   -> skipped
            ."BZR-ONLY,Milk,10,1\n";    // Bazaar only         -> outside access
        $before = [
            'shared' => (float) $this->sharedRecipe->items()->value('qty'),
            'triple' => (float) $this->tripleRecipe->items()->value('qty'),
            'bazaar' => (float) $this->bazaarRecipe->items()->value('qty'),
        ];

        $errors = [];
        $this->actingAs($this->bxcAdmin)
            ->post(route('backoffice.recipes.import.store'), ['file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv)])
            ->assertRedirect(route('backoffice.recipes.index'))
            ->assertSessionHas('import_errors', function ($e) use (&$errors) {
                $errors = $e;

                return true;
            });

        $this->assertCount(3, $errors);
        $this->assertStringContainsString("'MATCHA' dilewati. Recipe ini digunakan di outlet lain di luar akses Anda", $errors[0]);
        $this->assertStringContainsString("'TRIPLE' dilewati", $errors[1]);
        $this->assertStringContainsString("'BZR-ONLY' dilewati. Variant tidak tersedia pada outlet yang dapat kamu akses", $errors[2]);
        $this->assertSame(7.0, (float) $this->bxcRecipe->items()->where('ingredient_id', $this->milk->id)->value('qty'));
        $this->assertSame($before['shared'], (float) $this->sharedRecipe->items()->value('qty'));
        $this->assertSame($before['triple'], (float) $this->tripleRecipe->items()->value('qty'));
        $this->assertSame($before['bazaar'], (float) $this->bazaarRecipe->items()->value('qty'));
    }

    public function test_import_scope_follows_the_outlet_access_of_each_kind_of_user(): void
    {
        $csv = "variant_code,ingredient_name,qty,is_active\nBXC-ONLY,Milk,1,1\nMATCHA,Milk,2,1\nTRIPLE,Milk,3,1\nBZR-ONLY,Milk,4,1\n";
        $touched = function (?User $user, array $session = []) use ($csv): array {
            RecipeItem::query()->update(['qty' => 50]);
            $this->actingAs($user)->withSession($session)
                ->post(route('backoffice.recipes.import.store'), ['file' => UploadedFile::fake()->createWithContent('recipes.csv', $csv)]);

            return RecipeItem::with('recipe.variant')->where('ingredient_id', $this->milk->id)->where('qty', '!=', 50)->get()
                ->map(fn ($item) => $item->recipe->variant->code)->sort()->values()->all();
        };

        // Full access: everything, including the Bazaar-only Variant; a leftover selection changes nothing.
        $this->assertSame(['BXC-ONLY', 'BZR-ONLY', 'MATCHA', 'TRIPLE'], $touched($this->adminPusat));
        $this->assertSame(['BXC-ONLY', 'BZR-ONLY', 'MATCHA', 'TRIPLE'], $touched($this->adminPusat, ['active_backoffice_outlet_id' => $this->bxc->id]));
        // Limited to BXC: only the Variant whose every outlet the user owns.
        $this->assertSame(['BXC-ONLY'], $touched($this->bxcAdmin));
        // Limited to BXC+Bazaar: every Variant living inside those two outlets, never the
        // one that also uses Taman Anggrek.
        $this->assertSame(['BXC-ONLY', 'BZR-ONLY', 'MATCHA'], $touched($this->bxcBazaarAdmin));
    }

    // ---- helpers ---------------------------------------------------------------------------------

    /** Every mutation of a Recipe is refused (403 or a rejected reassignment) and nothing changes. */
    private function assertMutationDenied(Recipe $recipe, ProductVariant $reassignTarget): void
    {
        $item = $recipe->items()->first();
        $variantId = $recipe->product_variant_id;
        $name = $recipe->name;
        $items = $recipe->items()->count();
        $qty = (float) $item->qty;

        $this->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $variantId, 'name' => 'Hijacked', 'is_active' => 0])->assertForbidden();
        $this->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $reassignTarget->id, 'name' => $name, 'is_active' => 1])->assertForbidden();
        $this->delete(route('backoffice.recipes.destroy', $recipe))->assertForbidden();
        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->spare->id, 'qty' => 5])->assertForbidden();
        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 999])->assertForbidden();
        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $item]))->assertForbidden();

        $fresh = $recipe->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertSame($variantId, $fresh->product_variant_id);
        $this->assertSame($name, $fresh->name);
        $this->assertSame($items, $fresh->items()->count());
        $this->assertSame($qty, (float) $item->fresh()->qty);
    }

    /** Every mutation of a Recipe goes through. */
    private function assertMutationAllowed(Recipe $recipe): void
    {
        $item = $recipe->items()->first();

        $this->put(route('backoffice.recipes.update', $recipe), ['product_variant_id' => $recipe->product_variant_id, 'name' => 'Edited', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Edited', $recipe->fresh()->name);

        $this->post(route('backoffice.recipes.items.store', $recipe), ['ingredient_id' => $this->spare->id, 'qty' => 5])->assertSessionHasNoErrors();
        $this->assertSame(2, $recipe->items()->count());

        $this->put(route('backoffice.recipes.items.update', [$recipe, $item]), ['qty' => 42])->assertSessionHasNoErrors();
        $this->assertSame(42.0, (float) $item->fresh()->qty);

        $this->delete(route('backoffice.recipes.items.destroy', [$recipe, $recipe->items()->where('ingredient_id', $this->spare->id)->first()]))->assertSessionHasNoErrors();
        $this->assertSame(1, $recipe->items()->count());

        $this->delete(route('backoffice.recipes.destroy', $recipe))->assertSessionHasNoErrors();
        $this->assertFalse($recipe->fresh()->is_active);
    }

    private function assertRecipeUntouched(Recipe $recipe, ProductVariant $variant, RecipeItem $item): void
    {
        $this->assertTrue($recipe->is_active);
        $this->assertSame($variant->id, $recipe->product_variant_id);
        $this->assertSame('Recipe - '.$variant->name, $recipe->name);
        $this->assertSame(1, $recipe->items()->count());
        $this->assertSame((float) $item->qty, (float) $item->fresh()->qty);
    }

    private function sidebarFooterOutlet(string $html): string
    {
        $this->assertSame(1, preg_match('/<div class="sidebar-footer">(.*?)<\/div>/s', $html, $m));
        $lines = preg_split('/<br\s*\/?>/i', $m[1]);

        return trim($lines[1] ?? '');
    }

    private function makeVariant(string $name, string $code, array $outlets): ProductVariant
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'product_category_id' => $this->menuCategory->id,
            'name' => $name.' Product',
            'code' => $code.'-P',
            'is_active' => true,
        ]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'name' => $name,
            'code' => $code,
            'price' => 20000,
            'price_dine_in' => 20000,
            'price_delivery' => 22000,
            'is_active' => true,
        ]);
        $variant->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $variant;
    }

    private function makeIngredient(string $name, array $outlets, bool $active = true): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $this->ingredientCategory->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)),
            'unit' => 'gram',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => $active,
        ]);
        $ingredient->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $ingredient;
    }

    private function makeRecipe(ProductVariant $variant, Ingredient $ingredient): Recipe
    {
        $recipe = Recipe::create([
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'name' => 'Recipe - '.$variant->name,
            'is_active' => true,
        ]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $ingredient->id, 'qty' => 10, 'unit' => 'gram']);

        return $recipe;
    }

    private function makeUser(string $username, string $roleName, string $roleCode, Outlet $homeOutlet, array $outlets): User
    {
        $user = User::create([
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'password',
            'role_id' => Role::firstOrCreate(['code' => $roleCode], ['name' => $roleName])->id,
            'outlet_id' => $homeOutlet->id,
            'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
