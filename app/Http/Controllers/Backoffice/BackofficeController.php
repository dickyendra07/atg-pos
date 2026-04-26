<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\BackofficeNotification;
use App\Models\CashierShift;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\SalesTransaction;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    protected function applyDateFilter($query, ?string $dateFrom, ?string $dateTo, string $column = 'created_at')
    {
        if (! empty($dateFrom)) {
            $query->whereDate($column, '>=', $dateFrom);
        }

        if (! empty($dateTo)) {
            $query->whereDate($column, '<=', $dateTo);
        }

        return $query;
    }

    public function __invoke(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke Back Office.');
        }

        $roleCode = $user->role?->code;
        $userOutletId = $user->outlet?->id;

        if (in_array($roleCode, ['admin_outlet', 'kasir'], true)) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Akses back office penuh hanya untuk owner / admin pusat. Gunakan cashier untuk operasional harian.');
        }

        $outletOptions = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedOutletId = $request->filled('outlet_id')
            ? (int) $request->outlet_id
            : null;

        $dateFrom = $request->input('date_from') ?: now()->startOfMonth()->toDateString();
        $dateTo = $request->input('date_to') ?: now()->toDateString();

        $canSeeGlobalData = in_array($roleCode, ['owner', 'admin_pusat', 'staff_gudang'], true);
        $isAdminOutlet = $roleCode === 'admin_outlet';

        if (! $canSeeGlobalData && $isAdminOutlet) {
            $selectedOutletId = $userOutletId;
        }

        $productCount = Product::count();
        $variantCount = ProductVariant::count();
        $ingredientCount = Ingredient::count();
        $recipeCount = Recipe::count();
        $outletCount = Outlet::count();
        $warehouseCount = Warehouse::count();

        $transactionQuery = SalesTransaction::with(['items', 'outlet']);
        $shiftQuery = CashierShift::query();
        $movementQuery = StockMovement::with(['ingredient']);
        $stockBalanceQuery = StockBalance::with(['ingredient', 'outlet', 'warehouse']);

        if ($selectedOutletId) {
            $transactionQuery->where('outlet_id', $selectedOutletId);
            $shiftQuery->where('outlet_id', $selectedOutletId);

            $movementQuery->where(function ($q) use ($selectedOutletId) {
                $q->where(function ($sub) use ($selectedOutletId) {
                    $sub->where('location_type', 'outlet')
                        ->where('location_id', $selectedOutletId);
                })->orWhere(function ($sub) use ($selectedOutletId) {
                    $sub->where('reference_type', 'stock_transfer')
                        ->where('note', 'like', '%outlet ' . $selectedOutletId . '%');
                });
            });

            $stockBalanceQuery
                ->where('location_type', 'outlet')
                ->where('location_id', $selectedOutletId);
        }

        $this->applyDateFilter($transactionQuery, $dateFrom, $dateTo);
        $this->applyDateFilter($shiftQuery, $dateFrom, $dateTo, 'started_at');
        $this->applyDateFilter($movementQuery, $dateFrom, $dateTo);

        $transactions = $transactionQuery->latest()->get();
        $completedTransactions = $transactions->where('status', 'completed')->values();
        $voidTransactions = $transactions->where('status', 'void')->values();

        $totalSales = (float) $completedTransactions->sum('grand_total');
        $totalTransactions = $transactions->count();
        $completedTransactionCount = $completedTransactions->count();
        $voidTransactionCount = $voidTransactions->count();

        $itemsSold = (float) $completedTransactions->sum(function ($transaction) {
            return $transaction->items->sum('qty');
        });

        $averageOrder = $completedTransactionCount > 0
            ? $totalSales / $completedTransactionCount
            : 0;

        $paymentSummary = [
            'cash' => [
                'count' => $completedTransactions->where('payment_method', 'cash')->count(),
                'total' => (float) $completedTransactions->where('payment_method', 'cash')->sum('grand_total'),
            ],
            'qris' => [
                'count' => $completedTransactions->where('payment_method', 'qris')->count(),
                'total' => (float) $completedTransactions->where('payment_method', 'qris')->sum('grand_total'),
            ],
            'transfer' => [
                'count' => $completedTransactions->where('payment_method', 'transfer')->count(),
                'total' => (float) $completedTransactions->where('payment_method', 'transfer')->sum('grand_total'),
            ],
        ];

        $stockBalances = $stockBalanceQuery->get();
        $stockMovements = $movementQuery->latest()->get();

        $currentStockRows = $stockBalances->count();
        $totalQtyOnHand = (float) $stockBalances->sum('qty_on_hand');

        $lowStockCount = $stockBalances->filter(function ($stock) {
            $qty = (float) ($stock->qty_on_hand ?? 0);
            $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);

            return $qty > 0 && $qty <= $minimum;
        })->count();

        $outOfStockCount = $stockBalances->filter(function ($stock) {
            return (float) ($stock->qty_on_hand ?? 0) <= 0;
        })->count();

        $activeIngredientsInScope = $stockBalances
            ->pluck('ingredient_id')
            ->filter()
            ->unique()
            ->count();

        $movementLogCount = $stockMovements->count();
        $totalQtyIn = (float) $stockMovements->sum('qty_in');
        $totalQtyOut = (float) $stockMovements->sum('qty_out');

        $movementTypeSummary = [
            'stock_in' => $stockMovements->where('movement_type', 'stock_in')->count(),
            'stock_adjustment' => $stockMovements->where('movement_type', 'stock_adjustment')->count(),
            'transfer' => $stockMovements->filter(function ($movement) {
                return in_array($movement->movement_type, ['transfer_in', 'transfer_out'], true);
            })->count(),
            'production' => $stockMovements->filter(function ($movement) {
                return in_array($movement->movement_type, ['production_in', 'production_out'], true);
            })->count(),
        ];

        $shifts = $shiftQuery->get();
        $shiftCount = $shifts->count();
        $openShiftCount = $shifts->where('status', 'open')->count();
        $closedShiftCount = $shifts->where('status', 'closed')->count();

        $dailyTransactionSummary = SalesTransaction::query()
            ->selectRaw('DATE(created_at) as txn_date')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN grand_total ELSE 0 END) as total_sales")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions")
            ->selectRaw("SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_transactions")
            ->when($selectedOutletId, function ($query) use ($selectedOutletId) {
                $query->where('outlet_id', $selectedOutletId);
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('txn_date')
            ->get()
            ->map(function ($row) {
                return [
                    'date' => $row->txn_date,
                    'total_transactions' => (int) $row->total_transactions,
                    'total_sales' => (float) $row->total_sales,
                    'completed_transactions' => (int) $row->completed_transactions,
                    'void_transactions' => (int) $row->void_transactions,
                ];
            });

        $outletTransactionSummary = SalesTransaction::query()
            ->select('outlet_id')
            ->selectRaw('COUNT(*) as total_transactions')
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN grand_total ELSE 0 END) as total_sales")
            ->selectRaw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_transactions")
            ->selectRaw("SUM(CASE WHEN status = 'void' THEN 1 ELSE 0 END) as void_transactions")
            ->with('outlet')
            ->when($selectedOutletId, function ($query) use ($selectedOutletId) {
                $query->where('outlet_id', $selectedOutletId);
            })
            ->when($dateFrom, function ($query) use ($dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($dateTo, function ($query) use ($dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->groupBy('outlet_id')
            ->orderByDesc('total_sales')
            ->get()
            ->map(function ($row) {
                return [
                    'outlet_name' => $row->outlet->name ?? 'Outlet',
                    'total_transactions' => (int) $row->total_transactions,
                    'total_sales' => (float) $row->total_sales,
                    'completed_transactions' => (int) $row->completed_transactions,
                    'void_transactions' => (int) $row->void_transactions,
                ];
            });

        $topProducts = $completedTransactions
            ->flatMap(function ($transaction) {
                return $transaction->items;
            })
            ->groupBy(function ($item) {
                $productName = trim((string) ($item->product_name ?? '-'));
                $variantName = trim((string) ($item->variant_name ?? ''));

                return $variantName !== ''
                    ? $productName . ' - ' . $variantName
                    : $productName;
            })
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'qty' => (float) $items->sum('qty'),
                    'sales' => (float) $items->sum('line_total'),
                ];
            })
            ->sortByDesc('sales')
            ->take(10)
            ->values();

        $notificationQuery = BackofficeNotification::with(['transaction', 'outlet', 'createdBy'])
            ->latest();

        if ($selectedOutletId) {
            $notificationQuery->where('outlet_id', $selectedOutletId);
        }

        $backofficeNotifications = $notificationQuery
            ->take(8)
            ->get();

        $unreadNotificationCount = BackofficeNotification::query()
            ->whereNull('read_at')
            ->when($selectedOutletId, function ($query) use ($selectedOutletId) {
                $query->where('outlet_id', $selectedOutletId);
            })
            ->count();

        return view('backoffice.index', [
            'user' => $user,
            'outletOptions' => $outletOptions,
            'filters' => [
                'outlet_id' => $selectedOutletId,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'stats' => [
                'product_count' => $productCount,
                'variant_count' => $variantCount,
                'ingredient_count' => $ingredientCount,
                'recipe_count' => $recipeCount,
                'outlet_count' => $outletCount,
                'warehouse_count' => $warehouseCount,
                'transaction_count' => $totalTransactions,
                'completed_transaction_count' => $completedTransactionCount,
                'void_transaction_count' => $voidTransactionCount,
                'total_sales' => $totalSales,
                'items_sold' => $itemsSold,
                'average_order' => $averageOrder,
                'payment_summary' => $paymentSummary,
                'shift_count' => $shiftCount,
                'open_shift_count' => $openShiftCount,
                'closed_shift_count' => $closedShiftCount,
                'stock_movement_count' => $movementLogCount,
                'current_stock_rows' => $currentStockRows,
                'total_qty_on_hand' => $totalQtyOnHand,
                'low_stock_count' => $lowStockCount,
                'out_of_stock_count' => $outOfStockCount,
                'active_ingredients_in_scope' => $activeIngredientsInScope,
                'total_qty_in' => $totalQtyIn,
                'total_qty_out' => $totalQtyOut,
                'movement_type_summary' => $movementTypeSummary,
            ],
            'dailyTransactionSummary' => $dailyTransactionSummary,
            'outletTransactionSummary' => $outletTransactionSummary,
            'topProducts' => $topProducts,
            'backofficeNotifications' => $backofficeNotifications,
            'unreadNotificationCount' => $unreadNotificationCount,
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

    public function printSummary(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke print summary dashboard.');
        }

        $response = $this->__invoke($request);

        if (! method_exists($response, 'getData')) {
            return $response;
        }

        return view('backoffice.print-summary', $response->getData(true));
    }
}