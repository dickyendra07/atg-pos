<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Backoffice\Concerns\RespondsAsProductWorkspace;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\CategoryWriter;
use App\Services\ProductDuplicateGuard;
use App\Services\ProductWorkspace;
use App\Services\ProductWriter;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;

/**
 * Section saves of the Product Workspace (the page itself is ProductViewController::edit).
 *
 * Every action is bound to one {product}, checks the same role as the Product pages plus the
 * Product's outlet scope, and writes through the same writers as the classic routes. With
 * Accept: application/json the response carries the re-rendered sections; without it (no JS) the
 * user is redirected back to the same workspace section with a flash message.
 */
class ProductWorkspaceController extends Controller
{
    use RespondsAsProductWorkspace;

    public function updateGeneral(Request $request, Product $product, ProductWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);

        $validated = $request->validate($writer->generalRules($product) + ['confirm_similar' => 'nullable|boolean']);
        $attributes = collect($validated)->except('confirm_similar')->all();

        // Shared Product + limited user: refused here (422) before any duplicate warning or write.
        $writer->assertCanChangeGeneral($user, $product, $attributes);

        $similar = $writer->similarAfterGeneralChange($product, $attributes);

        if ($similar->isNotEmpty() && ! $request->boolean('confirm_similar')) {
            // Only Products this user may open are described; the rest is a generic notice (ProductDuplicateGuard).
            $guard = app(ProductDuplicateGuard::class);
            $split = $guard->split($similar, $user);
            $message = $guard->confirmationMessage($split);

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'needs_confirmation' => 'similar_product', 'message' => $message, 'similar_products' => $split['visible'], 'similar_hidden' => $split['hidden']], 409);
            }

            return back()->withInput()->with('similar_products', $split['visible'])->with('similar_hidden', $split['hidden']);
        }

        $writer->updateGeneral($user, $product, $attributes);

        return $this->saved($request, $product, $user, 'general', 'General Product berhasil disimpan.');
    }

    public function updateOutlets(Request $request, Product $product, ProductWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);

        $validated = $request->validate($writer->outletRules() + $writer->assignVariantsRules());
        $plan = $writer->updateOutlets($user, $product, $validated['outlet_ids'], (bool) ($validated['assign_variants'] ?? false));

        $deactivated = collect($plan['variants'])->where('will_deactivate', true)->where('was_active', true)->count();
        $message = 'Outlet Product berhasil disimpan.'
            .($deactivated ? ' '.$deactivated.' Variant dinonaktifkan karena tidak lagi memiliki outlet.' : '');

        if ($plan['assignments'] !== []) {
            $message .= ' '.count($plan['assignments']).' Variant aktif ikut di-assign ke outlet baru. Cek kesiapan jual (Recipe/Ingredient) di Stock & Readiness.';
        }

        return $this->saved($request, $product, $user, 'outlets', $message);
    }

    /** What saving the given outlets would change. Read-only; same computation as updateOutlets(). */
    public function previewOutlets(Request $request, Product $product, ProductWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);

        $validated = $request->validate([
            'outlet_ids' => 'nullable|array',
            'outlet_ids.*' => 'exists:outlets,id',
        ] + $writer->assignVariantsRules());

        $outletIds = $validated['outlet_ids'] ?? [];
        $writer->assertAccessibleOutletIds($user, $outletIds);
        $plan = $writer->planOutletChange($user, $product, $outletIds, (bool) ($validated['assign_variants'] ?? false));

        return response()->json([
            'ok' => true,
            'has_consequences' => $plan['has_consequences'],
            'empty_selection' => $outletIds === [],
            'removed' => $plan['removed'],
            'deactivated_variant_ids' => collect($plan['variants'])->where('will_deactivate', true)->pluck('id')->values()->all(),
            'assignable_variant_ids' => collect($plan['assignable'])->pluck('id')->values()->all(),
            'assigned_variant_ids' => collect($plan['assignments'])->pluck('id')->values()->all(),
            'promo_ids' => collect($plan['promos'])->pluck('id')->values()->all(),
            'html' => view('backoffice.products.workspace._outlet-preview', [
                'plan' => $plan,
                'emptySelection' => $outletIds === [],
            ])->render(),
        ]);
    }

    /** Menu Category created from the General section, with the Category pages' own rules. */
    public function storeMenuCategory(Request $request, Product $product, CategoryWriter $writer)
    {
        $user = $this->authorizeProduct($request, $product);
        abort_unless(CategoryWriter::canManage($user->loadMissing('roles')), 403, CategoryWriter::DENIED_MESSAGE);

        $category = $writer->create(ProductCategory::class, $writer->validated($request, ProductCategory::class, true));

        if (! $request->expectsJson()) {
            return redirect(ProductWorkspace::url($product, 'general', BackofficeReturnUrl::fromRequest($request)))
                ->with('success', 'Kategori berhasil ditambahkan.');
        }

        return response()->json([
            'ok' => true,
            'message' => $category->is_active
                ? 'Kategori berhasil ditambahkan dan dipilih.'
                : 'Kategori berhasil ditambahkan, tetapi nonaktif sehingga tidak dapat dipilih untuk Product.',
            'category' => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'brand_id' => (int) $category->brand_id,
                'is_active' => (bool) $category->is_active,
            ],
        ]);
    }
}
