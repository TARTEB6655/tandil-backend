<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

class EnhancedProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates comprehensive e-commerce product data with test images.
     */
    public function run()
    {
        $this->command->info('🛍️ Starting enhanced product seeding...');

        // Ensure categories exist
        $categories = $this->ensureCategories();
        
        // Create comprehensive product data
        $products = $this->createEnhancedProducts($categories);
        
        $this->command->info('✅ Created ' . count($products) . ' enhanced products with images!');
    }

    private function ensureCategories()
    {
        $categoriesData = [
            ['name' => 'Fertilizers', 'slug' => 'fertilizers'],
            ['name' => 'Soil & Potting Mix', 'slug' => 'soil-potting-mix'],
            ['name' => 'Garden Tools', 'slug' => 'garden-tools'],
            ['name' => 'Seeds & Seedlings', 'slug' => 'seeds-seedlings'],
            ['name' => 'Pest Control', 'slug' => 'pest-control'],
            ['name' => 'Irrigation', 'slug' => 'irrigation'],
            ['name' => 'Planters & Pots', 'slug' => 'planters-pots'],
            ['name' => 'Garden Decor', 'slug' => 'garden-decor'],
        ];

        $categories = [];
        foreach ($categoriesData as $catData) {
            $category = Category::firstOrCreate(
                ['slug' => $catData['slug']],
                array_merge($catData, ['description' => 'Category description'])
            );
            $categories[$catData['slug']] = $category;
        }

        return $categories;
    }

    private function createEnhancedProducts($categories)
    {
        $productsData = [
            // Fertilizers - More comprehensive
            ['name' => 'Premium Organic Fertilizer 5kg', 'category' => 'fertilizers', 'price' => 89.99, 'stock' => 150, 'sku' => 'FERT-ORG-001', 'description' => '100% organic fertilizer enriched with natural nutrients. Perfect for vegetables, fruits, and flowers.'],
            ['name' => 'NPK Balanced Fertilizer 20-20-20', 'category' => 'fertilizers', 'price' => 75.50, 'stock' => 200, 'sku' => 'FERT-NPK-002', 'description' => 'Balanced NPK formula for all-purpose plant nutrition. Suitable for all growth stages.'],
            ['name' => 'Liquid Fertilizer Concentrate 1L', 'category' => 'fertilizers', 'price' => 45.00, 'stock' => 120, 'sku' => 'FERT-LIQ-003', 'description' => 'Fast-acting liquid fertilizer. Mix with water for easy application.'],
            ['name' => 'Compost Fertilizer 15kg', 'category' => 'fertilizers', 'price' => 65.00, 'stock' => 80, 'sku' => 'FERT-COMP-004', 'description' => 'Rich compost fertilizer made from organic matter. Improves soil structure.'],
            ['name' => 'Rose Special Fertilizer 2kg', 'category' => 'fertilizers', 'price' => 35.99, 'stock' => 90, 'sku' => 'FERT-ROSE-005', 'description' => 'Specially formulated for roses and flowering plants. Promotes vibrant blooms.'],
            ['name' => 'Citrus Tree Fertilizer 3kg', 'category' => 'fertilizers', 'price' => 42.50, 'stock' => 70, 'sku' => 'FERT-CITRUS-006', 'description' => 'Designed for citrus trees. Contains essential micronutrients for fruit production.'],

            // Soil & Potting Mix
            ['name' => 'Premium Garden Soil 20kg', 'category' => 'soil-potting-mix', 'price' => 55.00, 'stock' => 200, 'sku' => 'SOIL-PREM-001', 'description' => 'Premium quality garden soil with organic matter. Perfect for outdoor gardens.'],
            ['name' => 'Potting Mix Premium 10L', 'category' => 'soil-potting-mix', 'price' => 32.99, 'stock' => 150, 'sku' => 'SOIL-POT-002', 'description' => 'Lightweight potting mix for indoor and container plants. Excellent drainage.'],
            ['name' => 'Cactus & Succulent Mix 5L', 'category' => 'soil-potting-mix', 'price' => 28.50, 'stock' => 120, 'sku' => 'SOIL-CACT-003', 'description' => 'Specialized mix for cacti and succulents. Fast-draining and well-aerated.'],
            ['name' => 'Seed Starting Mix 5L', 'category' => 'soil-potting-mix', 'price' => 24.99, 'stock' => 100, 'sku' => 'SOIL-SEED-004', 'description' => 'Fine-textured mix perfect for starting seeds. Promotes healthy root development.'],
            ['name' => 'Organic Compost 25kg', 'category' => 'soil-potting-mix', 'price' => 45.00, 'stock' => 85, 'sku' => 'SOIL-COMP-005', 'description' => 'Rich organic compost. Improves soil fertility and structure naturally.'],

            // Garden Tools
            ['name' => 'Professional Pruning Shears', 'category' => 'garden-tools', 'price' => 125.00, 'stock' => 50, 'sku' => 'TOOL-PRUNE-001', 'description' => 'Heavy-duty pruning shears with ergonomic handles. Sharp stainless steel blades.'],
            ['name' => 'Garden Spade Shovel Heavy Duty', 'category' => 'garden-tools', 'price' => 85.99, 'stock' => 60, 'sku' => 'TOOL-SPADE-002', 'description' => 'Durable steel spade with fiberglass handle. Perfect for digging and soil work.'],
            ['name' => 'Watering Can Premium 10L', 'category' => 'garden-tools', 'price' => 45.00, 'stock' => 75, 'sku' => 'TOOL-CAN-003', 'description' => 'Large capacity watering can with detachable rose. Made from durable plastic.'],
            ['name' => 'Garden Gloves Premium Set', 'category' => 'garden-tools', 'price' => 28.50, 'stock' => 100, 'sku' => 'TOOL-GLOVE-004', 'description' => 'Comfortable gardening gloves. Puncture-resistant and breathable.'],
            ['name' => 'Garden Rake Steel 16-Tine', 'category' => 'garden-tools', 'price' => 65.00, 'stock' => 45, 'sku' => 'TOOL-RAKE-005', 'description' => 'Heavy-duty steel rake for leaves and debris. Comfortable wooden handle.'],
            ['name' => 'Hedge Trimmer Electric', 'category' => 'garden-tools', 'price' => 199.99, 'stock' => 30, 'sku' => 'TOOL-HEDGE-006', 'description' => 'Electric hedge trimmer with 20-inch blade. Lightweight and easy to use.'],

            // Seeds & Seedlings
            ['name' => 'Tomato Seeds Premium Pack', 'category' => 'seeds-seedlings', 'price' => 12.99, 'stock' => 200, 'sku' => 'SEED-TOM-001', 'description' => 'Premium tomato seeds. High yield variety. 50 seeds per pack.'],
            ['name' => 'Mixed Vegetable Seeds Collection', 'category' => 'seeds-seedlings', 'price' => 18.50, 'stock' => 150, 'sku' => 'SEED-MIX-002', 'description' => 'Assorted vegetable seeds collection. Includes 10 popular varieties.'],
            ['name' => 'Herb Seeds Starter Kit', 'category' => 'seeds-seedlings', 'price' => 15.99, 'stock' => 180, 'sku' => 'SEED-HERB-003', 'description' => 'Complete herb garden starter kit. Includes basil, parsley, cilantro, and more.'],
            ['name' => 'Flower Seeds Mix 5 Varieties', 'category' => 'seeds-seedlings', 'price' => 14.50, 'stock' => 160, 'sku' => 'SEED-FLOW-004', 'description' => 'Beautiful flower seed mix. Creates colorful garden displays.'],
            ['name' => 'Lettuce Seeds Romaine', 'category' => 'seeds-seedlings', 'price' => 8.99, 'stock' => 220, 'sku' => 'SEED-LET-005', 'description' => 'Crisp romaine lettuce seeds. Fast-growing and high-yield variety.'],

            // Pest Control
            ['name' => 'Organic Pest Control Spray 500ml', 'category' => 'pest-control', 'price' => 35.00, 'stock' => 110, 'sku' => 'PEST-ORG-001', 'description' => 'Natural pest control spray. Safe for vegetables and fruits.'],
            ['name' => 'Insecticide Concentrate 250ml', 'category' => 'pest-control', 'price' => 42.50, 'stock' => 95, 'sku' => 'PEST-INS-002', 'description' => 'Effective insecticide for common garden pests. Dilute before use.'],
            ['name' => 'Fungicide Spray 500ml', 'category' => 'pest-control', 'price' => 38.99, 'stock' => 85, 'sku' => 'PEST-FUNG-003', 'description' => 'Prevents and treats fungal diseases. Safe for most plants.'],
            ['name' => 'Neem Oil Organic 250ml', 'category' => 'pest-control', 'price' => 28.00, 'stock' => 130, 'sku' => 'PEST-NEEM-004', 'description' => '100% organic neem oil. Natural pest deterrent and fungicide.'],

            // Irrigation
            ['name' => 'Garden Hose 50ft Premium', 'category' => 'irrigation', 'price' => 65.00, 'stock' => 70, 'sku' => 'IRR-HOSE-001', 'description' => 'Heavy-duty garden hose. Kink-resistant and UV protected.'],
            ['name' => 'Sprinkler System Automatic', 'category' => 'irrigation', 'price' => 149.99, 'stock' => 40, 'sku' => 'IRR-SPRINK-002', 'description' => 'Automatic sprinkler system with timer. Covers up to 1000 sq ft.'],
            ['name' => 'Drip Irrigation Kit', 'category' => 'irrigation', 'price' => 89.99, 'stock' => 55, 'sku' => 'IRR-DRIP-003', 'description' => 'Complete drip irrigation kit. Water-efficient system for gardens.'],
            ['name' => 'Water Timer Digital', 'category' => 'irrigation', 'price' => 45.00, 'stock' => 60, 'sku' => 'IRR-TIMER-004', 'description' => 'Digital water timer. Programmable schedules for automatic watering.'],

            // Planters & Pots
            ['name' => 'Ceramic Planter Set 3-Piece', 'category' => 'planters-pots', 'price' => 55.00, 'stock' => 80, 'sku' => 'POT-CERAM-001', 'description' => 'Beautiful ceramic planter set. Includes 3 sizes. Drainage holes included.'],
            ['name' => 'Hanging Basket 12 inch', 'category' => 'planters-pots', 'price' => 18.99, 'stock' => 120, 'sku' => 'POT-HANG-002', 'description' => 'Decorative hanging basket. Perfect for flowers and trailing plants.'],
            ['name' => 'Self-Watering Planter Large', 'category' => 'planters-pots', 'price' => 75.00, 'stock' => 65, 'sku' => 'POT-SELF-003', 'description' => 'Self-watering planter with reservoir. Reduces watering frequency.'],

            // Garden Decor
            ['name' => 'Solar Garden Lights Set 6-Pack', 'category' => 'garden-decor', 'price' => 45.99, 'stock' => 90, 'sku' => 'DECOR-LIGHT-001', 'description' => 'Solar-powered garden lights. Automatic on/off. Weather-resistant.'],
            ['name' => 'Garden Statue Bird Bath', 'category' => 'garden-decor', 'price' => 125.00, 'stock' => 25, 'sku' => 'DECOR-BATH-002', 'description' => 'Decorative bird bath statue. Attracts birds to your garden.'],
        ];

        $products = [];
        foreach ($productsData as $productData) {
            $category = $categories[$productData['category']] ?? $categories['fertilizers'];
            
            $product = Product::updateOrCreate(
                ['sku' => $productData['sku']],
                [
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'description' => $productData['description'],
                    'price' => $productData['price'],
                    'stock' => $productData['stock'],
                    'sku' => $productData['sku'],
                    'status' => 'active',
                    'type' => $category->name,
                    'track_quantity' => true,
                    'requires_shipping' => true,
                    'taxable' => true,
                ]
            );

            // Create test image using placeholder service
            $this->createProductImage($product, $productData['name']);
            
            $products[] = $product;
        }

        return $products;
    }

    private function createProductImage($product, $productName)
    {
        // Use placeholder.com which is reliable
        // Using different colors for variety
        $colors = ['4ade80', '3b82f6', 'a855f7', 'ec4899', 'f97316', '10b981', '6366f1'];
        $colorIndex = crc32($product->name) % count($colors);
        $color = $colors[$colorIndex];
        
        $imageUrl = sprintf(
            'https://via.placeholder.com/800x800/%s/ffffff?text=%s',
            $color,
            urlencode(substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $productName), 0, 25))
        );
        
        try {
            // Create products directory if it doesn't exist
            if (!Storage::disk('public')->exists('products')) {
                Storage::disk('public')->makeDirectory('products');
            }

            $imageName = 'product-' . Str::slug($product->name) . '-' . $product->id . '.jpg';
            $imagePath = 'products/' . $imageName;

            // Use curl if available, otherwise file_get_contents
            if (function_exists('curl_init')) {
                $ch = curl_init($imageUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
                $imageContent = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200 && $imageContent !== false && strlen($imageContent) > 1000) {
                    Storage::disk('public')->put($imagePath, $imageContent);
                } else {
                    throw new \Exception('Failed to download image');
                }
            } else {
                // Fallback to file_get_contents
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 10,
                        'user_agent' => 'Mozilla/5.0',
                        'follow_location' => true,
                    ]
                ]);

                $imageContent = @file_get_contents($imageUrl, false, $context);
                
                if ($imageContent === false || strlen($imageContent) < 1000) {
                    throw new \Exception('Failed to download image');
                }
                
                Storage::disk('public')->put($imagePath, $imageContent);
            }
            
            // Create or update ProductImage
            ProductImage::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'is_primary' => true
                ],
                [
                    'image_path' => $imagePath,
                    'sort_order' => 0,
                ]
            );

            // Update product main image
            $product->update(['image' => $imagePath]);
            
            $this->command->info("✓ Image created for: {$product->name}");
            
        } catch (\Exception $e) {
            $this->command->warn("Error creating image for {$product->name}: " . $e->getMessage());
            // Set a placeholder path that will show a fallback in the view
            $product->update(['image' => 'products/placeholder.jpg']);
        }
    }

}

