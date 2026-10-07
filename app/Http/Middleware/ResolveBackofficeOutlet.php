<?php

namespace App\Http\Middleware;

use App\Services\BackofficeOutletContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveBackofficeOutlet
{
    /** Session key of the removed Back Office outlet selector; ignored and cleaned up when found. */
    private const LEGACY_SESSION_KEY = 'active_backoffice_outlet_id';

    public function __construct(private readonly BackofficeOutletContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (session()->has(self::LEGACY_SESSION_KEY)) {
                session()->forget(self::LEGACY_SESSION_KEY);
            }

            View::share('backofficeOutletOptions', $this->context->accessibleOutlets($user));
            View::share('activeBackofficeOutlet', null);
            View::share('activeOutletLabel', $this->context->labelFor($user, null));
        }

        return $next($request);
    }
}
