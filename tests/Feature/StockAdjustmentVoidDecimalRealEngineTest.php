<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\StockAdjustment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\RealEngineHarness;
use Tests\TestCase;

/**
 * Non-integer quantities against REAL MySQL DECIMAL storage (see RealEngineHarness; skipped otherwise).
 *
 * The VOID service works in exact hundredths, but the adjustment WRITER computes the difference in PHP floats
 * (10.30 - 10.10 is 0.19999999999999929 as a float), and only MySQL's DECIMAL(12,2)/(14,2) columns turn that into
 * the stored 0.20. SQLite cannot show that, so these tests read the stored values back exactly as MySQL returns them
 * (DECIMAL comes back as text, "10.10") and compare TEXT or exact hundredths, never floats.
 */
class StockAdjustmentVoidDecimalRealEngineTest extends TestCase
{
    use RealEngineHarness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRealEngine();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->cleanupRealEngine();
        parent::tearDown();
    }

    public function test_case_1_a_positive_decimal_adjustment_is_reversed_exactly(): void
    {
        $this->seedExact($this->ingredient, '10.10');
        $adjustment = $this->adjust([$this->ingredient->id => '10.30']);
        $original = $this->movement($adjustment->items()->sole()->stock_movement_id);

        // Stored by MySQL as the exact decimal, not 0.19999999999999929.
        $this->assertSame(['0.20', '0.00'], [$original->qty_in, $original->qty_out]);
        $this->assertSame('10.30', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id));
        $item = $adjustment->items()->sole();
        $this->assertSame(['10.10', '10.30', '0.20'], [$item->getRawOriginal('system_qty'), $item->getRawOriginal('actual_qty'), $item->getRawOriginal('difference')]);
        $movementsBefore = DB::table('stock_movements')->count();

        $this->voidIt($adjustment);

        $this->assertSame('10.10', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id));
        $item = $item->fresh();
        $reversal = $this->movement($item->void_stock_movement_id);
        $this->assertSame(['0.00', '0.20'], [$reversal->qty_in, $reversal->qty_out]);
        $this->assertSame(['stock_adjustment', 'manual_adjustment_void', $adjustment->id], [$reversal->movement_type, $reversal->reference_type, (int) $reversal->reference_id]);
        $this->assertNotSame($item->stock_movement_id, $item->void_stock_movement_id, 'original and reversal are two different movements');
        $this->assertEquals($original, $this->movement($item->stock_movement_id), 'the original movement is unchanged to the last digit');
        $this->assertSame('void', $adjustment->fresh()->status);
        $this->assertSame($movementsBefore + 1, DB::table('stock_movements')->count(), 'exactly one new movement');
        $this->assertSame(1, DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->count());
    }

    public function test_case_2_a_negative_decimal_adjustment_is_reversed_exactly(): void
    {
        $this->seedExact($this->ingredient, '10.30');
        $adjustment = $this->adjust([$this->ingredient->id => '10.10']);
        $item = $adjustment->items()->sole();
        $original = $this->movement($item->stock_movement_id);

        $this->assertSame(['0.00', '0.20'], [$original->qty_in, $original->qty_out]);
        $this->assertSame('10.10', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertSame(['10.30', '10.10', '-0.20'], [$item->getRawOriginal('system_qty'), $item->getRawOriginal('actual_qty'), $item->getRawOriginal('difference')]);

        $this->voidIt($adjustment);

        $this->assertSame('10.30', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id));
        $reversal = $this->movement($item->fresh()->void_stock_movement_id);
        $this->assertSame(['0.20', '0.00'], [$reversal->qty_in, $reversal->qty_out]);
        $this->assertEquals($original, $this->movement($item->stock_movement_id));
        $this->assertSame('void', $adjustment->fresh()->status);
        $this->assertSame(1, DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->count());
    }

    public function test_case_3_a_multi_item_decimal_adjustment_reconciles_every_item_exactly(): void
    {
        $category = IngredientCategory::firstOrFail();
        $teh = $this->makeIngredient($category, 'Teh', 'gram');
        $boba = $this->makeIngredient($category, 'Boba', 'gram');
        $start = [
            [$this->ingredient, '10.10', '10.30', '0.20'],   // + small
            [$this->ingredient2, '3.33', '7.77', '4.44'],    // + larger, repeating-looking decimals
            [$teh, '100.05', '99.95', '-0.10'],              // - small
            [$boba, '0.07', '0.00', '-0.07'],                // - down to exactly zero
        ];
        foreach ($start as [$ingredient, $qty]) {
            $this->seedExact($ingredient, $qty);
        }

        $adjustment = $this->adjust(collect($start)->mapWithKeys(fn ($row) => [$row[0]->id => $row[2]])->all());
        foreach ($start as [$ingredient, , $actual, $delta]) {
            $this->assertSame($actual, $this->balanceExact($ingredient, 'warehouse', $this->warehouse->id), "{$ingredient->name} after the adjustment");
            $item = $adjustment->items()->where('ingredient_id', $ingredient->id)->sole();
            $this->assertSame($delta, $item->getRawOriginal('difference'), "{$ingredient->name} stored difference");
        }

        $this->voidIt($adjustment);

        foreach ($start as [$ingredient, $qty, , $delta]) {
            $this->assertSame($qty, $this->balanceExact($ingredient, 'warehouse', $this->warehouse->id), "{$ingredient->name} back to its starting stock, exactly");
            $item = $adjustment->items()->where('ingredient_id', $ingredient->id)->sole();
            $reversal = $this->movement($item->void_stock_movement_id);
            $this->assertSame(-$this->hundredths($delta), $this->hundredths($reversal->qty_in) - $this->hundredths($reversal->qty_out), "{$ingredient->name} reversal is exactly the opposite delta (hundredths)");
        }
        $this->assertSame(4, DB::table('stock_movements')->where('reference_type', 'manual_adjustment_void')->count(), 'one reversal per item, no more');
    }

    public function test_case_4_a_later_movement_before_the_void_is_kept_and_the_balance_is_never_reset(): void
    {
        $this->seedExact($this->ingredient, '10.10');
        $adjustment = $this->adjust([$this->ingredient->id => '10.30']); // +0.20 -> 10.30

        // A later stock change (0.15 used), written with MySQL's own decimal arithmetic.
        DB::update('update stock_balances set qty_on_hand = qty_on_hand - ? where ingredient_id = ?', ['0.15', $this->ingredient->id]);
        DB::table('stock_movements')->insert([
            'ingredient_id' => $this->ingredient->id, 'location_type' => 'warehouse', 'location_id' => $this->warehouse->id,
            'movement_type' => 'sales_usage', 'qty_in' => '0.00', 'qty_out' => '0.15', 'reference_type' => 'sales_transaction', 'reference_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame('10.15', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id));

        $this->voidIt($adjustment);

        $balance = $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id);
        $this->assertSame('9.95', $balance, 'the original +0.20 is taken off the CURRENT 10.15');
        $this->assertNotSame('10.10', $balance, 'never reset to system_qty');
    }

    public function test_case_5_stock_summary_uses_exact_decimals_and_splits_the_original_and_the_reversal_by_period(): void
    {
        Carbon::setTestNow('2026-10-01 08:00:00');
        $this->seedExact($this->ingredient, '10.10');
        $adjustment = $this->adjust([$this->ingredient->id => '10.30']); // +0.20 on 1 Oct

        Carbon::setTestNow('2026-10-05 08:00:00');
        $this->voidIt($adjustment->fresh()); // -0.20 on 5 Oct

        $original = $this->movement($adjustment->items()->sole()->stock_movement_id);
        $reversal = $this->movement($adjustment->items()->sole()->void_stock_movement_id);
        $this->assertSame(['2026-10-01', '2026-10-05'], [substr($original->created_at, 0, 10), substr($reversal->created_at, 0, 10)], 'the original keeps its date; the reversal is posted on the VOID day');

        $periods = [
            'original period only' => [['2026-10-01', '2026-10-03'], '0.20'],
            'reversal period only' => [['2026-10-04', '2026-10-31'], '-0.20'],
            'both periods' => [['2026-10-01', '2026-10-31'], '0.00'],
        ];

        foreach ($periods as $label => [[$from, $to], $expectedAdjustment]) {
            $row = $this->summaryRow(['summary_date_from' => $from, 'summary_date_to' => $to]);

            $this->assertSame($expectedAdjustment, $this->fixed2($row['adjustment']), "{$label}: adjustment column");
            // The database's own exact sum of the same movements agrees.
            $exact = DB::table('stock_movements')->where(['ingredient_id' => $this->ingredient->id, 'movement_type' => 'stock_adjustment', 'location_type' => 'warehouse'])
                ->whereBetween('created_at', ["{$from} 00:00:00", "{$to} 23:59:59"])
                ->selectRaw('COALESCE(SUM(qty_in),0) - COALESCE(SUM(qty_out),0) as net')->value('net');
            $this->assertSame($this->fixed2($exact), $this->fixed2($row['adjustment']), "{$label}: matches MySQL's exact SUM");
            $this->assertSame('10.10', $this->fixed2($row['ending_stock']), "{$label}: ending stock is the stored balance");
            $this->assertSame(
                $this->hundredths($this->fixed2($row['ending_stock'])),
                $this->hundredths($this->fixed2($row['opening_balance'])) + $this->hundredths($this->fixed2($row['purchase'])) + $this->hundredths($this->fixed2($row['transfer']))
                    - $this->hundredths($this->fixed2($row['sales'])) + $this->hundredths($this->fixed2($row['adjustment'])),
                "{$label}: opening + purchase + transfer - sales + adjustment = ending, exactly"
            );
        }
    }

    public function test_input_with_more_than_two_decimals_never_corrupts_the_books(): void
    {
        // The form accepts any numeric quantity. 10.105 cannot be stored exactly in DECIMAL(.,2): the VOID either reverses
        // exactly what was stored or refuses and changes nothing. Both are safe; a wrong balance is not.
        $this->seedExact($this->ingredient, '10.10');
        $afterSeed = $this->lastMovementId();
        $adjustment = $this->adjust([$this->ingredient->id => '10.105']);
        $storedBalance = $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id);
        $afterAdjustment = $this->lastMovementId();

        $this->actingAs($this->owner)->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => 'x', 'confirm' => '1']);

        if ($adjustment->fresh()->status === 'void') {
            $this->assertSame('10.10', $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id), 'reversed exactly what was stored');
        } else {
            $this->assertSame($storedBalance, $this->balanceExact($this->ingredient, 'warehouse', $this->warehouse->id), 'refused: nothing changed');
            $this->assertSame($afterAdjustment, $this->lastMovementId(), 'refused: no movement written');
        }
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '10.10', $afterSeed, 'the balance still equals the opening stock plus the ledger');
    }

    // ---- helpers ----------------------------------------------------------------------------------------------

    /** Opening stock in the warehouse as text, with its opening_balance movement, like a real import. */
    private function seedExact(Ingredient $ingredient, string $qty): void
    {
        $this->seedBalance($ingredient, 'warehouse', $this->warehouse->id, $qty);
        DB::table('stock_movements')->insert([
            'ingredient_id' => $ingredient->id, 'location_type' => 'warehouse', 'location_id' => $this->warehouse->id,
            'movement_type' => 'opening_balance', 'qty_in' => $qty, 'qty_out' => '0.00', 'reference_type' => 'import_opening_stock',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @param  array<int, string>  $actualByIngredient  actual quantities as text, exactly as a user would type them */
    private function adjust(array $actualByIngredient): StockAdjustment
    {
        $this->actingAs($this->owner)->post(route('backoffice.stock-balances.adjustment.store'), [
            'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'note' => 'decimal test',
            'items' => collect($actualByIngredient)->map(fn ($actual, $id) => ['ingredient_id' => $id, 'actual_qty' => $actual])->values()->all(),
        ])->assertRedirect();

        return StockAdjustment::orderByDesc('id')->firstOrFail();
    }

    private function voidIt(StockAdjustment $adjustment): void
    {
        $this->actingAs($this->owner)
            ->post(route('backoffice.stock-adjustments.void', $adjustment), ['void_reason' => 'Salah hitung', 'confirm' => '1'])
            ->assertSessionHas('success');
    }

    private function movement(int $id): object
    {
        return DB::table('stock_movements')->where('id', $id)->firstOrFail();
    }

    private function summaryRow(array $extra): array
    {
        $rows = $this->actingAs($this->owner)->get(route('backoffice.stock-balances.index', array_merge([
            'summary_location_type' => 'warehouse', 'summary_location_id' => $this->warehouse->id, 'ingredient_id' => $this->ingredient->id,
        ], $extra)))->assertOk()->viewData('stockSummaryRows');

        return collect($rows)->firstWhere('ingredient_name', 'Gula');
    }
}
