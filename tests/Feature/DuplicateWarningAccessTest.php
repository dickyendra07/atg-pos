<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\ProductDuplicateGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Feedback #04 final review: a look-alike warning never shows more than the user may see.
 *
 * A similar Product the user can open (ProductAccessPolicy) is described in full; one outside their access is only
 * a generic notice ("N similar Products outside your access, contact Owner/Admin Pusat") with no id, name, code,
 * outlet list or link. Same rule for Add Product, rename (Workspace and classic) and CSV import. Owner / Admin
 * Pusat always see everything. Duplicate prevention itself keeps working, nothing is merged or deleted.
 */
class DuplicateWarningAccessTest extends TestCase
{
    use RefreshDatabase;

    private const HIDDEN_CODE = 'ZZ-HIDDEN-77';

    private const VISIBLE_CODE = 'VIS-ALPHA-11';

    private Outlet $a;

    private Outlet $b;

    private Outlet $c;

    private Brand $brand;

    private ProductCategory $category;

    /** "Ube Latte" at Charlie only: invisible to a user limited to Alpha. */
    private Product $hiddenTwin;

    /** "Mocha Ice" at Alpha (+ Bravo): visible to a user limited to Alpha. */
    private Product $visibleTwin;

    /** "Plain" at Alpha only: the Product a limited user renames. */
    private Product $own;

    private User $owner;

    private User $pusat;

    private User $limitedA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Outlet::create(['name' => 'Alpha', 'code' => 'OA', 'is_active' => true]);
        $this->b = Outlet::create(['name' => 'Bravo', 'code' => 'OB', 'is_active' => true]);
        $this->c = Outlet::create(['name' => 'Charlie', 'code' => 'OC', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $this->category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);

        $this->hiddenTwin = $this->makeProduct('Ube Latte', self::HIDDEN_CODE, [$this->c]);
        $this->visibleTwin = $this->makeProduct('Mocha Ice', self::VISIBLE_CODE, [$this->a, $this->b]);
        $this->own = $this->makeProduct('Plain', 'PLAIN', [$this->a]);

        $this->owner = $this->makeUser('owner', [$this->a, $this->b, $this->c]);
        $this->pusat = $this->makeUser('admin_pusat', [$this->a]);
        $this->limitedA = $this->makeUser('admin_outlet', [$this->a]);
    }

    // ================================================================================================
    // The guard itself
    // ================================================================================================

    public function test_the_guard_describes_only_products_the_user_may_open(): void
    {
        $guard = app(ProductDuplicateGuard::class);

        $this->assertNull($guard->describe($this->hiddenTwin, $this->limitedA));
        $this->assertSame(self::VISIBLE_CODE, $guard->describe($this->visibleTwin, $this->limitedA)['code']);
        $this->assertSame(self::HIDDEN_CODE, $guard->describe($this->hiddenTwin, $this->owner)['code']);
        $this->assertSame(self::HIDDEN_CODE, $guard->describe($this->hiddenTwin, $this->pusat)['code']);

        $split = $guard->split(collect([$this->hiddenTwin, $this->visibleTwin]), $this->limitedA);
        $this->assertCount(1, $split['visible']);
        $this->assertSame(1, $split['hidden']);
        $this->assertSame(0, $guard->split(collect([$this->hiddenTwin, $this->visibleTwin]), $this->owner)['hidden']);
    }

    // ================================================================================================
    // Add Product
    // ================================================================================================

    public function test_admin_outlet_gets_a_generic_warning_for_an_inaccessible_look_alike(): void
    {
        $response = $this->actingAs($this->limitedA)->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW'));

        $response->assertRedirect()->assertSessionHas('similar_products', [])->assertSessionHas('similar_hidden', 1);
        $this->assertDatabaseMissing('products', ['code' => 'UBE-NEW']);
    }

    public function test_the_warning_page_for_admin_outlet_leaks_nothing_and_points_to_owner(): void
    {
        $page = $this->followingRedirects()->actingAs($this->limitedA)->from(route('backoffice.products.create'))
            ->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW'))->getContent();

        $this->assertStringContainsString('data-similar-products', $page, 'the warning is still shown');
        $this->assertStringContainsString('data-similar-hidden', $page);
        $this->assertStringContainsString('Hubungi Owner/Admin Pusat', $page);
        $this->assertStringContainsString('name="confirm_similar"', $page, 'intentional same-name creation stays possible');
        $this->assertStringNotContainsString(self::HIDDEN_CODE, $page);
        $this->assertStringNotContainsString('data-similar-product="', $page);
        $this->assertStringNotContainsString('outlet: Charlie', $page);
        $this->assertStringNotContainsString('/backoffice/products/'.$this->hiddenTwin->id.'/edit', $page);
    }

    public function test_admin_outlet_can_still_create_the_same_name_after_confirming(): void
    {
        $this->actingAs($this->limitedA)->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW', ['confirm_similar' => 1]))->assertSessionHasNoErrors();

        $created = Product::where('code', 'UBE-NEW')->firstOrFail();
        $this->assertSame([$this->a->id], $created->outlets()->pluck('outlets.id')->all());
        $this->assertSame(2, Product::where('name', 'Ube Latte')->count());
        $this->assertSame(self::HIDDEN_CODE, $this->hiddenTwin->fresh()->code, 'the existing Product is untouched');
    }

    public function test_admin_outlet_sees_full_details_for_a_look_alike_it_can_access(): void
    {
        $response = $this->actingAs($this->limitedA)->post(route('backoffice.products.store'), $this->payload('Mocha Ice', 'MOCHA-NEW'));

        $response->assertSessionHas('similar_hidden', 0);
        $similar = session('similar_products');
        $this->assertCount(1, $similar);
        $this->assertSame(self::VISIBLE_CODE, $similar[0]['code']);
        $this->assertSame($this->visibleTwin->id, $similar[0]['id']);
        $this->assertSame(['Alpha', 'Bravo'], $similar[0]['outlets']);
        $this->assertNotNull($similar[0]['url']);
    }

    public function test_a_mix_of_visible_and_inaccessible_look_alikes_is_split(): void
    {
        $this->makeProduct('Ube Latte', 'UBE-ALPHA', [$this->a]);   // visible twin of the hidden one's name

        $this->actingAs($this->limitedA)->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW'))->assertSessionHas('similar_hidden', 1);

        $this->assertSame(['UBE-ALPHA'], collect(session('similar_products'))->pluck('code')->all());
    }

    public function test_owner_and_admin_pusat_see_complete_details(): void
    {
        foreach ([$this->owner, $this->pusat] as $user) {
            $this->actingAs($user)->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW'))->assertSessionHas('similar_hidden', 0);

            $similar = session('similar_products');
            $this->assertCount(1, $similar, $user->role->code);
            $this->assertSame(self::HIDDEN_CODE, $similar[0]['code']);
            $this->assertSame(['Charlie'], $similar[0]['outlets']);
            $this->assertSame($this->hiddenTwin->id, $similar[0]['id']);
            $this->assertNotNull($similar[0]['url']);
        }

        $page = $this->followingRedirects()->actingAs($this->owner)->from(route('backoffice.products.create'))
            ->post(route('backoffice.products.store'), $this->payload('Ube Latte', 'UBE-NEW'))->getContent();
        $this->assertStringContainsString(self::HIDDEN_CODE, $page);
        $this->assertStringContainsString('outlet: Charlie', $page);
        $this->assertStringNotContainsString('data-similar-hidden', $page);
    }

    // ================================================================================================
    // Rename (Workspace General and classic update)
    // ================================================================================================

    public function test_a_rename_warning_for_admin_outlet_hides_an_inaccessible_look_alike(): void
    {
        $response = $this->putGeneral($this->limitedA, $this->own, ['name' => 'Ube Latte']);

        $response->assertStatus(409)->assertJsonPath('needs_confirmation', 'similar_product')->assertJsonPath('similar_products', [])->assertJsonPath('similar_hidden', 1);
        $this->assertStringContainsString('Hubungi Owner/Admin Pusat', $response->json('message'));
        $this->assertStringNotContainsString(self::HIDDEN_CODE, $response->getContent());
        $this->assertStringNotContainsString('Charlie', $response->getContent());
        $this->assertStringNotContainsString('/backoffice/products/'.$this->hiddenTwin->id, $response->getContent());
        $this->assertSame('Plain', $this->own->fresh()->name, 'nothing saved before the confirmation');

        // Intentional match stays possible once confirmed.
        $this->putGeneral($this->limitedA, $this->own, ['name' => 'Ube Latte', 'confirm_similar' => 1])->assertOk();
        $this->assertSame('Ube Latte', $this->own->fresh()->name);
        $this->assertSame(2, Product::where('name', 'Ube Latte')->count());
    }

    public function test_a_rename_warning_for_admin_outlet_shows_full_details_of_an_accessible_look_alike(): void
    {
        $response = $this->putGeneral($this->limitedA, $this->own, ['name' => 'Mocha Ice']);

        $response->assertStatus(409)->assertJsonPath('similar_hidden', 0)->assertJsonPath('similar_products.0.code', self::VISIBLE_CODE);
        $this->assertStringContainsString('kode '.self::VISIBLE_CODE, $response->json('message'));
        $this->assertStringContainsString('Alpha, Bravo', $response->json('message'));
    }

    public function test_a_rename_warning_for_owner_and_admin_pusat_shows_complete_details(): void
    {
        foreach ([$this->owner, $this->pusat] as $user) {
            $response = $this->putGeneral($user, $this->own, ['name' => 'Ube Latte']);

            $response->assertStatus(409)->assertJsonPath('similar_hidden', 0)->assertJsonPath('similar_products.0.code', self::HIDDEN_CODE);
            $this->assertStringContainsString('kode '.self::HIDDEN_CODE.', outlet: Charlie', $response->json('message'));
        }
    }

    public function test_the_general_form_page_after_a_hidden_rename_warning_leaks_nothing(): void
    {
        $page = $this->followingRedirects()->actingAs($this->limitedA)->from(route('backoffice.products.edit', [$this->own, 'section' => 'general']))
            ->put(route('backoffice.products.workspace.general', $this->own), [
                'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'PLAIN', 'is_active' => 1,
            ])->getContent();

        $this->assertStringContainsString('data-pw-similar-products', $page);
        $this->assertStringContainsString('data-pw-similar-hidden', $page);
        $this->assertStringContainsString('name="confirm_similar"', $page);
        $this->assertStringNotContainsString(self::HIDDEN_CODE, $page);
        $this->assertStringNotContainsString('data-pw-similar-product="', $page);
        $this->assertSame('Plain', $this->own->fresh()->name);
    }

    public function test_the_classic_update_rename_warning_respects_access_too(): void
    {
        $payload = ['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => 'Ube Latte', 'code' => 'PLAIN', 'is_active' => 1, 'outlet_ids' => [$this->a->id]];

        $this->actingAs($this->limitedA)->put(route('backoffice.products.update', $this->own), $payload)->assertSessionHas('similar_products', [])->assertSessionHas('similar_hidden', 1);
        $this->assertSame('Plain', $this->own->fresh()->name);

        $this->actingAs($this->owner)->put(route('backoffice.products.update', $this->own), $payload)->assertSessionHas('similar_hidden', 0);
        $this->assertSame(self::HIDDEN_CODE, session('similar_products')[0]['code']);

        $this->actingAs($this->limitedA)->put(route('backoffice.products.update', $this->own), $payload + ['confirm_similar' => 1])->assertSessionHasNoErrors();
        $this->assertSame('Ube Latte', $this->own->fresh()->name);
    }

    // ================================================================================================
    // CSV import
    // ================================================================================================

    public function test_csv_import_warnings_hide_an_inaccessible_look_alike(): void
    {
        $this->importProducts($this->limitedA, $this->a, "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE-NEW,,1\n");

        $this->assertDatabaseMissing('products', ['code' => 'UBE-NEW']);
        $report = collect(session('import_errors'))->implode(' ');
        $this->assertStringContainsString('mirip', $report);
        $this->assertStringContainsString('Hubungi Owner/Admin Pusat', $report);
        $this->assertStringContainsString('Buat meskipun mirip', $report);
        $this->assertStringNotContainsString(self::HIDDEN_CODE, $report);
        $this->assertStringNotContainsString('Charlie', $report);
        $this->assertStringContainsString('Dilewati: 1', session('success'));
    }

    public function test_csv_import_shows_full_details_for_accessible_look_alikes_and_owner_sees_all(): void
    {
        $this->importProducts($this->limitedA, $this->a, "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Mocha Ice,MOCHA-NEW,,1\n");
        $report = collect(session('import_errors'))->implode(' ');
        $this->assertStringContainsString('kode '.self::VISIBLE_CODE, $report);
        $this->assertStringContainsString('Alpha, Bravo', $report);
        $this->assertStringNotContainsString('Hubungi Owner/Admin Pusat', $report, 'nothing is hidden here');

        foreach ([$this->owner, $this->pusat] as $user) {
            $this->importProducts($user, $this->b, "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE-NEW,,1\n");
            $report = collect(session('import_errors'))->implode(' ');
            $this->assertStringContainsString('kode '.self::HIDDEN_CODE.', outlet: Charlie', $report, $user->role->code);
        }
    }

    public function test_csv_import_still_creates_the_same_name_when_confirmed_and_never_merges(): void
    {
        $this->importProducts($this->limitedA, $this->a, "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Ube Latte,UBE-NEW,,1\n", ['allow_similar' => 1]);

        $this->assertDatabaseHas('products', ['code' => 'UBE-NEW']);
        $this->assertSame(4, Product::count());
        $this->assertSame([$this->c->id], $this->hiddenTwin->outlets()->pluck('outlets.id')->all());
        $this->assertSame(self::HIDDEN_CODE, $this->hiddenTwin->fresh()->code);
    }

    public function test_import_of_an_existing_code_stays_assignment_only_and_still_works_after_the_change(): void
    {
        $variant = ProductVariant::create(['product_id' => $this->own->id, 'name' => 'Regular', 'code' => 'PLAIN-R', 'price' => 1000, 'price_dine_in' => 1000, 'price_delivery' => 1000, 'is_active' => true]);
        $variant->outlets()->sync([$this->a->id]);

        $this->importProducts($this->owner, $this->b, "brand_name,category_name,name,code,description,is_active\nATG,Kafei,Plain,PLAIN,,1\n");
        $this->actingAs($this->owner)->post(route('backoffice.variants.import.store'), ['file' => UploadedFile::fake()->createWithContent('v.csv', "product_code,outlet_code,name,code,price_dine_in,price_delivery,is_active\nPLAIN,OB,Regular,PLAIN-R,1000,1000,1\n")])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $this->own->outlets()->pluck('outlets.id')->all());
        $this->assertEqualsCanonicalizing([$this->a->id, $this->b->id], $variant->outlets()->pluck('outlets.id')->all());
        $this->assertSame(1000.0, (float) $variant->fresh()->price_dine_in);
        $this->assertSame(1, ProductVariant::count());
    }

    // ================================================================================================
    // helpers
    // ================================================================================================

    private function payload(string $name, string $code, array $extra = []): array
    {
        return $extra + [
            'brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name, 'code' => $code,
            'is_active' => 1, 'outlet_ids' => [$this->a->id],
        ];
    }

    private function putGeneral(User $user, Product $product, array $override = [])
    {
        return $this->actingAs($user)->putJson(route('backoffice.products.workspace.general', $product), $override + [
            'brand_id' => $product->brand_id, 'product_category_id' => $product->product_category_id, 'name' => $product->name,
            'code' => $product->code, 'description' => $product->description, 'is_active' => $product->is_active ? 1 : 0,
        ]);
    }

    private function importProducts(User $user, Outlet $outlet, string $csv, array $extra = [])
    {
        return $this->actingAs($user)->post(route('backoffice.products.import.store'), $extra + ['outlet_id' => $outlet->id, 'file' => UploadedFile::fake()->createWithContent('products.csv', $csv)]);
    }

    private function makeProduct(string $name, string $code, array $outlets): Product
    {
        $product = Product::create(['brand_id' => $this->brand->id, 'product_category_id' => $this->category->id, 'name' => $name, 'code' => $code, 'is_active' => true]);
        $product->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $product;
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
