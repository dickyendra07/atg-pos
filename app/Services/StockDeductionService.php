<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Outlet;
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
        $outletName = Outlet::find($outletId)?->name ?? ('Outlet ID '.$outletId);
        $ingredientNames = Ingredient::whereIn('id', array_keys($requirements))->pluck('name', 'id');
        $errors = [];

        foreach ($requirements as $ingredientId => $qtyNeeded) {
            $query = StockBalance::query()
                ->where('ingredient_id', $ingredientId)
                ->where('location_type', 'outlet')
                ->where('location_id', $outletId);

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $stockBalance = $query->first();
            $availableQty = (float) ($stockBalance?->qty_on_hand ?? 0);
            $ingredientName = $ingredientNames[$ingredientId] ?? ('Ingredient ID '.$ingredientId);

            if ($availableQty < (float) $qtyNeeded) {
                $errors[] = $ingredientName
                    .' di '.$outletName
                    .' tidak cukup. Butuh '
                    .number_format((float) $qtyNeeded, 2, ',', '.')
                    .', tersedia '
                    .number_format($availableQty, 2, ',', '.');
            }
        }

        if (! empty($errors)) {
            throw new RuntimeException(implode(' | ', $errors));
        }
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
            $stockBalance = StockBalance::where('ingredient_id', $ingredientId)
                ->where('location_type', 'outlet')
                ->where('location_id', $transaction->outlet_id)
                ->lockForUpdate()
                ->first();

            $availableQty = (float) ($stockBalance?->qty_on_hand ?? 0);

            if (! $stockBalance || $availableQty < (float) $qtyUsed) {
                $ingredientName = Ingredient::find($ingredientId)?->name ?? ('Ingredient ID '.$ingredientId);
                throw new RuntimeException('Stock '.$ingredientName.' berubah atau tidak mencukupi. Silakan ulangi checkout.');
            }

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
