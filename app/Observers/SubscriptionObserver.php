<?php

namespace App\Observers;

use App\Models\Subscription;
use App\Services\Loyalty\LoyaltyService;

class SubscriptionObserver
{
    public function __construct(
        private readonly LoyaltyService $loyalty
    ) {}

    public function created(Subscription $subscription): void
    {
        $this->loyalty->creditForPaidSubscription($subscription);
    }

    public function updated(Subscription $subscription): void
    {
        if (! $subscription->wasChanged('payment_status')) {
            return;
        }

        $this->loyalty->creditForPaidSubscription($subscription);
    }
}
