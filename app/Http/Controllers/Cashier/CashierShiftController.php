<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashierShiftController extends Controller
{
    protected function authorizeCashierAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);

        $allowedRoles = [
            'owner',
            'admin_outlet',
            'kasir',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke Shift Cashier.');
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

    protected function buildShiftSummary(?CashierShift $shift): array
    {
        if (! $shift) {
            return [
                'total_transactions' => 0,
                'total_sales' => 0,
                'cash_sales' => 0,
                'qris_sales' => 0,
                'transfer_sales' => 0,
                'void_transactions' => 0,
                'expected_cash' => 0,
                'difference' => 0,
            ];
        }

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

        $expectedCash = (float) ($shift->opening_cash ?? 0) + $cashSales;
        $closingCashActual = $shift->closing_cash_actual !== null
            ? (float) $shift->closing_cash_actual
            : null;

        return [
            'total_transactions' => $completedTransactions->count(),
            'total_sales' => (float) $completedTransactions->sum('grand_total'),
            'cash_sales' => $cashSales,
            'qris_sales' => $qrisSales,
            'transfer_sales' => $transferSales,
            'void_transactions' => $voidTransactions->count(),
            'expected_cash' => $expectedCash,
            'difference' => $closingCashActual !== null ? ($closingCashActual - $expectedCash) : 0,
        ];
    }

    public function start(Request $request)
    {
        $user = $this->authorizeCashierAccess();

        $validated = $request->validate([
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $existingOpenShift = $this->getActiveShift($user);

        if ($existingOpenShift) {
            $summary = $this->buildShiftSummary($existingOpenShift);

            return response()->json([
                'success' => true,
                'message' => 'Shift sudah aktif.',
                'shift' => [
                    'active_shift' => [
                        'id' => $existingOpenShift->id,
                        'status' => $existingOpenShift->status,
                        'started_at' => optional($existingOpenShift->started_at)->format('Y-m-d H:i:s'),
                        'ended_at' => optional($existingOpenShift->ended_at)->format('Y-m-d H:i:s'),
                        'opening_cash' => (float) $existingOpenShift->opening_cash,
                        'closing_cash_actual' => $existingOpenShift->closing_cash_actual !== null ? (float) $existingOpenShift->closing_cash_actual : null,
                        'closing_note' => $existingOpenShift->closing_note,
                    ],
                    'summary' => $summary,
                ],
            ]);
        }

        $shift = DB::transaction(function () use ($user, $validated) {
            return CashierShift::create([
                'user_id' => $user->id,
                'outlet_id' => $user->outlet?->id,
                'started_at' => now(),
                'opening_cash' => (float) $validated['opening_cash'],
                'status' => 'open',
            ]);
        });

        $shift->load('salesTransactions');

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil dibuka.',
            'shift' => [
                'active_shift' => [
                    'id' => $shift->id,
                    'status' => $shift->status,
                    'started_at' => optional($shift->started_at)->format('Y-m-d H:i:s'),
                    'ended_at' => optional($shift->ended_at)->format('Y-m-d H:i:s'),
                    'opening_cash' => (float) $shift->opening_cash,
                    'closing_cash_actual' => null,
                    'closing_note' => null,
                ],
                'summary' => $this->buildShiftSummary($shift),
            ],
        ]);
    }

    public function end(Request $request)
    {
        $user = $this->authorizeCashierAccess();

        $validated = $request->validate([
            'closing_cash_actual' => 'required|numeric|min:0',
            'closing_note' => 'nullable|string',
        ]);

        $shift = $this->getActiveShift($user);

        if (! $shift) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada shift aktif untuk ditutup.',
            ], 422);
        }

        DB::transaction(function () use ($shift, $validated) {
            $shift->update([
                'ended_at' => now(),
                'closing_cash_actual' => (float) $validated['closing_cash_actual'],
                'closing_note' => $validated['closing_note'] ?? null,
                'status' => 'closed',
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Shift berhasil ditutup.',
            'shift' => [
                'active_shift' => null,
                'summary' => [
                    'total_transactions' => 0,
                    'total_sales' => 0,
                    'cash_sales' => 0,
                    'qris_sales' => 0,
                    'transfer_sales' => 0,
                    'void_transactions' => 0,
                    'expected_cash' => 0,
                    'difference' => 0,
                ],
            ],
        ]);
    }
}