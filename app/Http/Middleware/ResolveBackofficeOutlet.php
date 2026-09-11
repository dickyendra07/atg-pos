<?php

namespace App\Http\Middleware;

use App\Services\BackofficeOutletContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveBackofficeOutlet
{
    public function __construct(private readonly BackofficeOutletContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $hadSelection = session()->has(BackofficeOutletContext::SESSION_KEY);
            $outlets = $this->context->accessibleOutlets($user);
            $activeOutlet = $this->context->activeOutlet($user);

            if ($hadSelection && ! $activeOutlet) {
                session()->flash('warning', 'Outlet Backoffice sebelumnya tidak aktif atau tidak lagi dapat diakses. Silakan pilih outlet yang valid.');
            }

            View::share('backofficeOutletOptions', $outlets);
            View::share('activeBackofficeOutlet', $activeOutlet);
        }

        return $next($request);
    }
}
