<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockMovementViewController extends Controller
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

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Stock Movements.');
        }

        return $user;
    }

    protected function buildFilteredQuery(Request $request)
    {
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
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('note', 'like', '%' . $search . '%')
                    ->orWhere('reference_type', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    public function __invoke(Request $request)
    {
        $user = $this->authorizeAccess();

        $ingredients = Ingredient::orderBy('name')->get();

        $stockMovements = $this->buildFilteredQuery($request)->get();

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

    public function exportCsv(Request $request): StreamedResponse
    {
        $this->authorizeAccess();

        $filename = 'stock_movements_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $stockMovements = $this->buildFilteredQuery($request)->get();

        return response()->stream(function () use ($stockMovements) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'date',
                'ingredient_name',
                'location_type',
                'location_id',
                'movement_type',
                'qty_in',
                'qty_out',
                'reference_type',
                'reference_id',
                'note',
            ]);

            foreach ($stockMovements as $movement) {
                fputcsv($handle, [
                    $movement->created_at?->format('Y-m-d H:i:s'),
                    $movement->ingredient->name ?? '',
                    $movement->location_type,
                    $movement->location_id,
                    $movement->movement_type,
                    (float) $movement->qty_in,
                    (float) $movement->qty_out,
                    $movement->reference_type,
                    $movement->reference_id,
                    $movement->note,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}