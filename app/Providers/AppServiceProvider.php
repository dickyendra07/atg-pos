<?php

namespace App\Providers;

use App\Support\BackofficeReturnUrl;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The current Backoffice list URL (search/filter/page included), for its Edit/Create links and
        // row-action forms to hand on as return_to. See BackofficeReturnUrl.
        View::composer('backoffice.*', function ($view) {
            $view->with('listReturnTo', BackofficeReturnUrl::current());
        });
    }
}
