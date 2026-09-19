<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BackofficeOutletContext;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    private function authorizeAccess(Request $request)
    {
        $user = $request->user()->loadMissing(['role', 'roles', 'outlet', 'outlets']);

        abort_unless($user->hasAnyRoleCode(['owner', 'admin_pusat', 'admin_outlet', 'staff_gudang']), 403);

        return $user;
    }

    public function index(Request $request, BackofficeOutletContext $context)
    {
        $user = $this->authorizeAccess($request);
        $activeOutletId = $context->activeOutletId($user);
        $permittedOutletIds = $context->accessibleOutlets($user)->pluck('id');
        $locationFilter = $request->input('outlet_id');

        $query = StockAdjustment::with(['items', 'user', 'outlet', 'warehouse'])->latest()->latest('id');

        $query->where(function ($scope) use ($permittedOutletIds) {
            $scope->where('location_type', 'warehouse')
                ->orWhere(function ($outletScope) use ($permittedOutletIds) {
                    $outletScope->where('location_type', 'outlet')->whereIn('location_id', $permittedOutletIds);
                });
        });

        if ($locationFilter === 'warehouse') {
            $query->where('location_type', 'warehouse');
        } elseif (is_numeric($locationFilter) && $permittedOutletIds->contains((int) $locationFilter)) {
            $query->where('location_type', 'outlet')->where('location_id', (int) $locationFilter);
        } elseif ($locationFilter === null && $activeOutletId) {
            $query->where('location_type', 'outlet')->where('location_id', $activeOutletId);
        }

        $query
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', (int) $request->user_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(fn ($search) => $search->where('reference', 'like', '%'.$term.'%')->orWhere('note', 'like', '%'.$term.'%'));
            });

        return view('backoffice.stock-adjustments.index', [
            'user' => $user,
            'adjustments' => $query->get(),
            'users' => User::whereIn('id', StockAdjustment::query()->whereNotNull('user_id')->select('user_id'))->orderBy('name')->get(['id', 'name']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['outlet_id', 'date_from', 'date_to', 'user_id', 'search']),
        ]);
    }

    public function show(Request $request, StockAdjustment $stockAdjustment, BackofficeOutletContext $context)
    {
        $user = $this->authorizeAccess($request);

        if ($stockAdjustment->location_type === 'outlet') {
            abort_unless($context->canAccess($user, (int) $stockAdjustment->location_id), 403);
        }

        $stockAdjustment->load(['items.ingredient.category', 'items.movement', 'user', 'outlet', 'warehouse']);

        return view('backoffice.stock-adjustments.show', ['user' => $user, 'adjustment' => $stockAdjustment]);
    }
}
