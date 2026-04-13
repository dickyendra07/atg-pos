<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\SalesTransaction;
use App\Services\StockDeductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
            'staff_gudang',
        ];

        if (! in_array($roleCode, $allowedRoles, true)) {
            return null;
        }

        return $user;
    }

    protected function authorizeTransactionAccess(SalesTransaction $transaction)
    {
        $user = Auth::user()->load(['role', 'outlet']);
        $roleCode = $user->role?->code;

        $fullAccessRoles = [
            'owner',
            'admin_pusat',
            'staff_gudang',
        ];

        if (in_array($roleCode, $fullAccessRoles, true)) {
            return $user;
        }

        if ($roleCode === 'admin_outlet') {
            if ((int) $user->outlet_id === (int) $transaction->outlet_id) {
                return $user;
            }

            return null;
        }

        if ($roleCode === 'kasir') {
            if (
                (int) $user->id === (int) $transaction->user_id ||
                (
                    ! empty($user->outlet_id) &&
                    (int) $user->outlet_id === (int) $transaction->outlet_id
                )
            ) {
                return $user;
            }

            return null;
        }

        return null;
    }

    protected function buildTransactionQuery(Request $request)
    {
        $query = SalesTransaction::with(['user', 'outlet', 'member', 'items', 'voidBy'])
            ->latest();

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('outlet_id')) {
            $query->where('outlet_id', $request->outlet_id);
        }

        return $query;
    }

    protected function buildReportData(Request $request): array
    {
        $transactions = $this->buildTransactionQuery($request)->get();

        $validTransactions = $transactions->filter(function ($transaction) {
            $status = strtolower((string) ($transaction->status ?? ''));
            $grandTotal = (float) ($transaction->grand_total ?? 0);

            return ! in_array($status, ['stock_blocked', 'void'], true) && $grandTotal > 0;
        })->values();

        $problemTransactions = $transactions->filter(function ($transaction) {
            $status = strtolower((string) ($transaction->status ?? ''));
            $grandTotal = (float) ($transaction->grand_total ?? 0);

            return in_array($status, ['stock_blocked', 'void'], true) || $grandTotal <= 0;
        })->values();

        $totalSales = (float) $validTransactions->sum('grand_total');
        $totalTransactions = $validTransactions->count();
        $averageOrderValue = $totalTransactions > 0 ? $totalSales / $totalTransactions : 0;
        $totalItemsSold = (float) $validTransactions->sum(function ($transaction) {
            return $transaction->items->sum('qty');
        });

        $paymentSummary = $validTransactions
            ->groupBy(function ($transaction) {
                $method = strtoupper(trim((string) ($transaction->payment_method ?? '')));

                if ($method === '' || $method === '-') {
                    return 'UNSET';
                }

                return $method;
            })
            ->map(function ($items) {
                return [
                    'count' => $items->count(),
                    'total' => (float) $items->sum('grand_total'),
                ];
            })
            ->sortByDesc('total');

        $topProducts = $validTransactions
            ->flatMap(function ($transaction) {
                return $transaction->items;
            })
            ->groupBy(function ($item) {
                return trim(($item->product_name ?? '-') . ' - ' . ($item->variant_name ?? '-'));
            })
            ->map(function ($items, $name) {
                return [
                    'name' => $name,
                    'qty' => (float) $items->sum('qty'),
                    'sales' => (float) $items->sum('line_total'),
                ];
            })
            ->sortByDesc('qty')
            ->take(5)
            ->values();

        $outletOptions = SalesTransaction::with('outlet')
            ->get()
            ->pluck('outlet')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        return [
            'transactions' => $transactions,
            'validTransactions' => $validTransactions,
            'problemTransactions' => $problemTransactions,
            'totalSales' => $totalSales,
            'totalTransactions' => $totalTransactions,
            'averageOrderValue' => $averageOrderValue,
            'totalItemsSold' => $totalItemsSold,
            'paymentSummary' => $paymentSummary,
            'topProducts' => $topProducts,
            'outletOptions' => $outletOptions,
            'filters' => [
                'date_from' => $request->date_from,
                'date_to' => $request->date_to,
                'payment_method' => $request->payment_method,
                'status' => $request->status,
                'outlet_id' => $request->outlet_id,
            ],
            'validTransactionsCount' => $validTransactions->count(),
            'problemTransactionsCount' => $problemTransactions->count(),
            'blockedTransactionsCount' => $transactions->where('status', 'stock_blocked')->count(),
            'voidTransactionsCount' => $transactions->filter(function ($transaction) {
                return strtolower((string) ($transaction->status ?? '')) === 'void';
            })->count(),
            'zeroAmountTransactionsCount' => $transactions->filter(function ($transaction) {
                return (float) ($transaction->grand_total ?? 0) <= 0;
            })->count(),
        ];
    }

    protected function buildLegacyCsvRows($transactions)
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $outletName = $transaction->outlet->name ?? '-';
            $businessName = $transaction->outlet->name ?? 'ATG POS';
            $date = $transaction->created_at?->format('Y-m-d H:i:s') ?? '-';
            $transactionNo = $transaction->transaction_number ?? '-';

            $payment = strtoupper(trim((string) ($transaction->payment_method ?? '')));
            if ($payment === '' || $payment === '-') {
                $payment = 'UNSET';
            }

            $rawStatus = strtolower(trim((string) ($transaction->status ?? '-')));
            $status = match ($rawStatus) {
                'completed', 'paid' => 'Dibayar',
                'void' => 'Void',
                'stock_blocked' => 'Stock Blocked',
                default => strtoupper((string) ($transaction->status ?? '-')),
            };

            $transactionDiscount = (float) ($transaction->discount_amount ?? 0);
            $transactionGrandTotal = (float) ($transaction->grand_total ?? 0);
            $transactionRefund = 0;

            $items = $transaction->items;

            if ($items->isEmpty()) {
                $rows[] = [
                    'outlet' => $outletName,
                    'business_name' => $businessName,
                    'date' => $date,
                    'no' => $transactionNo,
                    'item_name' => '-',
                    'quantity' => 0,
                    'price' => 0,
                    'total' => 0,
                    'discount' => 0,
                    'total_discount' => $transactionDiscount,
                    'total_refund' => $transactionRefund,
                    'grand_total' => $transactionGrandTotal,
                    'payment' => $payment,
                    'status' => $status,
                    'void_at' => $transaction->void_at?->format('Y-m-d H:i:s') ?? '',
                    'void_reason' => $transaction->void_reason ?? '',
                ];

                continue;
            }

            $itemsSubtotal = (float) $items->sum(function ($item) {
                return (float) ($item->line_total ?? 0);
            });

            foreach ($items as $item) {
                $qty = (float) ($item->qty ?? 0);
                $price = (float) ($item->price ?? 0);
                $lineTotal = (float) ($item->line_total ?? 0);

                $variantName = trim((string) ($item->variant_name ?? ''));
                $itemName = trim((string) ($item->product_name ?? '-'));
                if ($variantName !== '' && $variantName !== '-') {
                    $itemName .= ' - ' . $variantName;
                }

                $lineDiscount = 0;
                if ($transactionDiscount > 0 && $itemsSubtotal > 0) {
                    $lineDiscount = ($lineTotal / $itemsSubtotal) * $transactionDiscount;
                }

                $rows[] = [
                    'outlet' => $outletName,
                    'business_name' => $businessName,
                    'date' => $date,
                    'no' => $transactionNo,
                    'item_name' => $itemName,
                    'quantity' => $qty,
                    'price' => $price,
                    'total' => $lineTotal,
                    'discount' => $lineDiscount,
                    'total_discount' => $transactionDiscount,
                    'total_refund' => $transactionRefund,
                    'grand_total' => $transactionGrandTotal,
                    'payment' => $payment,
                    'status' => $status,
                    'void_at' => $transaction->void_at?->format('Y-m-d H:i:s') ?? '',
                    'void_reason' => $transaction->void_reason ?? '',
                ];
            }
        }

        return $rows;
    }

    public function index(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke halaman Transactions.');
        }

        $reportData = $this->buildReportData($request);

        return view('backoffice.transactions.index', array_merge([
            'user' => $user,
        ], $reportData));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses export CSV.');
        }

        $reportData = $this->buildReportData($request);
        $transactions = $reportData['transactions'];
        $rows = $this->buildLegacyCsvRows($transactions);

        $filename = 'transactions_pos_lama_format_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'outlet',
                'business_name',
                'date',
                'no',
                'item_name',
                'quantity',
                'price',
                'total',
                'discount',
                'total_discount',
                'total_refund',
                'grand_total',
                'payment',
                'status',
                'void_at',
                'void_reason',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['outlet'],
                    $row['business_name'],
                    $row['date'],
                    $row['no'],
                    $row['item_name'],
                    $row['quantity'],
                    $row['price'],
                    $row['total'],
                    $row['discount'],
                    $row['total_discount'],
                    $row['total_refund'],
                    $row['grand_total'],
                    $row['payment'],
                    $row['status'],
                    $row['void_at'],
                    $row['void_reason'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function printSummary(Request $request)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke print summary.');
        }

        $reportData = $this->buildReportData($request);

        return view('backoffice.transactions.print-summary', array_merge([
            'user' => $user,
        ], $reportData));
    }

    public function show(SalesTransaction $transaction)
    {
        $user = $this->authorizeTransactionAccess($transaction);

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke halaman Transaction Detail.');
        }

        $transaction->load(['user', 'outlet', 'member', 'items', 'voidBy']);

        return view('backoffice.transactions.show', [
            'user' => $user,
            'transaction' => $transaction,
        ]);
    }

    public function void(Request $request, SalesTransaction $transaction, StockDeductionService $stockDeductionService)
    {
        $user = $this->authorizeAccess();

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses void transaksi.');
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:1000',
        ], [
            'void_reason.required' => 'Alasan void wajib diisi.',
        ]);

        if (strtolower((string) $transaction->status) === 'void') {
            return redirect()
                ->route('backoffice.transactions.show', $transaction)
                ->with('error', 'Transaksi ini sudah berstatus void.');
        }

        DB::transaction(function () use ($transaction, $validated, $user, $stockDeductionService) {
            $transaction->refresh();
            $transaction->load(['items']);

            if (strtolower((string) $transaction->status) === 'void') {
                throw new \RuntimeException('Transaksi ini sudah void.');
            }

            $stockDeductionService->restoreFromVoidedTransaction($transaction);

            $transaction->update([
                'status' => 'void',
                'void_at' => now(),
                'void_reason' => $validated['void_reason'],
                'void_by_user_id' => $user->id,
            ]);
        });

        return redirect()
            ->route('backoffice.transactions.show', $transaction)
            ->with('success', 'Transaksi berhasil di-void dan stok sudah dikembalikan.');
    }

    public function receipt(SalesTransaction $transaction)
    {
        $user = $this->authorizeTransactionAccess($transaction);

        if (! $user) {
            return redirect()
                ->route('dashboard')
                ->with('error', 'Role kamu tidak punya akses ke halaman Receipt.');
        }

        $transaction->load(['user', 'outlet', 'member', 'items']);

        return view('backoffice.transactions.receipt', [
            'transaction' => $transaction,
        ]);
    }
}