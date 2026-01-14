<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Category;

class ProductFactory extends Factory
{
    public function definition()
    {
        $name = $this->faker->words(3, true);
        $price = $this->faker->randomFloat(2, 10, 1000);
        
        return [
            'category_id' => Category::inRandomOrder()->first()->id ?? 1,
            'name' => $name,
            'vendor' => $this->faker->optional()->company(),
            'type' => $this->faker->optional()->randomElement(['physical', 'digital', 'service']),
            'sku' => $this->faker->optional()->bothify('SKU-####-???'),
            'barcode' => $this->faker->optional()->ean13(),
            'description' => $this->faker->sentence(),
            'price' => $price,
            'compare_at_price' => $this->faker->optional()->randomFloat(2, $price * 1.1, $price * 1.5),
            'cost_per_item' => $this->faker->optional()->randomFloat(2, $price * 0.3, $price * 0.7),
            'stock' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['active', 'inactive', 'draft']),
            'track_quantity' => $this->faker->boolean(80),
            'allow_backorder' => $this->faker->boolean(20),
            'weight' => $this->faker->optional()->randomFloat(2, 0.1, 50),
            'weight_unit' => $this->faker->optional()->randomElement(['kg', 'g', 'lb', 'oz']),
            'tags' => $this->faker->optional()->words(3, true),
            'meta_title' => $this->faker->optional()->sentence(5),
            'meta_description' => $this->faker->optional()->sentence(10),
            'handle' => $this->faker->optional()->slug($name),
            'requires_shipping' => $this->faker->boolean(70),
            'taxable' => $this->faker->boolean(90),
            'image' => null,
        ];
    }
}
