<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackofficeCategoryAndListingTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Brand $brand;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);

        $role = Role::create(['name' => 'Owner', 'code' => 'owner']);
        $this->owner = User::create([
            'name' => 'owner', 'username' => 'owner', 'email' => 'owner@example.test',
            'password' => 'password', 'role_id' => $role->id, 'outlet_id' => $this->outlet->id, 'is_active' => true,
        ]);
        $this->owner->outlets()->sync([$this->outlet->id]);
    }

    // ---- Menu Category delete -------------------------------------------------------------

    public function test_unused_menu_category_can_be_deleted(): void
    {
        $category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Empty Category', 'code' => 'EMPTY', 'is_active' => true]);

        $response = $this->actingAs($this->owner)
            ->delete(route('backoffice.menu-categories.destroy', $category->id));

        $response->assertRedirect(route('backoffice.menu-categories.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);

        $this->get(route('backoffice.menu-categories.index'))
            ->assertOk()->assertDontSee('Empty Category');
    }

    public function test_delete_requires_the_delete_http_method(): void
    {
        $category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Csrf Check', 'code' => 'CSRFCHK', 'is_active' => true]);

        // A plain GET on the destroy URL must not delete anything (only DELETE is routed to it;
        // the Blade form uses @method('DELETE') + @csrf, matching this).
        $this->actingAs($this->owner)->get(route('backoffice.menu-categories.destroy', $category->id))->assertMethodNotAllowed();
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_delete_requires_authentication(): void
    {
        $category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Guest Check', 'code' => 'GUESTCHK', 'is_active' => true]);

        $this->delete(route('backoffice.menu-categories.destroy', $category->id))->assertRedirect(route('login'));
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_menu_category_still_used_by_products_cannot_be_deleted(): void
    {
        $category = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'In Use', 'code' => 'INUSE', 'is_active' => true]);
        $product = Product::create([
            'brand_id' => $this->brand->id, 'product_category_id' => $category->id,
            'name' => 'Product A', 'code' => 'PROD-A', 'is_active' => true,
        ]);
        $product->outlets()->sync([$this->outlet->id]);

        $response = $this->actingAs($this->owner)
            ->delete(route('backoffice.menu-categories.destroy', $category->id));

        $response->assertRedirect(route('backoffice.menu-categories.index'));
        $response->assertSessionHas('error', fn ($message) => str_contains($message, 'digunakan oleh 1 produk')
            && str_contains($message, 'Pindahkan kategori produk terlebih dahulu sebelum menghapus'));

        // Not deleted, and the FK's cascadeOnDelete never fired: the product still has its category.
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'product_category_id' => $category->id]);
    }

    public function test_deleting_one_menu_category_does_not_affect_unrelated_category_or_products(): void
    {
        $target = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Delete Me', 'code' => 'DELME', 'is_active' => true]);
        $untouched = ProductCategory::create(['brand_id' => $this->brand->id, 'name' => 'Keep Me', 'code' => 'KEEPME', 'is_active' => true]);
        $unrelatedProduct = Product::create([
            'brand_id' => $this->brand->id, 'product_category_id' => $untouched->id,
            'name' => 'Unrelated Product', 'code' => 'UNREL', 'is_active' => true,
        ]);
        $unrelatedProduct->outlets()->sync([$this->outlet->id]);

        $this->actingAs($this->owner)->delete(route('backoffice.menu-categories.destroy', $target->id));

        $this->assertDatabaseMissing('product_categories', ['id' => $target->id]);
        $this->assertDatabaseHas('product_categories', ['id' => $untouched->id, 'name' => 'Keep Me']);
        $this->assertDatabaseHas('products', ['id' => $unrelatedProduct->id, 'product_category_id' => $untouched->id]);
    }

    public function test_ingredient_category_has_no_delete_route_wired(): void
    {
        $category = IngredientCategory::create(['name' => 'Unused Bahan', 'code' => 'UNUSED_BAHAN', 'is_active' => true]);

        // Only GET (index/create/edit) and PUT (update) are routed for ingredient categories, so
        // an unrouted DELETE resolves to 405, confirming no delete endpoint exists for this domain.
        $this->actingAs($this->owner)
            ->delete(route('backoffice.ingredient-categories.index').'/'.$category->id)
            ->assertMethodNotAllowed();

        $this->assertDatabaseHas('ingredient_categories', ['id' => $category->id]);
    }

    // ---- Ingredient Index ordering ---------------------------------------------------------

    public function test_ingredient_index_default_order_is_alphabetic_by_name_then_category(): void
    {
        $catA = IngredientCategory::create(['name' => 'A Category', 'code' => 'A_CAT', 'is_active' => true]);
        $catZ = IngredientCategory::create(['name' => 'Z Category', 'code' => 'Z_CAT', 'is_active' => true]);

        // Same ingredient name in two categories, to prove the category is the tie-breaker.
        $zebra = $this->ingredient('zebra', $catZ);
        $apple = $this->ingredient('Apple', $catA);
        $mangoA = $this->ingredient('Mango', $catA);
        $mangoZ = $this->ingredient('mango', $catZ);

        $response = $this->actingAs($this->owner)->get(route('backoffice.ingredients.index'));
        $response->assertOk();

        $names = collect($response->viewData('ingredients'))->pluck('id')->all();

        $this->assertSame(
            [$apple->id, $mangoA->id, $mangoZ->id, $zebra->id],
            $names,
            'Expected Apple, Mango (A Category), mango (Z Category), zebra — case-insensitive name ASC then category ASC.'
        );
    }

    public function test_ingredient_index_search_and_active_outlet_still_work_with_new_ordering(): void
    {
        $cat = IngredientCategory::create(['name' => 'Cat', 'code' => 'CAT', 'is_active' => true]);
        $other = Outlet::create(['name' => 'Other', 'code' => 'OTHER', 'is_active' => true]);

        $here = $this->ingredient('Only Here', $cat);
        $here->outlets()->sync([$this->outlet->id]);

        $elsewhere = $this->ingredient('Only Elsewhere', $cat);
        $elsewhere->outlets()->sync([$other->id]);

        $this->actingAs($this->owner)->withSession(['active_backoffice_outlet_id' => $this->outlet->id])
            ->get(route('backoffice.ingredients.index'))
            ->assertOk()->assertSee('Only Here')->assertDontSee('Only Elsewhere');

        $this->get(route('backoffice.ingredients.index', ['search' => 'Elsewhere']))
            ->assertOk()->assertDontSee('Only Elsewhere');

        $this->get(route('backoffice.ingredients.index', ['search' => 'Here']))
            ->assertOk()->assertSee('Only Here');
    }

    private function ingredient(string $name, IngredientCategory $category): Ingredient
    {
        return Ingredient::create([
            'ingredient_category_id' => $category->id,
            'name' => $name,
            'code' => strtoupper(str_replace(' ', '_', $name)).'_'.random_int(1000, 9999),
            'unit' => 'gram',
            'ingredient_type' => Ingredient::TYPE_RAW,
            'minimum_stock' => 0,
            'cost_per_unit' => 1,
            'is_active' => true,
        ]);
    }
}
