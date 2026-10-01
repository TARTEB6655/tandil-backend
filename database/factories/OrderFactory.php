<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        $paymentStatus = $this->faker->randomElement(['pending','paid','failed']);
        
        return [
            'user_id' => null,
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'payment_status' => $paymentStatus,
            'payment_reference' => $paymentStatus === 'paid' ? $this->faker->uuid() : null,
            'payment_method' => $paymentStatus === 'paid' ? $this->faker->randomElement(['paypal', 'stripe', 'cash']) : null,
            'transaction_id' => $paymentStatus === 'paid' ? $this->faker->uuid() : null,
            'paid_at' => $paymentStatus === 'paid' ? now()->subDays(rand(0, 30)) : null,
            'order_status' => $this->faker->randomElement(['processing','shipped','delivered','cancelled']),
            'refunded_at' => null,
            'refund_amount' => null,
            'refund_reason' => null,
        ];
    }
}
