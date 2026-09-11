<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReceipt;
use App\Models\Warehouse;
use App\Services\BackofficeOutletContext;
use Illuminate\Http\Request;

class PurchaseReceiptController extends Controller
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
        $outletFilter = $request->input('outlet_id');

        $query = PurchaseReceipt::with(['items.ingredient', 'outlet', 'warehouse', 'createdBy'])->latest('received_date')->latest('id');

        $query->where(function ($scope) use ($permittedOutletIds) {
            $scope->where('destination_type', 'warehouse')
                ->orWhere(function ($outletScope) use ($permittedOutletIds) {
                    $outletScope->where('destination_type', 'outlet')->whereIn('destination_id', $permittedOutletIds);
                });
        });

        if ($outletFilter === 'warehouse') {
            $query->where('destination_type', 'warehouse');
        } elseif (is_numeric($outletFilter) && $permittedOutletIds->contains((int) $outletFilter)) {
            $query->where('destination_type', 'outlet')->where('destination_id', (int) $outletFilter);
        } elseif ($outletFilter === null && $activeOutletId) {
            $query->where('destination_type', 'outlet')->where('destination_id', $activeOutletId);
        }

        $query
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('received_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('received_date', '<=', $request->date_to))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->search);
                $q->where(fn ($search) => $search->where('reference_number', 'like', '%'.$term.'%')->orWhere('supplier_name', 'like', '%'.$term.'%'));
            });

        return view('backoffice.purchase-history.index', [
            'user' => $user,
            'receipts' => $query->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(),
            'filters' => $request->only(['outlet_id', 'date_from', 'date_to', 'status', 'search']),
        ]);
    }

    public function show(Request $request, PurchaseReceipt $purchaseReceipt, BackofficeOutletContext $context)
    {
        $user = $this->authorizeAccess($request);

        if ($purchaseReceipt->destination_type === 'outlet') {
            abort_unless($context->canAccess($user, (int) $purchaseReceipt->destination_id), 403);
        }

        $purchaseReceipt->load(['items.ingredient', 'outlet', 'warehouse', 'createdBy']);

        return view('backoffice.purchase-history.show', compact('user', 'purchaseReceipt'));
    }
}
