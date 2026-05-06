<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\Discount;
use App\Models\Member;
use App\Models\ProductVariant;
use App\Models\Promo;
use App\Models\BackofficeNotification;
use App\Models\ApprovalPin;
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
                    'is_promo_reward' => (bool) ($item['is_promo_reward'] ?? false),
                    'promo_id' => $item['promo_id'] ?? null,
                    'promo_name' => $item['promo_name'] ?? null,
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



    protected function promoAppliesToUserOutlet(Promo $promo, $user): bool
    {
        $promo->loadMissing(['outlets']);

        if ($promo->outlets->isEmpty()) {
            return true;
        }

        if (empty($user->outlet_id)) {
            return false;
        }

        return $promo->outlets->contains('id', (int) $user->outlet_id);
    }

    protected function upsertPromoCartItem(array $cart, ProductVariant $variant, float $qty, string $orderType, ?Promo $promo = null, bool $isReward = false): array
    {
        $variant->loadMissing(['product.brand', 'product.category']);

        $cartKey = ($isReward ? 'promo_reward_' : 'variant_') . $variant->id . '_' . $orderType . ($isReward && $promo ? '_promo_' . $promo->id : '');

        $price = $isReward
            ? 0
            : (method_exists($variant, 'getPriceByOrderType')
                ? $variant->getPriceByOrderType($orderType)
                : (float) ($orderType === 'delivery'
                    ? ($variant->price_delivery ?? $variant->price)
                    : ($variant->price_dine_in ?? $variant->price)));

        if (isset($cart[$cartKey])) {
            $cart[$cartKey]['qty'] = (float) ($cart[$cartKey]['qty'] ?? 0) + $qty;
            $cart[$cartKey]['line_total'] = (float) $cart[$cartKey]['qty'] * (float) $cart[$cartKey]['price'];
            return $cart;
        }

        $cart[$cartKey] = [
            'cart_key' => $cartKey,
            'variant_id' => $variant->id,
            'product_id' => $variant->product?->id,
            'product_name' => $variant->product?->name,
            'brand_name' => $variant->product?->brand?->name,
            'category_name' => $variant->product?->category?->name,
            'variant_name' => $variant->name . ($isReward ? ' [PROMO FREE ITEM]' : ''),
            'order_type' => $orderType,
            'less_sugar' => false,
            'less_ice' => false,
            'qty' => $qty,
            'price' => (float) $price,
            'line_total' => (float) $price * $qty,
            'is_promo_reward' => $isReward,
            'promo_id' => $promo?->id,
            'promo_name' => $promo?->name,
        ];

        return $cart;
    }

    public function applyPromo(Request $request, Promo $promo)
    {
        $user = $this->authorizeCashierAccess();

        if (! $this->getActiveShift($user)) {
            return $this->shiftBlockedResponse(
                $request,
                'Shift belum dibuka. Start shift dulu sebelum melakukan transaksi.'
            );
        }

        $promo->load(['requirements.variant.product.brand', 'rewards.variant.product.brand', 'outlets']);

        if (! $promo->is_active || $promo->status !== 'active' || ! $this->promoIsCurrentlyActive($promo) || ! $this->promoAppliesToUserOutlet($promo, $user)) {
            $message = 'Promo tidak aktif atau tidak berlaku untuk outlet ini.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->route('cashier.index')->with('error', $message);
        }

        if ($promo->requirements->isEmpty() && $promo->rewards->where('reward_type', 'free_item')->isEmpty()) {
            $message = 'Promo belum punya item yang bisa dimasukkan otomatis.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $message,
                ], 422);
            }

            return redirect()->route('cashier.index')->with('error', $message);
        }

        $orderType = $this->resolveOrderType($request->input('order_type', session('cashier_order_type', 'dine_in')));
        session(['cashier_order_type' => $orderType]);

        $cart = session('cashier_cart', []);

        foreach ($promo->requirements as $requirement) {
            if (! $requirement->variant) {
                continue;
            }

            $cart = $this->upsertPromoCartItem(
                cart: $cart,
                variant: $requirement->variant,
                qty: max(1, (float) $requirement->qty),
                orderType: $orderType,
                promo: $promo,
                isReward: false
            );
        }

        foreach ($promo->rewards as $reward) {
            if ($reward->reward_type !== 'free_item' || ! $reward->variant) {
                continue;
            }

            $cart = $this->upsertPromoCartItem(
                cart: $cart,
                variant: $reward->variant,
                qty: max(1, (float) $reward->qty),
                orderType: $orderType,
                promo: $promo,
                isReward: true
            );
        }

        session([
            'cashier_cart' => $cart,
            'cashier_quick_promo_id' => $promo->id,
        ]);

        $payload = $this->buildCartPayload($cart, session('cashier_member'), $orderType);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Promo ' . $promo->name . ' berhasil dimasukkan ke cart.',
                'cart' => $payload,
                'promo_id' => $promo->id,
            ]);
        }

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Promo ' . $promo->name . ' berhasil dimasukkan ke cart.');
    }



    protected function getPromoEligibleSubtotal(array $cart, Promo $promo): float
    {
        if ($promo->requirements->isEmpty()) {
            return 0;
        }

        $logic = strtolower((string) ($promo->requirement_logic ?? 'and'));

        $eligibleVariantIds = $promo->requirements
            ->filter(function ($requirement) use ($cart, $logic) {
                $cartQty = collect($cart)
                    ->where('variant_id', (int) $requirement->product_variant_id)
                    ->sum(function ($item) {
                        return (float) ($item['qty'] ?? 0);
                    });

                if ($logic === 'or') {
                    return $cartQty >= (float) $requirement->qty;
                }

                return true;
            })
            ->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($eligibleVariantIds->isEmpty()) {
            return 0;
        }

        return (float) collect($cart)
            ->filter(function ($item) use ($eligibleVariantIds) {
                return $eligibleVariantIds->contains((int) ($item['variant_id'] ?? 0))
                    && empty($item['is_promo_reward']);
            })
            ->sum(function ($item) {
                return (float) ($item['line_total'] ?? 0);
            });
    }

    protected function calculateDiscountAmount(array $cart, float $subtotal, $user, ?int $discountId, ?int $promoId): array
    {
        $discountAmount = 0;
        $discountLabel = null;
        $promoLabel = null;

        if ($discountId) {
            $discount = Discount::with(['outlets'])
                ->where('is_active', true)
                ->where(function ($query) use ($user) {
                    $query->whereDoesntHave('outlets');

                    if (! empty($user->outlet_id)) {
                        $query->orWhereHas('outlets', function ($outletQuery) use ($user) {
                            $outletQuery->where('outlets.id', $user->outlet_id);
                        });
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
                $promoEligibleSubtotal = $this->getPromoEligibleSubtotal($cart, $promo);
                $promoDiscountAmount = 0;

                foreach ($promo->rewards as $reward) {
                    if ($reward->reward_type === 'discount_percent') {
                        $promoDiscountAmount += $promoEligibleSubtotal * ((float) $reward->reward_value / 100);
                    }

                    if ($reward->reward_type === 'discount_amount') {
                        $promoDiscountAmount += (float) $reward->reward_value;
                    }

                    if ($reward->reward_type === 'free_item') {
                        continue;
                    }
                }

                $discountAmount += min($promoEligibleSubtotal, max(0, $promoDiscountAmount));
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
        $promoLabel = $discountResult['promo_label'] ?? null;
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
                $promoLabel,
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
                    'promo_name' => $promoLabel,
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


    protected function consumeApprovalPin(string $pinCode, string $purpose, $user, ?SalesTransaction $transaction = null): ApprovalPin
    {
        $pinCode = trim($pinCode);

        if ($pinCode === '') {
            throw new RuntimeException('PIN approval wajib diisi.');
        }

        $approvalPin = ApprovalPin::where('pin_code', $pinCode)
            ->whereNull('used_at')
            ->where(function ($query) use ($purpose) {
                $query->where('purpose', $purpose)
                    ->orWhere('purpose', 'all');
            })
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->when($transaction, function ($query) use ($transaction) {
                $query->where('sales_transaction_id', $transaction->id)
                    ->where('outlet_id', $transaction->outlet_id);
            })
            ->latest()
            ->lockForUpdate()
            ->first();

        if (! $approvalPin || ! $approvalPin->isUsableFor($purpose, $transaction?->outlet_id, $transaction?->id)) {
            throw new RuntimeException('PIN approval tidak valid untuk transaksi/outlet ini, sudah dipakai, atau sudah expired.');
        }

        $approvalPin->update([
            'used_at' => now(),
            'used_by_user_id' => $user->id,
        ]);

        return $approvalPin;
    }

    protected function createApprovalRequestNotification(SalesTransaction $transaction, $user, string $purpose, ?string $reason = null): void
    {
        $type = $purpose === 'void' ? 'transaction_void_request' : 'receipt_reprint_request';
        $title = $purpose === 'void' ? 'Request PIN Void' : 'Request PIN Reprint';
        $actionLabel = $purpose === 'void' ? 'void' : 'reprint ke-3';

        BackofficeNotification::create([
            'type' => $type,
            'title' => $title,
            'message' => 'Kasir ' . ($user->name ?? 'user') . ' meminta PIN untuk ' . $actionLabel . ' transaksi ' . ($transaction->transaction_number ?? '-') . ($reason ? '. Alasan: ' . $reason : ''),
            'sales_transaction_id' => $transaction->id,
            'outlet_id' => $transaction->outlet_id,
            'created_by_user_id' => $user->id,
        ]);
    }

    protected function authorizeCashierTransactionAccess(SalesTransaction $transaction)
    {
        $user = Auth::user()?->load(['role', 'outlet']);

        if (! $user) {
            return null;
        }

        if (! $user->canAccessCashier()) {
            return null;
        }

        if ($user->isFullAccessUser()) {
            return $user;
        }

        if ((int) ($transaction->outlet_id ?? 0) !== (int) ($user->outlet_id ?? 0)) {
            return null;
        }

        return $user;
    }

    public function cashierReceipt(Request $request, SalesTransaction $transaction)
    {
        $user = $this->authorizeCashierTransactionAccess($transaction);

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses receipt transaksi ini.');
        }

        $transaction->load(['user', 'outlet', 'member', 'items', 'voidBy']);

        $currentPrintCount = (int) ($transaction->receipt_print_count ?? 0);

        if ($currentPrintCount >= 2) {
            try {
                DB::transaction(function () use ($request, $user, $transaction) {
                    $this->consumeApprovalPin((string) $request->input('approval_pin'), 'reprint', $user, $transaction);
                });
            } catch (\Throwable $e) {
                $this->createApprovalRequestNotification($transaction, $user, 'reprint');

                return redirect()
                    ->route('cashier.index')
                    ->with('error', 'Reprint ke-3 butuh approval PIN. Request sudah masuk ke back office.');
            }
        }

        $transaction->increment('receipt_print_count');

        if ($currentPrintCount >= 2) {
            BackofficeNotification::create([
                'type' => 'receipt_reprint',
                'title' => 'Receipt di-reprint',
                'message' => 'Receipt transaksi ' . ($transaction->transaction_number ?? '-') . ' di-reprint dari kasir oleh ' . ($user->name ?? 'user') . '.',
                'sales_transaction_id' => $transaction->id,
                'outlet_id' => $transaction->outlet_id,
                'created_by_user_id' => $user->id,
            ]);
        }

        return view('backoffice.transactions.receipt', [
            'transaction' => $transaction->fresh(['user', 'outlet', 'member', 'items', 'voidBy']),
            'source' => 'cashier',
            'autoprint' => true,
            'isReprintReceipt' => $currentPrintCount >= 2,
            'reprintPrintedAt' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function cashierVoid(Request $request, SalesTransaction $transaction, StockDeductionService $stockDeductionService)
    {
        $user = $this->authorizeCashierTransactionAccess($transaction);

        if (! $user) {
            abort(403, 'Role kamu tidak punya akses void transaksi ini.');
        }

        $validated = $request->validate([
            'void_reason' => 'required|string|max:1000',
            'approval_pin' => 'nullable|string|max:20',
        ], [
            'void_reason.required' => 'Alasan void wajib diisi.',
        ]);

        if (strtolower((string) $transaction->status) === 'void') {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Transaksi ini sudah berstatus void.');
        }

        if (empty(trim((string) ($validated['approval_pin'] ?? '')))) {
            $this->createApprovalRequestNotification($transaction, $user, 'void', $validated['void_reason']);

            return redirect()
                ->route('cashier.index')
                ->with('error', 'Void butuh approval PIN. Request sudah masuk ke back office.');
        }

        try {
            DB::transaction(function () use ($transaction, $validated, $user, $stockDeductionService) {
                $lockedTransaction = SalesTransaction::whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (strtolower((string) $lockedTransaction->status) === 'void') {
                    throw new RuntimeException('Transaksi ini sudah void.');
                }

                try {
                    $this->consumeApprovalPin((string) ($validated['approval_pin'] ?? ''), 'void', $user, $lockedTransaction);
                } catch (\Throwable $e) {
                    $this->createApprovalRequestNotification($lockedTransaction, $user, 'void', $validated['void_reason']);
                    throw new RuntimeException('Void butuh approval PIN. Request sudah masuk ke back office.');
                }

                $lockedTransaction->update([
                    'status' => 'void',
                    'void_at' => now(),
                    'void_reason' => $validated['void_reason'],
                    'void_by_user_id' => $user->id,
                ]);

                $stockDeductionService->restoreFromVoidedTransaction($lockedTransaction);

                BackofficeNotification::create([
                    'type' => 'transaction_void',
                    'title' => 'Transaksi di-void',
                    'message' => 'Transaksi ' . ($lockedTransaction->transaction_number ?? '-') . ' di-void dari kasir oleh ' . ($user->name ?? 'user') . '. Alasan: ' . $validated['void_reason'],
                    'sales_transaction_id' => $lockedTransaction->id,
                    'outlet_id' => $lockedTransaction->outlet_id,
                    'created_by_user_id' => $user->id,
                ]);
            });
        } catch (\Throwable $e) {
            return redirect()
                ->route('cashier.index')
                ->with('error', 'Void gagal diproses: ' . $e->getMessage());
        }

        return redirect()
            ->route('cashier.index')
            ->with('success', 'Transaksi berhasil di-void dari kasir dan notifikasi sudah masuk back office.');
    }

}
