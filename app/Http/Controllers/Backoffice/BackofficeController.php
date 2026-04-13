<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\SalesTransaction;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

class BackofficeController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'kasir',
            'staff_gudang',
        ];

        if (! in_array($roleCode, $allowedRoles, true)) {
            return null;
        }

        return $user;
    }

    public function __invoke()
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke Back Office.');
        }

        $roleCode = $user->role?->code;
        $outletId = $user->outlet?->id;

        if (in_array($roleCode, ['admin_outlet', 'kasir'], true)) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Akses back office penuh hanya untuk owner / admin pusat. Gunakan cashier untuk operasional harian.');
        }

        $canSeeGlobalData = in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true);
        $isAdminOutlet = $roleCode === 'admin_outlet';

        $productCount = Product::count();
        $variantCount = ProductVariant::count();
        $ingredientCount = Ingredient::count();
        $recipeCount = Recipe::count();

        $outletCount = Outlet::count();
        $warehouseCount = Warehouse::count();

        $transactionQuery = SalesTransaction::query();
        $shiftQuery = CashierShift::query();
        $stockMovementQuery = StockMovement::query();

        if ($isAdminOutlet) {
            $transactionQuery->where('outlet_id', $outletId);
            $shiftQuery->where('outlet_id', $outletId);
            $stockMovementQuery
                ->where('location_type', 'outlet')
                ->where('location_id', $outletId);
        }

        $transactionCount = $transactionQuery->count();
        $completedTransactionCount = (clone $transactionQuery)
            ->where('status', 'completed')
            ->count();

        $voidTransactionCount = (clone $transactionQuery)
            ->where('status', 'void')
            ->count();

        $totalSales = (float) (clone $transactionQuery)
            ->where('status', 'completed')
            ->sum('grand_total');

        $shiftCount = $shiftQuery->count();
        $openShiftCount = (clone $shiftQuery)
            ->where('status', 'open')
            ->count();

        $closedShiftCount = (clone $shiftQuery)
            ->where('status', 'closed')
            ->count();

        $stockMovementCount = $stockMovementQuery->count();

        return view('backoffice.index', [
            'user' => $user,
            'stats' => [
                'product_count' => $productCount,
                'variant_count' => $variantCount,
                'ingredient_count' => $ingredientCount,
                'recipe_count' => $recipeCount,
                'outlet_count' => $outletCount,
                'warehouse_count' => $warehouseCount,
                'transaction_count' => $transactionCount,
                'completed_transaction_count' => $completedTransactionCount,
                'void_transaction_count' => $voidTransactionCount,
                'total_sales' => $totalSales,
                'shift_count' => $shiftCount,
                'open_shift_count' => $openShiftCount,
                'closed_shift_count' => $closedShiftCount,
                'stock_movement_count' => $stockMovementCount,
            ],
            'permissions' => [
                'can_see_global_data' => $canSeeGlobalData,
                'is_admin_outlet' => $isAdminOutlet,
                'can_manage_master_data' => in_array($roleCode, ['owner', 'admin_pusat'], true),
                'can_manage_stock' => in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true),
                'can_view_transactions' => in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true),
                'can_view_shifts' => in_array($roleCode, ['owner', 'admin_pusat'], true),
            ],
        ]);
    }
}