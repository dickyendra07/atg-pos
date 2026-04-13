<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseViewController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke halaman Warehouse.');
        }

        return $user;
    }

    public function index()
    {
        $user = $this->authorizeAccess();

        $warehouses = Warehouse::latest()->get();

        return view('backoffice.warehouses.index', [
            'user' => $user,
            'warehouses' => $warehouses,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();

        return view('backoffice.warehouses.create', [
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:warehouses,name',
            'code' => 'required|string|max:100|unique:warehouses,code',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        Warehouse::create($validated);

        return redirect()
            ->route('backoffice.warehouses.index')
            ->with('success', 'Warehouse baru berhasil ditambahkan.');
    }

    public function edit(Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        return view('backoffice.warehouses.edit', [
            'user' => $user,
            'warehouse' => $warehouse,
        ]);
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:warehouses,name,' . $warehouse->id,
            'code' => 'required|string|max:100|unique:warehouses,code,' . $warehouse->id,
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        $warehouse->update($validated);

        return redirect()
            ->route('backoffice.warehouses.index')
            ->with('success', 'Warehouse berhasil diupdate.');
    }

    public function stockIndex(Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $stockBalances = StockBalance::with(['ingredient.category'])
            ->where('location_type', 'warehouse')
            ->where('location_id', $warehouse->id)
            ->latest()
            ->get();

        return view('backoffice.warehouses.stock.index', [
            'user' => $user,
            'warehouse' => $warehouse,
            'stockBalances' => $stockBalances,
        ]);
    }

    public function stockCreate(Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::with(['category'])
            ->orderBy('name')
            ->get();

        return view('backoffice.warehouses.stock.create', [
            'user' => $user,
            'warehouse' => $warehouse,
            'ingredients' => $ingredients,
        ]);
    }

    public function stockStore(Request $request, Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $validated = $request->validate([
            'ingredient_id' => 'required|exists:ingredients,id',
            'qty_in' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($validated, $warehouse, $user) {
            $stockBalance = StockBalance::firstOrCreate(
                [
                    'ingredient_id' => $validated['ingredient_id'],
                    'location_type' => 'warehouse',
                    'location_id' => $warehouse->id,
                ],
                [
                    'qty_on_hand' => 0,
                ]
            );

            $currentQty = (float) $stockBalance->qty_on_hand;
            $newQty = $currentQty + (float) $validated['qty_in'];

            $stockBalance->update([
                'qty_on_hand' => $newQty,
            ]);

            StockMovement::create([
                'ingredient_id' => $validated['ingredient_id'],
                'location_type' => 'warehouse',
                'location_id' => $warehouse->id,
                'movement_type' => 'stock_in',
                'qty_in' => $validated['qty_in'],
                'qty_out' => 0,
                'reference_type' => 'warehouse_manual_stock_in',
                'reference_id' => null,
                'note' => $validated['note'] ?: 'Manual stock in ke warehouse oleh ' . $user->name,
            ]);
        });

        return redirect()
            ->route('backoffice.warehouses.stock.index', $warehouse)
            ->with('success', 'Stock warehouse berhasil ditambahkan.');
    }

    public function movementIndex(Warehouse $warehouse)
    {
        $user = $this->authorizeAccess();

        $movements = StockMovement::with(['ingredient.category'])
            ->where('location_type', 'warehouse')
            ->where('location_id', $warehouse->id)
            ->latest()
            ->get();

        return view('backoffice.warehouses.movements.index', [
            'user' => $user,
            'warehouse' => $warehouse,
            'movements' => $movements,
        ]);
    }
}