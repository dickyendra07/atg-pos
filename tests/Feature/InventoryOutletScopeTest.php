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
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProductWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UX-G blocker: Inventory Control and Stock Movements (the pages the Product Workspace links to) must
 * only expose the outlets the user may access. "Semua Outlet" is "all outlets within that user's access".
 * Read scoping only: no stock, movement or accounting rule is involved.
 */
class InventoryOutletScopeTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $bxc;

    private Outlet $ta;

    private Outlet $carstensz;

    private Warehouse $warehouse;

    private Ingredient $milk;

    private Product $product;

    private User $owner;

    private User $bxcOnly;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bxc = Outlet::create(['name' => 'BXC Mall', 'code' => 'BXC', 'is_active' => true]);
        $this->ta = Outlet::create(['name' => 'Tebet Arena', 'code' => 'TA', 'is_active' => true]);
        $this->carstensz = Outlet::create(['name' => 'Carstensz Point', 'code' => 'CAR', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang Utama', 'code' => 'GU', 'is_active' => true]);

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => 'Fresh Milk', 'code' => 'MILK', 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 10, 'cost_per_unit' => 10, 'is_active' => true,
        ]);
        $this->milk->outlets()->sync([$this->bxc->id, $this->ta->id, $this->carstensz->id]);

        // Distinct quantities and notes so a leak is unmistakable in the page / CSV.
        foreach ([[$this->bxc, 111], [$this->ta, 222], [$this->carstensz, 333]] as [$outlet, $qty]) {
            $this->balance('outlet', $outlet->id, $qty);
            $this->movement('outlet', $outlet->id, $qty, 'note-'.$outlet->code);
        }
        $this->balance('warehouse', $this->warehouse->id, 444);
        $this->movement('warehouse', $this->warehouse->id, 444, 'note-GU');

        $brand = Brand::create(['name' => 'ATG', 'code' => 'ATG', 'is_active' => true]);
        $productCategory = ProductCategory::create(['brand_id' => $brand->id, 'name' => 'Kafei', 'code' => 'KAFEI', 'is_active' => true]);
        $this->product = Product::create(['brand_id' => $brand->id, 'product_category_id' => $productCategory->id, 'name' => 'Ube Latte', 'code' => 'UBE', 'is_active' => true]);
        $this->product->outlets()->sync([$this->bxc->id, $this->ta->id]);
        $variant = ProductVariant::create(['product_id' => $this->product->id, 'name' => 'Regular', 'code' => 'UBE-R', 'price' => 1, 'price_dine_in' => 1, 'price_delivery' => 1, 'is_active' => true]);
        $variant->outlets()->sync([$this->bxc->id, $this->ta->id]);
        $recipe = Recipe::create(['product_id' => $this->product->id, 'product_variant_id' => $variant->id, 'name' => 'Recipe', 'is_active' => true]);
        RecipeItem::create(['recipe_id' => $recipe->id, 'ingredient_id' => $this->milk->id, 'qty' => 100, 'unit' => 'ml']);

        $this->owner = $this->user('owner', [$this->bxc, $this->ta, $this->carstensz]);
        $this->bxcOnly = $this->user('admin_outlet', [$this->bxc]);
    }

    // ================================================================================================
    // Stock Balances
    // ================================================================================================

    public function test_a_bxc_only_user_sees_only_bxc_outlet_balances_under_semua_outlet(): void
    {
        $response = $this->actingAs($this->bxcOnly)->get(route('backoffice.stock-balances.index'))->assertOk();

        $this->assertEqualsCanonicalizing(
            [['outlet', $this->bxc->id], ['warehouse', $this->warehouse->id]],
            $response->viewData('stockBalances')->map(fn ($row) => [$row->location_type, (int) $row->location_id])->all()
        );
        $this->assertEqualsCanonicalizing(
            [['outlet', $this->bxc->id], ['warehouse', $this->warehouse->id]],
            $response->viewData('stockSummaryRows')->map(fn ($row) => [$row['location_type'], $row['location_id']])->all()
        );
        $this->assertSame(2, $response->viewData('summary')['stock_rows']);
        $this->assertNoOtherOutlet($response->getContent(), ['222,00', '333,00']);
        $this->assertStringContainsString('111,00', $response->getContent());
    }

    public function test_an_explicit_unauthorized_outlet_filter_cannot_retrieve_that_outlets_balances(): void
    {
        $this->actingAs($this->bxcOnly);
        $queries = [
            ['summary_location_type' => 'outlet', 'summary_location_id' => $this->ta->id],
            ['summary_location_type' => 'outlet', 'summary_location_id' => $this->carstensz->id, 'ingredient_id' => $this->milk->id],
            ['location_type' => 'outlet', 'ingredient_id' => $this->milk->id],
            ['keyword' => (string) $this->ta->id],
            ['keyword' => 'Tebet'],
            ['need_action_location' => 'outlet:'.$this->ta->id, 'status' => 'safe'],
            ['summary_location_type' => 'outlet', 'summary_location_id' => $this->ta->id, 'summary_date_from' => '2020-01-01', 'summary_date_to' => '2030-01-01'],
        ];

        foreach ($queries as $query) {
            $response = $this->get(route('backoffice.stock-balances.index', $query))->assertOk();
            $label = json_encode($query);

            foreach ($response->viewData('stockBalances') as $row) {
                $this->assertTrue($row->location_type === 'warehouse' || (int) $row->location_id === $this->bxc->id, 'balances '.$label);
            }
            foreach ($response->viewData('stockSummaryRows') as $row) {
                $this->assertTrue($row['location_type'] === 'warehouse' || $row['location_id'] === $this->bxc->id, 'summary '.$label);
            }
            foreach ($response->viewData('needActionItems') as $row) {
                $this->assertNotContains($row['location_name'], ['Tebet Arena', 'Carstensz Point'], 'need action '.$label);
            }
            $this->assertNoOtherOutlet($response->getContent(), ['222,00', '333,00']);
        }

        // The CSV export shares the summary builder and is scoped the same way.
        foreach ([['summary_location_type' => 'outlet', 'summary_location_id' => $this->ta->id], []] as $query) {
            $csv = $this->get(route('backoffice.stock-balances.export.csv', $query))->assertOk()->streamedContent();

            $this->assertStringNotContainsString('Tebet Arena', $csv);
            $this->assertStringNotContainsString('Carstensz Point', $csv);
            $this->assertStringContainsString('BXC Mall', $csv);
        }
    }

    public function test_an_unreadable_summary_location_does_not_even_produce_zero_rows_for_that_outlet(): void
    {
        // Opname rows are generated for every ingredient of a chosen location, balance or not.
        $viaFilter = $this->actingAs($this->bxcOnly)
            ->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->carstensz->id]))
            ->viewData('stockSummaryRows');

        $this->assertNotContains('Carstensz Point', $viaFilter->pluck('location_name')->all());
        $this->assertNotContains($this->carstensz->id, $viaFilter->where('location_type', 'outlet')->pluck('location_id')->all());

        // The same call is still honoured for an outlet the user may read.
        $own = $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->bxc->id]))->viewData('stockSummaryRows');
        $this->assertSame([$this->bxc->id], $own->pluck('location_id')->unique()->values()->all());
    }

    public function test_the_adjustment_form_does_not_ship_other_outlets_balances(): void
    {
        $stockMap = $this->actingAs($this->bxcOnly)->get(route('backoffice.stock-balances.adjustment.create'))->assertOk()->viewData('stockMap');

        $this->assertEqualsCanonicalizing(['outlet:'.$this->bxc->id, 'warehouse:'.$this->warehouse->id], array_keys($stockMap));

        $ownerMap = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.adjustment.create'))->viewData('stockMap');
        $this->assertCount(4, $ownerMap);
    }

    // ================================================================================================
    // Stock Movements
    // ================================================================================================

    public function test_a_bxc_only_user_sees_only_bxc_movements_and_filters_cannot_widen_it(): void
    {
        $this->actingAs($this->bxcOnly);

        foreach ([[], ['ingredient_id' => $this->milk->id], ['search' => 'note-TA'], ['search' => 'note'], ['movement_type' => 'opening_balance', 'ingredient_id' => $this->milk->id]] as $query) {
            $response = $this->get(route('backoffice.stock-movements.index', $query))->assertOk();
            $locations = $response->viewData('stockMovements')->map(fn ($row) => [$row->location_type, (int) $row->location_id])->all();

            foreach ($locations as [$type, $id]) {
                $this->assertTrue($type === 'warehouse' || $id === $this->bxc->id, json_encode($query));
            }
            // (The page echoes a search term back into its own filter box, so only rows are checked for that one.)
            $html = $response->getContent();
            $echoed = (string) ($query['search'] ?? '');
            foreach (['note-TA', 'note-CAR'] as $needle) {
                if ($echoed === '' || ! str_contains($needle, $echoed) || $echoed === 'note') {
                    $this->assertStringNotContainsString($needle, $html);
                }
            }
        }

        $all = $this->get(route('backoffice.stock-movements.index'))->viewData('stockMovements');
        $this->assertCount(2, $all);
        $this->assertCount(0, $this->get(route('backoffice.stock-movements.index', ['search' => 'note-TA']))->viewData('stockMovements'));

        foreach ([[], ['search' => 'note-TA'], ['ingredient_id' => $this->milk->id]] as $query) {
            $csv = $this->get(route('backoffice.stock-movements.export.csv', $query))->assertOk()->streamedContent();

            $this->assertStringNotContainsString('note-TA', $csv);
            $this->assertStringNotContainsString('note-CAR', $csv);
        }
        $this->assertStringContainsString('note-BXC', $this->get(route('backoffice.stock-movements.export.csv'))->streamedContent());
    }

    // ================================================================================================
    // Owner / admin pusat unchanged
    // ================================================================================================

    public function test_owner_and_admin_pusat_keep_all_outlet_visibility(): void
    {
        foreach ([$this->owner, $this->user('admin_pusat', [$this->bxc, $this->ta, $this->carstensz])] as $user) {
            $this->actingAs($user);

            $balances = $this->get(route('backoffice.stock-balances.index'))->assertOk();
            $this->assertCount(4, $balances->viewData('stockBalances'));
            $this->assertCount(4, $balances->viewData('stockSummaryRows'));
            $this->assertStringContainsString('Tebet Arena', $balances->getContent());
            $this->assertStringContainsString('333,00', $balances->getContent());

            $this->assertCount(4, $this->get(route('backoffice.stock-movements.index'))->viewData('stockMovements'));
            $this->assertStringContainsString('note-CAR', $this->get(route('backoffice.stock-movements.export.csv'))->streamedContent());

            $filtered = $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->ta->id]));
            $this->assertSame([$this->ta->id], $filtered->viewData('stockSummaryRows')->pluck('location_id')->unique()->values()->all());
        }
    }

    // ================================================================================================
    // Active Outlet
    // ================================================================================================

    public function test_active_outlet_still_narrows_to_that_outlet_and_an_unallowed_one_is_not_honoured(): void
    {
        $own = $this->actingAs($this->bxcOnly)
            ->withSession(['active_backoffice_outlet_id' => $this->bxc->id])
            ->get(route('backoffice.stock-balances.index'))->assertOk();
        $this->assertSame([$this->bxc->id], $own->viewData('stockBalances')->pluck('location_id')->map(fn ($id) => (int) $id)->all(), 'active outlet: that outlet only, no warehouse');

        // An outlet outside the user's access cannot become the Active Outlet, by session or by request.
        $this->post(route('backoffice.active-outlet.update'), ['outlet_id' => $this->ta->id])->assertSessionHasErrors('outlet_id');
        $forged = $this->withSession(['active_backoffice_outlet_id' => $this->ta->id])->get(route('backoffice.stock-balances.index'))->assertOk();
        $this->assertNoOtherOutlet($forged->getContent(), ['222,00', '333,00']);
        $this->assertSame(2, $forged->viewData('stockBalances')->count(), 'falls back to the user\'s own Semua Outlet');

        $this->post(route('backoffice.active-outlet.update'), ['outlet_id' => 0]);
        $this->assertSame(2, $this->get(route('backoffice.stock-balances.index'))->viewData('stockBalances')->count());
    }

    public function test_semua_outlet_for_a_two_outlet_user_covers_exactly_those_two(): void
    {
        $two = $this->user('admin_outlet', [$this->bxc, $this->ta]);

        $rows = $this->actingAs($two)->get(route('backoffice.stock-balances.index'))->viewData('stockBalances');

        $this->assertEqualsCanonicalizing(
            [['outlet', $this->bxc->id], ['outlet', $this->ta->id], ['warehouse', $this->warehouse->id]],
            $rows->map(fn ($row) => [$row->location_type, (int) $row->location_id])->all()
        );
        $this->assertCount(3, $this->get(route('backoffice.stock-movements.index'))->viewData('stockMovements'));
    }

    // ================================================================================================
    // Product Workspace contextual links stay valid and safe
    // ================================================================================================

    public function test_workspace_stock_links_work_for_the_limited_user_and_return_to_the_stock_section(): void
    {
        $returnTo = ProductWorkspace::url($this->product, 'stock', null);
        $panel = $this->between(
            $this->actingAs($this->bxcOnly)->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->assertOk()->getContent(),
            'id="pw-section-stock"',
            '</section>'
        );

        $this->assertStringNotContainsString('Tebet Arena', $panel);
        $this->assertStringNotContainsString('222,00', $panel);

        $balanceLink = e(route('backoffice.stock-balances.index', ['ingredient_id' => $this->milk->id, 'return_to' => $returnTo], false));
        $movementLink = e(route('backoffice.stock-movements.index', ['ingredient_id' => $this->milk->id, 'return_to' => $returnTo], false));
        $this->assertStringContainsString($balanceLink, $panel);
        $this->assertStringContainsString($movementLink, $panel);

        $balances = $this->get(html_entity_decode($balanceLink))->assertOk();
        $movements = $this->get(html_entity_decode($movementLink))->assertOk();

        foreach ([$balances, $movements] as $page) {
            $html = $page->getContent();
            $this->assertStringContainsString('data-return-to-back', $html);
            $this->assertStringContainsString('href="'.e($returnTo).'"', $html);
            $this->assertStringContainsString('111,00', $balances->getContent());
            $this->assertNoOtherOutlet($html, ['222,00', '333,00', 'note-TA', 'note-CAR']);
        }

        // A hostile return_to is still ignored, and does not change what the user may see.
        foreach (['stock-balances.index', 'stock-movements.index'] as $name) {
            $hostile = $this->get(route('backoffice.'.$name, ['ingredient_id' => $this->milk->id, 'return_to' => 'https://evil.example/x']))->assertOk()->getContent();

            $this->assertStringNotContainsString('data-return-to-back', $hostile);
            $this->assertNoOtherOutlet($hostile, ['222,00', '333,00', 'note-TA', 'note-CAR']);
        }
    }

    // ================================================================================================
    // Read-only
    // ================================================================================================

    public function test_none_of_these_read_requests_write_anything(): void
    {
        $before = [DB::table('stock_balances')->orderBy('id')->get()->all(), DB::table('stock_movements')->orderBy('id')->get()->all()];
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace|alter|drop|create)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        foreach ([$this->bxcOnly, $this->owner] as $user) {
            $this->actingAs($user);
            $this->get(route('backoffice.stock-balances.index', ['summary_location_type' => 'outlet', 'summary_location_id' => $this->ta->id]))->assertOk();
            $this->get(route('backoffice.stock-balances.export.csv'))->assertOk()->streamedContent();
            $this->get(route('backoffice.stock-balances.adjustment.create'))->assertOk();
            $this->get(route('backoffice.stock-movements.index', ['ingredient_id' => $this->milk->id]))->assertOk();
            $this->get(route('backoffice.stock-movements.export.csv'))->assertOk()->streamedContent();
            $this->get(route('backoffice.products.edit', [$this->product, 'section' => 'stock']))->assertOk();
        }

        $this->assertSame([], $writes);
        $this->assertEquals($before, [DB::table('stock_balances')->orderBy('id')->get()->all(), DB::table('stock_movements')->orderBy('id')->get()->all()]);
    }

    // ---- helpers --------------------------------------------------------------------------------------

    private function assertNoOtherOutlet(string $html, array $needles): void
    {
        foreach (array_merge(['Tebet Arena', 'Carstensz Point'], $needles) as $needle) {
            $this->assertStringNotContainsString($needle, $html, 'leaked: '.$needle);
        }
    }

    private function between(string $html, string $start, string $end): string
    {
        $from = strpos($html, $start);
        $this->assertNotFalse($from, $start);

        return substr($html, $from, strpos($html, $end, $from) - $from);
    }

    private function balance(string $type, int $id, float $qty): void
    {
        StockBalance::create(['ingredient_id' => $this->milk->id, 'location_type' => $type, 'location_id' => $id, 'qty_on_hand' => $qty]);
    }

    private function movement(string $type, int $id, float $qty, string $note): void
    {
        StockMovement::create([
            'ingredient_id' => $this->milk->id, 'location_type' => $type, 'location_id' => $id, 'movement_type' => 'opening_balance',
            'qty_in' => $qty, 'qty_out' => 0, 'reference_type' => null, 'reference_id' => null, 'note' => $note,
        ]);
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
