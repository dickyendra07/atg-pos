<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Discount;
use App\Models\Member;
use App\Models\ProductVariant;
use App\Models\Promo;
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

        if (! in_array($user->role?->code, $allowedRoles, true)) {
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

        if (! in_array($type, ['dine_in', 'delivery'], true)) {
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


    protected function calculateDiscountAmount(array $cart, float $subtotal, $user, ?int $discountId, ?int $promoId): array
    {
        $discountAmount = 0;
        $discountLabel = null;
        $promoLabel = null;

        if ($discountId) {
            $discount = Discount::query()
                ->where('is_active', true)
                ->where(function ($query) use ($user) {
                    $query->whereNull('outlet_id');

                    if (! empty($user->outlet_id)) {
                        $query->orWhere('outlet_id', $user->outlet_id);
                    }
                })
                ->find($discountId);

            if ($discount) {
                if ($discount->type === 'percent') {
                    $discountAmount += $subtotal * ((float) $discount->value / 100);
                } else {
                    $discountAmount += (float) $discount->value;
                }

                $discountLabel = $discount->name;
            }
        }

        if ($promoId) {
            $promo = Promo::with(['requirements', 'rewards', 'outlets'])
                ->where('is_active', true)
                ->where('status', 'active')
                ->where(function ($query) use ($user) {
                    $query->whereDoesntHave('outlets');

                    if (! empty($user->outlet_id)) {
                        $query->orWhereHas('outlets', function ($outletQuery) use ($user) {
                            $outletQuery->where('outlets.id', $user->outlet_id);
                        });
                    }
                })
                ->find($promoId);

            if ($promo && $this->promoIsCurrentlyActive($promo) && $this->cartMeetsPromoRequirements($cart, $promo)) {
                foreach ($promo->rewards as $reward) {
                    if ($reward->reward_type === 'discount_percent') {
                        $discountAmount += $subtotal * ((float) $reward->reward_value / 100);
                    }

                    if ($reward->reward_type === 'discount_amount') {
                        $discountAmount += (float) $reward->reward_value;
                    }

                    if ($reward->reward_type === 'free_item' && ! empty($reward->product_variant_id)) {
                        $matchingCartItem = collect($cart)->first(function ($item) use ($reward) {
                            return (int) ($item['variant_id'] ?? 0) === (int) $reward->product_variant_id;
                        });

                        if ($matchingCartItem) {
                            $freeQty = min((float) $reward->qty, (float) ($matchingCartItem['qty'] ?? 0));
                            $discountAmount += $freeQty * (float) ($matchingCartItem['price'] ?? 0);
                        }
                    }
                }

                $promoLabel = $promo->name;
            }
        }

        $discountAmount = min($subtotal, max(0, $discountAmount));

        return [
            'discount_amount' => $discountAmount,
            'discount_label' => $discountLabel,
            'promo_label' => $promoLabel,
        ];
    }

    protected function promoIsCurrentlyActive(Promo $promo): bool
    {
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');
        $currentDay = strtolower(now()->format('l'));

        if (! empty($promo->start_date) && $promo->start_date->toDateString() > $today) {
            return false;
        }

        if (! empty($promo->end_date) && $promo->end_date->toDateString() < $today) {
            return false;
        }

        if (! empty($promo->start_time) && $promo->start_time > $currentTime) {
            return false;
        }

        if (! empty($promo->end_time) && $promo->end_time < $currentTime) {
            return false;
        }

        $activeDays = $promo->active_days ?? [];

        if (! empty($activeDays) && ! in_array($currentDay, $activeDays, true)) {
            return false;
        }

        return true;
    }

    protected function cartMeetsPromoRequirements(array $cart, Promo $promo): bool
    {
        if ($promo->requirements->isEmpty()) {
            return false;
        }

        $logic = strtolower((string) ($promo->requirement_logic ?? 'and'));

        $matches = $promo->requirements->map(function ($requirement) use ($cart) {
            $cartQty = collect($cart)
                ->where('variant_id', (int) $requirement->product_variant_id)
                ->sum(function ($item) {
                    return (float) ($item['qty'] ?? 0);
                });

            return $cartQty >= (float) $requirement->qty;
        });

        if ($logic === 'or') {
            return $matches->contains(true);
        }

        return $matches->every(fn ($matched) => $matched === true);
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
            'payment_method' => 'required|in:cash,qris,transfer,debit,credit',
            'amount_paid' => 'required|numeric|min:0',
            'order_type' => 'required|in:dine_in,delivery',
            'discount_id' => 'nullable|exists:discounts,id',
            'promo_id' => 'nullable|exists:promos,id',
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

        $discountResult = $this->calculateDiscountAmount(
            cart: $cart,
            subtotal: (float) $subtotal,
            user: $user,
            discountId: $request->filled('discount_id') ? (int) $request->input('discount_id') : null,
            promoId: $request->filled('promo_id') ? (int) $request->input('promo_id') : null,
        );

        $discountAmount = (float) $discountResult['discount_amount'];
        $grandTotal = max(0, (float) $subtotal - $discountAmount);

        $paymentMethod = strtolower((string) $request->input('payment_method'));
        $amountPaid = (float) $request->input('amount_paid');

        if ($paymentMethod === 'cash' && $amountPaid < $grandTotal) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Nominal cash kurang dari total transaksi.');
        }

        if (in_array($paymentMethod, ['qris', 'transfer', 'debit', 'credit'], true)) {
            $amountPaid = $grandTotal;
        }

        $changeAmount = max(0, $amountPaid - $grandTotal);
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

        try {
            $transaction = DB::transaction(function () use (
                $user,
                $activeShift,
                $cart,
                $subtotal,
                $discountAmount,
                $grandTotal,
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
                    'discount_amount' => $discountAmount,
                    'tax_amount' => 0,
                    'grand_total' => $grandTotal,
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
                        $earnedPoints = $member->addPointsFromAmount((float) $grandTotal);
                    }
                }

                return $transaction;
            });

            $stockDeductionService->deductFromTransaction($transaction);
        } catch (\Throwable $e) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Checkout gagal diproses: ' . $e->getMessage());
        }

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