<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // Load API routes with prefix 'api' and 'api' middleware group
            // ForceJsonResponse ensures all API routes expect JSON responses
            Route::middleware(['api', \App\Http\Middleware\ForceJsonResponse::class])
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Load web routes with 'web' middleware group
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
