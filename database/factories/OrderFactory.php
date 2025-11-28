<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => null,
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
            'payment_status' => $this->faker->randomElement(['pending','paid','failed']),
            'order_status' => $this->faker->randomElement(['processing','shipped','delivered']),
        ];
    }
}
