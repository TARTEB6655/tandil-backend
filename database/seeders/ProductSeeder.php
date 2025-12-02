<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        Product::insert([
            [
                'name' => 'Organic Fertilizer',
                'description' => 'High-quality organic fertilizer for all crops.',
                'price' => 150.00,
                'category_id' => 1,
                'stock' => 100,
            ],
            [
                'name' => 'Garden Soil',
                'description' => 'Rich garden soil ideal for vegetables.',
                'price' => 80.00,
                'category_id' => 2,
                'stock' => 200,
            ],
            [
                'name' => 'Pruning Shears',
                'description' => 'Sharp and durable pruning shears for trees and plants.',
                'price' => 500.00,
                'category_id' => 3,
                'stock' => 50,
            ],
            // Add more products as needed
        ]);
    }
}
