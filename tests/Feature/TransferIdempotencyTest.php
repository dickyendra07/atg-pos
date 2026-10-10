<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\Role;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TransferOperation;
use App\Models\User;
use App\Models\Warehouse;
use App\Exceptions\TransferOperationBusy;
use App\Exceptions\TransferOperationConflict;
use App\Services\TransferOperationService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * One business operation = one committed transfer creation. The form carries a server-issued operation key; the
 * first POST with a key claims it (unique index, same transaction as the postings) and every later POST with the
 * same key is a replay that posts nothing. Everything here is checked against the database, not only the response.
 * The genuinely concurrent behaviour (parallel processes, real row locks) is in RealEngineTransferIdempotencyTest.
 */
class TransferIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private const SAVED = 'Transfer bulk berhasil disimpan.';

    private const REPLAYED = 'Transfer ini sudah berhasil disimpan sebelumnya.';

    private const KEY_REJECTED = 'Kunci operasi ini tidak valid atau sudah dipakai. Muat ulang halaman form lalu coba lagi.';

    private Outlet $outlet;

    private Outlet $otherOutlet;

    private Warehouse $warehouse;

    private User $owner;

    private Ingredient $milk;

    private Ingredient $sugar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->outlet = Outlet::create(['name' => 'Outlet Satu', 'code' => 'O1', 'is_active' => true]);
        $this->otherOutlet = Outlet::create(['name' => 'Outlet Dua', 'code' => 'O2', 'is_active' => true]);
        $this->warehouse = Warehouse::create(['name' => 'Gudang', 'code' => 'W1', 'is_active' => true]);
        $this->owner = $this->makeUser('owner', Role::create(['name' => 'Owner', 'code' => 'owner']), [$this->outlet, $this->otherOutlet]);

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->milk = $this->makeIngredient($category, 'Milk');
        $this->sugar = $this->makeIngredient($category, 'Sugar');
        $this->stock($this->milk, 100);
        $this->stock($this->sugar, 50);
    }

    // ---- 1-4, 17-20: a new operation --------------------------------------------------------------------------

    public function test_a_new_single_item_operation_is_claimed_and_posted_once(): void
    {
        $key = (string) Str::uuid();

        $this->submit($this->payload($key, [[$this->milk, 5]]))->assertRedirect(route('backoffice.transfers.index'))->assertSessionHas('success', self::SAVED);

        $transfer = StockTransfer::firstOrFail();
        $operation = TransferOperation::firstOrFail();

        $this->assertSame($key, $operation->operation_key);
        $this->assertSame($this->owner->id, $operation->user_id);
        $this->assertSame('general_transfer', $operation->kind);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $operation->fingerprint);
        $this->assertSame([$transfer->id], $operation->transfer_ids);

        $this->assertSame(['transfer_in' => 1, 'transfer_out' => 1], $this->movementTypes());
        $this->assertSame([$transfer->id], StockMovement::pluck('reference_id')->unique()->values()->all(), 'both movements reference the transfer');
        $this->assertSame([95.0, 5.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->milk, 'outlet', $this->outlet->id)]);
        $this->assertSame('in_transit', $transfer->status);
        $this->assertSame(5.0, (float) $transfer->qty);
    }

    public function test_a_bulk_operation_is_one_record_listing_every_transfer_in_order(): void
    {
        $key = (string) Str::uuid();

        $this->submit($this->payload($key, [[$this->milk, 5], [$this->sugar, 3.5], [$this->milk, 2]]))->assertSessionHas('success', self::SAVED);

        $this->assertSame(1, TransferOperation::count(), 'the whole bulk is ONE operation, not one per item');
        $this->assertSame(3, StockTransfer::count());
        $this->assertSame(StockTransfer::orderBy('id')->pluck('id')->all(), TransferOperation::firstOrFail()->transfer_ids, 'every created id, in creation order');
        $this->assertSame(6, StockMovement::count());
        $this->assertEqualsCanonicalizing(StockTransfer::pluck('id')->all(), StockMovement::pluck('reference_id')->unique()->values()->all());
        $this->assertSame(['transfer_in' => 3, 'transfer_out' => 3], $this->movementTypes());
        $this->assertSame([93.0, 7.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->milk, 'outlet', $this->outlet->id)]);
        $this->assertSame([46.5, 3.5], [$this->qty($this->sugar, 'warehouse', $this->warehouse->id), $this->qty($this->sugar, 'outlet', $this->outlet->id)]);
        $this->assertCount(3, array_unique(StockTransfer::pluck('transfer_number')->all()), 'transfer numbers stay unique');
    }

    // ---- 5, 6, 7: replays -----------------------------------------------------------------------------------------

    public function test_a_sequential_duplicate_post_creates_nothing_more(): void
    {
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 5], [$this->sugar, 2]]);
        $this->submit($payload)->assertSessionHas('success', self::SAVED);
        $after = $this->snapshot();

        $this->submit($payload)->assertRedirect(route('backoffice.transfers.index'))->assertSessionHas('success', self::REPLAYED);

        $this->assertSame($after, $this->snapshot(), 'no transfer, movement, balance or operation row changed');
    }

    public function test_a_retry_after_commit_returns_already_saved_even_when_the_stock_is_no_longer_enough(): void
    {
        // 10 in stock, the original takes 7 -> 3 left. The identical retry would fail a stock check for 7, but it is
        // not new work: it must be recognised BEFORE any check on mutable stock.
        StockBalance::where('location_type', 'warehouse')->where('ingredient_id', $this->milk->id)->update(['qty_on_hand' => 10]);
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 7]]);
        $this->submit($payload)->assertSessionHas('success', self::SAVED);
        $this->assertSame(3.0, $this->qty($this->milk, 'warehouse', $this->warehouse->id));
        $after = $this->snapshot();

        $this->submit($payload)->assertSessionHas('success', self::REPLAYED)->assertSessionHasNoErrors();

        $this->assertSame($after, $this->snapshot());
        $this->assertSame(3.0, $this->qty($this->milk, 'warehouse', $this->warehouse->id));
    }

    public function test_a_retry_is_still_recognised_after_the_master_data_it_used_has_changed(): void
    {
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 5]]);
        $this->submit($payload)->assertSessionHas('success', self::SAVED);
        $after = $this->snapshot();

        $this->milk->delete();   // the ingredient is tombstoned afterwards

        $this->submit($payload)->assertSessionHas('success', self::REPLAYED)->assertSessionHasNoErrors();
        $this->assertSame($after, $this->snapshot());
    }

    // ---- 8, 9, 10: the same key / the same payload ---------------------------------------------------------------

    public function test_the_same_key_with_a_different_payload_is_refused_without_disclosure(): void
    {
        $key = (string) Str::uuid();
        $this->submit($this->payload($key, [[$this->milk, 5]]))->assertSessionHas('success', self::SAVED);
        $after = $this->snapshot();
        $original = StockTransfer::firstOrFail();

        $response = $this->submit($this->payload($key, [[$this->milk, 6]]));   // the quantity was edited

        $response->assertSessionHasErrors(['operation_key' => self::KEY_REJECTED]);
        $this->assertSame($after, $this->snapshot());
        $this->assertStringNotContainsString($original->transfer_number, json_encode(session('errors')->getBag('default')->all()), 'nothing about the original is disclosed');
        // The re-rendered form must not keep the burnt key, but must keep what the user typed.
        $this->assertNull(session()->getOldInput('operation_key'));
        $this->assertSame('6', (string) session()->getOldInput('items.0.qty'));
    }

    public function test_the_same_key_from_another_user_is_refused_generically(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->payload($key, [[$this->milk, 5]]);
        $this->submit($payload)->assertSessionHas('success', self::SAVED);
        $after = $this->snapshot();

        $other = $this->makeUser('owner2', Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']), [$this->outlet, $this->otherOutlet]);
        $response = $this->actingAs($other)->post(route('backoffice.transfers.store'), $payload);

        $response->assertSessionHasErrors(['operation_key' => self::KEY_REJECTED]);
        $this->assertSame($after, $this->snapshot());
        $this->assertSame($this->owner->id, TransferOperation::firstOrFail()->user_id, 'the claim still belongs to its owner');
        $this->assertStringNotContainsString($this->owner->name, json_encode(session('errors')->getBag('default')->all()));
    }

    public function test_a_different_key_with_an_identical_payload_is_a_new_operation(): void
    {
        $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 5]]))->assertSessionHas('success', self::SAVED);
        $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 5]]))->assertSessionHas('success', self::SAVED);

        $this->assertSame(2, TransferOperation::count());
        $this->assertSame(2, StockTransfer::count());
        $this->assertSame(4, StockMovement::count());
        $this->assertSame([90.0, 10.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->milk, 'outlet', $this->outlet->id)]);
        $this->assertSame(2, count(array_unique(StockTransfer::pluck('transfer_number')->all())));
    }

    // ---- 11, 12: retries after a failure leave the key usable -----------------------------------------------------

    public function test_a_retry_after_a_validation_failure_works_once_the_form_is_fixed(): void
    {
        $key = (string) Str::uuid();
        StockBalance::where('location_type', 'warehouse')->where('ingredient_id', $this->milk->id)->update(['qty_on_hand' => 4]);

        $this->submit($this->payload($key, [[$this->milk, 5]]))->assertSessionHasErrors('items.0.qty');   // 5 > 4

        $this->assertSame(0, TransferOperation::count(), 'the failed attempt left no claim behind');
        $this->assertSame(0, StockTransfer::count());
        $this->assertSame(0, StockMovement::count());
        $this->assertSame(0, StockBalance::where('location_type', 'outlet')->count(), 'and no placeholder destination balance');
        $this->assertSame($key, session()->getOldInput('operation_key'), 'the key is kept for the retry');

        $this->submit($this->payload($key, [[$this->milk, 4]]))->assertSessionHas('success', self::SAVED);   // fixed quantity, same key

        $this->assertSame(1, TransferOperation::count());
        $this->assertSame(1, StockTransfer::count());
        $this->assertSame(0.0, $this->qty($this->milk, 'warehouse', $this->warehouse->id));
    }

    public function test_a_retry_after_a_database_rollback_succeeds_exactly_once(): void
    {
        $key = (string) Str::uuid();
        $payload = $this->payload($key, [[$this->milk, 5], [$this->sugar, 2]]);
        $before = $this->snapshot();

        $seen = 0;
        StockMovement::creating(function () use (&$seen) {
            if (++$seen === 3) {   // dies halfway through the SECOND item
                throw new \RuntimeException('movement write failed');
            }
        });

        try {
            $this->withoutExceptionHandling();
            try {
                $this->submit($payload);
                $this->fail('the request must fail');
            } catch (\RuntimeException $e) {
                $this->assertSame('movement write failed', $e->getMessage());
            }
        } finally {
            StockMovement::flushEventListeners();
        }

        $this->assertSame($before, $this->snapshot(), 'claim, transfers, movements and balances all rolled back');
        $this->assertSame(0, TransferOperation::count());

        $this->submit($payload)->assertSessionHas('success', self::SAVED);   // the same key, now free

        $this->assertSame(1, TransferOperation::count());
        $this->assertSame(2, StockTransfer::count());
        $this->assertSame(4, StockMovement::count());
        $this->assertSame([95.0, 48.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->sugar, 'warehouse', $this->warehouse->id)]);
    }

    public function test_an_unrelated_unique_violation_is_not_mistaken_for_a_duplicate_operation(): void
    {
        TransferOperation::creating(function () {
            throw new UniqueConstraintViolationException('mysql', 'insert into `transfer_operations` ...', [], new \PDOException("Duplicate entry 'x' for key 'transfer_operations.some_other_index'"));
        });

        try {
            $this->expectException(UniqueConstraintViolationException::class);
            app(TransferOperationService::class)->claim((string) Str::uuid(), $this->owner->id, 'general_transfer', str_repeat('a', 64));
        } finally {
            TransferOperation::flushEventListeners();
        }
    }

    // ---- which constraint was violated: the driver's answer, never the SQL text --------------------------------------

    /** A violation exactly as Laravel reports it: the driver message + the whole INSERT statement (which names operation_key). */
    private function uniqueViolation(string $driverMessage, int $code = 1062, string $sqlState = '23000', ?array $errorInfo = ['default']): UniqueConstraintViolationException
    {
        $driver = new \PDOException("SQLSTATE[{$sqlState}]: Integrity constraint violation: {$code} {$driverMessage}");
        $driver->errorInfo = $errorInfo === ['default'] ? [$sqlState, $code, $driverMessage] : $errorInfo;

        return new UniqueConstraintViolationException(
            'mysql',
            'insert into `transfer_operations` (`operation_key`, `user_id`, `kind`, `fingerprint`, `transfer_ids`, `updated_at`, `created_at`) values (?, ?, ?, ?, ?, ?, ?)',
            ['0a1b2c3d-0000-4000-8000-000000000009', 1, 'general_transfer', str_repeat('a', 64), '[]', '2026-10-10 20:00:00', '2026-10-10 20:00:00'],
            $driver,
            ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'atg_pos']
        );
    }

    /** Pretends the INSERT of the claim hits the given violation, while a committed operation with this key really exists. */
    private function existingOperationWhoseInsertViolates(UniqueConstraintViolationException $violation, ?string $fingerprint = null, ?int $userId = null, string $kind = 'general_transfer'): array
    {
        $key = (string) Str::uuid();
        $fingerprint ??= str_repeat('a', 64);
        TransferOperation::create(['operation_key' => $key, 'user_id' => $userId ?? $this->owner->id, 'kind' => 'general_transfer', 'fingerprint' => str_repeat('a', 64), 'transfer_ids' => [7]]);
        TransferOperation::creating(function () use ($violation) {
            throw $violation;
        });

        return [$key, $fingerprint, $userId ?? $this->owner->id, $kind];
    }

    public static function operationKeyViolations(): array
    {
        return [
            'MySQL 8 qualifies the index with its table' => [1062, "Duplicate entry '0a1b2c3d-0000-4000-8000-000000000009' for key 'transfer_operations.transfer_operations_operation_key_unique'"],
            'MariaDB / older MySQL name the index alone' => [1062, "Duplicate entry '0a1b2c3d-0000-4000-8000-000000000009' for key 'transfer_operations_operation_key_unique'"],
            'SQLite names the column' => [19, 'UNIQUE constraint failed: transfer_operations.operation_key'],
        ];
    }

    #[DataProvider('operationKeyViolations')]
    public function test_a_violation_of_the_operation_key_index_is_recognized_as_a_replay(int $code, string $driverMessage): void
    {
        [$key, $fingerprint, $userId, $kind] = $this->existingOperationWhoseInsertViolates($this->uniqueViolation($driverMessage, $code));

        try {
            [$operation, $isNew] = app(TransferOperationService::class)->claim($key, $userId, $kind, $fingerprint);
        } finally {
            TransferOperation::flushEventListeners();
        }

        $this->assertFalse($isNew, 'a replay, not new work');
        $this->assertSame($key, $operation->operation_key);
        $this->assertSame([7], $operation->transfer_ids, 'the committed record is what comes back');
    }

    public static function otherConstraintViolations(): array
    {
        $key = "'0a1b2c3d-0000-4000-8000-000000000009'";

        return [
            'another index of the same table (MySQL 8)' => [1062, "Duplicate entry 'abc' for key 'transfer_operations.transfer_operations_fingerprint_unique'", '23000', ['default']],
            'another index of the same table (MariaDB)' => [1062, "Duplicate entry 'abc' for key 'transfer_operations_fingerprint_unique'", '23000', ['default']],
            'the primary key' => [1062, "Duplicate entry '7' for key 'transfer_operations.PRIMARY'", '23000', ['default']],
            'an index of ANOTHER table with a similar name' => [1062, "Duplicate entry 'TRF-1' for key 'stock_transfers.stock_transfers_transfer_number_unique'", '23000', ['default']],
            'the duplicate VALUE spells the operation-key index name' => [1062, "Duplicate entry 'transfer_operations_operation_key_unique' for key 'transfer_operations.transfer_operations_other_unique'", '23000', ['default']],
            'SQLite: another column' => [19, 'UNIQUE constraint failed: transfer_operations.fingerprint', '23000', ['default']],
            'SQLite: a composite that merely includes operation_key' => [19, 'UNIQUE constraint failed: transfer_operations.operation_key, transfer_operations.kind', '23000', ['default']],
            'a right-looking message with another SQLSTATE' => [1062, "Duplicate entry 'x' for key 'transfer_operations.transfer_operations_operation_key_unique'", 'HY000', ['default']],
            'no driver error information at all' => [1062, "Duplicate entry 'x' for key 'transfer_operations.transfer_operations_operation_key_unique'", '23000', null],
            'a driver this code does not know (PostgreSQL)' => [7, 'duplicate key value violates unique constraint "transfer_operations_operation_key_unique"', '23505', ['default']],
        ];
    }

    #[DataProvider('otherConstraintViolations')]
    public function test_a_violation_of_any_other_constraint_is_not_a_replay_even_though_the_formatted_sql_names_operation_key(int $code, string $driverMessage, string $sqlState, ?array $errorInfo): void
    {
        $violation = $this->uniqueViolation($driverMessage, $code, $sqlState, $errorInfo);
        $this->assertStringContainsString('operation_key', $violation->getMessage(), 'precondition: the formatted message DOES name the column (this is what fooled the first version)');

        // A committed operation with this very key, user, kind and fingerprint exists: a wrong classification would
        // look like a perfectly good replay. It must surface as the genuine database error instead.
        [$key, $fingerprint, $userId, $kind] = $this->existingOperationWhoseInsertViolates($violation);

        $thrown = null;
        try {
            app(TransferOperationService::class)->claim($key, $userId, $kind, $fingerprint);
        } catch (\Throwable $e) {
            $thrown = $e;
        } finally {
            TransferOperation::flushEventListeners();
        }

        $this->assertSame($violation, $thrown, 'the original database exception, untouched');
    }

    #[DataProvider('operationKeyViolations')]
    public function test_a_recognized_replay_with_another_payload_user_or_kind_is_still_refused(int $code, string $driverMessage): void
    {
        $other = $this->makeUser('someone-else', Role::firstOrCreate(['code' => 'owner'], ['name' => 'Owner']), [$this->outlet]);

        foreach ([
            'another payload' => ['fingerprint' => str_repeat('b', 64), 'user' => $this->owner->id, 'kind' => 'general_transfer'],
            'another user' => ['fingerprint' => str_repeat('a', 64), 'user' => $other->id, 'kind' => 'general_transfer'],
            'another kind' => ['fingerprint' => str_repeat('a', 64), 'user' => $this->owner->id, 'kind' => 'warehouse_transfer'],
        ] as $label => $claim) {
            [$key] = $this->existingOperationWhoseInsertViolates($this->uniqueViolation($driverMessage, $code));

            try {
                app(TransferOperationService::class)->claim($key, $claim['user'], $claim['kind'], $claim['fingerprint']);
                $this->fail("{$label}: must be refused");
            } catch (TransferOperationConflict) {
                $this->assertTrue(true);
            } finally {
                TransferOperation::flushEventListeners();
            }
        }
    }

    public function test_an_unrelated_unique_violation_stays_a_genuine_database_error_through_the_whole_request(): void
    {
        $violation = $this->uniqueViolation("Duplicate entry 'abc' for key 'transfer_operations.transfer_operations_fingerprint_unique'");
        TransferOperation::creating(function () use ($violation) {
            throw $violation;
        });
        $before = $this->snapshot();

        try {
            $this->withoutExceptionHandling();
            try {
                $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 5]]));
                $this->fail('the database error must surface, not be turned into a replay or a friendly message');
            } catch (UniqueConstraintViolationException $e) {
                $this->assertSame($violation, $e);
            }
        } finally {
            TransferOperation::flushEventListeners();
        }

        $this->assertSame($before, $this->snapshot(), 'nothing was posted');
    }

    public function test_a_lock_wait_timeout_on_the_claim_becomes_a_controlled_retry_message(): void
    {
        $key = (string) Str::uuid();
        TransferOperation::creating(function () {
            throw new QueryException('mysql', 'insert into `transfer_operations` ...', [], new \PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction'));
        });

        try {
            $response = $this->submit($this->payload($key, [[$this->milk, 5]]));
        } finally {
            TransferOperation::flushEventListeners();
        }

        $response->assertSessionHasErrors(['operation_key' => (new TransferOperationBusy())->getMessage()]);
        $this->assertSame($key, session()->getOldInput('operation_key'), 'the same form can be submitted again');
        $this->assertSame(0, StockTransfer::count());
        $this->assertSame(0, TransferOperation::count());
    }

    // ---- 13, 14: the key is mandatory -----------------------------------------------------------------------------

    public function test_a_missing_operation_key_is_refused_and_nothing_is_created(): void
    {
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 5]]);
        unset($payload['operation_key']);
        $before = $this->snapshot();

        $this->submit($payload)->assertSessionHasErrors('operation_key');

        $this->assertSame($before, $this->snapshot());
    }

    public function test_an_invalid_operation_key_is_refused_and_nothing_is_created(): void
    {
        $before = $this->snapshot();

        foreach (['not-a-uuid', '1234', str_repeat('a', 36), '12345678-1234-1234-1234-12345678901g', ['x'], ''] as $bad) {
            $this->submit($this->payload($bad, [[$this->milk, 5]]))->assertSessionHasErrors('operation_key');
        }

        $this->assertSame($before, $this->snapshot());
    }

    // ---- 15, 16: the form -------------------------------------------------------------------------------------------

    public function test_every_rendered_create_form_gets_its_own_key_in_a_hidden_field(): void
    {
        $keys = [];
        foreach ([1, 2, 3] as $_) {
            $html = $this->actingAs($this->owner)->get(route('backoffice.transfers.create'))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/<input type="hidden" name="operation_key" value="([0-9a-f\-]{36})">/', $html);
            preg_match('/name="operation_key" value="([0-9a-f\-]{36})"/', $html, $m);
            $this->assertTrue(Str::isUuid($m[1]));
            $keys[] = $m[1];
        }

        $this->assertCount(3, array_unique($keys), 'a reload is a new operation');
        $this->assertStringContainsString('name="_token"', $html, 'CSRF protection is untouched');
    }

    public function test_a_validation_failure_re_renders_the_same_key_but_a_junk_key_is_replaced(): void
    {
        $key = (string) Str::uuid();
        $bad = $this->payload($key, [[$this->milk, 5]]);
        $bad['sender_name'] = '';   // fails validation

        $this->from(route('backoffice.transfers.create'))->actingAs($this->owner)->post(route('backoffice.transfers.store'), $bad)
            ->assertRedirect(route('backoffice.transfers.create'))->assertSessionHasErrors('sender_name');
        $this->get(route('backoffice.transfers.create'))->assertSee('name="operation_key" value="'.$key.'"', false);

        $junk = $this->payload('<script>alert(1)</script>', [[$this->milk, 5]]);
        $this->from(route('backoffice.transfers.create'))->post(route('backoffice.transfers.store'), $junk)->assertSessionHasErrors('operation_key');
        $html = $this->get(route('backoffice.transfers.create'))->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertMatchesRegularExpression('/name="operation_key" value="[0-9a-f\-]{36}"/', $html);
    }

    // ---- 21, 22: authorization is current, also for a replay ---------------------------------------------------------

    public function test_outlet_authorization_is_checked_before_any_claim(): void
    {
        $limited = $this->limitedUser('admin-o1', $this->outlet);
        $key = (string) Str::uuid();
        $before = $this->snapshot();

        $this->actingAs($limited)->post(route('backoffice.transfers.store'), $this->payload($key, [[$this->milk, 5]], to: 'outlet:'.$this->otherOutlet->id))
            ->assertSessionHasErrors('from_location');

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, TransferOperation::count(), 'a refused request claims nothing');
    }

    public function test_a_replay_cannot_get_past_the_users_current_outlet_scope(): void
    {
        $limited = $this->limitedUser('admin-o1', $this->outlet);
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 5]]);   // warehouse -> their outlet
        $this->actingAs($limited)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);
        $after = $this->snapshot();

        // Their access moves to another outlet; the old key must not open the door again.
        $limited->outlets()->sync([$this->otherOutlet->id]);
        $limited->update(['outlet_id' => $this->otherOutlet->id]);

        $response = $this->actingAs($limited->fresh())->post(route('backoffice.transfers.store'), $payload);

        $response->assertSessionHasErrors('from_location');
        $this->assertSame($after, $this->snapshot());
        $this->assertNull(session('success'));
    }

    public function test_an_admin_outlet_with_access_can_create_and_replay(): void
    {
        $limited = $this->limitedUser('admin-o1', $this->outlet);
        $payload = $this->payload((string) Str::uuid(), [[$this->milk, 5]]);

        $this->actingAs($limited)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::SAVED);
        $this->actingAs($limited)->post(route('backoffice.transfers.store'), $payload)->assertSessionHas('success', self::REPLAYED);

        $this->assertSame(1, StockTransfer::count());
        $this->assertSame($limited->id, TransferOperation::firstOrFail()->user_id);
    }

    // ---- quantity precision and misc validation ---------------------------------------------------------------------

    public function test_a_quantity_the_database_cannot_store_exactly_is_refused_instead_of_rounded(): void
    {
        $before = $this->snapshot();

        foreach (['1.239', '0.005', '1e1', '1,5'] as $qty) {
            $this->submit($this->payload((string) Str::uuid(), [[$this->milk, $qty]]))->assertSessionHasErrors('items.0.qty');
        }

        $this->assertSame($before, $this->snapshot());

        foreach (['5', '5.5', '5.50', '0.01'] as $qty) {
            $this->submit($this->payload((string) Str::uuid(), [[$this->milk, $qty]]))->assertSessionHasNoErrors();
        }
        $this->assertSame(4, StockTransfer::count());
    }

    public function test_the_equivalent_spelling_of_a_quantity_is_the_same_operation(): void
    {
        $key = (string) Str::uuid();
        $this->submit($this->payload($key, [[$this->milk, '5']]))->assertSessionHas('success', self::SAVED);

        $this->submit($this->payload($key, [[$this->milk, '5.00']]))->assertSessionHas('success', self::REPLAYED);

        $this->assertSame(1, StockTransfer::count());
    }

    public function test_a_stock_failure_between_two_competing_operations_is_a_validation_error_not_a_server_error(): void
    {
        StockBalance::where('location_type', 'warehouse')->where('ingredient_id', $this->milk->id)->update(['qty_on_hand' => 10]);
        $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 8]]))->assertSessionHas('success', self::SAVED);

        $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 7]]))->assertStatus(302)->assertSessionHasErrors('items.0.qty');

        $this->assertSame(1, TransferOperation::count(), 'the loser left no claim');
        $this->assertSame(2.0, $this->qty($this->milk, 'warehouse', $this->warehouse->id));
    }

    public function test_the_status_actions_of_an_idempotently_created_transfer_still_work(): void
    {
        $this->submit($this->payload((string) Str::uuid(), [[$this->milk, 5]]));
        $transfer = StockTransfer::firstOrFail();

        $this->actingAs($this->owner)->post(route('backoffice.transfers.mark-cancelled', $transfer))->assertSessionHas('success');
        $this->assertSame([100.0, 0.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->milk, 'outlet', $this->outlet->id)]);
        $this->actingAs($this->owner)->post(route('backoffice.transfers.mark-in-transit', $transfer))->assertSessionHas('success');
        $this->assertSame([95.0, 5.0], [$this->qty($this->milk, 'warehouse', $this->warehouse->id), $this->qty($this->milk, 'outlet', $this->outlet->id)]);
        $this->assertSame(1, TransferOperation::count(), 'status changes never touch the operation record');
        $this->assertSame([$transfer->id], TransferOperation::firstOrFail()->transfer_ids);
    }

    // ---- helpers -------------------------------------------------------------------------------------------------------

    private function submit(array $payload)
    {
        return $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $payload);
    }

    /** @param  array<int, array{0: Ingredient, 1: int|float|string}>  $lines */
    private function payload(mixed $key, array $lines, ?string $from = null, ?string $to = null): array
    {
        return [
            'operation_key' => $key,
            'from_location' => $from ?? 'warehouse:'.$this->warehouse->id,
            'to_location' => $to ?? 'outlet:'.$this->outlet->id,
            'sender_name' => 'Owner',
            'receiver_name' => '',
            'items' => array_map(fn ($line) => ['ingredient_id' => $line[0]->id, 'qty' => $line[1]], $lines),
        ];
    }

    private function stock(Ingredient $ingredient, float $qty): void
    {
        StockBalance::create(['ingredient_id' => $ingredient->id, 'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'qty_on_hand' => $qty]);
    }

    private function qty(Ingredient $ingredient, string $type, int $id): float
    {
        return (float) StockBalance::where(['ingredient_id' => $ingredient->id, 'location_type' => $type, 'location_id' => $id])->value('qty_on_hand');
    }

    private function movementTypes(): array
    {
        return StockMovement::orderBy('id')->get()->groupBy('movement_type')->map->count()->sortKeys()->all();
    }

    private function snapshot(): array
    {
        return [
            'balances' => StockBalance::orderBy('id')->get(['id', 'ingredient_id', 'location_type', 'location_id', 'qty_on_hand'])->toArray(),
            'movements' => StockMovement::orderBy('id')->get(['id', 'movement_type', 'qty_in', 'qty_out', 'reference_id'])->toArray(),
            'transfers' => StockTransfer::orderBy('id')->get(['id', 'transfer_number', 'status', 'qty'])->toArray(),
            'operations' => TransferOperation::orderBy('id')->get(['id', 'operation_key', 'user_id', 'fingerprint', 'transfer_ids'])->toArray(),
        ];
    }

    private function makeIngredient(IngredientCategory $category, string $name): Ingredient
    {
        return Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => $name, 'code' => strtoupper($name), 'unit' => 'ml',
            'ingredient_type' => Ingredient::TYPE_RAW, 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
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
