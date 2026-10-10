<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferAndInventoryLocationTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $mainOutlet;

    private Outlet $otherOutlet;

    private Warehouse $warehouse;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mainOutlet = Outlet::create(['name' => 'Outlet Utama', 'code' => 'OU', 'is_active' => true]);
        $this->otherOutlet = Outlet::create(['name' => 'Outlet Cabang', 'code' => 'OC', 'is_active' => true]);
        Outlet::create(['name' => 'Outlet Tutup', 'code' => 'OT', 'is_active' => false]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang Utama', 'code' => 'GU', 'is_active' => true]);
        Warehouse::create(['name' => 'Gudang Nonaktif', 'code' => 'GN', 'is_active' => false]);

        $this->owner = $this->makeUser('owner', Role::create(['name' => 'Owner', 'code' => 'owner']), [$this->mainOutlet]);
    }

    // ---- Transfer create (regression: was 500 due to buildLocationOptions() without $user) -------

    public function test_full_access_user_can_open_transfer_create_with_all_active_locations(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backoffice.transfers.create'));

        $response->assertOk()
            ->assertSee('Gudang – Gudang Utama')
            ->assertSee('Outlet – Outlet Utama')
            ->assertSee('Outlet – Outlet Cabang')
            ->assertDontSee('Gudang Nonaktif')
            ->assertDontSee('Outlet Tutup');

        $this->assertSame(
            ['warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id, 'outlet:'.$this->mainOutlet->id],
            collect($response->viewData('locationOptions'))->pluck('value')->all()
        );
    }

    public function test_limited_user_transfer_create_only_offers_accessible_outlets(): void
    {
        $admin = $this->makeUser('admin-ou', Role::create(['name' => 'Admin Outlet', 'code' => 'admin_outlet']), [$this->mainOutlet]);

        $response = $this->actingAs($admin)->get(route('backoffice.transfers.create'));

        $response->assertOk()
            ->assertSee('Gudang – Gudang Utama')
            ->assertSee('Outlet – Outlet Utama')
            ->assertDontSee('Outlet Cabang');

        $this->assertSame(
            ['warehouse:'.$this->warehouse->id, 'outlet:'.$this->mainOutlet->id],
            collect($response->viewData('locationOptions'))->pluck('value')->all()
        );
    }

    public function test_transfer_create_keeps_warehouse_prefill_from_warehouse_page(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backoffice.transfers.create', [
            'from_location_type' => 'warehouse',
            'from_location_id' => $this->warehouse->id,
        ]));

        $response->assertOk();
        $this->assertSame('warehouse:'.$this->warehouse->id, $response->viewData('prefillFromLocation'));
    }

    public function test_role_without_backoffice_access_cannot_open_transfer_create(): void
    {
        $cashier = $this->makeUser('kasir', Role::create(['name' => 'Kasir', 'code' => 'kasir']), [$this->mainOutlet]);

        $this->actingAs($cashier)->get(route('backoffice.transfers.create'))->assertForbidden();
    }

    // ---- Inventory Control location filter ------------------------------------------------------

    public function test_inventory_control_for_a_limited_user_only_offers_their_outlet_and_the_warehouse(): void
    {
        $admin = $this->limitedUser('admin-ou', $this->mainOutlet);

        $response = $this->actingAs($admin)->get(route('backoffice.stock-balances.index'));

        $response->assertOk()
            ->assertSee('Outlet – Outlet Utama')
            ->assertSee('value="warehouse:'.$this->warehouse->id.'"', false)
            ->assertDontSee('value="outlet:'.$this->otherOutlet->id.'"', false);
    }

    public function test_inventory_control_for_all_outlets_still_offers_warehouses(): void
    {
        $response = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index'));

        $response->assertOk()
            ->assertSee('<th>Lokasi</th>', false)
            ->assertSee('Gudang – Gudang Utama')
            ->assertSee('Outlet – Outlet Utama')
            ->assertSee('Outlet – Outlet Cabang')
            ->assertSee('value="warehouse:'.$this->warehouse->id.'"', false);
    }

    // ---- Transfer status actions: outlet access -----------------------------------------------

    public function test_user_with_access_to_involved_outlet_can_receive_transfer(): void
    {
        $transfer = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id);
        $admin = $this->limitedUser('admin-oc', $this->otherOutlet);

        $this->actingAs($admin)->post(route('backoffice.transfers.mark-received', $transfer))
            ->assertRedirect(route('backoffice.transfers.index'));

        $this->assertSame('received', $transfer->fresh()->status);
    }

    public function test_user_of_other_outlet_cannot_receive_transfer(): void
    {
        $transfer = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id);
        $before = $this->stockSnapshot();

        $this->actingAs($this->limitedUser('admin-ou', $this->mainOutlet))
            ->post(route('backoffice.transfers.mark-received', $transfer))
            ->assertForbidden();

        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->received_at);
        $this->assertSame($before, $this->stockSnapshot());
    }

    public function test_user_of_other_outlet_cannot_cancel_transfer(): void
    {
        $transfer = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id);
        $before = $this->stockSnapshot();

        $this->actingAs($this->limitedUser('admin-ou', $this->mainOutlet))
            ->post(route('backoffice.transfers.mark-cancelled', $transfer))
            ->assertForbidden();

        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame($before, $this->stockSnapshot());
    }

    public function test_user_of_other_outlet_cannot_reopen_cancelled_transfer(): void
    {
        $transfer = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.mark-cancelled', $transfer));
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $before = $this->stockSnapshot();

        $this->actingAs($this->limitedUser('admin-ou', $this->mainOutlet))
            ->post(route('backoffice.transfers.mark-in-transit', $transfer))
            ->assertForbidden();

        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame($before, $this->stockSnapshot());
    }

    public function test_limited_user_cannot_act_on_transfer_between_outlets_they_cannot_access_or_warehouses_only(): void
    {
        $third = Outlet::create(['name' => 'Outlet Ketiga', 'code' => 'OK3', 'is_active' => true]);
        $outletToOutlet = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.mark-received', $outletToOutlet));
        $between = $this->sendTransfer('outlet:'.$this->otherOutlet->id, 'outlet:'.$third->id);

        $secondWarehouse = Warehouse::create(['name' => 'Gudang Kedua', 'code' => 'GK', 'is_active' => true]);
        $warehouseOnly = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'warehouse:'.$secondWarehouse->id);
        $before = $this->stockSnapshot();

        $admin = $this->limitedUser('admin-ou', $this->mainOutlet);
        foreach ([$between, $warehouseOnly] as $transfer) {
            $this->actingAs($admin)->post(route('backoffice.transfers.mark-received', $transfer))->assertForbidden();
            $this->actingAs($admin)->post(route('backoffice.transfers.mark-cancelled', $transfer))->assertForbidden();
            $this->assertSame('in_transit', $transfer->fresh()->status);
        }
        $this->assertSame($before, $this->stockSnapshot());
    }

    public function test_limited_user_with_access_to_either_side_of_outlet_transfer_can_act_on_it(): void
    {
        // Same scope as Transfer Index with an Active Outlet: a transfer is visible (and now
        // actionable) for an outlet when it is either the source or the destination.
        $transfer = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->mainOutlet->id);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.mark-received', $transfer));
        $outbound = $this->sendTransfer('outlet:'.$this->mainOutlet->id, 'outlet:'.$this->otherOutlet->id, 5);

        $this->actingAs($this->limitedUser('admin-ou', $this->mainOutlet))
            ->post(route('backoffice.transfers.mark-cancelled', $outbound))
            ->assertRedirect(route('backoffice.transfers.index'));

        $this->assertSame('cancelled', $outbound->fresh()->status);
    }

    public function test_owner_keeps_global_transfer_actions_including_warehouse_only_transfers(): void
    {
        $secondWarehouse = Warehouse::create(['name' => 'Gudang Kedua', 'code' => 'GK', 'is_active' => true]);
        $toOutlet = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id, 10);
        $warehouseOnly = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'warehouse:'.$secondWarehouse->id, 20);

        // The owner is not involved in either transfer as an outlet; existing behavior still applies.
        $this->actingAs($this->owner);

        $this->post(route('backoffice.transfers.mark-cancelled', $toOutlet))->assertRedirect(route('backoffice.transfers.index'));
        $this->assertSame('cancelled', $toOutlet->fresh()->status);
        $this->assertSame(100.0 - 20.0, $this->qty('warehouse', $this->warehouse->id));

        $this->post(route('backoffice.transfers.mark-in-transit', $toOutlet))->assertRedirect(route('backoffice.transfers.index'));
        $this->assertSame('in_transit', $toOutlet->fresh()->status);
        $this->assertSame(10.0, $this->qty('outlet', $this->otherOutlet->id));

        $this->post(route('backoffice.transfers.mark-received', $toOutlet))->assertRedirect(route('backoffice.transfers.index'));
        $this->assertSame('received', $toOutlet->fresh()->status);

        $this->post(route('backoffice.transfers.mark-received', $warehouseOnly))->assertRedirect(route('backoffice.transfers.index'));
        $this->assertSame('received', $warehouseOnly->fresh()->status);
    }

    // ---- Transfer Index + Export: outlet isolation --------------------------------------------

    public function test_transfer_index_and_export_are_scoped_to_limited_users_outlets(): void
    {
        $t = $this->transferMatrix();
        $admin = $this->limitedUser('admin-ou', $this->mainOutlet);

        $expected = [$t['warehouseToMine'], $t['mineToOther'], $t['legacyMine']];
        sort($expected);

        // A-D: "Semua Outlet" (no Active Outlet) for a limited user.
        $this->actingAs($admin);
        $this->assertSame($expected, $this->indexTransferIds());
        $this->assertSame($expected, $this->exportTransferIds());   // F: same dataset as the index
    }

    public function test_limited_user_with_two_outlets_sees_exactly_those_and_a_leftover_selection_changes_nothing(): void
    {
        $t = $this->transferMatrix();
        $admin = $this->limitedUser('admin-two', $this->mainOutlet, [$this->otherOutlet]);

        $this->actingAs($admin);
        $all = [$t['warehouseToMine'], $t['warehouseToOther'], $t['mineToOther'], $t['otherToThird'], $t['legacyMine']];
        sort($all);
        $this->assertSame($all, $this->indexTransferIds());
        $this->assertSame($all, $this->exportTransferIds());

        // A selection left in an old session (own outlet, or one the user cannot access) is ignored:
        // it neither narrows nor widens anything.
        foreach ([$this->otherOutlet->id, $t['thirdOutletId']] as $leftover) {
            $this->withSession(['active_backoffice_outlet_id' => $leftover]);
            $this->assertSame($all, $this->indexTransferIds());
            $this->assertSame($all, $this->exportTransferIds());
        }
    }

    public function test_owner_still_sees_every_transfer_in_index_and_export(): void
    {
        $t = $this->transferMatrix();
        $everything = StockTransfer::orderBy('id')->pluck('id')->all();

        // E: global role, every outlet.
        $this->actingAs($this->owner);
        $this->assertSame($everything, $this->indexTransferIds());
        $this->assertSame($everything, $this->exportTransferIds());
        $this->assertContains($t['warehouseToWarehouse'], $everything);

        // A selection left in an old session does not narrow the owner's view either.
        $this->withSession(['active_backoffice_outlet_id' => $this->otherOutlet->id]);
        $this->assertSame($everything, $this->indexTransferIds());
        $this->assertSame($everything, $this->exportTransferIds());
    }

    // ---- available-ingredients: outlet access ------------------------------------------------

    public function test_available_ingredients_enforces_outlet_access_but_keeps_warehouses_global(): void
    {
        $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->mainOutlet->id, 10);
        $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id, 15);
        $admin = $this->limitedUser('admin-ou', $this->mainOutlet);
        $before = $this->stockSnapshot();

        $this->actingAs($admin);
        $this->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'outlet:'.$this->mainOutlet->id]))
            ->assertOk()->assertJsonPath('items.0.name', 'Transfer Milk')->assertJsonPath('items.0.stock', 10);
        $this->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'warehouse:'.$this->warehouse->id]))
            ->assertOk()->assertJsonPath('items.0.stock', 75);
        $this->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'outlet:'.$this->otherOutlet->id]))
            ->assertForbidden()->assertJsonMissingPath('items');
        $this->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'outlet:999']))
            ->assertForbidden();
        $this->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'bogus']))
            ->assertStatus(422);

        $this->assertSame($before, $this->stockSnapshot());
    }

    public function test_owner_can_read_available_ingredients_for_any_location(): void
    {
        $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id, 15);

        $this->actingAs($this->owner)
            ->getJson(route('backoffice.transfers.available-ingredients', ['location' => 'outlet:'.$this->otherOutlet->id]))
            ->assertOk()->assertJsonPath('items.0.stock', 15);
    }

    /**
     * One transfer of each shape, created through the real store endpoint (plus one legacy row
     * written the way WarehouseTransferViewController does, without from/to location columns).
     */
    private function transferMatrix(): array
    {
        $third = Outlet::create(['name' => 'Outlet Ketiga', 'code' => 'OK3', 'is_active' => true]);
        $secondWarehouse = Warehouse::create(['name' => 'Gudang Kedua', 'code' => 'GK', 'is_active' => true]);

        $warehouseToMine = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->mainOutlet->id, 20);
        $warehouseToOther = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$this->otherOutlet->id, 20);
        $warehouseToThird = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'outlet:'.$third->id, 20);
        $mineToOther = $this->sendTransfer('outlet:'.$this->mainOutlet->id, 'outlet:'.$this->otherOutlet->id, 5);
        $otherToThird = $this->sendTransfer('outlet:'.$this->otherOutlet->id, 'outlet:'.$third->id, 5);
        $warehouseToWarehouse = $this->sendTransfer('warehouse:'.$this->warehouse->id, 'warehouse:'.$secondWarehouse->id, 5);

        $legacy = fn (Outlet $outlet) => StockTransfer::create([
            'warehouse_id' => $this->warehouse->id, 'outlet_id' => $outlet->id,
            'ingredient_id' => $this->transferIngredient()->id, 'qty' => 1, 'status' => 'completed',
        ])->id;

        return [
            'thirdOutletId' => $third->id,
            'warehouseToMine' => $warehouseToMine->id,
            'warehouseToOther' => $warehouseToOther->id,
            'warehouseToThird' => $warehouseToThird->id,
            'mineToOther' => $mineToOther->id,
            'otherToThird' => $otherToThird->id,
            'warehouseToWarehouse' => $warehouseToWarehouse->id,
            'legacyMine' => $legacy($this->mainOutlet),
            'legacyThird' => $legacy($third),
        ];
    }

    private function indexTransferIds(): array
    {
        $ids = collect($this->get(route('backoffice.transfers.index'))->assertOk()->viewData('transfers'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($ids);

        return $ids;
    }

    private function exportTransferIds(): array
    {
        $csv = $this->get(route('backoffice.transfers.export.csv'))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(preg_split("/\r\n|\n/", trim($csv))));
        $header = array_shift($rows);
        $numberColumn = array_search('transfer_number', $header, true);
        $numbers = array_column($rows, $numberColumn);

        $ids = StockTransfer::whereIn('transfer_number', $numbers)->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($ids);
        $this->assertCount(count($numbers), $ids, 'Every exported row must map to exactly one transfer.');

        return $ids;
    }

    private function sendTransfer(string $from, string $to, float $qty = 10): StockTransfer
    {
        $ingredient = $this->transferIngredient();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), [
            'operation_key' => (string) Str::uuid(),
            'from_location' => $from,
            'to_location' => $to,
            'sender_name' => 'Owner',
            'receiver_name' => '',
            'items' => [['ingredient_id' => $ingredient->id, 'qty' => $qty]],
        ])->assertRedirect(route('backoffice.transfers.index'));

        return StockTransfer::latest('id')->firstOrFail();
    }

    private function transferIngredient(): Ingredient
    {
        $existing = Ingredient::where('name', 'Transfer Milk')->first();
        if ($existing) {
            return $existing;
        }

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => 'Transfer Milk', 'code' => 'TRANSFER_MILK',
            'unit' => 'ml', 'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'qty_on_hand' => 100]);

        return $ingredient;
    }

    private function limitedUser(string $username, Outlet $outlet, array $moreOutlets = []): User
    {
        $role = Role::firstOrCreate(['code' => 'admin_outlet'], ['name' => 'Admin Outlet']);

        return $this->makeUser($username, $role, [$outlet, ...$moreOutlets]);
    }

    private function qty(string $type, int $id): float
    {
        return (float) StockBalance::where('location_type', $type)->where('location_id', $id)->value('qty_on_hand');
    }

    private function stockSnapshot(): array
    {
        return [
            'balances' => StockBalance::orderBy('id')->get(['id', 'location_type', 'location_id', 'qty_on_hand'])->toArray(),
            'movements' => StockMovement::count(),
            'transfers' => StockTransfer::orderBy('id')->get(['id', 'status', 'received_at'])->toArray(),
        ];
    }

    private function makeUser(string $username, Role $role, array $outlets): User
    {
        $user = User::create([
            'name' => $username, 'username' => $username, 'email' => $username.'@example.test',
            'password' => 'password', 'role_id' => $role->id, 'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }
}
