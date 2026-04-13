<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\Outlet;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseTransferViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Transfer Gudang.');
        }

        return $user;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::orderBy('name')->get();
        $outlets = Outlet::orderBy('name')->get();

        $query = StockTransfer::with([
            'warehouse',
            'outlet',
            'ingredient.category',
            'transferredBy',
        ])->latest();

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        $transfers = $query->get();

        return view('backoffice.warehouse-transfers.index', [
            'user' => $user,
            'transfers' => $transfers,
            'warehouses' => $warehouses,
            'outlets' => $outlets,
            'filters' => [
                'warehouse_id' => $request->warehouse_id,
                'outlet_id' => $request->outlet_id,
            ],
        ]);
    }

    public function create(Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $outlets = Outlet::where('is_active', true)
            ->orderBy('name')
            ->get();

        $stockBalances = StockBalance::with(['ingredient.category'])
            ->where('location_type', 'warehouse')
            ->where('location_id', $warehouse->id)
            ->where('qty_on_hand', '>', 0)
            ->orderByDesc('qty_on_hand')
            ->get();

        return view('backoffice.warehouse-transfers.create', [
            'user' => $user,
            'warehouse' => $warehouse,
            'outlets' => $outlets,
            'stockBalances' => $stockBalances,
        ]);
    }

    public function store(Request $request, Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $validated = $request->validate([
            'outlet_id' => 'required|exists:outlets,id',
            'ingredient_id' => 'required|exists:ingredients,id',
            'qty' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $warehouse, $user) {
            $warehouseStock = StockBalance::with('ingredient')
                ->where('location_type', 'warehouse')
                ->where('location_id', $warehouse->id)
                ->where('ingredient_id', $validated['ingredient_id'])
                ->lockForUpdate()
                ->first();

            if (! $warehouseStock) {
                abort(422, 'Stock ingredient di warehouse tidak ditemukan.');
            }

            $currentWarehouseQty = (float) $warehouseStock->qty_on_hand;
            $transferQty = (float) $validated['qty'];

            if ($transferQty > $currentWarehouseQty) {
                abort(422, 'Qty transfer melebihi stok warehouse saat ini.');
            }

            $warehouseStock->update([
                'qty_on_hand' => $currentWarehouseQty - $transferQty,
            ]);

            $outletStock = StockBalance::firstOrCreate(
                [
                    'ingredient_id' => $validated['ingredient_id'],
                    'location_type' => 'outlet',
                    'location_id' => $validated['outlet_id'],
                ],
                [
                    'qty_on_hand' => 0,
                ]
            );

            $currentOutletQty = (float) $outletStock->qty_on_hand;

            $outletStock->update([
                'qty_on_hand' => $currentOutletQty + $transferQty,
            ]);

            $transfer = StockTransfer::create([
                'warehouse_id' => $warehouse->id,
                'outlet_id' => $validated['outlet_id'],
                'ingredient_id' => $validated['ingredient_id'],
                'qty' => $validated['qty'],
                'transferred_by_user_id' => $user->id,
                'status' => 'completed',
                'note' => $validated['note'],
            ]);

            StockMovement::create([
                'ingredient_id' => $validated['ingredient_id'],
                'location_type' => 'warehouse',
                'location_id' => $warehouse->id,
                'movement_type' => 'transfer_out',
                'qty_in' => 0,
                'qty_out' => $validated['qty'],
                'reference_type' => 'warehouse_to_outlet_transfer',
                'reference_id' => $transfer->id,
                'note' => 'Transfer #' . $transfer->transfer_number . ' dari warehouse ' . $warehouse->name . ' ke outlet ID ' . $validated['outlet_id'] . ($validated['note'] ? ' | ' . $validated['note'] : ''),
            ]);

            StockMovement::create([
                'ingredient_id' => $validated['ingredient_id'],
                'location_type' => 'outlet',
                'location_id' => $validated['outlet_id'],
                'movement_type' => 'transfer_in',
                'qty_in' => $validated['qty'],
                'qty_out' => 0,
                'reference_type' => 'warehouse_to_outlet_transfer',
                'reference_id' => $transfer->id,
                'note' => 'Transfer #' . $transfer->transfer_number . ' masuk dari warehouse ' . $warehouse->name . ($validated['note'] ? ' | ' . $validated['note'] : ''),
            ]);
        });

        return redirect()
            ->route('backoffice.warehouse-transfers.index')
            ->with('success', 'Transfer gudang ke outlet berhasil disimpan.');
    }
}