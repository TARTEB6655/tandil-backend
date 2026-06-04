<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition()
    {
        $name = $this->faker->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->sentence(),
            'shipping_cost' => 50,
            'tax_percentage' => 5,
        ];
    }

    public function carDelivery(): static
    {
        return $this->state(fn () => [
            'shipping_cost' => 150,
            'tax_percentage' => 18,
        ]);
    }
}
