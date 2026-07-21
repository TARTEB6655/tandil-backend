<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\Subscription;
use App\Observers\DatabaseNotificationObserver;
use App\Observers\OrderObserver;
use App\Observers\SubscriptionObserver;
use Illuminate\Notifications\DatabaseNotification;
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
        // Avoid fatal errors if `composer install` has not run yet (vendor missing on deploy).
        if (class_exists(\Spatie\Translatable\Translatable::class) && $this->app->bound(\Spatie\Translatable\Translatable::class)) {
            $this->app->make(\Spatie\Translatable\Translatable::class)
                ->fallback(config('locales.fallback', config('app.fallback_locale', 'en')));
        }

        // Ensure view compiled directory exists (fixes view:clear errors)
        $viewPath = storage_path('framework/views');
        if (! is_dir($viewPath)) {
            @mkdir($viewPath, 0775, true);
        }

        // Use Tailwind pagination view
        \Illuminate\Pagination\Paginator::defaultView('vendor.pagination.tailwind');
        \Illuminate\Pagination\Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');

        Order::observe(OrderObserver::class);
        Subscription::observe(SubscriptionObserver::class);
        DatabaseNotification::observe(DatabaseNotificationObserver::class);
    }
}
