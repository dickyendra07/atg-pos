<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RealEngineHarness;
use Tests\TestCase;

/**
 * Duplicate Transfer creation on a REAL MySQL: separate PHP processes, separate connections, real InnoDB locks (see
 * RealEngineHarness; skipped unless the disposable-MySQL opt-in is given, run through tests/Support/real-engine.sh).
 *
 * Before the idempotency key two identical create requests (a retry, a double click, a parallel request) each created
 * their own transfers and each deducted the stock. Now one operation key = one committed set of postings. Every test
 * reads the database: operation rows, transfer rows and ids, movement rows with their references and quantities,
 * both balances, and the ledger reconciliation.
 */
class RealEngineTransferIdempotencyTest extends TestCase
{
    use RealEngineHarness;

    private const SETTLE_US = 3_000_000;

    private const SAVED = 'Transfer bulk berhasil disimpan.';

    private const REPLAYED = 'Transfer ini sudah berhasil disimpan sebelumnya.';

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

    // ---- A: two identical sequential requests ------------------------------------------------------------------

    public function test_a_two_identical_sequential_requests_post_once(): void
    {
        $base = $this->reset();
        $payload = $this->payload([[$this->ingredient, 5]]);

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::REPLAYED);

        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: 'sequential');
    }

    // ---- B, C: simultaneous identical requests --------------------------------------------------------------------

    public function test_b_two_identical_simultaneous_requests_post_once(): void
    {
        foreach ([1, 2, 3] as $trial) {
            $base = $this->reset();
            $payload = $this->payload([[$this->ingredient, 5]]);

            $results = $this->fireTogether(array_fill(0, 2, ['uri' => '/backoffice/transfers', 'data' => $payload]));

            $this->assertAllOk($results);
            $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: "B trial {$trial}");
        }
    }

    public function test_c_six_concurrent_requests_sharing_one_key_post_once(): void
    {
        foreach ([1, 2, 3] as $trial) {
            $base = $this->reset();
            $payload = $this->payload([[$this->ingredient, 5]]);

            $results = $this->fireTogether(array_fill(0, 6, ['uri' => '/backoffice/transfers', 'data' => $payload]));

            $this->assertAllOk($results);
            $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: "C trial {$trial}");
        }
    }

    // ---- D: retry after commit (response lost) --------------------------------------------------------------------------

    public function test_d_a_retry_after_commit_posts_nothing_even_though_the_stock_is_now_too_low(): void
    {
        $base = $this->reset(10, 0);
        $payload = $this->payload([[$this->ingredient, 7]]);

        $this->assertAllOk($this->fireTogether([['uri' => '/backoffice/transfers', 'data' => $payload]]));   // its response is never read
        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '3.00', outlet: '7.00', label: 'after the original');

        // The client retries the same operation: only 3 left, so a NEW 7 would be refused - the replay must not be.
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)
            ->assertSessionHas('success', self::REPLAYED)->assertSessionHasNoErrors();

        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '3.00', outlet: '7.00', label: 'after the retry');
    }

    // ---- E: retry after rollback ---------------------------------------------------------------------------------------------

    public function test_e_a_retry_after_a_rolled_back_request_succeeds_exactly_once(): void
    {
        $base = $this->reset();
        $payload = $this->payload([[$this->ingredient, 5], [$this->ingredient2, 3]]);
        $attempts = $this->failEveryMovementInsert();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)
            ->assertSessionHasErrors(['items' => 'Transfer sedang diproses oleh permintaan lain. Tidak ada stok yang berubah, silakan coba lagi.']);

        $this->assertSame(3, $attempts->failures, 'three bounded attempts');
        $this->assertOutcome($base, ops: 0, transfers: 0, warehouse: '100.00', outlet: '10.00', label: 'after the rolled back request', second: ['50.00', '0.00']);

        $attempts->armed = false;   // the database recovers; the user submits the same form again
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);

        $this->assertOutcome($base, ops: 1, transfers: 2, warehouse: '95.00', outlet: '15.00', label: 'after the retry', second: ['47.00', '3.00']);
    }

    // ---- F: the duplicate arrives while the first is still in flight --------------------------------------------------

    public function test_f_a_duplicate_arriving_mid_flight_waits_and_then_posts_nothing(): void
    {
        $base = $this->reset();
        $payload = $this->payload([[$this->ingredient, 5]]);

        $this->holdRowLock('stock_balances', $this->balanceId($this->ingredient, 'warehouse', $this->warehouse->id));   // the original is stuck mid-transaction
        $original = $this->spawn(['uri' => '/backoffice/transfers', 'data' => $payload]);
        $this->release($original);
        usleep(self::SETTLE_US);

        $this->assertSame(0, DB::table('transfer_operations')->count(), 'the original has claimed the key but has not committed');

        $duplicate = $this->spawn(['uri' => '/backoffice/transfers', 'data' => $payload]);
        $this->release($duplicate);
        usleep(self::SETTLE_US);

        $this->assertSame(0, DB::table('stock_transfers')->count(), 'neither request has committed anything yet');
        $this->releaseRowLock();

        $this->assertAllOk([$this->collect($original), $this->collect($duplicate)]);
        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: 'F');
    }

    public function test_f2_when_the_original_rolls_back_the_waiting_duplicate_is_released_and_the_key_stays_usable(): void
    {
        $base = $this->reset();
        $payload = $this->payload([[$this->ingredient, 5]]);
        $sourceBalance = $this->balanceId($this->ingredient, 'warehouse', $this->warehouse->id);

        $this->holdRowLock('stock_balances', $sourceBalance);
        // While it is held, the stock drops below what the operation needs; this commits when the lock is released.
        DB::connection('atg_lock')->update('update stock_balances set qty_on_hand = 1 where id = ?', [$sourceBalance]);

        $original = $this->spawn(['uri' => '/backoffice/transfers', 'data' => $payload]);
        $this->release($original);
        usleep(self::SETTLE_US);
        $duplicate = $this->spawn(['uri' => '/backoffice/transfers', 'data' => $payload]);
        $this->release($duplicate);
        usleep(self::SETTLE_US);
        $this->releaseRowLock();

        // The original is refused ("not enough stock") and rolls its claim back; the duplicate, released from its
        // wait, claims the key itself and is refused for the same honest reason. Nobody hangs, nothing is posted.
        $results = [$this->collect($original), $this->collect($duplicate)];
        foreach ($results as $result) {
            $this->assertTrue($result['validation_failed'] ?? false, json_encode($result));
            $this->assertSame(302, $result['status']);
        }
        $this->assertSame(0, DB::table('transfer_operations')->count(), 'no claim survives a rollback');
        $this->assertSame(0, DB::table('stock_transfers')->count());

        // The stock is back; the very same key now works, once.
        DB::table('stock_balances')->where('id', $sourceBalance)->update(['qty_on_hand' => 100]);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);
        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: 'F2 retry');
    }

    // ---- G: different keys, identical payload ---------------------------------------------------------------------------------

    public function test_g_two_different_operations_with_an_identical_payload_both_post(): void
    {
        $base = $this->reset();

        $results = $this->fireTogether([
            ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 5]])],
            ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 5]])],
        ]);

        $this->assertAllOk($results);
        $this->assertOutcome($base, ops: 2, transfers: 2, warehouse: '90.00', outlet: '20.00', label: 'G');
    }

    // ---- H: a bulk transfer submitted twice at once ----------------------------------------------------------------------------

    public function test_h_a_bulk_transfer_submitted_twice_concurrently_posts_once_as_one_operation(): void
    {
        foreach ([1, 2] as $trial) {
            $base = $this->reset();
            $payload = $this->payload([[$this->ingredient, 5], [$this->ingredient2, 3], [$this->ingredient, 2]]);   // a repeated ingredient too

            $this->assertAllOk($this->fireTogether(array_fill(0, 2, ['uri' => '/backoffice/transfers', 'data' => $payload])));

            $this->assertOutcome($base, ops: 1, transfers: 3, warehouse: '93.00', outlet: '17.00', label: "H trial {$trial}", second: ['47.00', '3.00']);
            $this->assertSame(3, count(json_decode((string) DB::table('transfer_operations')->value('transfer_ids'), true)), 'the one record lists all three transfers');
        }
    }

    // ---- I, J: the key is bound to its user and its payload ------------------------------------------------------------------

    public function test_i_two_users_reusing_one_key_only_the_first_posts(): void
    {
        $second = User::create(['name' => 'staff2', 'username' => 'staff2', 'email' => 's2@example.test', 'password' => 'password', 'role_id' => $this->owner->role_id, 'outlet_id' => $this->outlet->id, 'is_active' => true]);
        $second->outlets()->sync([$this->outlet->id, $this->outlet2->id]);

        foreach ([1, 2] as $trial) {
            $base = $this->reset();
            $payload = $this->payload([[$this->ingredient, 5]]);

            $results = $this->fireTogether([
                ['uri' => '/backoffice/transfers', 'data' => $payload, 'user' => $this->owner->id],
                ['uri' => '/backoffice/transfers', 'data' => $payload, 'user' => $second->id],
            ]);

            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])), 'exactly one request is accepted: '.json_encode($results));
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['validation_failed'] ?? false)), 'the other is refused: '.json_encode($results));
            $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: "I trial {$trial}");
            $this->assertSame(
                (int) DB::table('transfer_operations')->where('id', '>', $base['op'])->value('user_id'),
                (int) DB::table('stock_transfers')->where('id', '>', $base['transfer'])->value('transferred_by_user_id'),
                'the claim belongs to the user whose request posted'
            );
        }
    }

    public function test_j_the_same_key_with_a_changed_payload_only_the_first_posts(): void
    {
        $base = $this->reset();
        $key = (string) Str::uuid();

        $results = $this->fireTogether([
            ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 5]], key: $key)],
            ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 6]], key: $key)],
        ]);

        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])), json_encode($results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['validation_failed'] ?? false)), json_encode($results));
        $this->assertSame(1, DB::table('transfer_operations')->count());
        $this->assertSame(1, DB::table('stock_transfers')->count());
        $deducted = (float) DB::table('stock_transfers')->value('qty');
        $this->assertContains($deducted, [5.0, 6.0], 'one payload won, the other was refused, nothing was merged');
        $this->assertSame($this->fixed2(100 - $deducted), $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $base['movement'], 'J warehouse');
    }

    // ---- K, L: different operations competing ---------------------------------------------------------------------------------------

    public function test_k_concurrent_different_operations_on_a_shared_balance_all_post(): void
    {
        $base = $this->reset();

        $results = $this->fireTogether(array_map(fn () => ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 1]])], range(1, 6)));

        $this->assertAllOk($results);
        $this->assertOutcome($base, ops: 6, transfers: 6, warehouse: '94.00', outlet: '16.00', label: 'K');
    }

    public function test_l_when_stock_covers_only_one_of_two_different_operations_the_loser_gets_a_validation_error_not_a_500(): void
    {
        foreach ([1, 2, 3] as $trial) {
            $base = $this->reset(10, 0);

            $results = $this->fireTogether([
                ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 8]])],
                ['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 7]])],
            ]);

            $this->assertSame([302, 302], array_column($results, 'status'), "trial {$trial}: no server error ".json_encode($results));
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['ok'])), json_encode($results));
            $this->assertSame(1, count(array_filter($results, fn ($r) => $r['validation_failed'] ?? false)), json_encode($results));
            $this->assertSame(1, DB::table('transfer_operations')->where('id', '>', $base['op'])->count(), 'the loser left no claim');
            $this->assertSame(1, DB::table('stock_transfers')->where('id', '>', $base['transfer'])->count());
            $deducted = (float) DB::table('stock_transfers')->where('id', '>', $base['transfer'])->value('qty');
            $this->assertSame($this->fixed2(10 - $deducted), $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
            $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '10.00', $base['movement'], "L trial {$trial}");
        }
    }

    // ---- M: deadlocks ---------------------------------------------------------------------------------------------------------------

    public function test_m_opposite_direction_operations_still_complete_without_deadlock(): void
    {
        foreach ([1, 2, 3] as $trial) {
            $base = $this->reset(100, 100);

            $requests = [];
            foreach (range(1, 8) as $n) {
                $requests[] = ['uri' => '/backoffice/transfers', 'data' => $this->payload(
                    [[$this->ingredient, 1]],
                    from: $n % 2 ? "warehouse:{$this->warehouse->id}" : "outlet:{$this->outlet->id}",
                    to: $n % 2 ? "outlet:{$this->outlet->id}" : "warehouse:{$this->warehouse->id}"
                )];
            }

            $this->assertAllOk($this->fireTogether($requests));
            $this->assertOutcome($base, ops: 8, transfers: 8, warehouse: '100.00', outlet: '100.00', label: "M trial {$trial}", baseW: '100.00', baseO: '100.00');
        }
    }

    public function test_m2_a_real_deadlock_is_retried_with_the_claim_and_posts_exactly_once(): void
    {
        $base = $this->reset();
        // Created AFTER the two real balances so a range lock over them never reaches the rows under test.
        foreach (range(1, 400) as $n) {
            DB::table('stock_balances')->insert(['ingredient_id' => $this->ingredient2->id, 'location_type' => 'outlet', 'location_id' => 1000 + $n, 'qty_on_hand' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $fillerIds = DB::table('stock_balances')->where('ingredient_id', $this->ingredient2->id)->where('location_id', '>', 1000)->pluck('id')->all();
        $warehouseBalance = $this->balanceId($this->ingredient, 'warehouse', $this->warehouse->id);
        $deadlocksBefore = $this->deadlockCounter();

        // A second session holds the OUTLET balance and piles up locks, so InnoDB prefers to roll back the request.
        $this->holdRowLock('stock_balances', $this->balanceId($this->ingredient, 'outlet', $this->outlet->id));
        $session = DB::connection('atg_lock');
        foreach (array_chunk($fillerIds, 100) as $chunk) {
            $session->update('update stock_balances set qty_on_hand = qty_on_hand + 1 where id in ('.implode(',', $chunk).')');
        }

        // The request claims its key and locks the warehouse balance, then waits for the outlet balance ...
        $worker = $this->spawn(['uri' => '/backoffice/transfers', 'data' => $this->payload([[$this->ingredient, 5]])]);
        $this->release($worker);
        usleep(self::SETTLE_US);
        // ... and the other session now asks for the warehouse balance: a genuine lock cycle. The request is rolled back
        // (claim included) and must run again from scratch.
        $session->selectOne('select * from stock_balances where id = ? for update', [$warehouseBalance]);
        $this->releaseRowLock();

        $this->assertTrue($this->collect($worker)['ok']);
        $this->assertGreaterThanOrEqual($deadlocksBefore + 1, $this->deadlockCounter(), 'InnoDB really detected a deadlock');
        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: 'M2 after the retry');
    }

    public function test_m3_a_deadlock_after_some_postings_is_retried_without_duplicating_anything(): void
    {
        $base = $this->reset();
        $attempts = $this->failEveryMovementInsert(onlyFirst: true);

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->payload([[$this->ingredient, 5], [$this->ingredient2, 3]]))
            ->assertSessionHas('success', self::SAVED);

        $this->assertSame(1, $attempts->failures);
        $this->assertOutcome($base, ops: 1, transfers: 2, warehouse: '95.00', outlet: '15.00', label: 'M3', second: ['47.00', '3.00']);
    }

    // ---- N: a failed bulk transaction takes the claim with it ------------------------------------------------------------------

    public function test_n_a_failure_halfway_through_a_bulk_rolls_back_the_claim_the_transfers_and_the_balances(): void
    {
        $base = $this->reset();
        $payload = $this->payload([[$this->ingredient, 5], [$this->ingredient2, 3], [$this->ingredient, 2]]);

        $seen = 0;
        \App\Models\StockMovement::creating(function () use (&$seen) {
            if (++$seen === 4) {   // the second item's second movement: transfers and movements already exist in the transaction
                throw new \RuntimeException('movement write failed');
            }
        });

        try {
            $this->withoutExceptionHandling();
            try {
                $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload);
                $this->fail('the request must fail');
            } catch (\RuntimeException $e) {
                $this->assertSame('movement write failed', $e->getMessage());
            }
        } finally {
            \App\Models\StockMovement::flushEventListeners();
        }

        $this->assertOutcome($base, ops: 0, transfers: 0, warehouse: '100.00', outlet: '10.00', label: 'after the failure', second: ['50.00', '0.00']);

        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);   // the same key
        $this->assertOutcome($base, ops: 1, transfers: 3, warehouse: '93.00', outlet: '17.00', label: 'after the retry', second: ['47.00', '3.00']);
    }

    // ---- the claim wait ---------------------------------------------------------------------------------------------------------------------

    public function test_o_a_claim_that_waits_too_long_gives_a_controlled_retry_message_and_keeps_the_key_usable(): void
    {
        $base = $this->reset();
        $key = (string) Str::uuid();
        $payload = $this->payload([[$this->ingredient, 5]], key: $key);

        // Another session holds this very key, uncommitted - an original that is still being processed.
        config(['database.connections.atg_lock' => config('database.connections.'.config('database.default'))]);
        DB::purge('atg_lock');
        $holder = DB::connection('atg_lock');
        $holder->beginTransaction();
        $holder->insert('insert into transfer_operations (operation_key, user_id, kind, fingerprint, transfer_ids, created_at, updated_at) values (?, ?, ?, ?, ?, now(), now())', [$key, $this->owner->id, 'general_transfer', str_repeat('0', 64), '[]']);

        try {
            DB::statement('set session innodb_lock_wait_timeout = 2');
            $started = microtime(true);
            $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)
                ->assertSessionHasErrors(['operation_key' => (new \App\Exceptions\TransferOperationBusy())->getMessage()]);
            $this->assertGreaterThanOrEqual(1.5, microtime(true) - $started, 'it really waited for the first request');
            $this->assertLessThan(10, microtime(true) - $started, 'one bounded wait, not three attempts');
        } finally {
            DB::statement('set session innodb_lock_wait_timeout = 50');
            $holder->rollBack();
        }

        $this->assertSame(0, DB::table('stock_transfers')->count());
        $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);
        $this->assertOutcome($base, ops: 1, transfers: 1, warehouse: '95.00', outlet: '15.00', label: 'after the original gave up');
    }

    // ---- helpers ------------------------------------------------------------------------------------------------------------------------------

    /** Balances back to a known state; returns the baselines every later assertion is measured from. */
    private function reset(float $warehouse = 100, float $outlet = 10): array
    {
        foreach ([$this->ingredient, $this->ingredient2] as $ingredient) {
            $this->seedBalance($ingredient, 'warehouse', $this->warehouse->id, $ingredient === $this->ingredient ? $warehouse : 50);
            $this->seedBalance($ingredient, 'outlet', $this->outlet->id, $ingredient === $this->ingredient ? $outlet : 0);
        }

        return [
            'movement' => $this->lastMovementId(),
            'transfer' => (int) DB::table('stock_transfers')->max('id'),
            'op' => (int) DB::table('transfer_operations')->max('id'),
            'W' => $this->fixed2($warehouse), 'O' => $this->fixed2($outlet),
        ];
    }

    /** @param  array<int, array{0: \App\Models\Ingredient, 1: int|float}>  $lines */
    private function payload(array $lines, ?string $from = null, ?string $to = null, ?string $key = null): array
    {
        return [
            'operation_key' => $key ?? (string) Str::uuid(),
            'from_location' => $from ?? "warehouse:{$this->warehouse->id}", 'to_location' => $to ?? "outlet:{$this->outlet->id}",
            'sender_name' => 'Sender', 'receiver_name' => '',
            'items' => array_map(fn ($line) => ['ingredient_id' => $line[0]->id, 'qty' => $line[1]], $lines),
        ];
    }

    private function balanceId(\App\Models\Ingredient $ingredient, string $type, int $locationId): int
    {
        return (int) DB::table('stock_balances')->where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $locationId])->value('id');
    }

    private function assertAllOk(array $results): void
    {
        foreach ($results as $i => $result) {
            $this->assertTrue($result['ok'], "request #{$i} failed: ".json_encode($result));
        }
    }

    /**
     * The database after the dust settles, measured from $base: operation rows, transfers and their ids and numbers,
     * the movement pairs with their references and quantities, both balances of the first ingredient (and of the
     * second one for a bulk), and the ledger reconciliation.
     *
     * @param  array{0?: string, 1?: string}  $second  expected [warehouse, outlet] of ingredient2 when the test uses it
     */
    private function assertOutcome(array $base, int $ops, int $transfers, string $warehouse, string $outlet, string $label, array $second = [], array $secondBase = ['50.00', '0.00'], ?string $baseW = null, ?string $baseO = null): void
    {
        $opRows = DB::table('transfer_operations')->where('id', '>', $base['op'])->get();
        $transferRows = DB::table('stock_transfers')->where('id', '>', $base['transfer'])->orderBy('id')->get();
        $movements = DB::table('stock_movements')->where('id', '>', $base['movement'])->orderBy('id')->get();
        $ids = $transferRows->pluck('id')->all();

        $this->assertSame($ops, $opRows->count(), "{$label}: operation rows");
        $this->assertSame($transfers, $transferRows->count(), "{$label}: transfers");
        $this->assertSame($transfers * 2, $movements->count(), "{$label}: exactly one movement pair per transfer");
        $this->assertSame($transfers, $movements->where('movement_type', 'transfer_out')->count(), "{$label}: transfer_out");
        $this->assertSame($transfers, $movements->where('movement_type', 'transfer_in')->count(), "{$label}: transfer_in");
        $this->assertCount(count($ids), array_unique($transferRows->pluck('transfer_number')->all()), "{$label}: transfer numbers are unique");

        foreach ($transferRows as $transfer) {
            $pair = $movements->where('reference_id', $transfer->id);
            $this->assertSame(2, $pair->count(), "{$label}: transfer {$transfer->id} has its own pair");
            $this->assertSame($this->hundredths($transfer->qty), $this->hundredths($pair->firstWhere('movement_type', 'transfer_out')->qty_out), "{$label}: out quantity");
            $this->assertSame($this->hundredths($transfer->qty), $this->hundredths($pair->firstWhere('movement_type', 'transfer_in')->qty_in), "{$label}: in quantity");
        }
        $this->assertEqualsCanonicalizing($ids, $movements->pluck('reference_id')->unique()->values()->all(), "{$label}: movement references");

        $recorded = $opRows->flatMap(fn ($row) => json_decode($row->transfer_ids, true))->sort()->values()->all();
        $this->assertSame($ids, $recorded, "{$label}: the operation records list every created transfer, each exactly once");

        $this->assertSame($warehouse, $this->balance($this->ingredient, 'warehouse', $this->warehouse->id), "{$label}: warehouse balance");
        $this->assertSame($outlet, $this->balance($this->ingredient, 'outlet', $this->outlet->id), "{$label}: outlet balance");
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $baseW ?? $base['W'], $base['movement'], "{$label} (warehouse)");
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, $baseO ?? $base['O'], $base['movement'], "{$label} (outlet)");

        if ($second) {
            $this->assertSame($second[0], $this->balance($this->ingredient2, 'warehouse', $this->warehouse->id), "{$label}: ingredient 2 warehouse");
            $this->assertSame($second[1], $this->balance($this->ingredient2, 'outlet', $this->outlet->id), "{$label}: ingredient 2 outlet");
            $this->assertReconciled($this->ingredient2, 'warehouse', $this->warehouse->id, $secondBase[0], $base['movement'], "{$label} (ingredient 2 warehouse)");
            $this->assertReconciled($this->ingredient2, 'outlet', $this->outlet->id, $secondBase[1], $base['movement'], "{$label} (ingredient 2 outlet)");
        }
    }

    private function deadlockCounter(): int
    {
        return (int) DB::selectOne("select count from information_schema.innodb_metrics where name = 'lock_deadlocks'")->count;
    }

    /**
     * Makes the connection report a deadlock (the exact MySQL error) right after a stock_movements insert - on every
     * attempt, or only the first. It fires after the INSERT ran, so the attempt has already changed data when it dies.
     */
    private function failEveryMovementInsert(bool $onlyFirst = false): object
    {
        $counter = new \stdClass;
        $counter->failures = 0;
        $counter->armed = true;

        DB::listen(function ($query) use ($counter, $onlyFirst) {
            if (! $counter->armed || ! str_starts_with($query->sql, 'insert into `stock_movements`')) {
                return;
            }

            if ($onlyFirst && $counter->failures > 0) {
                return;
            }

            $counter->failures++;

            throw new \PDOException('SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction', 40001);
        });

        return $counter;
    }
}
