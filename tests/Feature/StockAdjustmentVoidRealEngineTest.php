<?php

namespace Tests\Feature;

use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Tests\Support\RealEngineHarness;
use Tests\TestCase;

/**
 * Stock Adjustment VOID under REAL concurrent sessions on a disposable MySQL/MariaDB (see RealEngineHarness;
 * skipped otherwise). SQLite in memory cannot show any of this: its lockForUpdate() does nothing.
 *
 * Every test ends with the same invariant: the stored balance equals the starting balance plus the ledger.
 */
class StockAdjustmentVoidRealEngineTest extends TestCase
{
    use RealEngineHarness;

    private const ROUNDS = 4;

    /** Each ordering scenario is repeated, and every repeat must show the same outcome. */
    private const ORDERING_REPEATS = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealEngine();
    }

    protected function tearDown(): void
    {
        $this->cleanupRealEngine();
        parent::tearDown();
    }

    public function test_many_simultaneous_voids_of_one_adjustment_create_exactly_one_reversal(): void
    {
        foreach (range(1, self::ROUNDS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $adjustment = $this->adjustWarehouse([$this->ingredient->id => 110]);
            $after = $this->lastMovementId();

            $results = $this->fireTogether(array_fill(0, 8, $this->voidRequest($adjustment)));

            $this->assertSame(1, collect($results)->where('ok', true)->count(), "round {$round}: ".json_encode($results));
            $this->assertSame(1, DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->where('reference_id', $adjustment->id)->count());
            $this->assertSame('100.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id), "round {$round}: reversed once, not 8 times");
            $this->assertSame('void', $adjustment->fresh()->status);
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '110.00', $after, "round {$round}");
        }
    }

    public function test_a_void_racing_stock_ins_neither_loses_nor_duplicates_anything(): void
    {
        foreach (range(1, self::ROUNDS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $adjustment = $this->adjustWarehouse([$this->ingredient->id => 110]);
            $before = $this->balance($this->ingredient, 'warehouse', $this->warehouse->id);
            $after = $this->lastMovementId();

            $requests = [$this->voidRequest($adjustment)];
            foreach (range(1, 6) as $unused) {
                $requests[] = [
                    'uri' => "/backoffice/warehouses/{$this->warehouse->id}/stock",
                    'data' => ['ingredient_id' => $this->ingredient->id, 'qty_in' => 1, 'note' => 'conc'],
                ];
            }
            $results = $this->fireTogether($requests);

            $this->assertTrue($results[0]['ok'], "round {$round}: the VOID has plenty of stock: ".json_encode($results[0]));
            $this->assertSame(6, collect($results)->slice(1)->where('ok', true)->count(), json_encode($results));
            // 110 (after the adjustment) - 10 (void) + 6 (stock ins) = 106: nothing lost, nothing doubled.
            $this->assertSame('106.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id), "round {$round}");
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before, $after, "round {$round}");
        }
    }

    /**
     * SCENARIO A, deterministic: the VOID is pinned half-way through its transaction (balance locked, everything
     * validated, the reversal about to be written) and only THEN does the newer adjustment start; it queues behind the
     * VOID's balance lock. Releasing the pin lets the VOID commit first and the newer adjustment continue.
     *
     * How the pin works, with no change to production code: a separate session holds an exclusive lock on the
     * ingredient row. Writing the first stock_movements row needs a shared lock on that row (foreign key check), so the
     * writer stops exactly there. The server itself (performance_schema) tells the test who waits on what.
     */
    public function test_scenario_a_void_commits_first_then_the_newer_adjustment_follows_current_stock(): void
    {
        $this->requireLockObservation();

        foreach (range(1, self::ORDERING_REPEATS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $old = $this->adjustWarehouse([$this->ingredient->id => 110]); // +10 -> 110
            $before = $this->balance($this->ingredient, 'warehouse', $this->warehouse->id);
            $after = $this->lastMovementId();
            $adjustmentsBefore = StockAdjustment::count();

            $this->holdRowLock('ingredients', $this->ingredient->id);

            $void = $this->spawn($this->voidRequest($old));
            $this->release($void);
            $this->waitUntilBlockedOn('ingredients'); // the VOID is mid-transaction, holding the balance lock

            $newer = $this->spawn($this->newAdjustmentRequest(120));
            $this->release($newer);
            $this->waitUntilBlockedOn('stock_balances'); // the newer adjustment is queued behind the VOID

            // The ordering is a fact, not a hope: both blockers are observed at the same moment, nothing committed yet.
            $this->assertGreaterThanOrEqual(1, $this->waitersBlockedOn('ingredients'), "round {$round}: VOID still pinned");
            $this->assertGreaterThanOrEqual(1, $this->waitersBlockedOn('stock_balances'), "round {$round}: newer adjustment queued behind it");
            $this->assertSame('completed', $old->fresh()->status, "round {$round}: the VOID has not committed yet");
            $this->assertSame($adjustmentsBefore, StockAdjustment::count(), "round {$round}: the newer adjustment has not committed yet");

            $this->releaseRowLock();
            $voidResult = $this->collect($void);
            $newResult = $this->collect($newer);

            $this->assertTrue($voidResult['ok'], "round {$round}: ".json_encode($voidResult));
            $this->assertTrue($newResult['ok'], "round {$round}: ".json_encode($newResult));

            // 1-2. The old adjustment is VOID, with exactly one reversal.
            $this->assertSame('void', $old->fresh()->status);
            $reversals = DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->where('reference_id', $old->id)->get();
            $this->assertCount(1, $reversals, "round {$round}: exactly one reversal");

            // 3. The newer adjustment worked from the stock as it is AFTER the VOID (110 - 10 = 100), not from 110.
            $newAdjustment = StockAdjustment::where('id', '>', $old->id)->orderBy('id')->firstOrFail();
            $item = $newAdjustment->items()->sole();
            $this->assertSame(['completed', '100.00', '120.00', '20.00'], [
                $newAdjustment->status, $this->fixed2($item->getRawOriginal('system_qty')), $this->fixed2($item->getRawOriginal('actual_qty')), $this->fixed2($item->getRawOriginal('difference')),
            ], "round {$round}: the newer adjustment saw the post-VOID balance");

            // 4. Balance and ledger agree; 5. nothing duplicated; the order in the ledger is VOID first.
            $this->assertSame('120.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before, $after, "round {$round}");
            $newMovement = DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->where('reference_id', $newAdjustment->id)->get();
            $this->assertCount(1, $newMovement);
            $this->assertLessThan($newMovement->first()->id, $reversals->first()->id, "round {$round}: the reversal was recorded before the newer movement");
            $this->assertSame(2, DB::table('stock_movements')->where('id', '>', $after)->count(), "round {$round}: exactly two new movements, no duplicates");
        }
    }

    /**
     * SCENARIO B, deterministic: this time the NEWER adjustment is pinned half-way through its transaction (it holds the
     * balance lock), and the VOID of the older one starts afterwards and queues behind it. Releasing the pin lets the
     * newer adjustment commit first; the VOID then gets the lock, sees it, and is refused without changing anything.
     */
    public function test_scenario_b_a_newer_adjustment_commits_first_and_the_void_is_refused(): void
    {
        $this->requireLockObservation();

        foreach (range(1, self::ORDERING_REPEATS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $old = $this->adjustWarehouse([$this->ingredient->id => 110]); // +10 -> 110
            $after = $this->lastMovementId();
            $adjustmentsBefore = StockAdjustment::count();

            $this->holdRowLock('ingredients', $this->ingredient->id);

            $newer = $this->spawn($this->newAdjustmentRequest(120));
            $this->release($newer);
            $this->waitUntilBlockedOn('ingredients'); // the newer adjustment holds the balance lock, not yet committed

            $void = $this->spawn($this->voidRequest($old));
            $this->release($void);
            $this->waitUntilBlockedOn('stock_balances'); // the VOID is queued behind it

            $this->assertGreaterThanOrEqual(1, $this->waitersBlockedOn('ingredients'), "round {$round}: newer adjustment still pinned");
            $this->assertGreaterThanOrEqual(1, $this->waitersBlockedOn('stock_balances'), "round {$round}: VOID queued behind it");
            $this->assertSame($adjustmentsBefore, StockAdjustment::count(), "round {$round}: the newer adjustment has not committed yet");

            $this->releaseRowLock();
            $newResult = $this->collect($newer);
            $voidResult = $this->collect($void);

            // 1. The newer adjustment committed.
            $this->assertTrue($newResult['ok'], "round {$round}: ".json_encode($newResult));
            $newAdjustment = StockAdjustment::where('id', '>', $old->id)->orderBy('id')->firstOrFail();
            $newItem = $newAdjustment->items()->sole();
            $this->assertSame('completed', $newAdjustment->status);
            $this->assertSame(['110.00', '120.00'], [$this->fixed2($newItem->getRawOriginal('system_qty')), $this->fixed2($newItem->getRawOriginal('actual_qty'))]);

            // 2-3. The VOID of the older one is refused, and says why; the older one stays completed.
            $this->assertFalse($voidResult['ok'], "round {$round}: ".json_encode($voidResult));
            $this->assertStringContainsString($newAdjustment->reference, (string) $voidResult['flash_error'], "round {$round}: refused because of the newer adjustment");
            $oldFresh = $old->fresh();
            $this->assertSame('completed', $oldFresh->status);
            $this->assertNull($oldFresh->void_at);
            $this->assertNull($oldFresh->void_reason);
            $this->assertNull($oldFresh->void_by_user_id);

            // 4-5. No reversal, and the rejected VOID changed no stock: only the newer adjustment moved it (110 -> 120).
            $this->assertSame(0, DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->count(), "round {$round}: no reversal movement");
            $this->assertNull($old->items()->sole()->void_stock_movement_id);
            $this->assertSame('120.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
            $this->assertSame(1, DB::table('stock_movements')->where('id', '>', $after)->count(), "round {$round}: only the newer adjustment's own movement");

            // 6. Every movement still points at a real adjustment.
            $this->assertSame(0, DB::table('stock_movements')->where('reference_type', 'manual_adjustment')
                ->whereNotIn('reference_id', DB::table('stock_adjustments')->select('id'))->count(), "round {$round}: no dangling movement reference");
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '110.00', $after, "round {$round}");
        }
    }

    public function test_two_voids_of_old_and_new_adjustments_never_leave_the_old_one_voided_alone(): void
    {
        foreach (range(1, self::ROUNDS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $first = $this->adjustWarehouse([$this->ingredient->id => 110]);
            $second = $this->adjustWarehouse([$this->ingredient->id => 125]);
            $before = $this->balance($this->ingredient, 'warehouse', $this->warehouse->id);
            $after = $this->lastMovementId();

            $this->fireTogether([$this->voidRequest($first), $this->voidRequest($second)]);

            $firstVoid = $first->fresh()->status === 'void';
            $secondVoid = $second->fresh()->status === 'void';
            $this->assertFalse($firstVoid && ! $secondVoid, "round {$round}: the older adjustment was voided while the newer one is still active");
            $this->assertTrue($secondVoid, "round {$round}: the newer one has no newer adjustment, so it can always be voided");
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before, $after, "round {$round}");
            $this->assertSame(
                (int) $firstVoid + (int) $secondVoid,
                DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->where('id', '>', $after)->count()
            );
        }
    }

    public function test_multi_item_voids_racing_multi_item_adjustments_in_opposite_order_stay_consistent(): void
    {
        foreach (range(1, self::ROUNDS) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $this->seedBalance($this->ingredient2, 'warehouse', $this->warehouse->id, 100);
            $old = $this->adjustWarehouse([$this->ingredient->id => 110, $this->ingredient2->id => 90]);
            $before = [$this->balance($this->ingredient, 'warehouse', $this->warehouse->id), $this->balance($this->ingredient2, 'warehouse', $this->warehouse->id)];
            $after = $this->lastMovementId();

            $this->fireTogether([
                $this->voidRequest($old),
                [
                    'uri' => '/backoffice/stock-balances/adjustment',
                    'data' => ['location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'items' => [
                        ['ingredient_id' => $this->ingredient2->id, 'actual_qty' => 70], ['ingredient_id' => $this->ingredient->id, 'actual_qty' => 130],
                    ]],
                ],
                [
                    'uri' => "/backoffice/warehouses/{$this->warehouse->id}/stock",
                    'data' => ['ingredient_id' => $this->ingredient2->id, 'qty_in' => 1, 'note' => 'conc'],
                ],
            ]);

            // A deadlock between these is resolved by the database (one request fails whole, a VOID is retried): the books must still balance.
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before[0], $after, "round {$round} (ingredient 1)");
            $this->assertReconciled($this->ingredient2, 'warehouse', $this->warehouse->id, $before[1], $after, "round {$round} (ingredient 2)");
            $this->assertSame(
                $old->fresh()->status === 'void' ? 2 : 0,
                DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->where('reference_id', $old->id)->count(),
                "round {$round}: a VOID is all or nothing"
            );
        }
    }

    private function newAdjustmentRequest(int $actual): array
    {
        return [
            'uri' => '/backoffice/stock-balances/adjustment',
            'data' => ['location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'note' => 'new count', 'items' => [['ingredient_id' => $this->ingredient->id, 'actual_qty' => $actual]]],
        ];
    }

    /** The ordering tests read who-waits-on-whom from MySQL 8's performance_schema; they cannot run elsewhere. */
    private function requireLockObservation(): void
    {
        if (! $this->canObserveLockWaits()) {
            $this->markTestSkipped('Deterministic ordering needs MySQL 8 performance_schema.data_lock_waits (not available on this server).');
        }
    }

    private function voidRequest(StockAdjustment $adjustment): array
    {
        return ['uri' => "/backoffice/stock-adjustments/{$adjustment->id}/void", 'data' => ['void_reason' => 'concurrency test', 'confirm' => '1']];
    }

    /** Saves a real adjustment through the form endpoint, in this process (committed, not part of any race). */
    private function adjustWarehouse(array $actualByIngredient): StockAdjustment
    {
        $this->actingAs($this->owner)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'note' => 'setup',
            'items' => collect($actualByIngredient)->map(fn ($actual, $id) => ['ingredient_id' => $id, 'actual_qty' => $actual])->values()->all(),
        ])->assertRedirect();

        return StockAdjustment::orderByDesc('id')->firstOrFail();
    }
}
