<?php

namespace App\Providers;

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
        app(\Spatie\Translatable\Translatable::class)
            ->fallback(config('locales.fallback', config('app.fallback_locale', 'en')));

        // Ensure view compiled directory exists (fixes view:clear errors)
        $viewPath = storage_path('framework/views');
        if (! is_dir($viewPath)) {
            @mkdir($viewPath, 0775, true);
        }

        // Use Tailwind pagination view
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.tailwind');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');
    }
}
