<?php

namespace Database\Factories;

use App\Models\OrderItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition()
    {
        return [
            'order_id' => null,
            'product_id' => null,
            'quantity' => $this->faker->numberBetween(1, 10),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'subtotal' => function (array $attributes) {
                return ($attributes['quantity'] ?? 1) * ($attributes['price'] ?? 100);
            },
        ];
    }
}

