<?php

namespace App\Services;

use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StockDeductionService
{
    public function __construct(private readonly SaleEligibilityService $saleEligibilityService) {}

    public function validateCartStock(array $cart, ?int $outletId): void
    {
        $requirements = $this->saleEligibilityService->requirementsForCart($cart, $outletId);
        $this->validateRequirementsStock($requirements, (int) $outletId);
    }

    public function validateRequirementsStock(array $requirements, int $outletId, bool $lockForUpdate = false): void
    {
        // Sales are allowed to take outlet stock below zero. Recipe/outlet validity is
        // enforced by SaleEligibilityService and the deduction is still fully recorded.
    }

    public function deductRequirements(SalesTransaction $transaction, array $requirements): void
    {
        if (! $transaction->outlet_id) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        if (StockMovement::where('movement_type', 'sales_usage')
            ->where('reference_type', 'sales_transaction')
            ->where('reference_id', $transaction->id)
            ->exists()) {
            throw new RuntimeException('Stock transaksi ini sudah pernah dipotong.');
        }

        foreach ($requirements as $ingredientId => $qtyUsed) {
            StockBalance::firstOrCreate(
                [
                    'ingredient_id' => $ingredientId,
                    'location_type' => 'outlet',
                    'location_id' => $transaction->outlet_id,
                ],
                ['qty_on_hand' => 0]
            );

            $stockBalance = StockBalance::where('ingredient_id', $ingredientId)
                ->where('location_type', 'outlet')
                ->where('location_id', $transaction->outlet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $availableQty = (float) $stockBalance->qty_on_hand;

            $stockBalance->update([
                'qty_on_hand' => $availableQty - (float) $qtyUsed,
            ]);

            StockMovement::create([
                'ingredient_id' => $ingredientId,
                'location_type' => 'outlet',
                'location_id' => $transaction->outlet_id,
                'movement_type' => 'sales_usage',
                'qty_in' => 0,
                'qty_out' => $qtyUsed,
                'reference_type' => 'sales_transaction',
                'reference_id' => $transaction->id,
                'note' => 'Auto deduction from cashier checkout '.($transaction->transaction_number ?? ('#'.$transaction->id)),
            ]);
        }
    }

    public function deductFromTransaction(SalesTransaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            $requirements = $this->saleEligibilityService->requirementsForTransaction($transaction);
            $this->validateRequirementsStock($requirements, (int) $transaction->outlet_id, true);
            $this->deductRequirements($transaction, $requirements);
        });
    }

    public function restoreFromVoidedTransaction(SalesTransaction $transaction): void
    {
        if (! $transaction->outlet_id) {
            throw new RuntimeException('Outlet transaksi tidak ditemukan.');
        }

        if (StockMovement::where('movement_type', 'sales_void_restore')
            ->where('reference_type', 'sales_transaction_void')
            ->where('reference_id', $transaction->id)
            ->exists()) {
            throw new RuntimeException('Stock transaksi ini sudah pernah dikembalikan.');
        }

        $deductions = StockMovement::where('movement_type', 'sales_usage')
            ->where('reference_type', 'sales_transaction')
            ->where('reference_id', $transaction->id)
            ->where('location_type', 'outlet')
            ->where('location_id', $transaction->outlet_id)
            ->selectRaw('ingredient_id, SUM(qty_out) as qty_out')
            ->groupBy('ingredient_id')
            ->get();

        foreach ($deductions as $deduction) {
            $qtyRestore = (float) $deduction->qty_out;

            if (! $deduction->ingredient_id || $qtyRestore <= 0) {
                continue;
            }

            $stockBalance = StockBalance::firstOrCreate(
                [
                    'ingredient_id' => $deduction->ingredient_id,
                    'location_type' => 'outlet',
                    'location_id' => $transaction->outlet_id,
                ],
                ['qty_on_hand' => 0]
            );

            $stockBalance = StockBalance::whereKey($stockBalance->id)
                ->lockForUpdate()
                ->firstOrFail();
            $stockBalance->update([
                'qty_on_hand' => (float) $stockBalance->qty_on_hand + $qtyRestore,
            ]);

            StockMovement::create([
                'ingredient_id' => $deduction->ingredient_id,
                'location_type' => 'outlet',
                'location_id' => $transaction->outlet_id,
                'movement_type' => 'sales_void_restore',
                'qty_in' => $qtyRestore,
                'qty_out' => 0,
                'reference_type' => 'sales_transaction_void',
                'reference_id' => $transaction->id,
                'note' => 'Stock restored from void transaction '.($transaction->transaction_number ?? ('#'.$transaction->id)),
            ]);
        }
    }
}
