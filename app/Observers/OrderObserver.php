<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\Loyalty\LoyaltyService;

class OrderObserver
{
    public function __construct(
        private readonly LoyaltyService $loyalty
    ) {}

    public function created(Order $order): void
    {
        $this->loyalty->creditForPaidOrder($order);
    }

    public function updated(Order $order): void
    {
        if (! $order->wasChanged('payment_status')) {
            return;
        }

        $this->loyalty->creditForPaidOrder($order);
    }
}
