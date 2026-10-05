<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;

/**
 * Which Products a Backoffice user may open and edit by ID.
 *
 * Products are GLOBAL rows assigned to outlets. Full-access roles (owner, admin pusat) may open any
 * Product, as before. A limited role (admin outlet) may only open a Product that is available in at
 * least one outlet it can access, so a Product ID cannot be guessed to read or edit another outlet's
 * Product (the Product Workspace shows Variants, Recipes, stock and Promos, not just the Product row).
 *
 * The Active Backoffice Outlet only filters lists; it does not narrow this check.
 */
class ProductAccessPolicy
{
    /** Roles allowed on the Product pages (primary role), as ProductViewController always checked. */
    public const ROLES = ['owner', 'admin_pusat', 'admin_outlet'];

    public const DENIED_MESSAGE = 'Product ini tidak tersedia di outlet yang dapat kamu akses.';

    public const ROLE_DENIED_MESSAGE = 'Role kamu tidak punya akses ke halaman Products.';

    public static function hasProductRole(User $user): bool
    {
        return in_array($user->role?->code, self::ROLES);
    }

    public function __construct(private readonly BackofficeOutletContext $context) {}

    public function canAccess(User $user, Product $product): bool
    {
        if ($user->isFullAccessUser()) {
            return true;
        }

        $accessibleIds = $this->context->accessibleOutlets($user)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $accessibleIds !== [] && $product->outlets()->whereIn('outlets.id', $accessibleIds)->exists();
    }

    public function authorize(User $user, Product $product): void
    {
        abort_unless($this->canAccess($user, $product), 403, self::DENIED_MESSAGE);
    }
}
