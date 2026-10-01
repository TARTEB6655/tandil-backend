<?php

namespace App\Providers;

use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Policies\VendorPolicy;
use App\Policies\VendorProductPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Vendor::class => VendorPolicy::class,
        VendorProduct::class => VendorProductPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
