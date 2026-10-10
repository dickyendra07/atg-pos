<?php

namespace Tests\Feature;

use App\Exceptions\StockAdjustmentVoidException;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\StockAdjustment;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentVoidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * Stock Adjustment VOID (PR #31). SQLite in memory: the logic, validation, authorization, audit trail and
 * reporting. Exactly-once under real concurrent sessions is proven separately on MySQL in
 * StockAdjustmentVoidRealEngineTest; nothing in this file claims to prove row locking.
 */
class StockAdjustmentVoidTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Outlet $outlet2;

    private Warehouse $warehouse;

    private User $owner;

    private User $adminPusat;

    private User $adminOutlet;

    private User $staffGudang;

    private User $kasir;

    private Ingredient $gula;

    private Ingredient $susu;

    private Ingredient $teh;

    protected function setUp(): void
    {
        parent::setUp();

        // Outlet id 1 and Warehouse id 1 on purpose: the same number in two different places.
        $this->outlet = Outlet::create(['name' => 'Outlet Satu', 'code' => 'O1', 'is_active' => true]);
        $this->outlet2 = Outlet::create(['name' => 'Outlet Dua', 'code' => 'O2', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang Pusat', 'code' => 'W1', 'is_active' => true]);
        $this->assertSame(1, $this->outlet->id);
        $this->assertSame(1, $this->warehouse->id);

        $this->owner = $this->makeUser('owner', 'owner', [$this->outlet, $this->outlet2]);
        $this->adminPusat = $this->makeUser('admin_pusat', 'admin_pusat', [$this->outlet, $this->outlet2]);
        $this->adminOutlet = $this->makeUser('admin_outlet', 'admin_outlet', [$this->outlet]);
        $this->staffGudang = $this->makeUser('staff_gudang', 'staff_gudang', [$this->outlet]);
        $this->kasir = $this->makeUser('kasir', 'kasir', [$this->outlet]);

        $category = IngredientCategory::create(['name' => 'Bahan', 'code' => 'BHN', 'is_active' => true]);
        $this->gula = $this->makeIngredient($category, 'Gula', 'gram');
        $this->susu = $this->makeIngredient($category, 'Susu', 'ml');
        $this->teh = $this->makeIngredient($category, 'Teh', 'gram');
    }

    // ---- 1-4: the four shapes of adjustment ----------------------------------------------------------

    public function test_voiding_a_positive_adjustment_reverses_only_its_own_delta(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->assertSame('15.00', $this->balance($this->gula, 'outlet', $this->outlet->id));

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertRedirect(route('backoffice.stock-adjustments.show', $adjustment));

        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $reversal = StockMovement::where('reference_type', 'manual_adjustment_void')->sole();
        $this->assertSame(['stock_adjustment', '0.00', '5.00', $adjustment->id], [$reversal->movement_type, $this->dec($reversal->getRawOriginal('qty_in')), $this->dec($reversal->getRawOriginal('qty_out')), (int) $reversal->reference_id]);
        $this->assertSame('void', $adjustment->fresh()->status);
    }

    public function test_voiding_a_negative_adjustment_puts_the_stock_back(): void
    {
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 100);
        $adjustment = $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 60]);
        $this->assertSame('60.00', $this->balance($this->gula, 'warehouse', $this->warehouse->id));

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame('100.00', $this->balance($this->gula, 'warehouse', $this->warehouse->id));
        $reversal = StockMovement::where('reference_type', 'manual_adjustment_void')->sole();
        $this->assertSame(['40.00', '0.00'], [$this->dec($reversal->getRawOriginal('qty_in')), $this->dec($reversal->getRawOriginal('qty_out'))]);
    }

    public function test_a_zero_difference_adjustment_is_voided_without_any_movement(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 10]);
        $this->assertSame(0, StockMovement::where('movement_type', 'stock_adjustment')->count());
        $movementsBefore = StockMovement::count();

        $this->voidAs($this->owner, $adjustment, 'Dobel input');

        $this->assertSame('void', $adjustment->fresh()->status);
        $this->assertSame($movementsBefore, StockMovement::count(), 'no movement is created for a zero difference');
        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $this->assertNull($adjustment->items()->sole()->void_stock_movement_id);
    }

    public function test_voiding_a_multi_ingredient_adjustment_reverses_every_item(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $this->seed1('outlet', $this->outlet->id, $this->susu, 50);
        $this->seed1('outlet', $this->outlet->id, $this->teh, 7);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15, $this->susu->id => 20, $this->teh->id => 7]);

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame(['10.00', '50.00', '7.00'], [
            $this->balance($this->gula, 'outlet', $this->outlet->id), $this->balance($this->susu, 'outlet', $this->outlet->id), $this->balance($this->teh, 'outlet', $this->outlet->id),
        ]);
        $this->assertSame(2, StockMovement::where('reference_type', 'manual_adjustment_void')->count(), 'the zero-difference item has no reversal');
        $this->assertSame(1, $adjustment->items()->whereNull('void_stock_movement_id')->count());
    }

    // ---- 5, 9, 10, 11, 32: all-or-nothing, and later activity is accounted for ----------------------

    public function test_one_failing_ingredient_blocks_the_whole_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $this->seed1('outlet', $this->outlet->id, $this->susu, 50);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15, $this->susu->id => 20]);
        // Gula (+5) can no longer be reversed after consumption; Susu (-30) alone would be fine.
        $this->consume($this->gula, 'outlet', $this->outlet->id, 14);
        $snapshot = $this->snapshot();

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('error');

        $this->assertSame($snapshot, $this->snapshot(), 'nothing changed, not even the item that was fine');
        $this->assertSame('completed', $adjustment->fresh()->status);
    }

    public function test_a_later_sale_stays_accounted_for_and_the_balance_is_never_reset(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 3); // 15 -> 12

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame('7.00', $this->balance($this->gula, 'outlet', $this->outlet->id), 'the original +5 is reversed: 12 - 5, not reset to 10');
    }

    public function test_a_later_transfer_stays_accounted_for(): void
    {
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 10);
        $adjustment = $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 15]);
        $this->actingAs($this->owner)->post(route('backoffice.warehouse-transfers.store', $this->warehouse), [
            'outlet_id' => $this->outlet->id, 'ingredient_id' => $this->gula->id, 'qty' => 8, 'note' => '',
        ])->assertRedirect();
        $this->assertSame('7.00', $this->balance($this->gula, 'warehouse', $this->warehouse->id));

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame('2.00', $this->balance($this->gula, 'warehouse', $this->warehouse->id));
        $this->assertSame('8.00', $this->balance($this->gula, 'outlet', $this->outlet->id), 'the destination is untouched');
    }

    public function test_insufficient_stock_blocks_the_void_and_changes_nothing(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 12); // 15 -> 3, reversing +5 would give -2
        $snapshot = $this->snapshot();

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('error');

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame('3.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
    }

    public function test_exactly_enough_stock_is_allowed_down_to_zero_but_never_below(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 10); // 15 -> 5 == the delta to reverse

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame('0.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $this->assertSame('void', $adjustment->fresh()->status);
    }

    public function test_a_balance_that_is_already_negative_can_still_be_raised_by_a_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 5]); // -5
        $this->consume($this->gula, 'outlet', $this->outlet->id, 8); // sales may take outlet stock below zero: 5 -> -3
        $this->assertSame('-3.00', $this->balance($this->gula, 'outlet', $this->outlet->id));

        $this->voidAs($this->owner, $adjustment, 'Salah hitung'); // +5 -> 2

        $this->assertSame('2.00', $this->balance($this->gula, 'outlet', $this->outlet->id), 'a void that adds stock does not cause a negative balance');
    }

    // ---- 6, 7, 24: repeated, stale, and reason --------------------------------------------------------

    public function test_a_double_submit_creates_one_reversal_only(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('success');
        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('error');

        $this->assertSame(1, StockMovement::where('reference_type', 'manual_adjustment_void')->count());
        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
    }

    public function test_a_stale_model_cannot_void_twice(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $stale = StockAdjustment::findOrFail($adjustment->id); // loaded while still 'completed'
        $service = app(StockAdjustmentVoidService::class);

        $service->void($adjustment, $this->owner, 'Pertama');

        $this->assertSame('completed', $stale->status, 'the stale instance still believes it is completed');
        try {
            $service->void($stale, $this->owner, 'Kedua');
            $this->fail('The second VOID must be refused.');
        } catch (StockAdjustmentVoidException $e) {
            $this->assertTrue($e->alreadyVoid);
        }

        $this->assertSame(1, StockMovement::where('reference_type', 'manual_adjustment_void')->count());
        $this->assertSame('Pertama', $adjustment->fresh()->void_reason);
        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
    }

    public function test_a_void_reason_is_required(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $snapshot = $this->snapshot();

        foreach (['', '   ', null] as $reason) {
            $this->from(route('backoffice.stock-adjustments.show', $adjustment))
                ->actingAs($this->owner)
                ->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => $reason, 'confirm' => '1'])
                ->assertSessionHasErrors('void_reason');
        }

        $this->from(route('backoffice.stock-adjustments.show', $adjustment))
            ->actingAs($this->owner)
            ->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => 'Alasan'])
            ->assertSessionHasErrors('confirm');

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame('completed', $adjustment->fresh()->status);

        $this->expectException(StockAdjustmentVoidException::class);
        app(StockAdjustmentVoidService::class)->void($adjustment, $this->owner, "  \n ");
    }

    // ---- 12: newer adjustments ------------------------------------------------------------------------

    public function test_a_newer_active_adjustment_for_the_same_ingredient_and_location_blocks_the_old_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $first = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $second = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 12]);
        $snapshot = $this->snapshot();

        $this->voidAs($this->owner, $first, 'Salah hitung')->assertSessionHas('error');
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertStringContainsString($second->reference, session('error'));

        // Last in, first out: voiding the newer one frees the older one.
        $this->voidAs($this->owner, $second, 'Salah hitung')->assertSessionHas('success');
        $this->assertSame('15.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $this->voidAs($this->owner, $first, 'Salah hitung')->assertSessionHas('success');
        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
    }

    public function test_a_newer_zero_difference_recount_also_blocks_the_old_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $first = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]); // confirms the count: zero difference

        $this->voidAs($this->owner, $first, 'Salah hitung')->assertSessionHas('error');

        $this->assertSame('completed', $first->fresh()->status);
    }

    public function test_a_newer_adjustment_elsewhere_or_for_another_ingredient_does_not_block(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 10); // same ingredient, location id 1 again
        $this->seed1('outlet', $this->outlet->id, $this->susu, 10);
        $first = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 99]); // warehouse #1 != outlet #1
        $this->adjust('outlet', $this->outlet->id, [$this->susu->id => 99]);

        $this->voidAs($this->owner, $first, 'Salah hitung')->assertSessionHas('success');

        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $this->assertSame('99.00', $this->balance($this->gula, 'warehouse', $this->warehouse->id));
    }

    // ---- 13-16, 19, 20: integrity of the original data -------------------------------------------------

    public function test_missing_original_movement_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_adjustment_items')->where('stock_adjustment_id', $adjustment->id)->update(['stock_movement_id' => null]);

        $this->assertBlocked($adjustment, 'movement asli tidak ditemukan');
    }

    public function test_a_deleted_original_movement_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        // What a reset or manual cleanup leaves behind: the foreign key (nullOnDelete) clears the item's link.
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->delete();
        $this->assertNull($adjustment->items()->sole()->stock_movement_id);

        $this->assertBlocked($adjustment, 'movement asli tidak ditemukan');
    }

    public function test_a_movement_pointing_at_another_adjustment_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->update(['reference_id' => $adjustment->id + 50]);

        $this->assertBlocked($adjustment, 'tidak merujuk ke adjustment ini');
    }

    public function test_a_movement_of_another_reference_type_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->update(['reference_type' => 'stock_opname']);

        $this->assertBlocked($adjustment, 'tidak merujuk ke adjustment ini');
    }

    public function test_a_movement_of_another_ingredient_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->update(['ingredient_id' => $this->susu->id]);

        $this->assertBlocked($adjustment, 'movement asli milik ingredient lain');
    }

    public function test_a_movement_at_another_location_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        // Same id, different type: the id alone must never be trusted.
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->update(['location_type' => 'warehouse']);

        $this->assertBlocked($adjustment, 'movement asli milik lokasi lain');
    }

    public function test_a_movement_with_the_wrong_quantity_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->update(['qty_in' => '4.99']);

        $this->assertBlocked($adjustment, 'qty movement asli tidak sama dengan selisih item');
    }

    public function test_an_item_whose_difference_does_not_add_up_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_adjustment_items')->where('stock_adjustment_id', $adjustment->id)->update(['difference' => '6.00']);

        $this->assertBlocked($adjustment, 'selisih item tidak sama dengan aktual dikurangi sistem');
    }

    public function test_a_movement_shared_by_two_items_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $movementId = $adjustment->items()->sole()->stock_movement_id;
        $other = StockAdjustment::create(['reference' => 'ADJ-OTHER-0001', 'location_type' => 'outlet', 'location_id' => $this->outlet2->id, 'user_id' => $this->owner->id]);
        $other->items()->create(['ingredient_id' => $this->susu->id, 'stock_movement_id' => $movementId, 'unit' => 'ml', 'system_qty' => 0, 'actual_qty' => 5, 'difference' => 5]);

        $this->assertBlocked($adjustment, 'movement asli dipakai item lain');
    }

    public function test_a_zero_difference_item_that_owns_a_movement_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_adjustment_items')->where('stock_adjustment_id', $adjustment->id)->update(['system_qty' => '15.00', 'difference' => '0.00']);

        $this->assertBlocked($adjustment, 'item tanpa selisih tetapi punya movement');
    }

    public function test_a_missing_balance_row_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        DB::table('stock_balances')->delete();

        $this->assertBlocked($adjustment, 'saldo stok di lokasi ini tidak ditemukan');
    }

    public function test_a_soft_deleted_ingredient_blocks_the_void(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->gula->delete(); // tombstoned by the cleanup flow: history is kept, the master data is gone

        $this->assertBlocked($adjustment, 'ingredient sudah dihapus dari sistem');
        $this->assertSame('15.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
    }

    public function test_an_inactive_outlet_is_not_voidable_and_stays_as_unreachable_as_before(): void
    {
        $inactive = Outlet::create(['name' => 'Outlet Tutup', 'code' => 'OT', 'is_active' => false]);
        $adjustment = $this->legacyAdjustment('outlet', $inactive->id, $this->gula, 10, 15);
        $snapshot = $this->snapshot();

        // Reading is unchanged (the existing rule hides inactive outlets even from the owner), VOID follows the same rule.
        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertForbidden();
        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertForbidden();

        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_an_inactive_warehouse_blocks_the_void(): void
    {
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 10);
        $adjustment = $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 15]);
        $this->warehouse->update(['is_active' => false]);

        $this->assertBlocked($adjustment, 'tidak ditemukan atau tidak aktif');
    }

    public function test_a_legacy_movement_without_an_adjustment_document_is_not_voidable(): void
    {
        // Stock opname writes stock_adjustment movements with no StockAdjustment at all: there is nothing to void.
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 10);
        $balanceId = StockBalance::where('ingredient_id', $this->gula->id)->value('id');
        $this->actingAs($this->owner)->post(route('backoffice.stock-balances.opname.store'), [
            'warehouse_id' => $this->warehouse->id, 'stock_balance_id' => $balanceId, 'physical_qty' => 4, 'note' => 'Opname',
        ])->assertRedirect();
        $this->assertSame(1, StockMovement::where('reference_type', 'stock_opname')->count());
        $this->assertSame(0, StockAdjustment::count());

        $this->actingAs($this->owner)->post(route('backoffice.stock-adjustments.void', 1), ['void_reason' => 'x', 'confirm' => '1'])->assertNotFound();
    }

    // ---- 17, 18, 28, 29: traceability, composite location, and nothing historical is rewritten ----------

    public function test_original_and_reversal_are_linked_and_the_original_is_untouched(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $item = $adjustment->items()->sole();
        $originalMovement = DB::table('stock_movements')->where('id', $item->stock_movement_id)->first();
        $originalItem = DB::table('stock_adjustment_items')->where('id', $item->id)->first();
        $originalAdjustment = DB::table('stock_adjustments')->where('id', $adjustment->id)->first();
        $movementCount = StockMovement::count();

        $this->voidAs($this->owner, $adjustment, 'Hitung ulang salah');

        $this->assertEquals($originalMovement, DB::table('stock_movements')->where('id', $item->stock_movement_id)->first(), 'original movement byte-for-byte unchanged');
        $after = DB::table('stock_adjustment_items')->where('id', $item->id)->first();
        $this->assertEquals(
            collect($originalItem)->except(['void_stock_movement_id', 'updated_at'])->all(),
            collect($after)->except(['void_stock_movement_id', 'updated_at'])->all(),
            'item quantities and original movement link unchanged'
        );
        $afterAdjustment = DB::table('stock_adjustments')->where('id', $adjustment->id)->first();
        foreach (['reference', 'location_type', 'location_id', 'user_id', 'note', 'created_at'] as $column) {
            $this->assertEquals($originalAdjustment->$column, $afterAdjustment->$column, "{$column} unchanged");
        }
        $this->assertSame($movementCount + 1, StockMovement::count(), 'exactly one new movement, none deleted');

        $reversal = StockMovement::findOrFail($after->void_stock_movement_id);
        $this->assertNotSame($item->stock_movement_id, $reversal->id);
        $this->assertSame(['manual_adjustment_void', $adjustment->id, $this->gula->id, 'outlet', $this->outlet->id], [
            $reversal->reference_type, (int) $reversal->reference_id, $reversal->ingredient_id, $reversal->location_type, (int) $reversal->location_id,
        ]);
        $this->assertSame('VOID '.$adjustment->reference.' | Original MOV-'.$item->stock_movement_id.' | Reason: Hitung ulang salah', $reversal->note);
        $this->assertSame([$this->owner->id, 'Hitung ulang salah'], [$adjustment->fresh()->void_by_user_id, $adjustment->fresh()->void_reason]);
        $this->assertNotNull($adjustment->fresh()->void_at);
    }

    public function test_voiding_a_warehouse_adjustment_never_touches_the_outlet_with_the_same_id(): void
    {
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 100);
        $this->seed1('outlet', $this->outlet->id, $this->gula, 40);
        $warehouseAdjustment = $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 60]);
        $this->assertSame(1, (int) $warehouseAdjustment->location_id);

        $this->voidAs($this->owner, $warehouseAdjustment, 'Salah hitung');

        $this->assertSame('100.00', $this->balance($this->gula, 'warehouse', 1));
        $this->assertSame('40.00', $this->balance($this->gula, 'outlet', 1), 'outlet #1 is not warehouse #1');
        $this->assertSame(['warehouse', 1], [StockMovement::where('reference_type', 'manual_adjustment_void')->sole()->location_type, 1]);
    }

    public function test_decimal_quantities_are_reversed_exactly(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, '10.10');
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => '10.30']); // +0.20
        $this->consume($this->gula, 'outlet', $this->outlet->id, '0.10'); // 10.20

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $this->assertSame('10.00', $this->balance($this->gula, 'outlet', $this->outlet->id));
        $reversal = StockMovement::where('reference_type', 'manual_adjustment_void')->sole();
        $this->assertSame(['0.00', '0.20'], [$this->dec($reversal->getRawOriginal('qty_in')), $this->dec($reversal->getRawOriginal('qty_out'))]);
    }

    // ---- 21-23: authorization ---------------------------------------------------------------------------

    public function test_owner_and_admin_pusat_can_void(): void
    {
        foreach ([$this->owner, $this->adminPusat] as $n => $user) {
            $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15 + $n], $this->seed1('outlet', $this->outlet->id, $this->gula, 10));

            $this->voidAs($user, $adjustment, 'Salah hitung')->assertSessionHas('success');

            $this->assertSame('void', $adjustment->fresh()->status);
            $this->assertSame($user->id, $adjustment->fresh()->void_by_user_id);
        }
    }

    public function test_every_other_role_gets_403_and_changes_nothing(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $this->seed1('warehouse', $this->warehouse->id, $this->gula, 10);
        $outletAdjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $warehouseAdjustment = $this->adjust('warehouse', $this->warehouse->id, [$this->gula->id => 15]);
        $snapshot = $this->snapshot();

        foreach ([$this->adminOutlet, $this->staffGudang, $this->kasir] as $user) {
            foreach ([$outletAdjustment, $warehouseAdjustment] as $adjustment) {
                $this->voidAs($user, $adjustment, 'Coba void')->assertForbidden();
            }
        }

        $this->assertSame($snapshot, $this->snapshot());
        $this->assertSame(0, StockAdjustment::where('status', 'void')->count());
    }

    public function test_a_forged_or_guessed_request_cannot_bypass_the_checks(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $snapshot = $this->snapshot();

        // Forged "everything is fine" fields do not matter to a forbidden role.
        $this->actingAs($this->adminOutlet)->post(route('backoffice.stock-adjustments.void', $adjustment), [
            'void_reason' => 'x', 'confirm' => '1', 'status' => 'void', 'void_by_user_id' => $this->owner->id,
        ])->assertForbidden();
        // The state change is POST only.
        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.void', $adjustment))->assertStatus(405);
        // Not signed in.
        auth()->logout();
        $this->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => 'x', 'confirm' => '1'])->assertRedirect();
        // Mass assignment of the status columns is not possible.
        $this->assertSame([], array_intersect(['status', 'void_at', 'void_reason', 'void_by_user_id'], (new StockAdjustment)->getFillable()));

        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_the_action_is_visible_only_to_roles_that_may_use_it(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $action = route('backoffice.stock-adjustments.void', $adjustment);

        foreach ([$this->owner, $this->adminPusat] as $user) {
            $this->actingAs($user)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk()->assertSee($action, false)->assertSee('VOID Adjustment');
        }
        // Existing read access is unchanged, without the action.
        foreach ([$this->adminOutlet, $this->staffGudang] as $user) {
            $this->actingAs($user)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk()->assertDontSee($action, false)->assertDontSee('VOID Adjustment');
        }
    }

    // ---- 25: atomic rollback ----------------------------------------------------------------------------

    public function test_a_failure_halfway_rolls_everything_back(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $this->seed1('outlet', $this->outlet->id, $this->susu, 50);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15, $this->susu->id => 20]);
        $snapshot = $this->snapshot();

        $writes = 0;
        Event::listen('eloquent.creating: '.StockMovement::class, function (StockMovement $movement) use (&$writes) {
            if ($movement->reference_type === 'manual_adjustment_void' && ++$writes === 2) {
                throw new RuntimeException('simulated failure on the second reversal');
            }
        });

        try {
            app(StockAdjustmentVoidService::class)->void($adjustment, $this->owner, 'Salah hitung');
            $this->fail('The simulated failure must surface.');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated failure on the second reversal', $e->getMessage());
        }

        $this->assertSame(2, $writes, 'the first reversal really was written before the failure');
        $this->assertSame($snapshot, $this->snapshot(), 'no status change, no balance change, no movement, no link');
        $this->assertSame('completed', $adjustment->fresh()->status);
    }

    // ---- 26, 27, 33, 34: history, detail, no writes, creation unchanged ------------------------------------

    public function test_voided_records_stay_in_history_and_the_detail_shows_the_full_audit_trail(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15], null, 'Catatan awal');
        $other = $this->adjust('outlet', $this->outlet->id, [$this->susu->id => 3]);
        $this->voidAs($this->owner, $adjustment->fresh(), 'Salah hitung fisik');
        $item = $adjustment->items()->sole()->fresh();

        $history = $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.index'))->assertOk();
        $history->assertSee($adjustment->reference)->assertSee($other->reference)->assertSee('VOID')->assertSee('COMPLETED');
        $this->assertSame(2, StockAdjustment::count(), 'nothing was deleted');

        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk()
            ->assertSee($adjustment->reference)
            ->assertSee('Salah hitung fisik')
            ->assertSee($this->owner->name)
            ->assertSee('MOV-'.$item->stock_movement_id)
            ->assertSee('MOV-'.$item->void_stock_movement_id)
            ->assertSee('Catatan awal')
            ->assertSee('Outlet Satu')
            ->assertDontSee(route('backoffice.stock-adjustments.void', $adjustment), false);
    }

    public function test_the_confirmation_panel_shows_impact_current_stock_reversal_and_result(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 3);

        $response = $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk();
        $response->assertSee('Konfirmasi VOID '.$adjustment->reference);
        $preview = $response->viewData('voidPreview');
        $this->assertTrue($preview['can_void']);
        $this->assertSame([500, -500, 1200, 700], [$preview['rows'][0]['original'], $preview['rows'][0]['reversal'], $preview['rows'][0]['current'], $preview['rows'][0]['resulting']]);

        $this->consume($this->gula, 'outlet', $this->outlet->id, 9); // 12 -> 3: the preview now explains why it is blocked
        $blocked = $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment))->viewData('voidPreview');
        $this->assertFalse($blocked['can_void']);
        $this->assertNotEmpty($blocked['blockers']);
    }

    public function test_the_preview_is_not_trusted_the_post_decides(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk()->assertViewHas('voidPreview', fn ($p) => $p['can_void']);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 14); // stock changes after the panel was rendered

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('error');

        $this->assertSame('completed', $adjustment->fresh()->status);
    }

    public function test_opening_history_and_detail_writes_nothing(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $voided = $this->adjust('outlet', $this->outlet->id, [$this->susu->id => 5]);
        $this->voidAs($this->owner, $voided, 'x');
        $snapshot = $this->snapshot();

        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.index'))->assertOk();
        $this->get(route('backoffice.stock-adjustments.show', $adjustment))->assertOk();
        $this->get(route('backoffice.stock-adjustments.show', $voided))->assertOk();

        $this->assertSame([], $writes);
        $this->assertSame($snapshot, $this->snapshot());
    }

    public function test_creating_adjustments_still_works_and_new_ones_are_completed(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $first = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $second = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);

        $this->assertMatchesRegularExpression('/^ADJ-\d{8}-0001$/', $first->reference);
        $this->assertMatchesRegularExpression('/^ADJ-\d{8}-0002$/', $second->reference);
        $this->assertSame(['completed', 'completed'], [$first->fresh()->status, $second->fresh()->status]);
        $this->assertSame(1, StockMovement::where('reference_type', 'manual_adjustment')->count(), 'only the one with a difference has a movement');
        $this->assertNull($first->fresh()->void_at);
    }

    public function test_existing_adjustments_created_before_the_migration_behave_as_completed(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->legacyAdjustment('outlet', $this->outlet->id, $this->gula, 10, 15);

        $this->assertSame('completed', $adjustment->fresh()->status);
        $this->actingAs($this->owner)->get(route('backoffice.stock-adjustments.index'))->assertOk()->assertSee('COMPLETED');
    }

    // ---- 30, 31: Stock Summary -----------------------------------------------------------------------------

    public function test_stock_summary_includes_the_reversal_and_reconciles(): void
    {
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);
        $this->consume($this->gula, 'outlet', $this->outlet->id, 3);

        $before = $this->summaryRow();
        $this->assertEquals(5, $before['adjustment']);
        $this->assertEquals(12, $before['ending_stock']);

        $this->voidAs($this->owner, $adjustment, 'Salah hitung');

        $after = $this->summaryRow();
        $this->assertEquals(0, $after['adjustment'], 'original +5 and reversal -5 net to zero');
        $this->assertEquals(7, $after['ending_stock']);
        $this->assertEquals($this->balance($this->gula, 'outlet', $this->outlet->id), number_format($after['ending_stock'], 2, '.', ''));
        // No date range: opening is the opening_balance movement only, everything else is a bucket.
        $this->assertEquals(10, $after['opening_balance']);
        $this->assertEquals(
            $after['ending_stock'],
            $after['opening_balance'] + $after['purchase'] + $after['transfer'] - $after['sales'] + $after['adjustment']
        );
    }

    public function test_the_original_stays_in_its_period_and_the_reversal_lands_in_the_void_period(): void
    {
        Carbon::setTestNow('2026-10-01 09:00:00');
        $this->seed1('outlet', $this->outlet->id, $this->gula, 10);
        $adjustment = $this->adjust('outlet', $this->outlet->id, [$this->gula->id => 15]);

        Carbon::setTestNow('2026-10-05 09:00:00');
        $this->voidAs($this->owner, $adjustment->fresh(), 'Salah hitung');

        $reversal = StockMovement::where('reference_type', 'manual_adjustment_void')->sole();
        $original = StockMovement::where('reference_type', 'manual_adjustment')->sole();
        $this->assertSame('2026-10-01', $original->created_at->toDateString(), 'the original keeps its own date');
        $this->assertSame('2026-10-05', $reversal->created_at->toDateString(), 'the reversal is posted when the VOID happened');

        $originalOnly = $this->summaryRow(['summary_date_from' => '2026-10-01', 'summary_date_to' => '2026-10-03']);
        $reversalOnly = $this->summaryRow(['summary_date_from' => '2026-10-04', 'summary_date_to' => '2026-10-31']);
        $both = $this->summaryRow(['summary_date_from' => '2026-10-01', 'summary_date_to' => '2026-10-31']);

        $this->assertEquals(5, $originalOnly['adjustment'], 'closed period still shows the original adjustment, unchanged');
        $this->assertEquals(-5, $reversalOnly['adjustment']);
        $this->assertEquals(0, $both['adjustment']);
        foreach ([$originalOnly, $reversalOnly, $both] as $row) {
            $this->assertEquals(10, $row['ending_stock']);
            $this->assertEquals($row['ending_stock'], $row['opening_balance'] + $row['purchase'] + $row['transfer'] - $row['sales'] + $row['adjustment']);
        }

        Carbon::setTestNow();
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    private function assertBlocked(StockAdjustment $adjustment, string $expectedReason): void
    {
        $snapshot = $this->snapshot();

        $this->voidAs($this->owner, $adjustment, 'Salah hitung')->assertSessionHas('error');

        $this->assertStringContainsString($expectedReason, session('error'));
        $this->assertSame($snapshot, $this->snapshot(), 'a blocked VOID changes nothing');
        $this->assertSame('completed', $adjustment->fresh()->status);
        $this->assertSame(0, StockMovement::where('reference_type', 'manual_adjustment_void')->count());
    }

    private function voidAs(User $user, StockAdjustment $adjustment, ?string $reason)
    {
        return $this->actingAs($user)->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => $reason, 'confirm' => '1']);
    }

    /** Creates an adjustment through the real form endpoint. Returns the saved StockAdjustment. */
    private function adjust(string $type, int $id, array $actualByIngredient, mixed $unused = null, ?string $note = null): StockAdjustment
    {
        $this->actingAs($this->owner)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => $type, 'location_id' => $id, 'note' => $note,
            'items' => collect($actualByIngredient)->map(fn ($actual, $ingredientId) => ['ingredient_id' => $ingredientId, 'actual_qty' => $actual])->values()->all(),
        ])->assertRedirect();

        return StockAdjustment::orderByDesc('id')->firstOrFail();
    }

    /** An adjustment as it existed before this feature: written straight to the tables, no status touched. */
    private function legacyAdjustment(string $type, int $id, Ingredient $ingredient, float $system, float $actual): StockAdjustment
    {
        $this->seed1($type, $id, $ingredient, $actual);
        $movement = StockMovement::create([
            'ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id, 'movement_type' => 'stock_adjustment',
            'qty_in' => max($actual - $system, 0), 'qty_out' => max($system - $actual, 0), 'reference_type' => 'manual_adjustment', 'reference_id' => 0,
        ]);
        $adjustment = StockAdjustment::create(['reference' => 'ADJ-LEGACY-0001', 'location_type' => $type, 'location_id' => $id, 'user_id' => $this->owner->id]);
        $movement->update(['reference_id' => $adjustment->id]);
        $adjustment->items()->create([
            'ingredient_id' => $ingredient->id, 'stock_movement_id' => $movement->id, 'unit' => $ingredient->unit,
            'system_qty' => $system, 'actual_qty' => $actual, 'difference' => $actual - $system,
        ]);

        return $adjustment;
    }

    /** Opening stock: a balance AND its opening_balance movement, like a real import. Returns 0 so it can be used inline. */
    private function seed1(string $type, int $id, Ingredient $ingredient, float|string $qty): int
    {
        StockBalance::updateOrCreate(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id], ['qty_on_hand' => $qty]);
        StockMovement::create([
            'ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id, 'movement_type' => 'opening_balance',
            'qty_in' => $qty, 'qty_out' => 0, 'reference_type' => 'import_opening_stock',
        ]);

        return 0;
    }

    /** A sale after the adjustment: a sales_usage movement and the matching balance change (outlet stock may go below zero). */
    private function consume(Ingredient $ingredient, string $type, int $id, float|string $qty): void
    {
        DB::table('stock_balances')->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])->decrement('qty_on_hand', $qty);
        StockMovement::create([
            'ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id, 'movement_type' => 'sales_usage',
            'qty_in' => 0, 'qty_out' => $qty, 'reference_type' => 'sales_transaction', 'reference_id' => 1,
        ]);
    }

    /** SQLite hands DECIMAL columns back as numbers, MySQL as strings: compare both as fixed two-decimal text. */
    private function dec(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function balance(Ingredient $ingredient, string $type, int $id): string
    {
        return number_format((float) StockBalance::where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])->value('qty_on_hand'), 2, '.', '');
    }

    /** Everything a blocked or forbidden VOID must leave exactly as it was. */
    private function snapshot(): array
    {
        return [
            'balances' => DB::table('stock_balances')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'movements' => DB::table('stock_movements')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'adjustments' => DB::table('stock_adjustments')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'items' => DB::table('stock_adjustment_items')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    private function summaryRow(array $extra = []): array
    {
        $rows = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index', array_merge([
            'summary_location_type' => 'outlet', 'summary_location_id' => $this->outlet->id, 'ingredient_id' => $this->gula->id,
        ], $extra)))->assertOk()->viewData('stockSummaryRows');

        return collect($rows)->firstWhere('ingredient_name', 'Gula');
    }

    private function makeUser(string $name, string $roleCode, array $outlets): User
    {
        $role = Role::firstOrCreate(['code' => $roleCode], ['name' => ucwords(str_replace('_', ' ', $roleCode))]);
        $user = User::create([
            'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password',
            'role_id' => $role->id, 'outlet_id' => $outlets[0]->id, 'is_active' => true,
        ]);
        $user->outlets()->sync(collect($outlets)->pluck('id')->all());

        return $user;
    }

    private function makeIngredient(IngredientCategory $category, string $name, string $unit): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => $unit,
            'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->outlets()->sync([$this->outlet->id, $this->outlet2->id]);

        return $ingredient;
    }
}
