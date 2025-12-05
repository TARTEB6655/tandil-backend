<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates dummy products with images for testing.
     */
    public function run()
    {
        // Ensure the products directory exists
        if (!Storage::disk('public')->exists('products')) {
            Storage::disk('public')->makeDirectory('products');
        }

        // Product data with image URLs (using Unsplash for real product images)
        $products = [
            // Fertilizers Category (id: 1)
            [
                'name' => 'Organic Fertilizer Premium',
                'description' => 'High-quality organic fertilizer enriched with essential nutrients for all types of crops. Promotes healthy growth and increases yield.',
                'price' => 150.00,
                'category_id' => 1,
                'stock' => 100,
                'image_url' => 'https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'NPK Fertilizer 20-20-20',
                'description' => 'Balanced NPK fertilizer perfect for vegetables and flowering plants. Provides essential nitrogen, phosphorus, and potassium.',
                'price' => 180.00,
                'category_id' => 1,
                'stock' => 75,
                'image_url' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Liquid Fertilizer Concentrate',
                'description' => 'Fast-acting liquid fertilizer that can be easily mixed with water. Ideal for foliar feeding and quick nutrient boost.',
                'price' => 220.00,
                'category_id' => 1,
                'stock' => 50,
                'image_url' => 'https://images.unsplash.com/photo-1610878180933-123728745d22?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Compost Fertilizer',
                'description' => 'Rich compost fertilizer made from organic matter. Improves soil structure and provides slow-release nutrients.',
                'price' => 120.00,
                'category_id' => 1,
                'stock' => 200,
                'image_url' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Seaweed Fertilizer',
                'description' => 'Natural seaweed-based fertilizer packed with micronutrients. Enhances plant growth and resistance to diseases.',
                'price' => 195.00,
                'category_id' => 1,
                'stock' => 60,
                'image_url' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=800&h=800&fit=crop',
            ],

            // Soil Category (id: 2)
            [
                'name' => 'Premium Garden Soil',
                'description' => 'Rich, well-draining garden soil ideal for vegetables and herbs. Contains organic matter and essential nutrients.',
                'price' => 80.00,
                'category_id' => 2,
                'stock' => 200,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Potting Mix Premium',
                'description' => 'Lightweight potting mix perfect for container gardening. Provides excellent drainage and aeration for healthy roots.',
                'price' => 95.00,
                'category_id' => 2,
                'stock' => 150,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Topsoil Enriched',
                'description' => 'High-quality topsoil enriched with compost and organic matter. Perfect for lawn establishment and garden beds.',
                'price' => 70.00,
                'category_id' => 2,
                'stock' => 180,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Cactus & Succulent Mix',
                'description' => 'Specialized soil mix for cacti and succulents. Fast-draining formula prevents root rot and overwatering.',
                'price' => 65.00,
                'category_id' => 2,
                'stock' => 120,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Seed Starting Mix',
                'description' => 'Fine-textured soil mix specifically designed for starting seeds. Provides optimal conditions for germination.',
                'price' => 55.00,
                'category_id' => 2,
                'stock' => 100,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],

            // Tools Category (id: 3)
            [
                'name' => 'Professional Pruning Shears',
                'description' => 'Sharp and durable pruning shears with ergonomic handles. Perfect for trimming trees, shrubs, and plants.',
                'price' => 500.00,
                'category_id' => 3,
                'stock' => 50,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Spade Shovel',
                'description' => 'Heavy-duty garden spade with sharp edge for digging and transplanting. Durable steel construction with comfortable grip.',
                'price' => 350.00,
                'category_id' => 3,
                'stock' => 40,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Rake',
                'description' => 'Sturdy garden rake for leveling soil, removing debris, and preparing seedbeds. Comfortable wooden handle.',
                'price' => 280.00,
                'category_id' => 3,
                'stock' => 35,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Watering Can 10L',
                'description' => 'Large capacity watering can with detachable rose for gentle watering. Made from durable plastic.',
                'price' => 450.00,
                'category_id' => 3,
                'stock' => 60,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Trowel Set',
                'description' => 'Complete set of 3 garden trowels in different sizes. Stainless steel heads with comfortable handles.',
                'price' => 320.00,
                'category_id' => 3,
                'stock' => 45,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Hedge Trimmer Electric',
                'description' => 'Powerful electric hedge trimmer for maintaining hedges and shrubs. Lightweight and easy to use.',
                'price' => 1200.00,
                'category_id' => 3,
                'stock' => 25,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Gloves Premium',
                'description' => 'Heavy-duty garden gloves with reinforced fingertips. Provides protection while maintaining dexterity.',
                'price' => 150.00,
                'category_id' => 3,
                'stock' => 80,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Wheelbarrow Heavy Duty',
                'description' => 'Sturdy wheelbarrow with steel frame and pneumatic tire. Perfect for moving soil, compost, and garden materials.',
                'price' => 1800.00,
                'category_id' => 3,
                'stock' => 20,
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
        ];

        $this->command->info('Creating products with images...');

        foreach ($products as $index => $productData) {
            $imageUrl = $productData['image_url'];
            unset($productData['image_url']);

            // Download and store image
            try {
                $imageName = 'product-' . Str::slug($productData['name']) . '-' . time() . '-' . $index . '.jpg';
                $imagePath = 'products/' . $imageName;

                // Download image
                $response = Http::timeout(30)->get($imageUrl);
                
                if ($response->successful()) {
                    // Store image in public storage
                    Storage::disk('public')->put($imagePath, $response->body());
                    $productData['image'] = $imagePath;
                    $this->command->info("Downloaded image for: {$productData['name']}");
                } else {
                    $this->command->warn("Failed to download image for: {$productData['name']}");
                    $productData['image'] = null;
                }
            } catch (\Exception $e) {
                $this->command->error("Error downloading image for {$productData['name']}: " . $e->getMessage());
                $productData['image'] = null;
            }

            // Create or update product
            Product::updateOrCreate(
                ['name' => $productData['name']],
                $productData
            );
            $this->command->info("Created/Updated product: {$productData['name']}");
        }

        $this->command->info('Products seeded successfully!');
    }
}
