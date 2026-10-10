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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Transfer status transitions: the lifecycle matrix, exactly-once stock posting, legacy rows, missing balances
 * and the deterministic balance lock order. Runs on the default in-memory database; the genuinely parallel
 * behaviour (row locks, deadlocks, retries) is proven on a real MySQL in RealEngineTransferConcurrencyTest.
 *
 * Lifecycle: create -> in_transit (stock moved) ; in_transit -> received (status only) ; in_transit -> cancelled
 * (stock reversed once) ; cancelled -> in_transit (stock re-applied once) ; received -> in_transit (status only).
 */
class TransferStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private Outlet $outlet;

    private Outlet $otherOutlet;

    private Warehouse $warehouse;

    private User $owner;

    private Ingredient $milk;

    /** @var array<string, float> opening stock by "ingredient|type|id"; anything not listed opened at 0 */
    private array $openings = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'Outlet Satu', 'code' => 'O1', 'is_active' => true]);
        $this->otherOutlet = Outlet::create(['name' => 'Outlet Dua', 'code' => 'O2', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang', 'code' => 'W1', 'is_active' => true]);
        $this->owner = $this->makeUser('owner', Role::create(['name' => 'Owner', 'code' => 'owner']), [$this->outlet, $this->otherOutlet]);

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = $this->makeIngredient($category, 'Milk');
        $this->openBalance($this->milk, 'warehouse', $this->warehouse->id, 100);
    }

    // ---- 1-4, 20-24: the normal lifecycle posts exactly one movement pair per transition -------------

    public function test_normal_lifecycle_posts_one_movement_pair_per_transition(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);

        $this->assertSame('in_transit', $transfer->status);
        $this->assertSame(['transfer_in' => 1, 'transfer_out' => 1], $this->movements($transfer));
        $this->assertSame([90.0, 10.0], $this->wo());
        $this->assertSame(['transfer_in' => 10.0, 'transfer_out' => 10.0], $this->movementQuantities($transfer));

        $this->act('mark-received', $transfer)->assertSessionHas('success', 'Transfer item berhasil ditandai sebagai diterima.');
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertNotNull($transfer->fresh()->received_at);
        $this->assertSame(['transfer_in' => 1, 'transfer_out' => 1], $this->movements($transfer), 'receiving moves no stock');
        $this->assertSame([90.0, 10.0], $this->wo());

        $this->act('mark-in-transit', $transfer);   // received -> in_transit: status only
        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertNull($transfer->fresh()->received_at);
        $this->assertSame(['transfer_in' => 1, 'transfer_out' => 1], $this->movements($transfer), 'reverting a receipt moves no stock');
        $this->assertSame([90.0, 10.0], $this->wo());

        $this->act('mark-cancelled', $transfer)->assertSessionHas('success', 'Transfer item berhasil dibatalkan dan stok sudah di-rollback.');
        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame([100.0, 0.0], $this->wo());
        $this->assertSame(['transfer_cancel_out' => 1, 'transfer_cancel_return' => 1, 'transfer_in' => 1, 'transfer_out' => 1], $this->movements($transfer));
        $this->assertSame(['transfer_cancel_out' => 10.0, 'transfer_cancel_return' => 10.0, 'transfer_in' => 10.0, 'transfer_out' => 10.0], $this->movementQuantities($transfer));

        $this->act('mark-in-transit', $transfer);
        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame([90.0, 10.0], $this->wo());
        $this->assertSame(1, $this->movements($transfer)['transfer_out_reactivated']);
        $this->assertSame(1, $this->movements($transfer)['transfer_in_reactivated']);
        $this->assertSame(10.0, $this->movementQuantities($transfer)['transfer_out_reactivated']);
        $this->assertLedgerMatchesBalances();
    }

    // ---- 9, 10: no-ops --------------------------------------------------------------------------------

    public function test_in_transit_to_in_transit_is_a_no_op_that_writes_nothing(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $before = [$this->snapshot(), $transfer->fresh()->updated_at];

        $this->travel(5)->minutes();
        $this->act('mark-in-transit', $transfer)->assertSessionHas('success');

        $this->assertSame($before[0], $this->snapshot());
        $this->assertEquals($before[1], $transfer->fresh()->updated_at, 'not even updated_at moves');
    }

    public function test_repeated_receive_keeps_the_first_received_at(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->act('mark-received', $transfer);
        $first = $transfer->fresh()->received_at;

        Carbon::setTestNow('2026-10-10 17:30:00');
        $this->act('mark-received', $transfer)->assertSessionHas('success', 'Transfer item ini sudah berstatus diterima.');

        $this->assertEquals($first, $transfer->fresh()->received_at);
        $this->assertSame('2026-10-10 09:00:00', $transfer->fresh()->received_at->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    // ---- 11, 12, 13: retries and repeated lifecycle cycles ---------------------------------------------

    public function test_sequential_retry_after_a_successful_cancel_posts_nothing_more(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->act('mark-cancelled', $transfer);
        $after = $this->snapshot();

        $this->act('mark-cancelled', $transfer)->assertSessionHas('success', 'Transfer item ini sudah berstatus cancelled.');

        $this->assertSame($after, $this->snapshot());
        $this->assertSame(1, $this->movements($transfer)['transfer_cancel_return']);
        $this->assertSame([100.0, 0.0], $this->wo());
    }

    public function test_sequential_retry_after_a_successful_reactivation_posts_nothing_more(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->act('mark-cancelled', $transfer);
        $this->act('mark-in-transit', $transfer);
        $after = $this->snapshot();

        $this->act('mark-in-transit', $transfer)->assertSessionHas('success');

        $this->assertSame($after, $this->snapshot());
        $this->assertSame(1, $this->movements($transfer)['transfer_out_reactivated']);
        $this->assertSame([90.0, 10.0], $this->wo());
    }

    public function test_repeated_cancel_and_reactivate_cycles_each_post_their_own_pair(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);

        foreach ([1, 2, 3] as $cycle) {
            $this->act('mark-cancelled', $transfer);
            $this->assertSame([100.0, 0.0], $this->wo(), "cycle {$cycle} cancelled");
            $this->act('mark-in-transit', $transfer);
            $this->assertSame([90.0, 10.0], $this->wo(), "cycle {$cycle} reactivated");
        }

        $this->assertSame(
            ['transfer_cancel_out' => 3, 'transfer_cancel_return' => 3, 'transfer_in' => 1, 'transfer_in_reactivated' => 3, 'transfer_out' => 1, 'transfer_out_reactivated' => 3],
            $this->movements($transfer)
        );
        $this->assertLedgerMatchesBalances();
    }

    // ---- 15: invalid transitions are rejected without any stock change ---------------------------------

    public function test_received_cannot_be_cancelled_and_cancelled_cannot_be_received(): void
    {
        $received = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->act('mark-received', $received);
        $afterReceive = $this->snapshot();

        $this->act('mark-cancelled', $received)->assertSessionHas('success', 'Transfer item yang sudah diterima tidak bisa dibatalkan.');
        $this->assertSame($afterReceive, $this->snapshot());
        $this->assertSame('received', $received->fresh()->status);

        $cancelled = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 5);
        $this->act('mark-cancelled', $cancelled);
        $afterCancel = $this->snapshot();

        $this->act('mark-received', $cancelled)->assertSessionHas('success', 'Transfer item yang sudah dibatalkan tidak bisa langsung ditandai diterima.');
        $this->assertSame($afterCancel, $this->snapshot());
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertNull($cancelled->fresh()->received_at);
    }

    // ---- 17, 18, 19: insufficient stock and rollback ----------------------------------------------------

    public function test_source_with_insufficient_stock_rejects_the_transfer_and_writes_nothing(): void
    {
        $before = $this->snapshot();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, [[$this->milk, 101]]))
            ->assertSessionHasErrors('items.0.qty');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, StockBalance::where('location_type', 'outlet')->count(), 'no placeholder destination balance is left behind');
    }

    public function test_cancel_is_refused_when_the_destination_no_longer_has_the_stock(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        StockBalance::where('location_type', 'outlet')->update(['qty_on_hand' => 4]);   // sold meanwhile
        $before = $this->snapshot();

        $this->act('mark-cancelled', $transfer)->assertSessionHas('error');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('in_transit', $transfer->fresh()->status);
    }

    public function test_reactivation_is_refused_when_the_source_no_longer_has_the_stock(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->act('mark-cancelled', $transfer);
        StockBalance::where('location_type', 'warehouse')->update(['qty_on_hand' => 3]);
        $before = $this->snapshot();

        $this->act('mark-in-transit', $transfer)->assertSessionHas('error');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_a_failure_between_the_two_movements_rolls_back_the_whole_cancellation(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $before = $this->snapshot();

        $seen = 0;
        StockMovement::creating(function () use (&$seen) {
            if (++$seen === 2) {
                throw new \RuntimeException('movement write failed');
            }
        });

        try {
            $this->act('mark-cancelled', $transfer)->assertSessionHas('error', 'movement write failed');
        } finally {
            StockMovement::flushEventListeners();
        }

        $this->assertSame($before, $this->snapshot(), 'balances, movements and status are all exactly as before');
        $this->assertSame('in_transit', $transfer->fresh()->status);
    }

    // ---- 26: legacy rows ---------------------------------------------------------------------------------

    public function test_legacy_completed_rows_cannot_change_status_through_any_action(): void
    {
        $legacy = StockTransfer::create([
            'warehouse_id' => $this->warehouse->id, 'outlet_id' => $this->outlet->id,
            'ingredient_id' => $this->milk->id, 'qty' => 5, 'status' => 'completed',
        ]);
        $before = $this->snapshot();

        foreach (['mark-received', 'mark-cancelled', 'mark-in-transit'] as $action) {
            $this->act($action, $legacy)->assertSessionHas('error');
            $this->assertSame('completed', $legacy->fresh()->status, $action);
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_pending_rows_are_not_silently_moved_into_stock_states(): void
    {
        $pending = StockTransfer::create([
            'ingredient_id' => $this->milk->id, 'qty' => 5, 'status' => 'pending',
            'from_location_type' => 'warehouse', 'from_location_id' => $this->warehouse->id,
            'to_location_type' => 'outlet', 'to_location_id' => $this->outlet->id,
        ]);
        $before = $this->snapshot();

        foreach (['mark-received', 'mark-cancelled', 'mark-in-transit'] as $action) {
            $this->act($action, $pending)->assertSessionHas('error');
            $this->assertSame('pending', $pending->fresh()->status, $action);
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_row_without_locations_never_runs_a_stock_operation(): void
    {
        $noLocations = StockTransfer::create([
            'warehouse_id' => $this->warehouse->id, 'outlet_id' => $this->outlet->id,
            'ingredient_id' => $this->milk->id, 'qty' => 5, 'status' => 'in_transit',
        ]);
        $before = $this->snapshot();

        $this->act('mark-cancelled', $noLocations)->assertSessionHas('error');

        $this->assertSame('in_transit', $noLocations->fresh()->status);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, StockBalance::whereNull('location_type')->count());

        // A status-only step needs no locations, and a cancelled row of this kind can never be re-applied.
        $this->act('mark-received', $noLocations);
        $this->assertSame('received', $noLocations->fresh()->status);

        $cancelledNoLocations = StockTransfer::create([
            'ingredient_id' => $this->milk->id, 'qty' => 5, 'status' => 'cancelled', 'outlet_id' => $this->outlet->id,
        ]);
        $this->act('mark-in-transit', $cancelledNoLocations)->assertSessionHas('error');
        $this->assertSame('cancelled', $cancelledNoLocations->fresh()->status);
        $this->assertSame($before['balances'], $this->snapshot()['balances']);
    }

    // ---- 27: missing StockBalance rows -------------------------------------------------------------------

    public function test_missing_destination_balance_is_created_once_and_reused(): void
    {
        $this->assertSame(0, StockBalance::where('location_type', 'outlet')->count());

        $first = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->assertSame(1, $this->balanceRows('outlet', $this->outlet->id));

        $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 5);
        $this->assertSame(1, $this->balanceRows('outlet', $this->outlet->id));
        $this->assertSame(15.0, $this->qty('outlet', $this->outlet->id));

        // The destination row vanishing (e.g. master-data cleanup) is recreated by the reactivation, not duplicated.
        $this->act('mark-cancelled', $first);
        StockBalance::where('location_type', 'outlet')->delete();
        $this->act('mark-in-transit', $first);
        $this->assertSame(1, $this->balanceRows('outlet', $this->outlet->id));
        $this->assertSame(10.0, $this->qty('outlet', $this->outlet->id));
    }

    public function test_missing_source_balance_on_cancel_is_recreated_and_credited(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        StockBalance::where('location_type', 'warehouse')->delete();

        $this->act('mark-cancelled', $transfer)->assertSessionHas('success');

        $this->assertSame(1, $this->balanceRows('warehouse', $this->warehouse->id));
        $this->assertSame(10.0, $this->qty('warehouse', $this->warehouse->id));
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_missing_destination_balance_on_cancel_is_refused_cleanly(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        StockBalance::where('location_type', 'outlet')->delete();
        $before = $this->snapshot();

        $this->act('mark-cancelled', $transfer)->assertSessionHas('error', 'Stock lokasi tujuan tidak ditemukan untuk rollback transfer.');

        $this->assertSame($before, $this->snapshot());
    }

    public function test_missing_source_balance_on_create_is_refused_without_a_destination_placeholder(): void
    {
        StockBalance::where('location_type', 'warehouse')->delete();
        $before = $this->snapshot();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, [[$this->milk, 1]]))
            ->assertSessionHasErrors('items.0.ingredient_id');

        $this->assertSame($before, $this->snapshot());
    }

    // ---- 28: several items in one transfer ---------------------------------------------------------------

    public function test_bulk_transfer_posts_every_item_and_each_item_has_its_own_lifecycle(): void
    {
        $sugar = $this->makeIngredient($this->milk->category, 'Sugar');
        $this->openBalance($sugar, 'warehouse', $this->warehouse->id, 50);

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, [[$this->milk, 10], [$sugar, 7]]))
            ->assertRedirect(route('backoffice.transfers.index'));

        [$milkTransfer, $sugarTransfer] = [StockTransfer::where('ingredient_id', $this->milk->id)->firstOrFail(), StockTransfer::where('ingredient_id', $sugar->id)->firstOrFail()];
        $this->assertSame(2, StockTransfer::count());
        $this->assertSame(4, StockMovement::count());

        $this->act('mark-cancelled', $sugarTransfer);

        $this->assertSame('cancelled', $sugarTransfer->fresh()->status);
        $this->assertSame('in_transit', $milkTransfer->fresh()->status);
        $this->assertSame(50.0, $this->qty('warehouse', $this->warehouse->id, $sugar));
        $this->assertSame(0.0, $this->qty('outlet', $this->outlet->id, $sugar));
        $this->assertSame(90.0, $this->qty('warehouse', $this->warehouse->id, $this->milk));
        $this->assertSame(10.0, $this->qty('outlet', $this->outlet->id, $this->milk));
        $this->assertLedgerMatchesBalances();
    }

    public function test_bulk_transfer_is_all_or_nothing(): void
    {
        // Two lines of the same ingredient pass the per-line check (60 <= 100) but together exceed the stock;
        // the locked re-check catches it, and nothing - transfers, movements, balances - survives.
        $before = $this->snapshot();

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, [[$this->milk, 60], [$this->milk, 60]]));
            $this->fail('the second line must be refused');
        } catch (\RuntimeException $e) {
            $this->assertSame('Stock asal berubah saat proses transfer. Silakan ulangi lagi.', $e->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, StockTransfer::count());
    }

    // ---- 14, 15: the deterministic balance lock order -----------------------------------------------------

    public function test_every_direction_locks_the_balances_in_the_same_order(): void
    {
        $sugar = $this->makeIngredient($this->milk->category, 'Sugar');
        $this->openBalance($sugar, 'warehouse', $this->warehouse->id, 50);
        $this->openBalance($this->milk, 'outlet', $this->outlet->id, 50);
        $this->openBalance($sugar, 'outlet', $this->outlet->id, 50);

        $lockSequence = function (callable $run): array {
            $seen = [];
            DB::listen(function ($query) use (&$seen) {
                // lockStockBalances() reads each balance by its full identity: ingredient, type, id (+ limit 1).
                if (preg_match('/^select \* from "stock_balances" where .*"ingredient_id" = \? and "location_type" = \? and "location_id" = \?\)? limit 1$/', $query->sql)) {
                    $seen[] = $query->bindings[0].':'.$query->bindings[1].':'.$query->bindings[2];
                }
            });
            $run();

            return $seen;
        };

        $W = 'warehouse:'.$this->warehouse->id;
        $O = 'outlet:'.$this->outlet->id;
        $expectedMilk = [$this->milk->id.':warehouse:'.$this->warehouse->id, $this->milk->id.':outlet:'.$this->outlet->id];

        $forward = null;
        $sequences = [
            'W->O store' => $lockSequence(function () use ($W, $O, &$forward) {
                $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload($W, $O, [[$this->milk, 1]]));
                $forward = StockTransfer::latest('id')->firstOrFail();
            }),
        ];
        $backward = null;
        $sequences['O->W store'] = $lockSequence(function () use ($W, $O, &$backward) {
            $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload($O, $W, [[$this->milk, 1]]));
            $backward = StockTransfer::latest('id')->firstOrFail();
        });
        $sequences['W->O cancel'] = $lockSequence(fn () => $this->act('mark-cancelled', $forward));
        $sequences['O->W cancel'] = $lockSequence(fn () => $this->act('mark-cancelled', $backward));
        $sequences['W->O reactivate'] = $lockSequence(fn () => $this->act('mark-in-transit', $forward));
        $sequences['O->W reactivate'] = $lockSequence(fn () => $this->act('mark-in-transit', $backward));

        foreach ($sequences as $label => $seen) {
            $this->assertSame($expectedMilk, array_slice($seen, -2), "{$label}: warehouse balance first, then outlet balance, whatever the direction");
        }

        // Bulk transfers listing their items in opposite orders lock in the same global order too.
        $a = $lockSequence(fn () => $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload($W, $O, [[$this->milk, 1], [$sugar, 1]])));
        $b = $lockSequence(fn () => $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload($O, $W, [[$sugar, 1], [$this->milk, 1]])));
        $expectedBulk = [
            $this->milk->id.':warehouse:'.$this->warehouse->id, $this->milk->id.':outlet:'.$this->outlet->id,
            $sugar->id.':warehouse:'.$this->warehouse->id, $sugar->id.':outlet:'.$this->outlet->id,
        ];
        $this->assertSame($expectedBulk, array_slice($a, -4));
        $this->assertSame($expectedBulk, array_slice($b, -4));
    }

    // ---- 25: Admin Outlet permissions are unchanged and still enforced on the locked row -------------------

    public function test_admin_outlet_runs_the_full_cycle_on_a_transfer_involving_their_outlet(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $admin = $this->limitedUser('admin-o1', $this->outlet);

        foreach (['mark-cancelled', 'mark-in-transit', 'mark-received', 'mark-in-transit'] as $action) {
            $this->actingAs($admin)->post(route('backoffice.transfers.'.$action, $transfer))->assertRedirect(route('backoffice.transfers.index'));
        }

        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame([90.0, 10.0], $this->wo());
        $this->assertLedgerMatchesBalances();
    }

    public function test_admin_outlet_without_access_changes_nothing_on_any_action(): void
    {
        $transfer = $this->send('warehouse:'.$this->warehouse->id, 'outlet:'.$this->outlet->id, 10);
        $this->act('mark-cancelled', $transfer);
        $before = $this->snapshot();
        $stranger = $this->limitedUser('admin-o2', $this->otherOutlet);

        foreach (['mark-received', 'mark-cancelled', 'mark-in-transit'] as $action) {
            $this->actingAs($stranger)->post(route('backoffice.transfers.'.$action, $transfer))->assertForbidden();
        }

        $this->assertSame($before, $this->snapshot());
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    /** POST one of the three status actions as the owner (or whoever is acting). */
    private function act(string $action, StockTransfer $transfer)
    {
        return $this->actingAs($this->owner)->post(route('backoffice.transfers.'.$action, $transfer));
    }

    private function send(string $from, string $to, float $qty): StockTransfer
    {
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload($from, $to, [[$this->milk, $qty]]))
            ->assertRedirect(route('backoffice.transfers.index'));

        return StockTransfer::latest('id')->firstOrFail();
    }

    /** @param  array<int, array{0: Ingredient, 1: float|int}>  $lines */
    private function payload(string $from, string $to, array $lines): array
    {
        return [
            'from_location' => $from, 'to_location' => $to, 'sender_name' => 'Owner', 'receiver_name' => '',
            'items' => array_map(fn ($line) => ['ingredient_id' => $line[0]->id, 'qty' => $line[1]], $lines),
        ];
    }

    private function makeIngredient(IngredientCategory $category, string $name): Ingredient
    {
        $ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $ingredient->setRelation('category', $category);

        return $ingredient;
    }

    private function openBalance(Ingredient $ingredient, string $type, int $id, float $qty): void
    {
        StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id, 'qty_on_hand' => $qty]);
        $this->openings["{$ingredient->id}|{$type}|{$id}"] = $qty;
    }

    private function qty(string $type, int $id, ?Ingredient $ingredient = null): float
    {
        return (float) StockBalance::where('ingredient_id', ($ingredient ?? $this->milk)->id)
            ->where('location_type', $type)->where('location_id', $id)->value('qty_on_hand');
    }

    /** [warehouse qty, first outlet qty] of the milk. */
    private function wo(): array
    {
        return [$this->qty('warehouse', $this->warehouse->id), $this->qty('outlet', $this->outlet->id)];
    }

    private function balanceRows(string $type, int $id): int
    {
        return StockBalance::where('ingredient_id', $this->milk->id)->where('location_type', $type)->where('location_id', $id)->count();
    }

    /** @return array<string, int> movements of one transfer by type, sorted by type */
    private function movements(StockTransfer $transfer): array
    {
        return $this->transferMovements($transfer)->groupBy('movement_type')->map->count()->sortKeys()->all();
    }

    /** @return array<string, float> quantity moved per movement type (qty_in or qty_out, whichever is set) */
    private function movementQuantities(StockTransfer $transfer): array
    {
        return $this->transferMovements($transfer)->mapWithKeys(fn ($m) => [$m->movement_type => max((float) $m->qty_in, (float) $m->qty_out)])->sortKeys()->all();
    }

    private function transferMovements(StockTransfer $transfer)
    {
        return StockMovement::where('reference_id', $transfer->id)
            ->whereIn('reference_type', ['general_transfer', 'general_transfer_cancel', 'general_transfer_reactivated'])
            ->orderBy('id')->get();
    }

    private function snapshot(): array
    {
        return [
            'balances' => StockBalance::orderBy('id')->get(['id', 'ingredient_id', 'location_type', 'location_id', 'qty_on_hand'])->toArray(),
            'movements' => StockMovement::count(),
            'transfers' => StockTransfer::orderBy('id')->get(['id', 'status', 'received_at'])->toArray(),
        ];
    }

    /** The ledger invariant: every balance equals its opening stock plus the net of its movements. */
    private function assertLedgerMatchesBalances(): void
    {
        foreach (StockBalance::all() as $balance) {
            $net = (float) StockMovement::where(['ingredient_id' => $balance->ingredient_id, 'location_type' => $balance->location_type, 'location_id' => $balance->location_id])
                ->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as net')->value('net');
            $opening = $this->openings["{$balance->ingredient_id}|{$balance->location_type}|{$balance->location_id}"] ?? 0.0;

            $this->assertEqualsWithDelta($opening + $net, (float) $balance->qty_on_hand, 0.001, "{$balance->location_type}:{$balance->location_id} ingredient {$balance->ingredient_id}");
        }
    }

    private function limitedUser(string $username, Outlet $outlet): User
    {
        return $this->makeUser($username, Role::firstOrCreate(['code' => 'admin_outlet'], ['name' => 'Admin Outlet']), [$outlet]);
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
