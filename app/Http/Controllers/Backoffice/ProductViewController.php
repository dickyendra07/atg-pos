<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductViewController extends Controller
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
            abort(403, 'Role kamu tidak punya akses ke halaman Products.');
        }

        return $user;
    }

    public function index()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $products = Product::with(['brand', 'category', 'variants'])
            ->latest()
            ->get();

        return view('backoffice.products.index', [
            'user' => $user,
            'products' => $products,
        ]);
    }

    public function create()
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $brands = Brand::orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();

        return view('backoffice.products.create', [
            'user' => $user,
            'brands' => $brands,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code',
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        Product::create($validated);

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product baru berhasil ditambahkan.');
    }

    public function edit(Product $product)
    {
        $user = $this->authorizeAccess();
        $user->load(['outlet']);

        $brands = Brand::orderBy('name')->get();
        $categories = ProductCategory::orderBy('name')->get();

        return view('backoffice.products.edit', [
            'user' => $user,
            'product' => $product,
            'brands' => $brands,
            'categories' => $categories,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeAccess();

        $validated = $request->validate([
            'brand_id' => 'required|exists:brands,id',
            'product_category_id' => 'required|exists:product_categories,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:products,code,' . $product->id,
            'description' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $product->update($validated);

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product berhasil diupdate.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeAccess();

        $product->loadCount('variants');

        if ($product->variants_count > 0) {
            return redirect()
                ->route('backoffice.products.index')
                ->with('error', 'Product tidak bisa dihapus karena masih punya variants. Hapus variants dulu.');
        }

        $productName = $product->name;
        $product->delete();

        return redirect()
            ->route('backoffice.products.index')
            ->with('success', 'Product "' . $productName . '" berhasil dihapus.');
    }
}