<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductVariantViewController extends Controller
{
    protected function authorizeAccess()
    {
        $user = Auth::user()->load(['role']);

        $allowedRoles = [
            'owner',
            'admin_pusat',
            'admin_outlet',
        ];

        if (! in_array($user->role?->code, $allowedRoles)) {
            abort(403, 'Role kamu tidak punya akses ke halaman Variants.');
        }

        return $user;
    }

    public function index()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $variants = ProductVariant::with(['product.brand', 'product.category'])
            ->latest()
            ->get();

        return view('backoffice.variants.index', [
            'user' => $user,
            'variants' => $variants,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        return view('backoffice.variants.create', [
            'user' => $user,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:product_variants,code',
            'price_dine_in' => 'required|numeric|min:0',
            'price_delivery' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        ProductVariant::create([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'price' => $validated['price_dine_in'],
            'price_dine_in' => $validated['price_dine_in'],
            'price_delivery' => $validated['price_delivery'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant baru berhasil ditambahkan.');
    }

    public function edit(ProductVariant $variant)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category'])
            ->orderBy('name')
            ->get();

        return view('backoffice.variants.edit', [
            'user' => $user,
            'variant' => $variant,
            'products' => $products,
        ]);
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:product_variants,code,' . $variant->id,
            'price_dine_in' => 'required|numeric|min:0',
            'price_delivery' => 'required|numeric|min:0',
            'is_active' => 'required|boolean',
        ]);

        $variant->update([
            'product_id' => $validated['product_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'price' => $validated['price_dine_in'],
            'price_dine_in' => $validated['price_dine_in'],
            'price_delivery' => $validated['price_delivery'],
            'is_active' => $validated['is_active'],
        ]);

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant berhasil diupdate.');
    }

    public function destroy(ProductVariant $variant)
    {
        $this->authorizeAccess();

        $variant->loadCount([
            'recipe',
            'salesTransactionItems',
        ]);

        if ($variant->recipe_count > 0 || $variant->sales_transaction_items_count > 0) {
            return redirect()
                ->route('backoffice.variants.index')
                ->with('error', 'Variant tidak bisa dihapus karena masih dipakai di recipe / transaksi.');
        }

        $variantName = $variant->name;
        $variant->delete();

        return redirect()
            ->route('backoffice.variants.index')
            ->with('success', 'Variant "' . $variantName . '" berhasil dihapus.');
    }
}