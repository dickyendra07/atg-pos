<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Backoffice\Concerns\RespondsAsProductWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\BackofficeOutletContext;
use App\Services\VariantWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;

/**
 * Variants & Pricing in the Product Workspace: one Variant at a time, written by VariantWriter (the
 * same rules as the classic group editor). Every {variant} must belong to {product}.
 */
class ProductWorkspaceVariantController extends Controller
{
    use RespondsAsProductWorkspace;

    /** Drawer forms, built from the Product's CURRENT outlets (never a stale copy from the page). */
    public function createForm(Request $request, Product $product)
    {
        $user = $this->authorizeProduct($request, $product);

        return $this->form($request, $user, $product, null);
    }

    public function editForm(Request $request, Product $product, ProductVariant $variant)
    {
        $user = $this->authorizeProduct($request, $product);
        $this->assertOwnership($product, $variant);

        return $this->form($request, $user, $product, $variant);
    }

    public function store(Request $request, Product $product, VariantWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);

        $variant = $writer->create($user, $product, $this->validated($request, $writer));

        return $this->saved($request, $product, $user, 'variants', 'Variant "'.$variant->name.'" berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product, ProductVariant $variant, VariantWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);
        $this->assertOwnership($product, $variant);

        $writer->update($user, $product, $variant, $this->validated($request, $writer));

        return $this->saved($request, $product, $user, 'variants', 'Variant "'.$variant->fresh()->name.'" berhasil disimpan.');
    }

    public function deactivate(Request $request, Product $product, ProductVariant $variant, VariantWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);
        $this->assertOwnership($product, $variant);

        $writer->deactivate($user, $variant);

        return $this->saved($request, $product, $user, 'variants', 'Variant "'.$variant->name.'" berhasil dinonaktifkan. Outlet, Recipe, dan riwayat transaksi tetap tersimpan.');
    }

    private function form(Request $request, User $user, Product $product, ?ProductVariant $variant)
    {
        return response()->json([
            'ok' => true,
            'html' => view('backoffice.products.workspace._variant-form', $this->formData($request, $user, $product, $variant))->render(),
        ]);
    }

    private function assertOwnership(Product $product, ProductVariant $variant): void
    {
        abort_unless((int) $variant->product_id === (int) $product->id, 404);
    }

    /** Rupiah inputs ("Rp. 12.000") are normalised to digits before the shared numeric rules run. */
    private function validated(Request $request, VariantWriter $writer): array
    {
        foreach (['price_dine_in', 'price_delivery'] as $field) {
            if ($request->has($field)) {
                $request->merge([$field => VariantWriter::normalizeRupiah($request->input($field))]);
            }
        }

        return $request->validate($writer->rowRules(), [
            'name.required' => 'Nama variant wajib diisi.',
            'outlet_ids.required' => 'Minimal pilih 1 outlet untuk Variant.',
            'price_dine_in.required' => 'Harga dine in wajib diisi.',
            'price_delivery.required' => 'Harga delivery wajib diisi.',
        ]);
    }

    private function formData(Request $request, User $user, Product $product, ?ProductVariant $variant): array
    {
        $accessibleIds = app(BackofficeOutletContext::class)->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id);
        $productOutlets = $product->outlets()->orderBy('name')->get();
        $assigned = $variant ? $variant->outlets()->pluck('outlets.id')->map(fn ($id) => (int) $id) : null;

        return [
            'product' => $product,
            'variant' => $variant,
            'workspaceReturnTo' => BackofficeReturnUrl::fromRequest($request),
            // Product outlets the user may (un)assign; a new Variant starts with all of them.
            'outletChoices' => $productOutlets
                ->filter(fn ($outlet) => $accessibleIds->contains((int) $outlet->id))
                ->map(fn ($outlet) => [
                    'id' => (int) $outlet->id,
                    'name' => $outlet->name,
                    'checked' => $assigned === null || $assigned->contains((int) $outlet->id),
                ])->values()->all(),
            // Assigned outside the user's access: shown, kept on save (VariantWriter::preservedOutletIds).
            'lockedOutlets' => $variant
                ? $productOutlets->whereIn('id', app(VariantWriter::class)->preservedOutletIds($user, $product, $variant))->pluck('name')->values()->all()
                : [],
        ];
    }
}
