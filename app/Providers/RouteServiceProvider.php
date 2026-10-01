<?php

namespace App\Providers;

use App\Models\Vendor;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        Route::bind('vendor', function (string $value) {
            if (request()->is('admin/*') || request()->is('api/admin/*')) {
                return Vendor::withTrashed()->findOrFail($value);
            }

            return Vendor::findOrFail($value);
        });
    }
}
