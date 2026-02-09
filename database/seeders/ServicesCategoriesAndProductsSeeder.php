<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds exactly 5 service categories (with icons) and 10 products.
 * Clears existing categories and products first so the database is not full of dummy data.
 */
class ServicesCategoriesAndProductsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Clearing existing categories and products...');

        Product::query()->delete();
        Category::query()->delete();

        $this->command->info('Seeding 5 categories (with icons) and 10 products...');

        $categories = [
            [
                'name' => 'Watering',
                'description' => 'Regular watering and irrigation services for your plants.',
                'icon' => 'water',
                'is_active' => true,
            ],
            [
                'name' => 'Planting',
                'description' => 'Planting, repotting, and soil preparation.',
                'icon' => 'leaf',
                'is_active' => true,
            ],
            [
                'name' => 'Cleaning',
                'description' => 'Garden and outdoor area cleaning services.',
                'icon' => 'broom',
                'is_active' => true,
            ],
            [
                'name' => 'Full Care',
                'description' => 'Complete plant and garden care packages.',
                'icon' => 'heart',
                'is_active' => true,
            ],
            [
                'name' => 'Maintenance',
                'description' => 'Ongoing maintenance and pruning services.',
                'icon' => 'wrench',
                'is_active' => true,
            ],
        ];

        $createdCategories = [];
        foreach ($categories as $data) {
            $slug = Str::slug($data['name']);
            $category = Category::create([
                'name' => $data['name'],
                'slug' => $slug,
                'description' => $data['description'],
                'icon' => $data['icon'],
                'is_active' => $data['is_active'],
            ]);
            $createdCategories[$data['name']] = $category;
        }

        // 10 products: 2 per category
        $products = [
            ['category' => 'Watering', 'name' => 'Weekly Watering Visit', 'description' => 'One visit per week to water all plants.', 'price' => 25.00],
            ['category' => 'Watering', 'name' => 'Bi-weekly Irrigation Check', 'description' => 'Inspection and adjustment of irrigation systems.', 'price' => 35.00],
            ['category' => 'Planting', 'name' => 'Single Plant Planting', 'description' => 'Plant one tree or shrub with soil prep.', 'price' => 45.00],
            ['category' => 'Planting', 'name' => 'Garden Bed Preparation', 'description' => 'Soil preparation and mulching for a new bed.', 'price' => 80.00],
            ['category' => 'Cleaning', 'name' => 'Garden Clean-up (Small)', 'description' => 'Leaves, debris removal for small gardens.', 'price' => 55.00],
            ['category' => 'Cleaning', 'name' => 'Garden Clean-up (Large)', 'description' => 'Full clean-up for large outdoor areas.', 'price' => 120.00],
            ['category' => 'Full Care', 'name' => 'Full Care Monthly Package', 'description' => 'Watering, pruning, and light cleaning once per month.', 'price' => 150.00],
            ['category' => 'Full Care', 'name' => 'Full Care Visit (One-time)', 'description' => 'One comprehensive care visit.', 'price' => 95.00],
            ['category' => 'Maintenance', 'name' => 'Pruning & Trimming', 'description' => 'Hedge and shrub pruning service.', 'price' => 65.00],
            ['category' => 'Maintenance', 'name' => 'Lawn Mowing (Standard)', 'description' => 'Standard lawn mowing for average-sized lawn.', 'price' => 40.00],
        ];

        foreach ($products as $index => $data) {
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
            Product::create([
                'category_id' => $category->id,
                'name' => $data['name'],
                'description' => $data['description'],
                'price' => $data['price'],
                'stock' => 0,
                'status' => 'active',
                'handle' => $handle,
                'sku' => 'SRV-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
            ]);
        }

        $this->command->info('Done: 5 categories (with icons) and 10 products. No other dummy data.');
    }
}
