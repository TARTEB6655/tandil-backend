<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CompleteShopDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates complete shop data: categories, products with images, orders, and transactions.
     */
    public function run()
    {
        $this->command->info('🌱 Starting complete shop data seeding...');

        // 1. Create Categories
        $this->command->info('📁 Creating categories...');
        $categories = $this->createCategories();
        $this->command->info('✅ Created ' . count($categories) . ' categories.');

        // 2. Create Products with Images
        $this->command->info('📦 Creating products with images...');
        $products = $this->createProducts($categories);
        $this->command->info('✅ Created ' . count($products) . ' products.');

        // 3. Create Orders
        $this->command->info('🛒 Creating orders...');
        $orders = $this->createOrders($products);
        $this->command->info('✅ Created ' . count($orders) . ' orders.');

        // 4. Create Transactions
        $this->command->info('💳 Creating transactions...');
        $transactions = $this->createTransactions($orders);
        $this->command->info('✅ Created ' . count($transactions) . ' transactions.');

        $this->command->info('🎉 Complete shop data seeded successfully!');
    }

    private function createCategories()
    {
        $categoriesData = [
            [
                'name' => 'Fertilizers',
                'slug' => 'fertilizers',
                'description' => 'Organic and chemical fertilizers for all types of plants and crops. Boost growth and yield with premium nutrients.',
            ],
            [
                'name' => 'Soil & Potting Mix',
                'slug' => 'soil-potting-mix',
                'description' => 'Premium garden soil, potting mixes, and specialized soil blends for different plant types.',
            ],
            [
                'name' => 'Garden Tools',
                'slug' => 'garden-tools',
                'description' => 'Professional and home gardening tools including shovels, pruners, rakes, and more.',
            ],
            [
                'name' => 'Seeds & Seedlings',
                'slug' => 'seeds-seedlings',
                'description' => 'High-quality seeds and seedlings for vegetables, fruits, flowers, and herbs.',
            ],
            [
                'name' => 'Pest Control',
                'slug' => 'pest-control',
                'description' => 'Organic and chemical pest control solutions to protect your plants from insects and diseases.',
            ],
            [
                'name' => 'Irrigation',
                'slug' => 'irrigation',
                'description' => 'Watering systems, hoses, sprinklers, and irrigation equipment for efficient plant watering.',
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $catData) {
            $category = Category::updateOrCreate(
                ['slug' => $catData['slug']],
                $catData
            );
            $categories[] = $category;
        }

        return $categories;
    }

    private function createProducts($categories)
    {
        $productsData = [
            // Fertilizers
            [
                'name' => 'Premium Organic Fertilizer 5kg',
                'description' => 'High-quality organic fertilizer enriched with essential nutrients for all types of crops. Promotes healthy growth and increases yield. Made from natural compost and organic matter.',
                'category_id' => $categories[0]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1625246333195-78d9c38ad449?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'NPK Balanced Fertilizer 20-20-20',
                'description' => 'Balanced NPK fertilizer perfect for vegetables and flowering plants. Provides essential nitrogen, phosphorus, and potassium in equal proportions for optimal growth.',
                'category_id' => $categories[0]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Liquid Fertilizer Concentrate 1L',
                'description' => 'Fast-acting liquid fertilizer that can be easily mixed with water. Ideal for foliar feeding and quick nutrient boost. Suitable for all plant types.',
                'category_id' => $categories[0]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1610878180933-123728745d22?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Compost Fertilizer 15kg',
                'description' => 'Rich compost fertilizer made from organic matter. Improves soil structure and provides slow-release nutrients. Perfect for all garden types.',
                'category_id' => $categories[0]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1593113598332-cd288d649433?w=800&h=800&fit=crop',
            ],

            // Soil & Potting Mix
            [
                'name' => 'Premium Garden Soil 20kg',
                'description' => 'Rich, well-draining garden soil ideal for vegetables and herbs. Contains organic matter and essential nutrients. Perfect for raised beds and garden plots.',
                'category_id' => $categories[1]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Potting Mix Premium 10L',
                'description' => 'Lightweight potting mix perfect for container gardening. Provides excellent drainage and aeration for healthy roots. Enriched with perlite and vermiculite.',
                'category_id' => $categories[1]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Cactus & Succulent Mix 5L',
                'description' => 'Specialized soil mix for cacti and succulents. Fast-draining formula prevents root rot and overwatering. Perfect for desert plants.',
                'category_id' => $categories[1]->id,
                'price' => 65.00,
                'compare_at_price' => 80.00,
                'cost_per_item' => 35.00,
                'stock' => 120,
                'status' => 'active',
                'vendor' => 'Green Garden Co',
                'type' => 'Physical',
                'sku' => 'SOIL-CACT-013',
                'barcode' => '1234567890135',
                'weight' => '5',
                'weight_unit' => 'kg',
                'tags' => 'cactus,succulent,soil,mix',
                'meta_title' => 'Cactus & Succulent Mix 5L',
                'meta_description' => 'Specialized soil mix for cacti and succulents.',
                'handle' => 'cactus-succulent-mix-5l',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=800&h=800&fit=crop',
            ],

            // Garden Tools
            [
                'name' => 'Professional Pruning Shears',
                'description' => 'Sharp and durable pruning shears with ergonomic handles. Perfect for trimming trees, shrubs, and plants. Made from high-grade steel with comfortable grip.',
                'category_id' => $categories[2]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Spade Shovel Heavy Duty',
                'description' => 'Heavy-duty garden spade with sharp edge for digging and transplanting. Durable steel construction with comfortable grip. Perfect for all digging tasks.',
                'category_id' => $categories[2]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Watering Can Premium 10L',
                'description' => 'Large capacity watering can with detachable rose for gentle watering. Made from durable plastic with comfortable handle. Perfect for indoor and outdoor plants.',
                'category_id' => $categories[2]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Garden Gloves Premium Set',
                'description' => 'Heavy-duty garden gloves with reinforced fingertips. Provides protection while maintaining dexterity. Set of 2 pairs in different sizes.',
                'category_id' => $categories[2]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1598300042247-d088f8ab3a91?w=800&h=800&fit=crop',
            ],

            // Seeds & Seedlings
            [
                'name' => 'Tomato Seeds Premium Pack',
                'description' => 'Premium quality tomato seeds with high germination rate. Produces large, juicy tomatoes perfect for salads and cooking. Includes 50 seeds per pack.',
                'category_id' => $categories[3]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&h=800&fit=crop',
            ],
            [
                'name' => 'Mixed Vegetable Seeds Collection',
                'description' => 'Complete collection of vegetable seeds including carrots, lettuce, peppers, and cucumbers. Perfect for starting your vegetable garden. 200+ seeds total.',
                'category_id' => $categories[3]->id,
                'price' => 120.00,
                'compare_at_price' => 150.00,
                'cost_per_item' => 60.00,
                'stock' => 150,
                'status' => 'active',
                'vendor' => 'Tandil Supplies',
                'type' => 'Physical',
                'sku' => 'SEED-MIX-014',
                'barcode' => '1234567890136',
                'weight' => '0.1',
                'weight_unit' => 'kg',
                'tags' => 'seeds,vegetable,mixed,collection',
                'meta_title' => 'Mixed Vegetable Seeds Collection',
                'meta_description' => 'Complete collection of vegetable seeds for your garden.',
                'handle' => 'mixed-vegetable-seeds-collection',
                'track_quantity' => true,
                'allow_backorder' => false,
                'requires_shipping' => true,
                'taxable' => true,
                'image_url' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=800&h=800&fit=crop',
            ],

            // Pest Control
            [
                'name' => 'Organic Pest Control Spray 500ml',
                'description' => 'Natural and organic pest control spray safe for plants and environment. Effectively controls common garden pests without harmful chemicals. Made from neem oil and natural ingredients.',
                'category_id' => $categories[4]->id,
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
                'image_url' => 'https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?w=800&h=800&fit=crop',
            ],
        ];

        $products = [];
        foreach ($productsData as $index => $productData) {
            $imageUrl = $productData['image_url'] ?? null;
            unset($productData['image_url']);

            // Generate handle if not provided
            if (empty($productData['handle'])) {
                $productData['handle'] = Str::slug($productData['name']);
                $counter = 1;
                $originalHandle = $productData['handle'];
                while (Product::where('handle', $productData['handle'])->exists()) {
                    $productData['handle'] = $originalHandle . '-' . $counter;
                    $counter++;
                }
            }

            // Create or update product
            $product = Product::updateOrCreate(
                ['sku' => $productData['sku']],
                $productData
            );

            // Add product image
            if ($imageUrl) {
                try {
                    $imageName = 'product-' . Str::slug($product->name) . '-' . time() . '.jpg';
                    $imagePath = 'products/' . $imageName;

                    // Download and store image
                    try {
                        $context = stream_context_create([
                            'http' => [
                                'timeout' => 10,
                                'user_agent' => 'Mozilla/5.0',
                            ]
                        ]);
                        $imageContent = @file_get_contents($imageUrl, false, $context);
                        if ($imageContent !== false && strlen($imageContent) > 0) {
                            Storage::disk('public')->put($imagePath, $imageContent);
                            
                            // Create ProductImage record
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
                            $this->command->info("✓ Image added for: {$product->name}");
                        } else {
                            $this->command->warn("Could not download image for {$product->name}");
                        }
                    } catch (\Exception $e) {
                        $this->command->warn("Error downloading image for {$product->name}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    $this->command->warn("Could not add image for {$product->name}: " . $e->getMessage());
                }
            }

            $products[] = $product;
        }

        return $products;
    }

    private function createOrders($products)
    {
        // Get some users (clients) to create orders for
        $users = User::where('role', 'client')->take(5)->get();
        
        // If no client users exist, create some
        if ($users->isEmpty()) {
            $this->command->info('Creating client users for orders...');
            for ($i = 1; $i <= 5; $i++) {
                $user = User::create([
                    'name' => 'Client User ' . $i,
                    'email' => 'client' . $i . '@example.com',
                    'phone' => '7000000' . $i,
                    'password' => bcrypt('password123'),
                    'role' => 'client',
                    'status' => 'active',
                ]);
                $user->assignRole('client');
                $users->push($user);
            }
            $this->command->info('Created ' . $users->count() . ' client users.');
        }

        $orders = [];
        $orderStatuses = ['pending', 'processing', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed'];
        $paymentMethods = ['paypal', 'stripe', 'cash', 'bank_transfer'];

        for ($i = 0; $i < 20; $i++) {
            $user = $users->random();
            $orderStatus = $orderStatuses[array_rand($orderStatuses)];
            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $paymentMethod = $paymentMethods[array_rand($paymentMethods)];

            // Select random products for this order
            $productsCollection = collect($products);
            $selectedProducts = $productsCollection->random(min(rand(1, 4), count($products)));
            $totalAmount = 0;
            $orderItems = [];

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $price = $product->price;
                $subtotal = $price * $quantity;
                $totalAmount += $subtotal;

                $orderItems[] = [
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ];
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total_amount' => $totalAmount,
                'order_status' => $orderStatus,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethod,
                'payment_reference' => 'PAY-' . strtoupper(Str::random(10)),
                'paid_at' => $paymentStatus === 'paid' ? Carbon::now()->subDays(rand(1, 30)) : null,
                'created_at' => Carbon::now()->subDays(rand(1, 60)),
            ]);

            // Create order items
            foreach ($orderItems as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $orders[] = $order;
        }

        return $orders;
    }

    private function createTransactions($orders)
    {
        $transactions = [];
        $gateways = ['paypal', 'stripe', 'bank_transfer'];
        $statuses = ['completed', 'pending', 'failed', 'refunded'];

        foreach ($orders as $order) {
            if ($order->payment_status === 'paid') {
                $transaction = Transaction::create([
                    'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                    'transactionable_type' => Order::class,
                    'transactionable_id' => $order->id,
                    'type' => 'payment',
                    'gateway' => $gateways[array_rand($gateways)],
                    'payment_method' => $order->payment_method,
                    'amount' => $order->total_amount,
                    'currency' => 'USD',
                    'status' => 'completed',
                    'gateway_transaction_id' => 'GW-' . strtoupper(Str::random(15)),
                    'gateway_response' => ['status' => 'success', 'message' => 'Payment processed successfully'],
                    'processed_at' => $order->paid_at ?? Carbon::now(),
                ]);

                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }
}

