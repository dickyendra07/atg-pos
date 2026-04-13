<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Member;
use App\Models\ProductVariant;
use App\Models\SalesTransaction;
use App\Services\StockDeductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CartController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke Cashier.');
        }

        return $user;
    }

    protected function getActiveShift($user): ?CashierShift
    {
        return CashierShift::where('user_id', $user->id)
            ->where('status', 'open')
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    protected function buildCartPayload(array $cart, ?array $member, string $orderType): array
    {
        $subtotal = collect($cart)->sum(function ($item) {
            return (float) ($item['line_total'] ?? 0);
        });

        return [
            'order_type' => $orderType,
            'cart_count' => count($cart),
            'subtotal' => (float) $subtotal,
            'subtotal_formatted' => 'Rp ' . number_format((float) $subtotal, 0, ',', '.'),
            'member' => $member,
            'items' => collect($cart)->values()->map(function ($item) {
                return [
                    'cart_key' => $item['cart_key'] ?? '',
                    'variant_id' => $item['variant_id'] ?? null,
                    'product_id' => $item['product_id'] ?? null,
                    'product_name' => $item['product_name'] ?? 'Product',
                    'variant_name' => $item['variant_name'] ?? null,
                    'order_type' => $item['order_type'] ?? 'dine_in',
                    'less_sugar' => (bool) ($item['less_sugar'] ?? false),
                    'less_ice' => (bool) ($item['less_ice'] ?? false),
                    'qty' => (float) ($item['qty'] ?? 0),
                    'price' => (float) ($item['price'] ?? 0),
                    'line_total' => (float) ($item['line_total'] ?? 0),
                ];
            })->values()->all(),
        ];
    }

    protected function resolveOrderType(?string $orderType = null): string
    {
        $type = strtolower((string) $orderType);

        if (! in_array($type, ['dine_in', 'delivery'])) {
            $type = 'dine_in';
        }

        return $type;
    }

    protected function generateDailyTransactionNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = 'TRX-' . $today . '-';

        $lastTransactionToday = SalesTransaction::whereDate('created_at', now()->toDateString())
            ->where('transaction_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->first();

        $nextNumber = 1;

        if ($lastTransactionToday && ! empty($lastTransactionToday->transaction_number)) {
            $parts = explode('-', $lastTransactionToday->transaction_number);
            $lastSequence = (int) end($parts);

            if ($lastSequence > 0) {
                $nextNumber = $lastSequence + 1;
            }
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    protected function shiftBlockedResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()
            ->route('cashier.index')
            ->with('error', $message);
    }

    public function add(Request $request, ProductVariant $variant)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $variant->load(['product.brand', 'product.category']);

        $orderType = $this->resolveOrderType($request->input('order_type', session('cashier_order_type', 'dine_in')));
        session(['cashier_order_type' => $orderType]);

        $cart = session('cashier_cart', []);
        $cartKey = 'variant_' . $variant->id . '_' . $orderType;

        $price = method_exists($variant, 'getPriceByOrderType')
            ? $variant->getPriceByOrderType($orderType)
            : (float) ($orderType === 'delivery'
                ? ($variant->price_delivery ?? $variant->price)
                : ($variant->price_dine_in ?? $variant->price));

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] += 1;
            $cart[$cartKey]['line_total'] = $cart[$cartKey]['qty'] * $cart[$cartKey]['price'];
        } else {
            $cart[$cartKey] = [
                'cart_key' => $cartKey,
                'variant_id' => $variant->id,
                'product_id' => $variant->product?->id,
                'product_name' => $variant->product?->name,
                'brand_name' => $variant->product?->brand?->name,
                'category_name' => $variant->product?->category?->name,
                'variant_name' => $variant->name,
                'order_type' => $orderType,
                'less_sugar' => false,
                'less_ice' => false,
                'qty' => 1,
                'price' => (float) $price,
                'line_total' => (float) $price,
            ];
        }

        session(['cashier_cart' => $cart]);

        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Item berhasil masuk ke keranjang.',
                'cart' => $payload,
            ]);
        }

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Item berhasil masuk ke keranjang.');
    }

    public function increase(Request $request, string $cartKey)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $cart = session('cashier_cart', []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] += 1;
            $cart[$cartKey]['line_total'] = $cart[$cartKey]['qty'] * $cart[$cartKey]['price'];
            session(['cashier_cart' => $cart]);
        }

        $orderType = session('cashier_order_type', 'dine_in');
        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Qty item berhasil ditambah.',
                'cart' => $payload,
            ]);
        }

        return redirect()->route('cashier.index');
    }

    public function decrease(Request $request, string $cartKey)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $cart = session('cashier_cart', []);

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] -= 1;

            if ($cart[$cartKey]['qty'] <= 0) {
                unset($cart[$cartKey]);
            } else {
                $cart[$cartKey]['line_total'] = $cart[$cartKey]['qty'] * $cart[$cartKey]['price'];
            }

            session(['cashier_cart' => $cart]);
        }

        $orderType = session('cashier_order_type', 'dine_in');
        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Qty item berhasil dikurangi.',
                'cart' => $payload,
            ]);
        }

        return redirect()->route('cashier.index');
    }

    public function remove(Request $request, string $cartKey)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $cart = session('cashier_cart', []);

        if (isset($cart[$cartKey])) {
            unset($cart[$cartKey]);
            session(['cashier_cart' => $cart]);
        }

        $orderType = session('cashier_order_type', 'dine_in');
        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Item berhasil dihapus dari cart.',
                'cart' => $payload,
            ]);
        }

        return redirect()->route('cashier.index');
    }

    public function toggleModifier(Request $request, string $cartKey)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $validated = $request->validate([
            'modifier' => 'required|in:less_sugar,less_ice',
        ]);

        $cart = session('cashier_cart', []);

        if (! isset($cart[$cartKey])) {
            return response()->json([
                'success' => false,
                'message' => 'Item cart tidak ditemukan.',
            ], 404);
        }

        $modifier = $validated['modifier'];
        $cart[$cartKey][$modifier] = ! (bool) ($cart[$cartKey][$modifier] ?? false);

        session(['cashier_cart' => $cart]);

        $orderType = session('cashier_order_type', 'dine_in');
        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        return response()->json([
            'success' => true,
            'message' => 'Modifier item berhasil diupdate.',
            'cart' => $payload,
        ]);
    }

    public function clear(Request $request)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        session()->forget('cashier_cart');
        session()->forget('cashier_member');

        $orderType = session('cashier_order_type', 'dine_in');
        $payload = $this->buildCartPayload([], null, $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Keranjang berhasil dikosongkan.',
                'cart' => $payload,
            ]);
        }

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Keranjang berhasil dikosongkan.');
    }

    public function checkout(Request $request, StockDeductionService $stockDeductionService)
    {
        $user = $this->authorizeCashierAccess();
        $activeShift = $this->getActiveShift($user);

        if (! $activeShift) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $request->validate([
            'payment_method' => 'required|in:cash,qris,transfer',
            'amount_paid' => 'required|numeric|min:0',
            'order_type' => 'required|in:dine_in,delivery',
        ]);

        $cart = session('cashier_cart', []);
        $memberSession = session('cashier_member');
        $orderType = $this->resolveOrderType($request->input('order_type'));
        session(['cashier_order_type' => $orderType]);

        if (empty($cart)) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Keranjang masih kosong.');
        }

        $subtotal = collect($cart)->sum(function ($item) {
            return (float) ($item['line_total'] ?? 0);
        });

        $paymentMethod = $request->input('payment_method');
        $amountPaid = (float) $request->input('amount_paid');

        if ($paymentMethod === 'cash' && $amountPaid < $subtotal) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Nominal cash kurang dari total transaksi.');
        }

        if (in_array($paymentMethod, ['qris', 'transfer'])) {
            $amountPaid = $subtotal;
        }

        $changeAmount = max(0, $amountPaid - $subtotal);
        $earnedPoints = 0;

        try {
            $stockDeductionService->validateCartStock(
                cart: $cart,
                outletId: $user->outlet?->id
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Checkout diblok karena stok bahan tidak cukup: ' . $e->getMessage());
        }

        $transaction = DB::transaction(function () use (
            $user,
            $activeShift,
            $cart,
            $subtotal,
            $paymentMethod,
            $amountPaid,
            $changeAmount,
            $memberSession,
            &$earnedPoints
        ) {
            $transaction = SalesTransaction::create([
                'transaction_number' => $this->generateDailyTransactionNumber(),
                'user_id' => $user->id,
                'outlet_id' => $user->outlet?->id,
                'cashier_shift_id' => $activeShift->id,
                'member_id' => $memberSession['id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'grand_total' => $subtotal,
                'payment_method' => $paymentMethod,
                'payment_status' => 'paid',
                'amount_paid' => $amountPaid,
                'change_amount' => $changeAmount,
                'status' => 'completed',
            ]);

            foreach ($cart as $item) {
                $variantName = $item['variant_name'] ?? null;
                $itemOrderType = $item['order_type'] ?? 'dine_in';

                if ($variantName) {
                    $variantName .= ' [' . strtoupper(str_replace('_', ' ', $itemOrderType)) . ']';
                } else {
                    $variantName = strtoupper(str_replace('_', ' ', $itemOrderType));
                }

                $transaction->items()->create([
                    'product_id' => $item['product_id'] ?? null,
                    'product_variant_id' => $item['variant_id'] ?? null,
                    'product_name' => $item['product_name'] ?? '-',
                    'variant_name' => $variantName,
                    'less_sugar' => (bool) ($item['less_sugar'] ?? false),
                    'less_ice' => (bool) ($item['less_ice'] ?? false),
                    'qty' => $item['qty'] ?? 1,
                    'price' => $item['price'] ?? 0,
                    'line_total' => $item['line_total'] ?? 0,
                ]);
            }

            if (! empty($memberSession['id'])) {
                $member = Member::find($memberSession['id']);

                if ($member && $member->is_active) {
                    $earnedPoints = $member->addPointsFromAmount((float) $subtotal);
                }
            }

            return $transaction;
        });

        $stockDeductionService->deductFromTransaction($transaction);

        session()->forget('cashier_cart');
        session()->forget('cashier_member');

        session([
            'last_checkout' => [
                'transaction_id' => $transaction->id,
                'transaction_number' => $transaction->transaction_number,
                'grand_total' => (float) $transaction->grand_total,
                'payment_method' => strtoupper((string) $transaction->payment_method),
                'change_amount' => (float) $transaction->change_amount,
                'created_at' => optional($transaction->created_at)->format('Y-m-d H:i:s'),
            ],
        ]);

        $message = 'Checkout berhasil.';

        if ($earnedPoints > 0) {
            $message .= ' Member mendapat ' . $earnedPoints . ' poin.';
        } else {
            $message .= ' Tidak ada poin tambahan untuk transaksi ini.';
        }

        $message .= ' Stock deduction sudah dijalankan.';

        return redirect()
            ->route('cashier.index')
            ->with('success', $message);
    }
}