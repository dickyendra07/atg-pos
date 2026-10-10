<?php

namespace Tests\Feature;

use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RealEngineHarness;
use Tests\TestCase;

/**
 * Transfer status transitions under REAL concurrency: separate PHP processes, separate MySQL connections, real
 * InnoDB row locks (see RealEngineHarness; skipped unless the disposable-MySQL opt-in is given, run through
 * tests/Support/real-engine.sh).
 *
 * Before the fix two requests for the SAME transfer both read its status before any lock, so both passed the
 * check and both posted the stock (double cancel: +10 instead of +5; double reactivation: -10 instead of -5), a
 * receive racing a cancellation left "received" on stock that had been rolled back, and transfers in opposite
 * directions locked their balances in opposite orders and deadlocked. Every test here states the invariant that
 * must hold afterwards: status, both balances, the exact movement pairs (and their quantities), and that each
 * balance equals its starting value plus the ledger.
 *
 * "Pinned" tests hold a real row lock from a third session so the requests are known to be inside the race window
 * at the same time; "unpinned" tests just fire the requests at the same instant.
 */
class RealEngineTransferConcurrencyTest extends TestCase
{
    use RealEngineHarness;

    private const W0 = 100;

    private const O0 = 10;

    private const QTY = 5;

    /** Long enough for a booted worker to reach (and block on) its lock after release(). */
    private const SETTLE_US = 3_000_000;

    private const CREATED = ['transfer_in' => 1, 'transfer_out' => 1];

    private const CANCELLED = ['transfer_cancel_out' => 1, 'transfer_cancel_return' => 1, 'transfer_in' => 1, 'transfer_out' => 1];

    private const REACTIVATED = [
        'transfer_cancel_out' => 1, 'transfer_cancel_return' => 1, 'transfer_in' => 1, 'transfer_in_reactivated' => 1,
        'transfer_out' => 1, 'transfer_out_reactivated' => 1,
    ];

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

    // ---- F1: two cancellations of one transfer ----------------------------------------------------------

    public function test_two_concurrent_cancellations_reverse_the_stock_once(): void
    {
        foreach ([1, 2] as $trial) {
            [$id, $after] = $this->freshTransfer();
            $results = $this->pinned('stock_balances', $this->balanceId('warehouse', $this->warehouse->id), array_fill(0, 2, $this->action($id, 'mark-cancelled')));

            $this->assertAllOk($results);
            $this->expectState($id, $after, 'cancelled', self::CANCELLED, "pinned #{$trial}");
        }

        foreach ([1, 2, 3, 4] as $trial) {
            [$id, $after] = $this->freshTransfer();
            $this->assertAllOk($results = $this->fireTogether(array_fill(0, 3, $this->action($id, 'mark-cancelled'))));
            $this->expectState($id, $after, 'cancelled', self::CANCELLED, "unpinned #{$trial}");
        }
    }

    // ---- F2: two reactivations of one cancelled transfer --------------------------------------------------

    public function test_two_concurrent_reactivations_apply_the_stock_once(): void
    {
        foreach ([1, 2] as $trial) {
            [$id, $after] = $this->freshTransfer('cancelled');
            $results = $this->pinned('stock_balances', $this->balanceId('warehouse', $this->warehouse->id), array_fill(0, 2, $this->action($id, 'mark-in-transit')));

            $this->assertAllOk($results);
            $this->expectState($id, $after, 'in_transit', self::REACTIVATED, "pinned #{$trial}");
        }

        foreach ([1, 2, 3, 4] as $trial) {
            [$id, $after] = $this->freshTransfer('cancelled');
            $this->assertAllOk($this->fireTogether(array_fill(0, 3, $this->action($id, 'mark-in-transit'))));
            $this->expectState($id, $after, 'in_transit', self::REACTIVATED, "unpinned #{$trial}");
        }
    }

    public function test_repeated_cancel_reactivate_cycles_still_work_after_the_fix(): void
    {
        [$id, $after] = $this->freshTransfer();

        foreach ([1, 2] as $cycle) {
            $this->assertAllOk($this->fireTogether(array_fill(0, 2, $this->action($id, 'mark-cancelled'))));
            $this->assertSame(['100.00', '10.00'], $this->wo(), "cycle {$cycle}: cancelled once");
            $this->assertAllOk($this->fireTogether(array_fill(0, 2, $this->action($id, 'mark-in-transit'))));
            $this->assertSame(['95.00', '15.00'], $this->wo(), "cycle {$cycle}: reactivated once");
        }

        $this->assertSame(
            ['transfer_cancel_out' => 2, 'transfer_cancel_return' => 2, 'transfer_in' => 1, 'transfer_in_reactivated' => 2, 'transfer_out' => 1, 'transfer_out_reactivated' => 2],
            $this->movementCounts($id)
        );
        $this->assertBalancesReconciled($after, 'two full cycles');
    }

    // ---- F3: receive racing cancellation --------------------------------------------------------------------

    public function test_receive_and_cancel_racing_on_the_transfer_row_end_consistent(): void
    {
        foreach (['receive-first', 'cancel-first'] as $order) {
            foreach ([1, 2] as $trial) {
                [$id, $after] = $this->freshTransfer();
                $requests = $order === 'receive-first'
                    ? [$this->action($id, 'mark-received'), $this->action($id, 'mark-cancelled')]
                    : [$this->action($id, 'mark-cancelled'), $this->action($id, 'mark-received')];

                $this->assertAllOk($this->pinned('stock_transfers', $id, $requests));

                // Whichever request got the transfer row first wins completely; the other is refused untouched.
                $status = DB::table('stock_transfers')->where('id', $id)->value('status');
                $this->assertContains($status, ['received', 'cancelled'], "{$order} #{$trial}");
                $status === 'received'
                    ? $this->expectState($id, $after, 'received', self::CREATED, "{$order} #{$trial}")
                    : $this->expectState($id, $after, 'cancelled', self::CANCELLED, "{$order} #{$trial}");
            }
        }
    }

    public function test_receive_cancel_and_in_transit_noop_fired_together_end_consistent(): void
    {
        foreach (range(1, 4) as $trial) {
            [$id, $after] = $this->freshTransfer();
            $this->assertAllOk($this->fireTogether([$this->action($id, 'mark-received'), $this->action($id, 'mark-cancelled'), $this->action($id, 'mark-in-transit')]));

            $status = DB::table('stock_transfers')->where('id', $id)->value('status');
            // received -> in_transit is a supported workflow, so any of the three can legitimately be last; what
            // may never happen is a status that contradicts the stock or a second cancellation pair.
            $this->assertContains($status, ['received', 'cancelled', 'in_transit'], "trial {$trial}");
            // cancel then the in-transit request is a legitimate serial order: it reactivates, and the history shows it.
            $status === 'cancelled'
                ? $this->expectState($id, $after, $status, self::CANCELLED, "trial {$trial}")
                : $this->expectState($id, $after, $status, self::CREATED, "trial {$trial}", [self::REACTIVATED]);
        }
    }

    // ---- F4: opposite directions never deadlock --------------------------------------------------------------

    public function test_opposite_direction_transfers_complete_without_deadlock(): void
    {
        foreach (range(1, 4) as $trial) {
            $this->resetBalances(100, 100);
            $after = $this->lastMovementId();
            $transfersBefore = StockTransfer::count();

            $requests = [];
            foreach (range(1, 8) as $n) {
                $requests[] = ['uri' => '/backoffice/transfers', 'data' => $n % 2
                    ? $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet->id}")
                    : $this->storeData("outlet:{$this->outlet->id}", "warehouse:{$this->warehouse->id}")];
            }

            $this->assertAllOk($this->fireTogether($requests));

            $this->assertSame($transfersBefore + 8, StockTransfer::count(), "trial {$trial}");
            $this->assertSame(16, DB::table('stock_movements')->where('id', '>', $after)->count(), "trial {$trial}: one pair per transfer");
            $this->assertSame(['100.00', '100.00'], $this->wo(), "trial {$trial}: four each way cancel out");
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, "trial {$trial} warehouse");
            $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '100.00', $after, "trial {$trial} outlet");
        }
    }

    public function test_opposite_direction_cancellations_complete_without_deadlock(): void
    {
        foreach (range(1, 3) as $trial) {
            $this->resetBalances(100, 100);
            $after = $this->lastMovementId();

            $ids = [];
            foreach (range(1, 8) as $n) {
                $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $n % 2
                    ? $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet->id}")
                    : $this->storeData("outlet:{$this->outlet->id}", "warehouse:{$this->warehouse->id}"))->assertRedirect();
                $ids[] = (int) StockTransfer::max('id');
            }

            $this->assertAllOk($this->fireTogether(array_map(fn ($id) => $this->action($id, 'mark-cancelled'), $ids)));

            $this->assertSame(['cancelled' => 8], DB::table('stock_transfers')->whereIn('id', $ids)->pluck('status')->countBy()->all(), "trial {$trial}");
            $this->assertSame(['100.00', '100.00'], $this->wo(), "trial {$trial}: everything is back");
            foreach ($ids as $id) {
                $this->assertSame(['transfer_cancel_out' => 1, 'transfer_cancel_return' => 1, 'transfer_in' => 1, 'transfer_out' => 1], $this->movementCounts($id), "trial {$trial} transfer {$id}");
            }
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, "trial {$trial} warehouse");
            $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '100.00', $after, "trial {$trial} outlet");
        }
    }

    public function test_bulk_transfers_listing_their_items_in_opposite_orders_complete_without_deadlock(): void
    {
        foreach (range(1, 3) as $trial) {
            foreach ([$this->ingredient, $this->ingredient2] as $ingredient) {
                $this->seedBalance($ingredient, 'warehouse', $this->warehouse->id, 100);
                $this->seedBalance($ingredient, 'outlet', $this->outlet->id, 100);
            }
            $after = $this->lastMovementId();

            $requests = [];
            foreach (range(1, 6) as $n) {
                $items = $n % 2 ? [$this->ingredient, $this->ingredient2] : [$this->ingredient2, $this->ingredient];
                $requests[] = ['uri' => '/backoffice/transfers', 'data' => $this->storeData(
                    $n % 2 ? "warehouse:{$this->warehouse->id}" : "outlet:{$this->outlet->id}",
                    $n % 2 ? "outlet:{$this->outlet->id}" : "warehouse:{$this->warehouse->id}",
                    items: $items
                )];
            }

            $this->assertAllOk($this->fireTogether($requests));

            $this->assertSame(24, DB::table('stock_movements')->where('id', '>', $after)->count(), "trial {$trial}: 6 requests x 2 items x a pair");
            foreach ([$this->ingredient, $this->ingredient2] as $ingredient) {
                $this->assertSame('100.00', $this->balance($ingredient, 'warehouse', $this->warehouse->id), "trial {$trial}");
                $this->assertSame('100.00', $this->balance($ingredient, 'outlet', $this->outlet->id), "trial {$trial}");
                $this->assertReconciled($ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, "trial {$trial} warehouse");
                $this->assertReconciled($ingredient, 'outlet', $this->outlet->id, '100.00', $after, "trial {$trial} outlet");
            }
        }
    }

    // ---- missing balances, distinct transfers on one balance -----------------------------------------------

    public function test_concurrent_transfers_to_a_destination_without_a_balance_create_it_exactly_once(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $this->assertSame(0, DB::table('stock_balances')->where(['ingredient_id' => $this->ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet2->id])->count());
        $after = $this->lastMovementId();

        $this->assertAllOk($this->fireTogether(array_map(fn () => ['uri' => '/backoffice/transfers', 'data' => $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet2->id}", 1)], range(1, 6))));

        $this->assertSame(1, DB::table('stock_balances')->where(['ingredient_id' => $this->ingredient->id, 'location_type' => 'outlet', 'location_id' => $this->outlet2->id])->count(), 'one row for the identity');
        $this->assertSame('6.00', $this->balance($this->ingredient, 'outlet', $this->outlet2->id));
        $this->assertSame('94.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet2->id, '0.00', $after, 'new destination');
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'source');
    }

    public function test_distinct_transfers_sharing_a_balance_cancel_and_reactivate_without_lost_updates(): void
    {
        $this->resetBalances(100, 10);
        $after = $this->lastMovementId();
        $ids = [];
        foreach (range(1, 6) as $n) {
            $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet->id}", 1))->assertRedirect();
            $ids[] = (int) StockTransfer::max('id');
        }

        $this->assertAllOk($this->fireTogether(array_map(fn ($id) => $this->action($id, 'mark-cancelled'), $ids)));
        $this->assertSame(['100.00', '10.00'], $this->wo());

        $this->assertAllOk($this->fireTogether(array_map(fn ($id) => $this->action($id, 'mark-in-transit'), $ids)));
        $this->assertSame(['94.00', '16.00'], $this->wo());
        $this->assertBalancesReconciled($after, 'six transfers cancelled then reactivated together');
    }

    // ---- deadlock retry ---------------------------------------------------------------------------------------

    public function test_a_real_deadlock_is_retried_and_the_ledger_ends_correct(): void
    {
        // A second session takes the OUTLET balance and piles up locks so InnoDB prefers to roll back the request.
        [$id, $after] = $this->freshTransfer();
        // Created AFTER the two real balances: a range lock over rows that come before them in the primary key
        // never reaches them (a range scan also locks the first row after its range).
        foreach (range(1, 400) as $n) {
            DB::table('stock_balances')->insert(['ingredient_id' => $this->ingredient2->id, 'location_type' => 'outlet', 'location_id' => 1000 + $n, 'qty_on_hand' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $fillerIds = DB::table('stock_balances')->where('ingredient_id', $this->ingredient2->id)->pluck('id')->all();
        $warehouseBalance = $this->balanceId('warehouse', $this->warehouse->id);
        $deadlocksBefore = $this->deadlockCounter();

        $this->holdRowLock('stock_balances', $this->balanceId('outlet', $this->outlet->id));
        $session = DB::connection('atg_lock');
        // Small lists only: with more than ~200 values MySQL stops probing the index and scans (and locks) the table.
        foreach (array_chunk($fillerIds, 100) as $chunk) {
            $session->update('update stock_balances set qty_on_hand = qty_on_hand + 1 where id in ('.implode(',', $chunk).')');
        }

        // The cancel locks transfer row -> warehouse balance, then waits for the outlet balance held above ...
        $worker = $this->spawn($this->action($id, 'mark-cancelled'));
        $this->release($worker);
        usleep(self::SETTLE_US);

        // ... and the other session now asks for the warehouse balance: a genuine lock cycle. The request holds far
        // fewer locks, so InnoDB rolls IT back; it must retry the whole operation from the start.
        $session->selectOne('select * from stock_balances where id = ? for update', [$warehouseBalance]);
        $this->releaseRowLock();

        $result = $this->collect($worker);

        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertGreaterThanOrEqual($deadlocksBefore + 1, $this->deadlockCounter(), 'InnoDB really detected a deadlock');
        $this->expectState($id, $after, 'cancelled', self::CANCELLED, 'after the retry');
    }

    public function test_a_deadlock_during_posting_is_retried_without_duplicating_movements(): void
    {
        // The simulated deadlock hits AFTER the first movement of the first attempt was written, so the retry
        // must start from a clean rolled-back state.
        foreach ([
            'cancel' => fn (int $id) => $this->actingAs($this->owner)->post("/backoffice/transfers/{$id}/mark-cancelled"),
            'reactivate' => fn (int $id) => $this->actingAs($this->owner)->post("/backoffice/transfers/{$id}/mark-in-transit"),
        ] as $label => $send) {
            [$id, $after] = $this->freshTransfer($label === 'cancel' ? 'in_transit' : 'cancelled');
            $attempts = $this->failMovementInsertOnce();

            $send($id)->assertSessionHas('success');

            $this->assertSame(1, $attempts->failures, "{$label}: exactly one simulated deadlock");
            $this->assertSame(3, $attempts->started, "{$label}: attempt 1 died on its first movement, attempt 2 wrote the pair");
            $this->expectState($id, $after, $label === 'cancel' ? 'cancelled' : 'in_transit', $label === 'cancel' ? self::CANCELLED : self::REACTIVATED, "{$label} after retry");
        }

        // Creating a transfer is retried the same way and ends with exactly one transfer and one pair.
        $this->resetBalances(100, 10);
        $after = $this->lastMovementId();
        $transfers = StockTransfer::count();
        $attempts = $this->failMovementInsertOnce();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet->id}"))->assertRedirect();

        $this->assertSame($transfers + 1, StockTransfer::count());
        $this->assertSame(2, DB::table('stock_movements')->where('id', '>', $after)->count());
        $this->assertSame(['99.00', '11.00'], $this->wo(), 'a single transfer of 1');
    }

    public function test_when_every_attempt_deadlocks_nothing_changes_and_the_user_gets_a_safe_message(): void
    {
        [$id, $after] = $this->freshTransfer();
        $before = $this->stateSnapshot();
        $attempts = $this->failMovementInsertOnce(always: true);

        $this->actingAs($this->owner)->post("/backoffice/transfers/{$id}/mark-cancelled")
            ->assertSessionHas('error', 'Transfer sedang diproses oleh permintaan lain. Tidak ada stok yang berubah, silakan coba lagi.');

        $this->assertSame(3, $attempts->failures, 'bounded: exactly three attempts');
        $this->assertSame(3, $attempts->started, 'each attempt died on its first movement');
        $this->assertSame($before, $this->stateSnapshot(), 'no balance, movement or status change survives');
    }

    public function test_business_failures_are_not_retried(): void
    {
        [$id] = $this->freshTransfer();
        DB::table('stock_balances')->where(['ingredient_id' => $this->ingredient->id, 'location_type' => 'outlet'])->update(['qty_on_hand' => 1]);   // too little to roll back
        $queries = 0;
        DB::listen(function ($query) use (&$queries) {
            if (str_contains($query->sql, 'for update') && str_contains($query->sql, '`stock_transfers`')) {
                $queries++;
            }
        });

        $this->actingAs($this->owner)->post("/backoffice/transfers/{$id}/mark-cancelled")->assertSessionHas('error');

        $this->assertSame(1, $queries, 'one attempt only: a validation failure is never retried');
        $this->assertSame('in_transit', DB::table('stock_transfers')->where('id', $id)->value('status'));
    }

    // ---- helpers --------------------------------------------------------------------------------------------------

    private function action(int $transferId, string $verb): array
    {
        return ['uri' => "/backoffice/transfers/{$transferId}/{$verb}"];
    }

    /** @param  array<int, \App\Models\Ingredient>|null  $items */
    private function storeData(string $from, string $to, int|float $qty = 1, ?array $items = null): array
    {
        return [
            'operation_key' => (string) Str::uuid(),
            'from_location' => $from, 'to_location' => $to, 'sender_name' => 'Sender', 'receiver_name' => '',
            'items' => array_map(fn ($ingredient) => ['ingredient_id' => $ingredient->id, 'qty' => $qty], $items ?? [$this->ingredient]),
        ];
    }

    private function resetBalances(float $warehouse, float $outlet): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, $warehouse);
        $this->seedBalance($this->ingredient, 'outlet', $this->outlet->id, $outlet);
    }

    /** Warehouse 100 / outlet 10, one W->O transfer of 5 in_transit (95 / 15), optionally already cancelled (100 / 10). */
    private function freshTransfer(string $state = 'in_transit'): array
    {
        $this->resetBalances(self::W0, self::O0);
        $after = $this->lastMovementId();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->storeData("warehouse:{$this->warehouse->id}", "outlet:{$this->outlet->id}", self::QTY))->assertRedirect();
        $id = (int) StockTransfer::max('id');

        if ($state === 'cancelled') {
            $this->actingAs($this->owner)->post("/backoffice/transfers/{$id}/mark-cancelled")->assertRedirect();
        }

        return [$id, $after];
    }

    private function balanceId(string $type, int $locationId): int
    {
        return (int) DB::table('stock_balances')->where(['ingredient_id' => $this->ingredient->id, 'location_type' => $type, 'location_id' => $locationId])->value('id');
    }

    /** @return array{0: string, 1: string} [warehouse, outlet] balances of the main ingredient */
    private function wo(): array
    {
        return [$this->balance($this->ingredient, 'warehouse', $this->warehouse->id), $this->balance($this->ingredient, 'outlet', $this->outlet->id)];
    }

    /**
     * Holds a real row lock, lets every request reach the race window (in the given order), then lets them all go.
     * Each request is released one by one and given time to arrive and block, so their arrival order is known.
     *
     * @param  array<int, array{uri: string}>  $requests
     * @return array<int, array>
     */
    private function pinned(string $table, int $rowId, array $requests): array
    {
        $this->holdRowLock($table, $rowId);

        $handles = [];
        foreach ($requests as $request) {
            $handles[] = $handle = $this->spawn($request);
            $this->release($handle);
            usleep(self::SETTLE_US);
        }

        $this->releaseRowLock();

        return array_map(fn ($handle) => $this->collect($handle), $handles);
    }

    private function assertAllOk(array $results): void
    {
        foreach ($results as $i => $result) {
            $this->assertTrue($result['ok'], "request #{$i} failed: ".json_encode($result));
        }
    }

    /** @return array<string, int> this transfer's movements by type, sorted */
    private function movementCounts(int $transferId): array
    {
        return $this->transferMovements($transferId)->groupBy('movement_type')->map->count()->sortKeys()->all();
    }

    private function transferMovements(int $transferId)
    {
        return DB::table('stock_movements')->where('reference_id', $transferId)
            ->whereIn('reference_type', ['general_transfer', 'general_transfer_cancel', 'general_transfer_reactivated'])
            ->orderBy('id')->get();
    }

    /** The state every race must end in: status, balances, the exact movement pairs and quantities, ledger. */
    private function expectState(int $id, int $after, string $status, array $history, string $label, array $alsoAcceptable = []): void
    {
        $transfer = DB::table('stock_transfers')->where('id', $id)->first();
        $this->assertSame($status, $transfer->status, "{$label}: status");
        $status === 'received'
            ? $this->assertNotNull($transfer->received_at, "{$label}: received_at")
            : $this->assertNull($transfer->received_at, "{$label}: received_at");

        $expectedBalances = $status === 'cancelled' ? ['100.00', '10.00'] : ['95.00', '15.00'];
        $this->assertSame($expectedBalances, $this->wo(), "{$label}: warehouse / outlet balance");
        $this->assertContains($this->movementCounts($id), [$history, ...$alsoAcceptable], "{$label}: movement pairs");

        foreach ($this->transferMovements($id) as $movement) {
            $this->assertSame(self::QTY * 100, max($this->hundredths($movement->qty_in), $this->hundredths($movement->qty_out)), "{$label}: {$movement->movement_type} quantity");
        }

        $this->assertBalancesReconciled($after, $label);
    }

    private function assertBalancesReconciled(int $after, string $label): void
    {
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, "{$label} (warehouse)");
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '10.00', $after, "{$label} (outlet)");
    }

    private function stateSnapshot(): array
    {
        return json_decode(json_encode([
            DB::table('stock_balances')->orderBy('id')->get(['id', 'qty_on_hand']),
            DB::table('stock_movements')->count(),
            DB::table('stock_transfers')->orderBy('id')->get(['id', 'status', 'received_at']),
        ]), true);
    }

    private function deadlockCounter(): int
    {
        return (int) DB::selectOne("select count from information_schema.innodb_metrics where name = 'lock_deadlocks'")->count;
    }

    /**
     * Makes the connection report a deadlock (the exact error MySQL gives) right after a stock_movements insert,
     * once or on every attempt. Thrown after the INSERT ran, so the attempt has already changed data when it dies.
     */
    private function failMovementInsertOnce(bool $always = false): object
    {
        $counter = new \stdClass;
        $counter->started = 0;     // movement inserts seen, the failed one included
        $counter->failures = 0;
        $armed = true;

        DB::listen(function ($query) use ($counter, &$armed, $always) {
            if (! $armed || ! str_starts_with($query->sql, 'insert into `stock_movements`')) {
                return;
            }

            $counter->started++;

            if ($always || $counter->failures === 0) {
                $counter->failures++;

                throw new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction', 40001);
            }
        });

        return $counter;
    }
}
