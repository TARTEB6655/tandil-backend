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
        // Routes are now loaded in bootstrap/app.php (Laravel 11 style)
        // This method is kept for compatibility but routes are loaded via bootstrap/app.php
        // If you need to add route model bindings or other route-related logic, add it here
    }
}
