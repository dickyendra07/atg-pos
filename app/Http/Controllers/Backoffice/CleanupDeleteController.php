<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\CleanupDeletionService as Cleanup;
use App\Services\ProductAccessPolicy;
use App\Support\BackofficeReturnUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Temporary cleanup delete (Product, Variant, Ingredient, Recipe).
 *
 * Every request, in this order: the feature flag, the role (owner / admin_pusat), the resource, the
 * typed confirmation, then the service (which validates the impact again under a lock). A hidden button
 * is never the protection: these checks run on the server for any direct or forged request.
 */
class CleanupDeleteController extends Controller
{
    private const FALLBACK_ROUTE = [
        Cleanup::TYPE_PRODUCT => 'backoffice.products.index',
        Cleanup::TYPE_VARIANT => 'backoffice.variants.index',
        Cleanup::TYPE_INGREDIENT => 'backoffice.ingredients.index',
        Cleanup::TYPE_RECIPE => 'backoffice.recipes.index',
    ];

    /** JSON for the confirmation dialog: counts, blockers, notes (fresh at the moment the dialog opens). */
    public function impact(Request $request, string $type, int $id, Cleanup $service): JsonResponse
    {
        Cleanup::authorize($request->user());
        abort_unless(in_array($type, Cleanup::TYPES, true), 404);

        $model = $service->find($type, $id);
        abort_unless($model, 404, 'Data sudah tidak ada.');
        $this->authorizeResource($request, $type, $model);

        return response()->json(['ok' => true, 'impact' => $service->impact($type, $model)]);
    }

    public function destroy(Request $request, string $type, int $id, Cleanup $service)
    {
        Cleanup::authorize($request->user());
        abort_unless(in_array($type, Cleanup::TYPES, true), 404);

        $label = Cleanup::label($type);
        $model = $service->find($type, $id);

        if (! $model) {
            return $this->respond($request, $type, 404, 'warning', $label.' sudah tidak ada.');
        }

        $this->authorizeResource($request, $type, $model);

        $result = $service->delete($type, $id, (string) $request->input('confirmation', ''));

        return match ($result['status']) {
            Cleanup::STATUS_DELETED => $this->respond($request, $type, 200, 'success', $this->successMessage($type, $result['name'])),
            Cleanup::STATUS_BLOCKED => $this->respond($request, $type, 422, 'error', $label.' tidak dapat dihapus: '.($result['impact']['blockers'][0]['message'] ?? 'masih ada ketergantungan.')),
            Cleanup::STATUS_MISSING => $this->respond($request, $type, 404, 'warning', $label.' sudah tidak ada.'),
            default => $this->respond($request, $type, 422, 'error', 'Konfirmasi tidak cocok. Ketik nama '.$label.' persis seperti yang tertera untuk menghapus.'),
        };
    }

    /** The only roles that get here own every record; the Product outlet policy is still applied for Product pages. */
    private function authorizeResource(Request $request, string $type, $model): void
    {
        $product = match ($type) {
            Cleanup::TYPE_PRODUCT => $model,
            Cleanup::TYPE_VARIANT => $model->product,
            default => null,
        };

        if ($product instanceof Product) {
            app(ProductAccessPolicy::class)->authorize($request->user(), $product);
        }
    }

    private function successMessage(string $type, string $name): string
    {
        return match ($type) {
            Cleanup::TYPE_RECIPE => 'Recipe "'.$name.'" dihapus permanen. Variant tidak punya Recipe sampai Recipe baru dibuat.',
            default => Cleanup::label($type).' "'.$name.'" dihapus dari sistem. Riwayat transaksi dan stok tetap tersimpan.',
        };
    }

    private function respond(Request $request, string $type, int $status, string $flash, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $flash === 'success', 'message' => $message], $status);
        }

        return BackofficeReturnUrl::redirect($request, self::FALLBACK_ROUTE[$type])->with($flash, $message);
    }
}
