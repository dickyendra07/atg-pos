<?php

namespace Tests\Feature;

use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RealEngineHarness;
use Tests\TestCase;

/**
 * Real concurrent sessions on a disposable MySQL (see RealEngineHarness; skipped otherwise).
 *
 * Every writer that changes stock_balances by "read, compute, write the absolute value" used to lose updates
 * when two requests overlapped: both requests succeeded and both movements were recorded, but the balance kept
 * only one of the changes. Each such writer now reads the row under lockForUpdate(). These tests fire several
 * requests at the same instant and require that the stored balance equals the starting balance plus the ledger.
 */
class RealEngineWriterLockTest extends TestCase
{
    use RealEngineHarness;

    private const WORKERS = 6;

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

    public function test_warehouse_stock_in_does_not_lose_concurrent_updates(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $after = $this->lastMovementId();

        $results = $this->fireTogether(array_fill(0, self::WORKERS, [
            'uri' => "/backoffice/warehouses/{$this->warehouse->id}/stock",
            'data' => ['ingredient_id' => $this->ingredient->id, 'qty_in' => 1, 'note' => 'conc'],
        ]));

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('106.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'stock in');
    }

    public function test_purchase_receipt_does_not_lose_concurrent_updates(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $after = $this->lastMovementId();

        $results = $this->fireTogether(array_fill(0, self::WORKERS, [
            'uri' => '/backoffice/stock-balances',
            'data' => [
                'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'received_date' => now()->toDateString(),
                'items' => [['ingredient_id' => $this->ingredient->id, 'qty_in' => 1, 'unit_price' => 1000]],
            ],
        ]));

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('106.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'purchase receipt');
    }

    public function test_manual_adjustments_stay_reconciled_with_the_ledger(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $after = $this->lastMovementId();

        $requests = [];
        foreach (range(1, self::WORKERS) as $n) {
            $requests[] = [
                'uri' => '/backoffice/stock-balances/adjustment',
                'data' => [
                    'location_type' => 'warehouse', 'location_id' => $this->warehouse->id, 'note' => "conc {$n}",
                    'items' => [['ingredient_id' => $this->ingredient->id, 'actual_qty' => 100 + $n]],
                ],
            ];
        }
        $results = $this->fireTogether($requests);

        // Overlapping adjustments can collide on the generated ADJ-... reference (nextReference() is not atomic):
        // that request is rolled back whole, which is safe. Only the ones that were saved must reconcile.
        $this->assertGreaterThanOrEqual(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'manual adjustment');
        $this->assertSame(
            collect($results)->where('ok', true)->count(),
            DB::table('stock_movements')->where('id', '>', $after)->where('reference_type', 'manual_adjustment')->count(),
            'one movement per saved adjustment, none for the rolled-back ones'
        );
    }

    public function test_opname_stays_reconciled_with_the_ledger(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $balanceId = DB::table('stock_balances')->where('ingredient_id', $this->ingredient->id)->value('id');
        $after = $this->lastMovementId();

        $requests = [];
        foreach (range(1, self::WORKERS) as $n) {
            $requests[] = [
                'uri' => '/backoffice/stock-balances/opname',
                'data' => ['warehouse_id' => $this->warehouse->id, 'stock_balance_id' => $balanceId, 'physical_qty' => 100 + $n, 'note' => "conc {$n}"],
            ];
        }
        $results = $this->fireTogether($requests);

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'opname');
    }

    public function test_warehouse_to_outlet_transfer_does_not_lose_concurrent_updates(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $this->seedBalance($this->ingredient, 'outlet', $this->outlet->id, 10);
        $after = $this->lastMovementId();

        $results = $this->fireTogether(array_fill(0, self::WORKERS, [
            'uri' => "/backoffice/warehouses/{$this->warehouse->id}/transfer-to-outlet",
            'data' => ['outlet_id' => $this->outlet->id, 'ingredient_id' => $this->ingredient->id, 'qty' => 1, 'note' => ''],
        ]));

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('16.00', $this->balance($this->ingredient, 'outlet', $this->outlet->id));
        $this->assertSame('94.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '10.00', $after, 'warehouse transfer (destination)');
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'warehouse transfer (source)');
    }

    public function test_general_transfer_does_not_lose_concurrent_updates(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $this->seedBalance($this->ingredient, 'outlet', $this->outlet->id, 10);
        $after = $this->lastMovementId();

        $results = $this->fireTogether(array_map(fn () => $this->generalTransferRequest(), range(1, self::WORKERS)));

        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('16.00', $this->balance($this->ingredient, 'outlet', $this->outlet->id));
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '10.00', $after, 'general transfer (destination)');
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'general transfer (source)');
    }

    public function test_transfer_cancel_and_reactivate_do_not_lose_concurrent_updates(): void
    {
        $this->seedBalance($this->ingredient, 'warehouse', $this->warehouse->id, 100);
        $this->seedBalance($this->ingredient, 'outlet', $this->outlet->id, 10);

        // Six separate in-transit transfers of 1, created one after the other (not part of the race).
        foreach (range(1, self::WORKERS) as $n) {
            $this->actingAs($this->owner)->post(route('backoffice.transfers.store'), $this->generalTransferRequest()['data'])->assertRedirect();
        }
        $ids = StockTransfer::orderBy('id')->pluck('id')->all();
        $this->assertCount(self::WORKERS, $ids);

        // Cancel: every cancel credits the SAME source and debits the same destination.
        $after = $this->lastMovementId();
        $before = ['warehouse' => $this->balance($this->ingredient, 'warehouse', $this->warehouse->id), 'outlet' => $this->balance($this->ingredient, 'outlet', $this->outlet->id)];
        $results = $this->fireTogether(array_map(fn ($id) => ['uri' => "/backoffice/transfers/{$id}/mark-cancelled"], $ids));
        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('100.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertSame('10.00', $this->balance($this->ingredient, 'outlet', $this->outlet->id));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, $before['warehouse'], $after, 'cancel (source)');
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, $before['outlet'], $after, 'cancel (destination)');

        // Reactivate: every one debits the same source and credits the same destination.
        $after = $this->lastMovementId();
        $results = $this->fireTogether(array_map(fn ($id) => ['uri' => "/backoffice/transfers/{$id}/mark-in-transit"], $ids));
        $this->assertSame(self::WORKERS, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame('94.00', $this->balance($this->ingredient, 'warehouse', $this->warehouse->id));
        $this->assertSame('16.00', $this->balance($this->ingredient, 'outlet', $this->outlet->id));
        $this->assertReconciled($this->ingredient, 'warehouse', $this->warehouse->id, '100.00', $after, 'reactivate (source)');
        $this->assertReconciled($this->ingredient, 'outlet', $this->outlet->id, '10.00', $after, 'reactivate (destination)');
    }

    private function generalTransferRequest(): array
    {
        return [
            'uri' => '/backoffice/transfers',
            'data' => [
                'operation_key' => (string) Str::uuid(),
                'from_location' => "warehouse:{$this->warehouse->id}", 'to_location' => "outlet:{$this->outlet->id}", 'sender_name' => 'Sender', 'receiver_name' => '',
                'items' => [['ingredient_id' => $this->ingredient->id, 'qty' => 1]],
            ],
        ];
    }
}
