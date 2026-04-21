<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Product;
use App\Models\SalesTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashierController extends Controller
{
    protected function authorizeCashierAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        if (! $user->canAccessCashier()) {
            return null;
        }

        return $user;
    }

    protected function getActiveShift($user): ?CashierShift
    {
        return CashierShift::with(['salesTransactions'])
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    protected function buildShiftSummary(?CashierShift $activeShift): array
    {
        if (! $activeShift) {
            return [
                'total_transactions' => 0,
                'total_sales' => 0,
                'cash_sales' => 0,
                'qris_sales' => 0,
                'transfer_sales' => 0,
                'void_transactions' => 0,
                'expected_cash' => 0,
            ];
        }

        $completedTransactions = $activeShift->salesTransactions
            ->where('status', 'completed')
            ->values();

        $voidTransactions = $activeShift->salesTransactions
            ->where('status', 'void')
            ->values();

        $cashSales = (float) $completedTransactions
            ->where('payment_method', 'cash')
            ->sum('grand_total');

        $qrisSales = (float) $completedTransactions
            ->where('payment_method', 'qris')
            ->sum('grand_total');

        $transferSales = (float) $completedTransactions
            ->where('payment_method', 'transfer')
            ->sum('grand_total');

        return [
            'total_transactions' => $completedTransactions->count(),
            'total_sales' => (float) $completedTransactions->sum('grand_total'),
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'transfer_sales' => $transferSales,
            'void_transactions' => $voidTransactions->count(),
            'expected_cash' => (float) $activeShift->opening_cash + $cashSales,
        ];
    }

    protected function getRecentReceipts($user)
    {
        $query = SalesTransaction::with([
            'outlet',
            'user',
            'items',
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(10);

        if ($user->outlet_id) {
            $query->where('outlet_id', $user->outlet_id);
        }

        return $query->get();
    }

    public function __invoke()
    {
        $user = $this->authorizeCashierAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke Cashier.');
        }

        $products = Product::with(['brand', 'category', 'variants'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $cart = session('cashier_cart', []);
        $member = session('cashier_member');
        $orderType = session('cashier_order_type', 'dine_in');

        $subtotal = collect($cart)->sum(function ($item) {
            return (float) ($item['line_total'] ?? 0);
        });

        $activeShift = $this->getActiveShift($user);
        $shiftSummary = $this->buildShiftSummary($activeShift);
        $recentReceipts = $this->getRecentReceipts($user);

        return view('cashier.index', [
            'user' => $user,
            'products' => $products,
            'cart' => $cart,
            'member' => $member,
            'subtotal' => $subtotal,
            'successMessage' => session('success'),
            'orderType' => $orderType,
            'activeShift' => $activeShift,
            'shiftSummary' => $shiftSummary,
            'recentReceipts' => $recentReceipts,
        ]);
    }

    public function setOrderType(Request $request)
    {
        $user = $this->authorizeCashierAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke Cashier.');
        }

        $validated = $request->validate([
            'order_type' => 'required|in:dine_in,delivery',
        ]);

        session(['cashier_order_type' => $validated['order_type']]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Order type berhasil diganti ke ' . strtoupper(str_replace('_', ' ', $validated['order_type'])) . '.',
                'cart' => [
                    'order_type' => $validated['order_type'],
                    'cart_count' => 0,
                    'subtotal' => 0,
                    'subtotal_formatted' => 'Rp 0',
                    'member' => null,
                    'items' => [],
                ],
            ]);
        }

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Order type berhasil diganti ke ' . strtoupper(str_replace('_', ' ', $validated['order_type'])) . '.');
    }

    public function newTransaction()
    {
        $user = $this->authorizeCashierAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke Cashier.');
        }

        session()->forget('last_checkout');

        return redirect()->route('cashier.index');
    }
}