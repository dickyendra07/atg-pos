<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockBalanceViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles, true)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Inventory Control.');
        }

        return $user;
    }

    protected function applyStockFilters(Request $request)
    {
        $baseQuery = StockBalance::with(['ingredient.category', 'warehouse', 'outlet'])
            ->orderByDesc('id');

        if ($request->filled('ingredient_id')) {
            $baseQuery->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('location_type')) {
            $baseQuery->where('location_type', $request->location_type);
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->keyword);

            $baseQuery->where(function ($q) use ($keyword) {
                $q->whereHas('ingredient', function ($sub) use ($keyword) {
                    $sub->where('name', 'like', '%' . $keyword . '%');
                });

                if (is_numeric($keyword)) {
                    $q->orWhere('location_id', (int) $keyword);
                }
            });
        }

        $stockBalances = $baseQuery->get();

        if ($request->filled('status')) {
            $stockBalances = $stockBalances->filter(function ($stock) use ($request) {
                $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);
                $qty = (float) ($stock->qty_on_hand ?? 0);

                if ($request->status === 'out') {
                    return $qty <= 0;
                }

                if ($request->status === 'low') {
                    return $qty > 0 && $qty <= $minimum;
                }

                if ($request->status === 'safe') {
                    return $qty > $minimum;
                }

                return true;
            })->values();
        }

        return $stockBalances;
    }

    protected function getLocationLabel(StockBalance $stock): string
    {
        if (($stock->location_type ?? null) === 'warehouse') {
            return $stock->warehouse->name ?? ('Warehouse ID ' . ($stock->location_id ?? '-'));
        }

        if (($stock->location_type ?? null) === 'outlet') {
            return $stock->outlet->name ?? ('Outlet ID ' . ($stock->location_id ?? '-'));
        }

        return '-';
    }

    protected function getStockStatusLabel(StockBalance $stock): string
    {
        $qty = (float) ($stock->qty_on_hand ?? 0);
        $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);

        if ($qty <= 0) {
            return 'Out of Stock';
        }

        if ($qty <= $minimum) {
            return 'Low Stock';
        }

        return 'Safe';
    }
    protected function getNeedActionType(StockBalance $stock): ?string
    {
        $qty = (float) ($stock->qty_on_hand ?? 0);
        $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);

        if ($qty <= 0) {
            return 'out';
        }

        if ($qty <= $minimum) {
            return 'low';
        }

        return null;
    }

    protected function getNeedActionRecommendation(StockBalance $stock): string
    {
    $actionType = $this->getNeedActionType($stock);

        if ($actionType === 'out') {
            if (($stock->location_type ?? null) === 'outlet') {
                return 'Segera buat Purchase Order atau transfer stok ke outlet ini.';
        }

        return 'Segera buat Purchase Order atau lakukan penyesuaian stok.';
    }

    if ($actionType === 'low') {
        if (($stock->location_type ?? null) === 'outlet') {
            return 'Pantau stok, siapkan restock atau transfer dari warehouse.';
        }

        return 'Pantau stok dan siapkan Purchase Order berikutnya.';
    }

    return '-';
}

    protected function applySummaryLocationFilters($query, Request $request)
    {
        if ($request->filled('summary_location_type')) {
            $query->where('location_type', $request->summary_location_type);
        }

        if ($request->filled('summary_location_type') && $request->filled('summary_location_id')) {
            $query->where('location_id', $request->summary_location_id);
        }

        return $query;
    }

    protected function sumNetMovement(Collection $movements): float
    {
        return (float) $movements->sum('qty_in') - (float) $movements->sum('qty_out');
    }

    protected function buildStockSummaryRows(Request $request, Collection $ingredients): Collection
    {
        $dateFrom = $request->filled('summary_date_from')
            ? Carbon::parse($request->summary_date_from)->startOfDay()
            : null;

        $dateTo = $request->filled('summary_date_to')
            ? Carbon::parse($request->summary_date_to)->endOfDay()
            : null;

        $movementBaseQuery = StockMovement::with(['ingredient.category']);

        if ($request->filled('ingredient_id')) {
            $movementBaseQuery->where('ingredient_id', $request->ingredient_id);
        }

        $this->applySummaryLocationFilters($movementBaseQuery, $request);

        if ($dateTo) {
            $movementBaseQuery->where('created_at', '<=', $dateTo);
        }

        $allRelevantMovements = $movementBaseQuery->get();

        $balanceBaseQuery = StockBalance::with(['ingredient.category']);

        if ($request->filled('ingredient_id')) {
            $balanceBaseQuery->where('ingredient_id', $request->ingredient_id);
        }

        $this->applySummaryLocationFilters($balanceBaseQuery, $request);

        $filteredBalances = $balanceBaseQuery->get();

        $stockSummaryRows = [];

        foreach ($ingredients as $ingredient) {
            if ($request->filled('ingredient_id') && (int) $request->ingredient_id !== (int) $ingredient->id) {
                continue;
            }

            $ingredientMovements = $allRelevantMovements->where('ingredient_id', $ingredient->id)->values();
            $ingredientBalances = $filteredBalances->where('ingredient_id', $ingredient->id)->values();

            if ($dateFrom) {
                $openingMovements = $ingredientMovements
                    ->filter(function ($movement) use ($dateFrom) {
                        return $movement->created_at && $movement->created_at->lt($dateFrom);
                    })
                    ->values();

                $periodMovements = $ingredientMovements
                    ->filter(function ($movement) use ($dateFrom) {
                        return $movement->created_at && $movement->created_at->gte($dateFrom);
                    })
                    ->values();

                $openingBalance = $this->sumNetMovement($openingMovements);
            } else {
                $periodMovements = $ingredientMovements->values();

                $openingBalance = (float) $ingredientMovements
                    ->where('movement_type', 'opening_balance')
                    ->sum('qty_in') - (float) $ingredientMovements
                    ->where('movement_type', 'opening_balance')
                    ->sum('qty_out');
            }

            if ($ingredientMovements->isEmpty() && $ingredientBalances->isEmpty() && $openingBalance == 0.0) {
                continue;
            }

            $stockIn = (float) $periodMovements
                ->where('movement_type', 'stock_in')
                ->sum('qty_in');

            $transferIn = (float) $periodMovements
                ->where('movement_type', 'transfer_in')
                ->sum('qty_in');

            $transferOut = (float) $periodMovements
                ->where('movement_type', 'transfer_out')
                ->sum('qty_out');

            $productionIn = (float) $periodMovements
                ->where('movement_type', 'production_in')
                ->sum('qty_in');

            $productionOut = (float) $periodMovements
                ->where('movement_type', 'production_out')
                ->sum('qty_out');

            $adjustmentIn = (float) $periodMovements
                ->where('movement_type', 'stock_adjustment')
                ->sum('qty_in');

            $adjustmentOut = (float) $periodMovements
                ->where('movement_type', 'stock_adjustment')
                ->sum('qty_out');

            if ($dateFrom || $dateTo) {
                $endingStock = $openingBalance + $this->sumNetMovement($periodMovements);
            } else {
                $endingStock = (float) $ingredientBalances->sum('qty_on_hand');
            }

            $minimumStock = (float) ($ingredient->minimum_stock ?? 0);

            $stockSummaryRows[] = [
                'category_name' => $ingredient->category->name ?? '-',
                'ingredient_name' => $ingredient->name,
                'unit' => $ingredient->unit,
                'minimum_stock' => $minimumStock,
                'opening_balance' => $openingBalance,
                'stock_in' => $stockIn,
                'transfer_in' => $transferIn,
                'transfer_out' => $transferOut,
                'production_in' => $productionIn,
                'production_out' => $productionOut,
                'adjustment_in' => $adjustmentIn,
                'adjustment_out' => $adjustmentOut,
                'ending_stock' => $endingStock,
                'need_action' => $endingStock <= $minimumStock,
                'is_zero' => $endingStock <= 0,
            ];
        }

        return collect($stockSummaryRows)
            ->sortBy('ingredient_name')
            ->values();
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with('category')
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stockBalances = $this->applyStockFilters($request);

        $distinctLocations = $stockBalances
            ->map(function ($stock) {
                return ($stock->location_type ?? '-') . ':' . ($stock->location_id ?? '-');
            })
            ->unique()
            ->count();

        $summary = [
            'locations' => $distinctLocations,
            'stock_rows' => $stockBalances->count(),
            'safe_stock' => 0,
            'need_action' => 0,
            'zero_stock' => 0,
        ];

        foreach ($stockBalances as $stock) {
            $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);
            $qty = (float) ($stock->qty_on_hand ?? 0);

            if ($qty <= 0) {
                $summary['zero_stock']++;
                $summary['need_action']++;
            } elseif ($qty <= $minimum) {
                $summary['need_action']++;
            } else {
                $summary['safe_stock']++;
            }
        }

        $stockSummaryRows = $this->buildStockSummaryRows($request, $ingredients);

        $needActionItems = $stockBalances
            ->filter(function ($stock) {
                return $this->getNeedActionType($stock) !== null;
            })
            ->map(function ($stock) {
                $actionType = $this->getNeedActionType($stock);
                $qty = (float) ($stock->qty_on_hand ?? 0);
                $minimum = (float) ($stock->ingredient->minimum_stock ?? 0);

                return [
                    'category_name' => $stock->ingredient->category->name ?? '-',
                    'ingredient_name' => $stock->ingredient->name ?? '-',
                    'unit' => $stock->ingredient->unit ?? '-',
                    'location_type' => ucfirst($stock->location_type ?? '-'),
                    'location_name' => $this->getLocationLabel($stock),
                    'qty_on_hand' => $qty,
                    'minimum_stock' => $minimum,
                    'status_label' => $actionType === 'out' ? 'Out of Stock' : 'Low Stock',
                    'status_class' => $actionType === 'out' ? 'status-out' : 'status-low',
                    'recommended_action' => $this->getNeedActionRecommendation($stock),
                ];
            })
            ->sortBy([
                ['status_label', 'asc'],
                ['ingredient_name', 'asc'],
            ])
            ->values();

        return view('backoffice.stock-balances.index', [
            'user' => $user,
            'ingredients' => $ingredients,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
            'stockBalances' => $stockBalances,
            'stocks' => $stockBalances,
            'summary' => $summary,
            'stockSummaryRows' => $stockSummaryRows,
            'needActionItems' => $needActionItems,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAccess();

        $stocks = $this->applyStockFilters($request);

        $filename = 'stock_balances_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($stocks) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'category_name',
                'ingredient_name',
                'unit',
                'minimum_stock',
                'location_type',
                'location_name',
                'qty_on_hand',
                'status',
            ]);

            foreach ($stocks as $stock) {
                fputcsv($handle, [
                    $stock->ingredient->category->name ?? '-',
                    $stock->ingredient->name ?? '-',
                    $stock->ingredient->unit ?? '-',
                    (float) ($stock->ingredient->minimum_stock ?? 0),
                    $stock->location_type ?? '-',
                    $this->getLocationLabel($stock),
                    (float) ($stock->qty_on_hand ?? 0),
                    $this->getStockStatusLabel($stock),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with(['category'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('backoffice.stock-balances.create', [
            'user' => $user,
            'ingredients' => $ingredients,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'location_type' => 'required|in:warehouse,outlet',
            'location_id' => 'required|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.qty_in' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.note' => 'nullable|string|max:255',
        ], [
            'items.required' => 'Minimal harus ada 1 item purchase order.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.qty_in.required' => 'Qty wajib diisi di setiap baris.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi di setiap baris.',
        ]);

        if ($validated['location_type'] === 'warehouse') {
            $location = Warehouse::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Warehouse tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        } else {
            $location = Outlet::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Outlet tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        }

        $items = collect($validated['items'])
            ->filter(function ($item) {
                return ! empty($item['ingredient_id'])
                    && (float) ($item['qty_in'] ?? 0) > 0
                    && (float) ($item['unit_price'] ?? 0) >= 0;
            })
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withErrors(['items' => 'Tidak ada item valid untuk disimpan.'])
                ->withInput();
        }

        DB::transaction(function () use ($validated, $items) {
            foreach ($items as $item) {
                $qtyIn = (float) ($item['qty_in'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineTotal = $qtyIn * $unitPrice;

                $stockBalance = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $currentQty = (float) $stockBalance->qty_on_hand;
                $newQty = $currentQty + $qtyIn;

                $stockBalance->update([
                    'qty_on_hand' => $newQty,
                ]);

                $baseNote = trim((string) ($item['note'] ?? ''));
                $purchaseNote = 'Purchase order dari luar sistem'
                    . ' | Harga Satuan: Rp ' . number_format($unitPrice, 0, ',', '.')
                    . ' | Qty: ' . number_format($qtyIn, 2, ',', '.')
                    . ' | Total: Rp ' . number_format($lineTotal, 0, ',', '.');

                StockMovement::create([
                    'ingredient_id' => $item['ingredient_id'],
                    'location_type' => $validated['location_type'],
                    'location_id' => $validated['location_id'],
                    'movement_type' => 'stock_in',
                    'qty_in' => $qtyIn,
                    'qty_out' => 0,
                    'reference_type' => 'manual_stock_in',
                    'reference_id' => null,
                    'note' => $baseNote !== '' ? $baseNote . ' | ' . $purchaseNote : $purchaseNote,
                ]);
            }
        });

        $locationLabel = $validated['location_type'] === 'warehouse' ? 'warehouse' : 'outlet';

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Purchase Order berhasil disimpan ke ' . $locationLabel . ' tujuan.');
    }

    public function createAdjustment()
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with(['category'])
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stockMap = StockBalance::query()
            ->select('ingredient_id', 'location_type', 'location_id', 'qty_on_hand')
            ->get()
            ->groupBy(function ($row) {
                return $row->location_type . ':' . $row->location_id;
            })
            ->map(function ($rows) {
                return $rows->mapWithKeys(function ($row) {
                    return [
                        $row->ingredient_id => (float) $row->qty_on_hand,
                    ];
                })->toArray();
            })
            ->toArray();

        return view('backoffice.stock-balances.adjustment', [
            'user' => $user,
            'ingredients' => $ingredients,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
            'stockMap' => $stockMap,
        ]);
    }

    public function storeAdjustment(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'location_type' => 'required|in:warehouse,outlet',
            'location_id' => 'required|integer|min:1',
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|exists:ingredients,id',
            'items.*.actual_qty' => 'required|numeric|min:0',
            'items.*.note' => 'required|string|max:255',
        ], [
            'items.required' => 'Minimal harus ada 1 item adjustment.',
            'items.*.ingredient_id.required' => 'Ingredient wajib dipilih di setiap baris.',
            'items.*.actual_qty.required' => 'Stok aktual wajib diisi di setiap baris.',
            'items.*.note.required' => 'Keterangan wajib diisi di setiap baris.',
        ]);

        if ($validated['location_type'] === 'warehouse') {
            $location = Warehouse::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Warehouse tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        } else {
            $location = Outlet::query()
                ->where('is_active', true)
                ->find($validated['location_id']);

            if (! $location) {
                return back()
                    ->withErrors(['location_id' => 'Outlet tujuan tidak ditemukan atau tidak aktif.'])
                    ->withInput();
            }
        }

        $items = collect($validated['items'])
            ->filter(function ($item) {
                return ! empty($item['ingredient_id']) && isset($item['actual_qty']);
            })
            ->values();

        if ($items->isEmpty()) {
            return back()
                ->withErrors(['items' => 'Tidak ada item adjustment valid untuk disimpan.'])
                ->withInput();
        }

        $changedCount = 0;

        DB::transaction(function () use ($validated, $items, &$changedCount) {
            foreach ($items as $item) {
                $stockBalance = StockBalance::firstOrCreate(
                    [
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                    ],
                    [
                        'qty_on_hand' => 0,
                    ]
                );

                $systemQty = (float) $stockBalance->qty_on_hand;
                $actualQty = (float) $item['actual_qty'];
                $diff = $actualQty - $systemQty;

                $stockBalance->update([
                    'qty_on_hand' => $actualQty,
                ]);

                if ($diff != 0.0) {
                    StockMovement::create([
                        'ingredient_id' => $item['ingredient_id'],
                        'location_type' => $validated['location_type'],
                        'location_id' => $validated['location_id'],
                        'movement_type' => 'stock_adjustment',
                        'qty_in' => $diff > 0 ? $diff : 0,
                        'qty_out' => $diff < 0 ? abs($diff) : 0,
                        'reference_type' => 'manual_adjustment',
                        'reference_id' => null,
                        'note' => ($item['note'] ?? 'Adjustment manual')
                            . ' | System: ' . $systemQty
                            . ' | Aktual: ' . $actualQty,
                    ]);

                    $changedCount++;
                }
            }
        });

        $locationLabel = $validated['location_type'] === 'warehouse' ? 'warehouse' : 'outlet';

        if ($changedCount === 0) {
            return redirect()
                ->route('backoffice.stock-balances.index')
                ->with('success', 'Adjustment selesai, tetapi tidak ada perubahan stok karena semua nilai aktual sama dengan stok sistem di ' . $locationLabel . ' tujuan.');
        }

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Adjustment bulk berhasil disimpan ke ' . $locationLabel . ' tujuan.');
    }

    public function createOpname(Request $request)
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedWarehouseId = $request->get('warehouse_id');

        $stockBalancesQuery = StockBalance::with(['ingredient.category'])
            ->where('location_type', 'warehouse')
            ->orderByDesc('id');

        if ($selectedWarehouseId) {
            $stockBalancesQuery->where('location_id', $selectedWarehouseId);
        }

        $stockBalances = $stockBalancesQuery->get();

        return view('backoffice.stock-balances.opname', [
            'user' => $user,
            'warehouses' => $warehouses,
            'stockBalances' => $stockBalances,
            'selectedWarehouseId' => $selectedWarehouseId,
        ]);
    }

    public function storeOpname(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'stock_balance_id' => 'required|exists:stock_balances,id',
            'physical_qty' => 'required|numeric|min:0',
            'note' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($validated) {
            $stockBalance = StockBalance::with('ingredient')->findOrFail($validated['stock_balance_id']);

            if (
                $stockBalance->location_type !== 'warehouse' ||
                (int) $stockBalance->location_id !== (int) $validated['warehouse_id']
            ) {
                abort(422, 'Stock item yang dipilih tidak sesuai dengan warehouse opname.');
            }

            $systemQty = (float) $stockBalance->qty_on_hand;
            $physicalQty = (float) $validated['physical_qty'];
            $diff = $physicalQty - $systemQty;

            $stockBalance->update([
                'qty_on_hand' => $physicalQty,
            ]);

            if ($diff != 0.0) {
                StockMovement::create([
                    'ingredient_id' => $stockBalance->ingredient_id,
                    'location_type' => 'warehouse',
                    'location_id' => $stockBalance->location_id,
                    'movement_type' => 'stock_adjustment',
                    'qty_in' => $diff > 0 ? $diff : 0,
                    'qty_out' => $diff < 0 ? abs($diff) : 0,
                    'reference_type' => 'stock_opname',
                    'reference_id' => null,
                    'note' => $validated['note'] . ' | System: ' . $systemQty . ' | Fisik: ' . $physicalQty,
                ]);
            }
        });

        return redirect()
            ->route('backoffice.stock-balances.index')
            ->with('success', 'Opname gudang berhasil disimpan.');
    }

    public function importForm()
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $outlets = Outlet::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('backoffice.stock-balances.import', [
            'user' => $user,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
        ]);
    }

    public function importStore(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
        ]);

        $realPath = $request->file('file')->getRealPath();

        if (! $realPath || ! file_exists($realPath)) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'File upload tidak ditemukan. Coba upload ulang.');
        }

        $content = file_get_contents($realPath);

        if ($content === false || trim($content) === '') {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'File CSV kosong atau tidak bisa dibaca.');
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $lines = preg_split("/\r\n|\n|\r/", trim($content));

        if (! $lines || count($lines) < 2) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'CSV minimal harus punya header dan 1 baris data.');
        }

        $firstLine = $lines[0];
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $header = str_getcsv($firstLine, $delimiter);
        $header = array_map(function ($value) {
            return trim(strtolower($value));
        }, $header);

        $expectedHeader = [
            'ingredient_name',
            'location_type',
            'location_id',
            'qty_on_hand',
            'note',
        ];

        if ($header !== $expectedHeader) {
            return redirect()
                ->route('backoffice.stock-balances.import')
                ->with('error', 'Header CSV tidak sesuai template. Pastikan urutannya: ingredient_name,location_type,location_id,qty_on_hand,note');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $successRows = [];

        DB::transaction(function () use ($lines, $delimiter, &$imported, &$skipped, &$errors, &$successRows) {
            foreach (array_slice($lines, 1) as $index => $line) {
                $rowNumber = $index + 2;

                if (trim($line) === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: baris kosong.";
                    continue;
                }

                $row = str_getcsv($line, $delimiter);

                if (count($row) < 5) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: jumlah kolom kurang dari 5.";
                    continue;
                }

                $ingredientName = trim($row[0] ?? '');
                $locationType = trim(strtolower($row[1] ?? ''));
                $locationIdRaw = trim($row[2] ?? '');
                $qtyOnHandRaw = trim($row[3] ?? '');
                $note = trim($row[4] ?? '');

                if ($ingredientName === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient_name kosong.";
                    continue;
                }

                if ($locationType === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_type kosong.";
                    continue;
                }

                if (! in_array($locationType, ['outlet', 'warehouse'], true)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_type '{$locationType}' tidak valid.";
                    continue;
                }

                if ($locationIdRaw === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_id kosong.";
                    continue;
                }

                if (! ctype_digit($locationIdRaw)) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: location_id harus angka bulat.";
                    continue;
                }

                if ($qtyOnHandRaw === '') {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty_on_hand kosong.";
                    continue;
                }

                if (! is_numeric($qtyOnHandRaw) || (float) $qtyOnHandRaw < 0) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: qty_on_hand tidak valid.";
                    continue;
                }

                $ingredient = Ingredient::whereRaw('LOWER(name) = ?', [mb_strtolower($ingredientName)])->first();

                if (! $ingredient) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' tidak ditemukan.";
                    continue;
                }

                $locationId = (int) $locationIdRaw;
                $qtyOnHand = (float) $qtyOnHandRaw;

                $alreadyExists = StockBalance::where('ingredient_id', $ingredient->id)
                    ->where('location_type', $locationType)
                    ->where('location_id', $locationId)
                    ->exists();

                if ($alreadyExists) {
                    $skipped++;
                    $errors[] = "Baris {$rowNumber}: ingredient '{$ingredientName}' di {$locationType} ID {$locationId} sudah punya stock balance, jadi baris ini di-skip.";
                    continue;
                }

                StockBalance::create([
                    'ingredient_id' => $ingredient->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'qty_on_hand' => $qtyOnHand,
                ]);

                StockMovement::create([
                    'ingredient_id' => $ingredient->id,
                    'location_type' => $locationType,
                    'location_id' => $locationId,
                    'movement_type' => 'opening_balance',
                    'qty_in' => $qtyOnHand,
                    'qty_out' => 0,
                    'reference_type' => 'import_opening_stock',
                    'reference_id' => null,
                    'note' => $note !== '' ? $note : 'Opening stock import',
                ]);

                $imported++;
                $successRows[] = "Baris {$rowNumber}: {$ingredientName} berhasil diimport ke {$locationType} ID {$locationId} dengan qty {$qtyOnHand}.";
            }
        });

        $message = "Import Opening Stock selesai. Data masuk: {$imported}. Data di-skip: {$skipped}.";

        if ($imported === 0 && $skipped > 0) {
            $message .= " Tidak ada data baru yang masuk karena semua baris dilewati.";
        }

        return redirect()
            ->route('backoffice.stock-balances.import')
            ->with('success', $message)
            ->with('import_success_rows', $successRows)
            ->with('import_errors', $errors);
    }

    public function downloadTemplate(): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'sample_opening_stock_import_template.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['ingredient_name', 'location_type', 'location_id', 'qty_on_hand', 'note']);
            fputcsv($handle, ['Black Tea', 'outlet', '1', '1000', 'Opening stock outlet 1']);
            fputcsv($handle, ['Fresh Milk', 'outlet', '1', '800', 'Opening stock outlet 1']);
            fputcsv($handle, ['Liquid Sugar', 'warehouse', '1', '5000', 'Opening stock gudang utama']);

            fclose($handle);
        }, 200, $headers);
    }
}