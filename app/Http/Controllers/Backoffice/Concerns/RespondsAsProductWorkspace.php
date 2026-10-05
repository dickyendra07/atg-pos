<?php

namespace App\Http\Controllers\Backoffice\Concerns;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductAccessPolicy;
use App\Services\ProductWorkspace;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\Request;

/**
 * Shared by the Product Workspace endpoints: the Product-page role + Product outlet scope check, and
 * the "saved" response (re-rendered sections as JSON, or a redirect back to the section without JS).
 */
trait RespondsAsProductWorkspace
{
    protected function authorizeProduct(Request $request, Product $product): User
    {
        $user = $request->user()->load(['role']);

        abort_unless(ProductAccessPolicy::hasProductRole($user), 403, ProductAccessPolicy::ROLE_DENIED_MESSAGE);
        app(ProductAccessPolicy::class)->authorize($user, $product);

        return $user;
    }

    protected function saved(Request $request, Product $product, User $user, string $section, string $message)
    {
        if (! $request->expectsJson()) {
            return redirect(ProductWorkspace::url($product, $section, BackofficeReturnUrl::fromRequest($request)))
                ->with('success', $message);
        }

        $data = app(ProductWorkspace::class)->viewData($request, $product, $user, $section);

        $sections = [];
        foreach (array_keys(ProductWorkspace::SECTIONS) as $key) {
            $sections[$key] = view('backoffice.products.workspace.'.$key, $data)->render();
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
            'section' => $section,
            'title' => $product->name,
            'header' => view('backoffice.products.workspace._header', $data)->render(),
            'sections' => $sections,
        ]);
    }
}
