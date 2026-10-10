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

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealEngine();
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

    public function test_a_void_racing_a_new_adjustment_can_never_both_win_in_the_wrong_order(): void
    {
        $voidFirst = $blockedByNewer = 0;

        // The VOID is started 0..~150 ms after the new adjustment, so both orders of the race actually happen.
        foreach (range(1, 12) as $round) {
            $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
            $old = $this->adjustWarehouse([$this->ingredient->id => 110]);
            $before = $this->balance($this->ingredient, 'warehouse', $this->warehouse->id);
            $after = $this->lastMovementId();

            [$void, $new] = $this->fireTogether([
                $this->voidRequest($old) + ['delay_ms' => ($round - 1) * 14],
                [
                    'uri' => '/backoffice/stock-balances/adjustment',
                    'data' => ['location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'note' => 'new count', 'items' => [['ingredient_id' => $this->ingredient->id, 'actual_qty' => 120]]],
                ],
            ]);

            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before, $after, "round {$round}");
            $reversal = DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->where('reference_id', $old->id)->value('id');
            $newMovement = DB::table('stock_movements')->where('reference_type', 'manual_adjustment')->where('id', '>', $after)->value('id');

            if ($void['ok'] && $new['ok']) {
                // Both committed: the VOID must have been serialised BEFORE the new count, never after it.
                $this->assertLessThan($newMovement, $reversal, "round {$round}: a VOID may not slip in after a newer adjustment");
                $voidFirst++;
            } elseif (! $void['ok'] && $new['ok']) {
                $this->assertNull($reversal, "round {$round}: blocked VOID wrote nothing");
                $blockedByNewer++;
            }
            $this->assertSame($void['ok'], $reversal !== null, "round {$round}: status and reversal agree");
        }

        $this->addToAssertionCount($voidFirst + $blockedByNewer);
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
