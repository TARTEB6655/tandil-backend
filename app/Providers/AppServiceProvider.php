<?php

namespace App\Providers;

use App\Contracts\SmsSender;
use App\Models\Order;
use App\Models\Subscription;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\TwilioSmsSender;
use App\Observers\DatabaseNotificationObserver;
use App\Observers\OrderObserver;
use App\Observers\SubscriptionObserver;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsSender::class, function () {
            $driver = (string) config('services.sms.driver', 'log');

            return $driver === 'twilio'
                ? $this->app->make(TwilioSmsSender::class)
                : $this->app->make(LogSmsSender::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // UAE product — keep all app clocks on Dubai time (overrides mistaken APP_TIMEZONE=UTC).
        $tz = 'Asia/Dubai';
        config(['app.timezone' => $tz]);
        date_default_timezone_set($tz);

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

        $this->registerCatalogConvertRoutesWhenMissingFromCache();
    }

    /**
     * Live servers with stale bootstrap/cache/routes-v7.php never load new api.php routes.
     * Register convert endpoints on boot when absent (safe no-op when route cache is fresh).
     */
    private function registerCatalogConvertRoutesWhenMissingFromCache(): void
    {
        if (! $this->app->routesAreCached()) {
            return;
        }

        if (Route::has('admin.services.convert-to-category')
            || Route::has('bootstrap.admin.services.convert-to-category')) {
            return;
        }

        Route::middleware(['api', 'auth:sanctum,web', 'role:admin'])->group(function (): void {
            Route::post('api/admin/services/{id}/convert-to-category', [
                \App\Http\Controllers\Api\Admin\ServiceController::class,
                'convertToCategory',
            ])->name('admin.services.convert-to-category');

            Route::post('api/admin/categories/{id}/convert-to-service', [
                \App\Http\Controllers\CategoryController::class,
                'convertToService',
            ])->name('admin.categories.convert-to-service');

            Route::post('api/admin/catalog/convert/service-to-category', [
                \App\Http\Controllers\Api\Admin\ServiceController::class,
                'convertToCategoryFromBody',
            ])->name('admin.catalog.service-to-category');

            Route::post('api/admin/catalog/convert/category-to-service', [
                \App\Http\Controllers\CategoryController::class,
                'convertToServiceFromBody',
            ])->name('admin.catalog.category-to-service');

            Route::post('api/admin/catalog/restore-platform-services', [
                \App\Http\Controllers\Api\Admin\ServiceController::class,
                'restorePlatformServices',
            ])->name('admin.catalog.restore-platform-services');
        });
    }
}
