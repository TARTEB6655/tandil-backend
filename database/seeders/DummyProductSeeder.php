<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Str;

class DummyProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 10+ diverse dummy products for testing.
     */
    public function run()
    {
        // Get or create categories
        $fertilizerCategory = Category::firstOrCreate(
            ['name' => 'Fertilizers'],
            ['slug' => 'fertilizers', 'description' => 'Plant fertilizers and nutrients']
        );

        $soilCategory = Category::firstOrCreate(
            ['name' => 'Soil'],
            ['slug' => 'soil', 'description' => 'Garden soil and potting mixes']
        );

        $toolsCategory = Category::firstOrCreate(
            ['name' => 'Tools'],
            ['slug' => 'tools', 'description' => 'Gardening tools and equipment']
        );

        $seedsCategory = Category::firstOrCreate(
            ['name' => 'Seeds'],
            ['slug' => 'seeds', 'description' => 'Plant seeds and seedlings']
        );

        $pesticidesCategory = Category::firstOrCreate(
            ['name' => 'Pesticides'],
            ['slug' => 'pesticides', 'description' => 'Pest control products']
        );

        // Product data with all fields
        $products = [
            [
                'name' => 'Premium Organic Fertilizer 5kg',
                'description' => 'High-quality organic fertilizer enriched with essential nutrients for all types of crops. Promotes healthy growth and increases yield. Made from natural compost and organic matter.',
                'category_id' => $fertilizerCategory->id,
                'price' => 150.00,
                'compare_at_price' => 180.00,
                'cost_per_item' => 90.00,
                'stock' => 100,
                'status' => 'active',
                'vendor' => 'Tandil Supplies',
                'type' => 'Physical',
                'sku' => 'FERT-ORG-001',
                'barcode' => '1234567890123',
                'weight' => '5',
                'weight_unit' => 'kg',
                'tags' => 'organic,fertilizer,premium,nutrients',
                'meta_title' => 'Premium Organic Fertilizer - Tandil',
                'meta_description' => 'Buy premium organic fertilizer for your garden. High-quality nutrients for healthy plant growth.',
                'handle' => 'premium-organic-fertilizer-5kg',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'NPK Balanced Fertilizer 20-20-20',
                'description' => 'Balanced NPK fertilizer perfect for vegetables and flowering plants. Provides essential nitrogen, phosphorus, and potassium in equal proportions for optimal growth.',
                'category_id' => $fertilizerCategory->id,
                'price' => 180.00,
                'compare_at_price' => 220.00,
                'cost_per_item' => 110.00,
                'stock' => 75,
                'status' => 'active',
                'vendor' => 'Green Garden Co',
                'type' => 'Physical',
                'sku' => 'FERT-NPK-002',
                'barcode' => '1234567890124',
                'weight' => '3',
                'weight_unit' => 'kg',
                'tags' => 'npk,fertilizer,balanced,vegetables',
                'meta_title' => 'NPK Balanced Fertilizer 20-20-20',
                'meta_description' => 'Balanced NPK fertilizer for vegetables and flowering plants.',
                'handle' => 'npk-balanced-fertilizer-20-20-20',
                'track_quantity' => true,
                'allow_backorder' => true,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Liquid Fertilizer Concentrate 1L',
                'description' => 'Fast-acting liquid fertilizer that can be easily mixed with water. Ideal for foliar feeding and quick nutrient boost. Suitable for all plant types.',
                'category_id' => $fertilizerCategory->id,
                'price' => 220.00,
                'compare_at_price' => 250.00,
                'cost_per_item' => 130.00,
                'stock' => 50,
                'status' => 'active',
                'vendor' => 'AgriTech Solutions',
                'type' => 'Physical',
                'sku' => 'FERT-LIQ-003',
                'barcode' => '1234567890125',
                'weight' => '1',
                'weight_unit' => 'kg',
                'tags' => 'liquid,fertilizer,concentrate,fast-acting',
                'meta_title' => 'Liquid Fertilizer Concentrate 1L',
                'meta_description' => 'Fast-acting liquid fertilizer concentrate for quick plant nutrition.',
                'handle' => 'liquid-fertilizer-concentrate-1l',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Premium Garden Soil 20kg',
                'description' => 'Rich, well-draining garden soil ideal for vegetables and herbs. Contains organic matter and essential nutrients. Perfect for raised beds and garden plots.',
                'category_id' => $soilCategory->id,
                'price' => 80.00,
                'compare_at_price' => 100.00,
                'cost_per_item' => 45.00,
                'stock' => 200,
                'status' => 'active',
                'vendor' => 'Nature Products',
                'type' => 'Physical',
                'sku' => 'SOIL-PREM-004',
                'barcode' => '1234567890126',
                'weight' => '20',
                'weight_unit' => 'kg',
                'tags' => 'soil,garden,premium,organic',
                'meta_title' => 'Premium Garden Soil 20kg',
                'meta_description' => 'Premium garden soil for vegetables and herbs.',
                'handle' => 'premium-garden-soil-20kg',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Potting Mix Premium 10L',
                'description' => 'Lightweight potting mix perfect for container gardening. Provides excellent drainage and aeration for healthy roots. Enriched with perlite and vermiculite.',
                'category_id' => $soilCategory->id,
                'price' => 95.00,
                'compare_at_price' => 115.00,
                'cost_per_item' => 50.00,
                'stock' => 150,
                'status' => 'active',
                'vendor' => 'Tandil Supplies',
                'type' => 'Physical',
                'sku' => 'SOIL-POT-005',
                'barcode' => '1234567890127',
                'weight' => '10',
                'weight_unit' => 'kg',
                'tags' => 'potting,mix,container,premium',
                'meta_title' => 'Potting Mix Premium 10L',
                'meta_description' => 'Premium potting mix for container gardening.',
                'handle' => 'potting-mix-premium-10l',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Professional Pruning Shears',
                'description' => 'Sharp and durable pruning shears with ergonomic handles. Perfect for trimming trees, shrubs, and plants. Made from high-grade steel with comfortable grip.',
                'category_id' => $toolsCategory->id,
                'price' => 500.00,
                'compare_at_price' => 600.00,
                'cost_per_item' => 250.00,
                'stock' => 50,
                'status' => 'active',
                'vendor' => 'Green Garden Co',
                'type' => 'Physical',
                'sku' => 'TOOL-SHEAR-006',
                'barcode' => '1234567890128',
                'weight' => '0.5',
                'weight_unit' => 'kg',
                'tags' => 'pruning,shears,tools,professional',
                'meta_title' => 'Professional Pruning Shears',
                'meta_description' => 'Professional grade pruning shears for garden maintenance.',
                'handle' => 'professional-pruning-shears',
                'track_quantity' => true,
                'allow_backorder' => true,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Garden Spade Shovel Heavy Duty',
                'description' => 'Heavy-duty garden spade with sharp edge for digging and transplanting. Durable steel construction with comfortable grip. Perfect for all digging tasks.',
                'category_id' => $toolsCategory->id,
                'price' => 350.00,
                'compare_at_price' => 420.00,
                'cost_per_item' => 180.00,
                'stock' => 40,
                'status' => 'active',
                'vendor' => 'AgriTech Solutions',
                'type' => 'Physical',
                'sku' => 'TOOL-SPADE-007',
                'barcode' => '1234567890129',
                'weight' => '2',
                'weight_unit' => 'kg',
                'tags' => 'spade,shovel,digging,tools',
                'meta_title' => 'Garden Spade Shovel Heavy Duty',
                'meta_description' => 'Heavy-duty garden spade for digging and transplanting.',
                'handle' => 'garden-spade-shovel-heavy-duty',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Tomato Seeds Premium Pack',
                'description' => 'Premium quality tomato seeds with high germination rate. Produces large, juicy tomatoes perfect for salads and cooking. Includes 50 seeds per pack.',
                'category_id' => $seedsCategory->id,
                'price' => 45.00,
                'compare_at_price' => 55.00,
                'cost_per_item' => 20.00,
                'stock' => 300,
                'status' => 'active',
                'vendor' => 'Nature Products',
                'type' => 'Physical',
                'sku' => 'SEED-TOM-008',
                'barcode' => '1234567890130',
                'weight' => '0.05',
                'weight_unit' => 'kg',
                'tags' => 'seeds,tomato,vegetable,premium',
                'meta_title' => 'Tomato Seeds Premium Pack',
                'meta_description' => 'Premium tomato seeds with high germination rate.',
                'handle' => 'tomato-seeds-premium-pack',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Organic Pest Control Spray 500ml',
                'description' => 'Natural and organic pest control spray safe for plants and environment. Effectively controls common garden pests without harmful chemicals. Made from neem oil and natural ingredients.',
                'category_id' => $pesticidesCategory->id,
                'price' => 120.00,
                'compare_at_price' => 150.00,
                'cost_per_item' => 60.00,
                'stock' => 80,
                'status' => 'active',
                'vendor' => 'Tandil Supplies',
                'type' => 'Physical',
                'sku' => 'PEST-ORG-009',
                'barcode' => '1234567890131',
                'weight' => '0.5',
                'weight_unit' => 'kg',
                'tags' => 'pest,control,organic,spray',
                'meta_title' => 'Organic Pest Control Spray 500ml',
                'meta_description' => 'Natural organic pest control spray for garden.',
                'handle' => 'organic-pest-control-spray-500ml',
                'track_quantity' => true,
                'allow_backorder' => true,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Watering Can Premium 10L',
                'description' => 'Large capacity watering can with detachable rose for gentle watering. Made from durable plastic with comfortable handle. Perfect for indoor and outdoor plants.',
                'category_id' => $toolsCategory->id,
                'price' => 450.00,
                'compare_at_price' => 550.00,
                'cost_per_item' => 220.00,
                'stock' => 60,
                'status' => 'active',
                'vendor' => 'Green Garden Co',
                'type' => 'Physical',
                'sku' => 'TOOL-WATER-010',
                'barcode' => '1234567890132',
                'weight' => '1.2',
                'weight_unit' => 'kg',
                'tags' => 'watering,can,tools,premium',
                'meta_title' => 'Watering Can Premium 10L',
                'meta_description' => 'Premium watering can with detachable rose.',
                'handle' => 'watering-can-premium-10l',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Compost Fertilizer 15kg',
                'description' => 'Rich compost fertilizer made from organic matter. Improves soil structure and provides slow-release nutrients. Perfect for all garden types.',
                'category_id' => $fertilizerCategory->id,
                'price' => 120.00,
                'compare_at_price' => 140.00,
                'cost_per_item' => 65.00,
                'stock' => 200,
                'status' => 'active',
                'vendor' => 'Nature Products',
                'type' => 'Physical',
                'sku' => 'FERT-COMP-011',
                'barcode' => '1234567890133',
                'weight' => '15',
                'weight_unit' => 'kg',
                'tags' => 'compost,fertilizer,organic,soil',
                'meta_title' => 'Compost Fertilizer 15kg',
                'meta_description' => 'Rich compost fertilizer for garden improvement.',
                'handle' => 'compost-fertilizer-15kg',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
            [
                'name' => 'Garden Gloves Premium Set',
                'description' => 'Heavy-duty garden gloves with reinforced fingertips. Provides protection while maintaining dexterity. Set of 2 pairs in different sizes.',
                'category_id' => $toolsCategory->id,
                'price' => 150.00,
                'compare_at_price' => 180.00,
                'cost_per_item' => 75.00,
                'stock' => 80,
                'status' => 'active',
                'vendor' => 'AgriTech Solutions',
                'type' => 'Physical',
                'sku' => 'TOOL-GLOVE-012',
                'barcode' => '1234567890134',
                'weight' => '0.3',
                'weight_unit' => 'kg',
                'tags' => 'gloves,garden,protection,tools',
                'meta_title' => 'Garden Gloves Premium Set',
                'meta_description' => 'Premium garden gloves with reinforced fingertips.',
                'handle' => 'garden-gloves-premium-set',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
            ],
        ];

        $this->command->info('Creating dummy products...');

        foreach ($products as $productData) {
            // Generate handle if not provided
            if (empty($productData['handle'])) {
                $productData['handle'] = Str::slug($productData['name']);
                // Ensure uniqueness
                $counter = 1;
                $originalHandle = $productData['handle'];
                while (Product::where('handle', $productData['handle'])->exists()) {
                    $productData['handle'] = $originalHandle . '-' . $counter;
                    $counter++;
                }
            }

            // Create or update product
            Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            $this->command->info("✓ Created/Updated: {$productData['name']}");
        }

        $this->command->info('✅ Successfully created ' . count($products) . ' dummy products!');
    }
}

