<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockMovementViewController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Stock Movements.');
        }

        $ingredients = Ingredient::orderBy('name')->get();

        $query = StockMovement::with(['ingredient'])
            ->orderByDesc('id');

        if ($request->filled('ingredient_id')) {
            $query->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', '%' . $search . '%')
                    ->orWhere('reference_type', 'like', '%' . $search . '%');
            });
        }

        $stockMovements = $query->get();

        return view('backoffice.stock-movements.index', [
            'user' => $user,
            'stockMovements' => $stockMovements,
            'ingredients' => $ingredients,
            'filters' => [
                'ingredient_id' => $request->ingredient_id,
                'movement_type' => $request->movement_type,
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'search' => $request->search,
            ],
        ]);
    }
}