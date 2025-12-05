<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Report;
use App\Models\Complaint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompleteDataSeeder extends Seeder
{
    /**
     * Run comprehensive seeding for all tables with complete dummy data
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting complete data seeding...');

        // 1. Seed Roles and Permissions
        $this->command->info('📋 Seeding roles and permissions...');
        $this->call(RoleSeeder::class);
        $this->call(RolePermissionSeeder::class);
        $this->command->info('✅ Roles and permissions seeded.');

        // 2. Seed Admin User
        $this->command->info('👤 Seeding admin user...');
        $this->call(AdminUserSeeder::class);
        $this->command->info('✅ Admin user seeded.');

        // 3. Create Areas
        $this->command->info('📍 Creating areas...');
        $areas = [];
        $areaNames = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain'];
        foreach ($areaNames as $name) {
            $areas[] = Area::firstOrCreate(
                ['name' => $name],
                ['description' => "Service area for {$name}"]
            );
        }
        $this->command->info('✅ Created ' . count($areas) . ' areas.');

        // 4. Create Users with all roles
        $this->command->info('👥 Creating users...');
        $clients = [];
        $technicians = [];
        $supervisors = [];
        $areaManagers = [];
        $hrUsers = [];

        // Create 30 clients
        for ($i = 1; $i <= 30; $i++) {
            $client = User::firstOrCreate(
                ['email' => "client{$i}@example.com"],
                [
                    'name' => "Client {$i}",
                    'password' => 'password', // Auto-hashed by model
                    'role' => 'client',
                    'status' => 'active',
                    'phone' => '7000000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($client, 'assignRole')) {
                $client->assignRole('client');
            }
            $clients[] = $client;
        }

        // Create 15 technicians
        for ($i = 1; $i <= 15; $i++) {
            $tech = User::firstOrCreate(
                ['email' => "technician{$i}@example.com"],
                [
                    'name' => "Technician {$i}",
                    'password' => 'password',
                    'role' => 'technician',
                    'status' => 'active',
                    'phone' => '7100000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($tech, 'assignRole')) {
                $tech->assignRole('technician');
            }
            $technicians[] = $tech;
        }

        // Create 8 supervisors
        for ($i = 1; $i <= 8; $i++) {
            $supervisor = User::firstOrCreate(
                ['email' => "supervisor{$i}@example.com"],
                [
                    'name' => "Supervisor {$i}",
                    'password' => 'password',
                    'role' => 'supervisor',
                    'status' => 'active',
                    'phone' => '7200000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($supervisor, 'assignRole')) {
                $supervisor->assignRole('supervisor');
            }
            $supervisors[] = $supervisor;
        }

        // Create 5 area managers
        for ($i = 1; $i <= 5; $i++) {
            $am = User::firstOrCreate(
                ['email' => "areamanager{$i}@example.com"],
                [
                    'name' => "Area Manager {$i}",
                    'password' => 'password',
                    'role' => 'area_manager',
                    'status' => 'active',
                    'phone' => '7300000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($am, 'assignRole')) {
                $am->assignRole('area_manager');
            }
            $areaManagers[] = $am;
        }

        // Create 3 HR users
        for ($i = 1; $i <= 3; $i++) {
            $hr = User::firstOrCreate(
                ['email' => "hr{$i}@example.com"],
                [
                    'name' => "HR User {$i}",
                    'password' => 'password',
                    'role' => 'hr',
                    'status' => 'active',
                    'phone' => '7400000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($hr, 'assignRole')) {
                $hr->assignRole('hr');
            }
            $hrUsers[] = $hr;
        }

        $this->command->info('✅ Created users: ' . count($clients) . ' clients, ' . count($technicians) . ' technicians, ' . count($supervisors) . ' supervisors, ' . count($areaManagers) . ' area managers, ' . count($hrUsers) . ' HR users.');

        // 5. Create Categories
        $this->command->info('📦 Creating categories...');
        $categories = [];
        $categoryNames = [
            'Fertilizers',
            'Seeds',
            'Tools',
            'Irrigation Equipment',
            'Pesticides',
            'Garden Supplies',
            'Machinery',
            'Organic Products'
        ];
        foreach ($categoryNames as $name) {
            $categories[] = Category::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'description' => "Category for {$name}"
                ]
            );
        }
        $this->command->info('✅ Created ' . count($categories) . ' categories.');

        // 6. Create Products with images
        $this->command->info('🛍️ Creating products...');
        $products = [];
        $productNames = [
            'Premium Organic Fertilizer 5kg',
            'Tomato Seeds - Hybrid Variety',
            'Garden Shovel - Heavy Duty',
            'Drip Irrigation Kit 50m',
            'Natural Pesticide Spray 1L',
            'Garden Hose 30m',
            'Lawn Mower - Electric',
            'Compost Bin 100L',
            'Pruning Shears - Professional',
            'Watering Can 10L',
            'Garden Gloves - Leather',
            'Seedling Trays 50 Cells',
            'Garden Rake - Steel',
            'Sprinkler System - Automatic',
            'Organic Soil Mix 20kg',
            'Garden Trowel Set',
            'Plant Pots - Ceramic Set',
            'Garden Fork - Stainless Steel',
            'Hedge Trimmer - Cordless',
            'Mulch - Organic 25kg',
        ];

        for ($i = 0; $i < count($productNames); $i++) {
            $category = $categories[array_rand($categories)];
            $price = rand(50, 2000);
            $stock = rand(0, 500);
            
            $product = Product::firstOrCreate(
                ['name' => $productNames[$i]],
                [
                    'description' => "High-quality {$productNames[$i]} for your gardening needs. Perfect for professional and home gardeners.",
                    'category_id' => $category->id,
                    'price' => $price,
                    'compare_at_price' => $price * 1.2,
                    'stock' => $stock,
                    'status' => $stock > 0 ? 'active' : 'draft',
                    'sku' => 'SKU-' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                    'barcode' => 'BAR' . rand(100000, 999999),
                    'vendor' => ['Tandil Supplies', 'Green Garden Co', 'AgriTech Solutions', 'Nature Products'][array_rand(['Tandil Supplies', 'Green Garden Co', 'AgriTech Solutions', 'Nature Products'])],
                    'type' => ['Physical', 'Digital', 'Service'][array_rand(['Physical', 'Digital', 'Service'])],
                    'handle' => Str::slug($productNames[$i]),
                    'track_quantity' => true,
                    'requires_shipping' => true,
                    'taxable' => true,
                    'weight' => rand(1, 50),
                    'weight_unit' => 'kg',
                ]
            );
            $products[] = $product;
        }
        $this->command->info('✅ Created ' . count($products) . ' products.');

        // 7. Create Orders with Order Items
        $this->command->info('🛒 Creating orders...');
        $orders = [];
        $paymentMethods = ['stripe', 'paypal', 'razorpay', 'cash'];
        $orderStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
        $paymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        for ($i = 1; $i <= 50; $i++) {
            $client = $clients[array_rand($clients)];
            $numItems = rand(1, 5);
            $totalAmount = 0;
            $orderItems = [];

            // Create order items
            for ($j = 0; $j < $numItems; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 10);
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

            $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
            $orderStatus = $orderStatuses[array_rand($orderStatuses)];
            
            $order = Order::create([
                'user_id' => $client->id,
                'total_amount' => $totalAmount,
                'payment_status' => $paymentStatus,
                'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                'payment_reference' => 'REF-' . strtoupper(Str::random(10)),
                'order_status' => $orderStatus,
                'paid_at' => $paymentStatus === 'paid' ? Carbon::now()->subDays(rand(1, 30)) : null,
                'refunded_at' => $paymentStatus === 'refunded' ? Carbon::now()->subDays(rand(1, 10)) : null,
                'refund_amount' => $paymentStatus === 'refunded' ? $totalAmount * 0.8 : null,
                'refund_reason' => $paymentStatus === 'refunded' ? 'Customer request' : null,
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
        $this->command->info('✅ Created ' . count($orders) . ' orders with items.');

        // 8. Create Transactions
        $this->command->info('💳 Creating transactions...');
        $transactions = [];
        $gateways = ['stripe', 'paypal', 'razorpay'];
        $transactionTypes = ['payment', 'refund'];
        $transactionStatuses = ['pending', 'completed', 'failed', 'refunded', 'cancelled'];

        foreach ($orders as $order) {
            if ($order->payment_status === 'paid') {
                // Create payment transaction
                $transaction = Transaction::create([
                    'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                    'transactionable_type' => Order::class,
                    'transactionable_id' => $order->id,
                    'type' => 'payment',
                    'gateway' => $order->payment_method ?? $gateways[array_rand($gateways)],
                    'payment_method' => 'card',
                    'amount' => $order->total_amount,
                    'currency' => 'AED',
                    'status' => 'completed',
                    'gateway_transaction_id' => $order->transaction_id,
                    'processed_at' => $order->paid_at ?? Carbon::now(),
                    'created_at' => $order->created_at,
                ]);
                $transactions[] = $transaction;
            }

            if ($order->payment_status === 'refunded' && $order->refunded_at) {
                // Create refund transaction
                $refundTransaction = Transaction::create([
                    'transaction_id' => 'REF-' . strtoupper(Str::random(12)),
                    'transactionable_type' => Order::class,
                    'transactionable_id' => $order->id,
                    'type' => 'refund',
                    'gateway' => $order->payment_method ?? $gateways[array_rand($gateways)],
                    'payment_method' => 'card',
                    'amount' => $order->refund_amount ?? $order->total_amount,
                    'currency' => 'AED',
                    'status' => 'completed',
                    'notes' => $order->refund_reason ?? 'Admin refund',
                    'processed_at' => $order->refunded_at,
                    'created_at' => $order->refunded_at,
                ]);
                $transactions[] = $refundTransaction;
            }
        }
        $this->command->info('✅ Created ' . count($transactions) . ' transactions.');

        // 9. Create Subscriptions
        $this->command->info('📅 Creating subscriptions...');
        $subscriptions = [];
        $plans = ['1_month', '3_month', '6_month', '12_month'];
        $planMonths = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
        $planAmounts = ['1_month' => 500, '3_month' => 1450, '6_month' => 2900, '12_month' => 5500];
        
        foreach (array_slice($clients, 0, 20) as $client) {
            $plan = $plans[array_rand($plans)];
            $months = $planMonths[$plan];
            $amount = $planAmounts[$plan];
            $startDate = Carbon::now()->subMonths(rand(1, 12));
            $endDate = $startDate->copy()->addMonths($months);
            
            $subscription = Subscription::create([
                'client_id' => $client->id,
                'plan' => $plan,
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_status' => rand(0, 1) ? 'paid' : 'pending',
                'total_visits' => $months,
                'completed_visits' => rand(0, $months),
                'paid_at' => rand(0, 1) ? $startDate : null,
                'created_at' => $startDate,
            ]);
            $subscriptions[] = $subscription;
        }
        $this->command->info('✅ Created ' . count($subscriptions) . ' subscriptions.');

        // 10. Create Visits
        $this->command->info('🏠 Creating visits...');
        $visits = [];
        $visitStatuses = ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'];

        foreach ($subscriptions as $subscription) {
            if (rand(0, 1)) {
                $technician = $technicians[array_rand($technicians)];
                $supervisor = $supervisors[array_rand($supervisors)];
                $area = $areas[array_rand($areas)];
                $status = $visitStatuses[array_rand($visitStatuses)];
                
                $scheduledDate = Carbon::now()->addDays(rand(1, 30));
                $visit = Visit::create([
                    'subscription_id' => $subscription->id,
                    'technician_id' => $technician->id,
                    'supervisor_id' => $supervisor->id,
                    'area_id' => $area->id,
                    'scheduled_date' => $scheduledDate,
                    'status' => $status,
                    'accepted_at' => $status !== 'pending' ? $scheduledDate->copy()->subHours(2) : null,
                    'started_at' => in_array($status, ['in_progress', 'completed']) ? $scheduledDate->copy()->addMinutes(30) : null,
                    'completed_at' => $status === 'completed' ? $scheduledDate->copy()->addHours(2) : null,
                    'notes' => $status === 'completed' ? 'Visit completed successfully. All tasks performed as scheduled.' : null,
                    'created_at' => Carbon::now()->subDays(rand(1, 60)),
                ]);
                $visits[] = $visit;
            }
        }
        $this->command->info('✅ Created ' . count($visits) . ' visits.');

        // 11. Create Reports
        $this->command->info('📄 Creating reports...');
        $reports = [];
        foreach (array_filter($visits, fn($v) => $v->status === 'completed') as $visit) {
            if (rand(0, 1)) {
                $report = Report::create([
                    'visit_id' => $visit->id,
                    'supervisor_id' => $visit->supervisor_id,
                    'technician_notes' => 'Service report for visit #' . $visit->id,
                    'supervisor_notes' => rand(0, 1) ? 'Report reviewed and approved' : null,
                    'created_at' => $visit->completed_at ?? Carbon::now(),
                ]);
                $reports[] = $report;
            }
        }
        $this->command->info('✅ Created ' . count($reports) . ' reports.');

        // 12. Create Complaints
        $this->command->info('⚠️ Creating complaints...');
        $complaints = [];
        $complaintStatuses = ['open', 'in_progress', 'resolved', 'escalated'];
        
        foreach (array_slice($visits, 0, 10) as $visit) {
            if (rand(0, 1)) {
                $complaint = Complaint::create([
                    'visit_id' => $visit->id,
                    'client_id' => $visit->subscription->client_id,
                    'notes' => 'Complaint about visit #' . $visit->id . ': Sample complaint description',
                    'status' => $complaintStatuses[array_rand($complaintStatuses)],
                    'created_at' => Carbon::now()->subDays(rand(1, 30)),
                ]);
                $complaints[] = $complaint;
            }
        }
        $this->command->info('✅ Created ' . count($complaints) . ' complaints.');

        // 13. Seed Email Templates (if table exists)
        try {
            $this->command->info('📧 Seeding email templates...');
            $this->call(EmailTemplateSeeder::class);
            $this->command->info('✅ Email templates seeded.');
        } catch (\Exception $e) {
            $this->command->warn('⚠️ Email templates table not found. Skipping...');
        }

        $this->command->info('');
        $this->command->info('🎉 Complete data seeding finished!');
        $this->command->info('');
        $this->command->info('Summary:');
        $this->command->info('  - Users: ' . (count($clients) + count($technicians) + count($supervisors) + count($areaManagers) + count($hrUsers) + 1) . ' (including admin)');
        $this->command->info('  - Categories: ' . count($categories));
        $this->command->info('  - Products: ' . count($products));
        $this->command->info('  - Orders: ' . count($orders));
        $this->command->info('  - Transactions: ' . count($transactions));
        $this->command->info('  - Subscriptions: ' . count($subscriptions));
        $this->command->info('  - Visits: ' . count($visits));
        $this->command->info('  - Reports: ' . count($reports));
        $this->command->info('  - Complaints: ' . count($complaints));
        $this->command->info('');
        $this->command->info('Default password for all users: password');
    }
}

