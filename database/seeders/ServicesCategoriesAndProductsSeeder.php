<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds service categories and products from scratch (Place Service Orders).
 * Categories = filters in the app (e.g. Watering, Planting). Products = services under each category.
 */
class ServicesCategoriesAndProductsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding service categories and products...');

        $categories = [
            [
                'name' => 'Watering',
                'description' => 'Regular watering and irrigation services for your plants.',
                'is_active' => true,
            ],
            [
                'name' => 'Planting',
                'description' => 'Planting, repotting, and soil preparation.',
                'is_active' => true,
            ],
            [
                'name' => 'Cleaning',
                'description' => 'Garden and outdoor area cleaning services.',
                'is_active' => true,
            ],
            [
                'name' => 'Full Care',
                'description' => 'Complete plant and garden care packages.',
                'is_active' => true,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Ongoing maintenance and pruning services.',
                'is_active' => true,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $data) {
            $slug = Str::slug($data['name']);
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'is_active' => $data['is_active'],
                ]
            );
            $createdCategories[$data['name']] = $category;
        }

        // 5 products: one per category (so each category has at least one service to show)
        $products = [
            ['category' => 'Watering', 'name' => 'Weekly Watering Visit', 'description' => 'One visit per week to water all plants.', 'price' => 25.00],
            ['category' => 'Planting', 'name' => 'Single Plant Planting', 'description' => 'Plant one tree or shrub with soil prep.', 'price' => 45.00],
            ['category' => 'Cleaning', 'name' => 'Garden Clean-up (Small)', 'description' => 'Leaves, debris removal for small gardens.', 'price' => 55.00],
            ['category' => 'Full Care', 'name' => 'Full Care Monthly Package', 'description' => 'Watering, pruning, and light cleaning once per month.', 'price' => 150.00],
            ['category' => 'Maintenance', 'name' => 'Pruning & Trimming', 'description' => 'Hedge and shrub pruning service.', 'price' => 65.00],
        ];

        foreach ($products as $data) {
            $category = $createdCategories[$data['category']] ?? null;
            if (!$category) {
                continue;
            }
            $slug = Str::slug($data['name']);
            $handle = $slug;
            $counter = 0;
            while (Product::where('handle', $handle)->exists()) {
                $counter++;
                $handle = $slug . '-' . $counter;
            }
            Product::firstOrCreate(
                [
                    'name' => $data['name'],
                    'category_id' => $category->id,
                ],
                [
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'stock' => 0,
                    'status' => 'active',
                    'handle' => $handle,
                    'sku' => 'SRV-' . uniqid(),
                ]
            );
        }

        $this->command->info('Created ' . count($createdCategories) . ' categories and ' . count($products) . ' products.');
    }
}
