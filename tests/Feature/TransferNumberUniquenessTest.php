<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Outlet;
use App\Models\StockTransfer;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UX-H: the intermittent TransferAndInventoryLocationTest failure, and its hardening.
 *
 * Transfer numbers are TRF-<second>-<3 random digits> on a unique column. Rows created in the same second
 * collided about 1 time in 900 per pair, which surfaced as a 500. A pre-check ("is this candidate free?")
 * is not atomic: two requests can both see a candidate as free. The unique index is the final authority and
 * an auto-generated number that loses the race at INSERT time is retried (bounded) with a fresh number.
 */
class TransferNumberUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private Ingredient $ingredient;

    private Outlet $outlet;

    protected function setUp(): void
    {
        parent::setUp();

        $category = IngredientCategory::create(['name' => 'Dairy', 'code' => 'DAIRY', 'is_active' => true]);
        $this->ingredient = Ingredient::create([
            'ingredient_category_id' => $category->id, 'name' => 'Milk', 'code' => 'MILK', 'unit' => 'ml',
            'ingredient_type' => 'raw', 'minimum_stock' => 0, 'cost_per_unit' => 1, 'is_active' => true,
        ]);
        $this->outlet = Outlet::create(['name' => 'Alpha', 'code' => 'A', 'is_active' => true]);
        ScriptedNumberTransfer::$numbers = [];
        ScriptedNumberTransfer::$draws = 0;
    }

    public function test_many_transfers_created_within_the_same_second_get_unique_numbers_in_the_same_format(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');

        // 300 rows in one frozen second: without any collision handling a collision is practically certain
        // (birthday problem over 900 values); here every number is distinct.
        for ($i = 0; $i < 300; $i++) {
            $this->transfer();
        }

        $numbers = StockTransfer::pluck('transfer_number');

        $this->assertCount(300, $numbers->unique());
        $numbers->each(fn ($number) => $this->assertMatchesRegularExpression('/^TRF-20260615100000-\d{3}$/', $number));

        Carbon::setTestNow();
    }

    public function test_a_real_race_between_the_free_check_and_the_insert_is_retried_with_a_fresh_number(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        $raced = null;
        StockTransfer::query()->exists();   // boot the model first so its own creating hook is registered before ours

        // Request B wins the window between "candidate is free" and "insert": it takes the very number
        // request A just chose, once. (The model's own creating hook has already run when this fires.)
        StockTransfer::creating(function (StockTransfer $transfer) use (&$raced) {
            if ($raced === null && $transfer->transfer_number) {
                $raced = $transfer->transfer_number;
                DB::table('stock_transfers')->insert(['transfer_number' => $raced, 'ingredient_id' => $this->ingredient->id, 'qty' => 1]);
            }
        });

        $created = $this->transfer();

        $this->assertNotNull($raced);
        $this->assertNotSame($raced, $created->transfer_number, 'request A got a different number than the one B took');
        $this->assertMatchesRegularExpression('/^TRF-20260615100000-\d{3}$/', $created->transfer_number);
        $this->assertSame($created->transfer_number, $created->fresh()->transfer_number, 'the stored row carries the retried number');
        $this->assertSame(1, StockTransfer::where('transfer_number', $created->transfer_number)->count());
        // (Request B is simulated on the same connection here, inside A's savepoint, so rolling A's failed
        // attempt back also discards B's stand-in row. In production B is its own connection and commit.)

        Carbon::setTestNow();
    }

    public function test_a_forced_first_candidate_collision_retries_successfully(): void
    {
        $this->transfer('TRF-20260615100000-111');
        ScriptedNumberTransfer::$numbers = ['TRF-20260615100000-111', 'TRF-20260615100000-222'];

        $created = ScriptedNumberTransfer::create(['ingredient_id' => $this->ingredient->id, 'outlet_id' => $this->outlet->id, 'qty' => 1]);

        $this->assertSame('TRF-20260615100000-222', $created->transfer_number);
        $this->assertSame(2, ScriptedNumberTransfer::$draws, 'one collision, one fresh draw');
        $this->assertSame(['TRF-20260615100000-111', 'TRF-20260615100000-222'], StockTransfer::orderBy('id')->pluck('transfer_number')->all());
    }

    public function test_the_retry_is_bounded_and_the_last_collision_is_thrown_unchanged(): void
    {
        $this->transfer('TRF-20260615100000-111');
        ScriptedNumberTransfer::$numbers = array_fill(0, 50, 'TRF-20260615100000-111');

        try {
            ScriptedNumberTransfer::create(['ingredient_id' => $this->ingredient->id, 'outlet_id' => $this->outlet->id, 'qty' => 1]);
            $this->fail('A permanent collision must surface as the original unique violation.');
        } catch (UniqueConstraintViolationException $e) {
            $this->assertStringContainsString('transfer_number', $e->getMessage());
        }

        $this->assertSame(StockTransfer::NUMBER_INSERT_ATTEMPTS, ScriptedNumberTransfer::$draws, 'never more than the bounded number of attempts');
        $this->assertSame(1, StockTransfer::count(), 'nothing half-written');
    }

    public function test_an_unrelated_database_error_is_not_swallowed_or_retried(): void
    {
        ScriptedNumberTransfer::$numbers = ['TRF-20260615100000-333', 'TRF-20260615100000-444'];

        try {
            // ingredient_id is NOT NULL: a database error that is not a transfer_number collision.
            ScriptedNumberTransfer::create(['ingredient_id' => null, 'outlet_id' => $this->outlet->id, 'qty' => 1]);
            $this->fail('An unrelated QueryException must be thrown.');
        } catch (QueryException $e) {
            $this->assertNotInstanceOf(UniqueConstraintViolationException::class, $e);
        }

        $this->assertSame(1, ScriptedNumberTransfer::$draws, 'no retry for an unrelated error');
        $this->assertSame(0, StockTransfer::count());
    }

    public function test_a_number_supplied_by_the_caller_is_never_replaced_or_retried(): void
    {
        $this->transfer('TRF-MANUAL-1');
        ScriptedNumberTransfer::$numbers = ['TRF-20260615100000-555'];

        $this->expectException(UniqueConstraintViolationException::class);

        try {
            ScriptedNumberTransfer::create(['transfer_number' => 'TRF-MANUAL-1', 'ingredient_id' => $this->ingredient->id, 'outlet_id' => $this->outlet->id, 'qty' => 1]);
        } finally {
            $this->assertSame(0, ScriptedNumberTransfer::$draws, 'the supplied number is not regenerated');
            $this->assertSame(1, StockTransfer::count());
        }
    }

    public function test_a_collision_inside_the_callers_transaction_does_not_break_that_transaction(): void
    {
        // Both transfer screens create their rows inside DB::transaction(); the retry must work there and
        // leave the surrounding work intact (it runs in a savepoint).
        $this->transfer('TRF-20260615100000-111');
        ScriptedNumberTransfer::$numbers = ['TRF-20260615100000-111', 'TRF-20260615100000-222', 'TRF-20260615100000-333'];

        DB::transaction(function () {
            $first = ScriptedNumberTransfer::create(['ingredient_id' => $this->ingredient->id, 'outlet_id' => $this->outlet->id, 'qty' => 1]);
            $second = ScriptedNumberTransfer::create(['ingredient_id' => $this->ingredient->id, 'outlet_id' => $this->outlet->id, 'qty' => 2]);

            $this->assertSame('TRF-20260615100000-222', $first->transfer_number);
            $this->assertSame('TRF-20260615100000-333', $second->transfer_number);
        });

        $this->assertSame(3, StockTransfer::count());
        $this->assertCount(3, StockTransfer::pluck('transfer_number')->unique());
    }

    private function transfer(?string $number = null): StockTransfer
    {
        return StockTransfer::create(array_filter([
            'transfer_number' => $number,
            'ingredient_id' => $this->ingredient->id,
            'outlet_id' => $this->outlet->id,
            'qty' => 1,
        ], fn ($value) => $value !== null));
    }
}

/**
 * StockTransfer whose candidate numbers are scripted, to force a collision at INSERT time deterministically
 * (the real pre-check would otherwise steer around an existing number). Same table, same insert path.
 */
class ScriptedNumberTransfer extends StockTransfer
{
    protected $table = 'stock_transfers';

    /** @var string[] */
    public static array $numbers = [];

    public static int $draws = 0;

    public static function freshTransferNumber(): string
    {
        self::$draws++;

        return array_shift(self::$numbers) ?? 'TRF-20260615100000-999';
    }
}
