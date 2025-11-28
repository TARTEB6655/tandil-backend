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
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // Load web routes with 'web' middleware group
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
