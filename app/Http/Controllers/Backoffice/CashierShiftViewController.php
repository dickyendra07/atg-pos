<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CashierShiftViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
        ];

        if (! in_array($roleCode, $allowedRoles)) {
            return null;
        }

        return $user;
    }

    protected function buildShiftQuery(Request $request, $user)
    {
        $query = CashierShift::with([
            'user.role',
            'outlet',
            'salesTransactions.items',
        ])->latest('started_at');

        if ($user->role?->code === 'admin_outlet') {
            $query->where('outlet_id', $user->outlet?->id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('started_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('started_at', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('outlet_id') && $user->role?->code !== 'admin_outlet') {
            $query->where('outlet_id', $request->outlet_id);
        }

        if ($request->filled('user_keyword')) {
            $keyword = trim((string) $request->user_keyword);

            $query->whereHas('user', function ($subQuery) use ($keyword) {
                $subQuery->where('name', 'like', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    protected function calculateShiftMetrics(CashierShift $shift): array
    {
        $completedTransactions = $shift->salesTransactions
            ->where('status', 'completed')
            ->values();

        $voidTransactions = $shift->salesTransactions
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

        $totalSales = (float) $completedTransactions->sum('grand_total');
        $openingCash = (float) ($shift->opening_cash ?? 0);
        $expectedCash = $openingCash + $cashSales;
        $closingCashActual = $shift->closing_cash_actual !== null
            ? (float) $shift->closing_cash_actual
            : null;

        $difference = $closingCashActual !== null
            ? $closingCashActual - $expectedCash
            : null;

        return [
            'completed_transactions_count' => $completedTransactions->count(),
            'void_transactions_count' => $voidTransactions->count(),
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'transfer_sales' => $transferSales,
            'total_sales' => $totalSales,
            'opening_cash' => $openingCash,
            'expected_cash' => $expectedCash,
            'closing_cash_actual' => $closingCashActual,
            'difference' => $difference,
        ];
    }

    protected function buildIndexSummary($shifts): array
    {
        $totalShifts = $shifts->count();
        $openShifts = $shifts->where('status', 'open')->count();
        $closedShifts = $shifts->where('status', 'closed')->count();

        $totalSales = 0;
        $totalCashSales = 0;
        $totalExpectedCash = 0;

        foreach ($shifts as $shift) {
            $metrics = $this->calculateShiftMetrics($shift);
            $totalSales += $metrics['total_sales'];
            $totalCashSales += $metrics['cash_sales'];
            $totalExpectedCash += $metrics['expected_cash'];
        }

        return [
            'total_shifts' => $totalShifts,
            'open_shifts' => $openShifts,
            'closed_shifts' => $closedShifts,
            'total_sales' => (float) $totalSales,
            'total_cash_sales' => (float) $totalCashSales,
            'total_expected_cash' => (float) $totalExpectedCash,
        ];
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke halaman Shift.');
        }

        $shifts = $this->buildShiftQuery($request, $user)->get();

        $shiftRows = $shifts->map(function ($shift) {
            $metrics = $this->calculateShiftMetrics($shift);

            return [
                'model' => $shift,
                'metrics' => $metrics,
            ];
        });

        $summary = $this->buildIndexSummary($shifts);

        $outletOptions = CashierShift::with('outlet')
            ->get()
            ->pluck('outlet')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return view('backoffice.shifts.index', [
            'user' => $user,
            'shiftRows' => $shiftRows,
            'summary' => $summary,
            'outletOptions' => $outletOptions,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'status' => $request->status,
                'outlet_id' => $request->outlet_id,
                'user_keyword' => $request->user_keyword,
            ],
        ]);
    }

    public function show(CashierShift $shift)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke detail Shift.');
        }

        if (
            $user->role?->code === 'admin_outlet'
            && (int) $shift->outlet_id !== (int) $user->outlet?->id
        ) {
            return redirect()
                ->route('backoffice.shifts.index')
                ->with('error', 'Kamu tidak punya akses ke shift outlet lain.');
        }

        $shift->load([
            'user.role',
            'outlet',
            'salesTransactions.items',
        ]);

        $metrics = $this->calculateShiftMetrics($shift);

        return view('backoffice.shifts.show', [
            'user' => $user,
            'shift' => $shift,
            'metrics' => $metrics,
        ]);
    }
}