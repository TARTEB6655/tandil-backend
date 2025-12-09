<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Report;
use App\Models\Complaint;
use App\Models\Tip;
use App\Models\Cart;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompleteDataSeeder extends Seeder
{
    private $users = [];
    private $areas = [];
    private $categories = [];
    private $products = [];

    /**
     * Run comprehensive seeding for all tables with complete dummy data
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting complete data seeding...');
        $this->command->info('');

        // 1. Seed Admin User
        $this->seedAdminUser();

        // 2. Create Areas
        $this->seedAreas();

        // 3. Create Users with all roles
        $this->seedUsers();

        // 4. Create Categories
        $this->seedCategories();

        // 5. Create Products
        $this->seedProducts();

        // 6. Create Subscriptions
        $this->seedSubscriptions();

        // 7. Create Visits
        $this->seedVisits();

        // 8. Create Reports
        $this->seedReports();

        // 9. Create Complaints
        $this->seedComplaints();

        // 10. Create Orders
        $this->seedOrders();

        // 11. Create Transactions
        $this->seedTransactions();

        // 12. Create Tips
        $this->seedTips();

        // 13. Create Employees (HR)
        $this->seedEmployees();

        // 14. Seed Email Templates
        $this->seedEmailTemplates();

        // 15. Display User Credentials
        $this->displayUserCredentials();

        $this->command->info('');
        $this->command->info('🎉 Complete data seeding finished!');
        $this->command->info('');
    }

    private function seedAdminUser(): void
    {
        $this->command->info('👤 Seeding admin user...');
        $this->call(AdminUserSeeder::class);
        $admin = User::where('email', env('APP_ADMIN_EMAIL', 'admin@tandil.com'))->first();
        if ($admin) {
            $this->users['admin'] = $admin;
        }
        $this->command->info('✅ Admin user seeded.');
    }

    private function seedAreas(): void
    {
        $this->command->info('📍 Creating areas...');
        $areaNames = [
            'Dubai',
            'Abu Dhabi',
            'Sharjah',
            'Ajman',
            'Ras Al Khaimah',
            'Fujairah',
            'Umm Al Quwain'
        ];

        foreach ($areaNames as $name) {
            $this->areas[] = Area::create([
                'name' => $name,
                'description' => "Service area for {$name} - Comprehensive agricultural and garden services available."
            ]);
        }
        $this->command->info('✅ Created ' . count($this->areas) . ' areas.');
    }

    private function seedUsers(): void
    {
        $this->command->info('👥 Creating users...');
        $password = 'password123'; // Consistent password for all test users

        // Create 10 clients
        for ($i = 1; $i <= 10; $i++) {
            $this->users['clients'][] = User::create([
                'name' => "Client User {$i}",
                'email' => "client{$i}@test.com",
                'phone' => '+971501234' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'password' => $password,
                    'role' => 'client',
                    'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->users['clients'][$i - 1]->assignRole('client');
        }

        // Create 8 technicians
        for ($i = 1; $i <= 8; $i++) {
            $this->users['technicians'][] = User::create([
                    'name' => "Technician {$i}",
                'email' => "technician{$i}@test.com",
                'phone' => '+971502234' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'password' => $password,
                    'role' => 'technician',
                    'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->users['technicians'][$i - 1]->assignRole('technician');
        }

        // Create 5 supervisors
        for ($i = 1; $i <= 5; $i++) {
            $this->users['supervisors'][] = User::create([
                    'name' => "Supervisor {$i}",
                'email' => "supervisor{$i}@test.com",
                'phone' => '+971503234' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'password' => $password,
                    'role' => 'supervisor',
                    'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->users['supervisors'][$i - 1]->assignRole('supervisor');
        }

        // Create 3 area managers
        for ($i = 1; $i <= 3; $i++) {
            $this->users['area_managers'][] = User::create([
                    'name' => "Area Manager {$i}",
                'email' => "areamanager{$i}@test.com",
                'phone' => '+971504234' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'password' => $password,
                    'role' => 'area_manager',
                    'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->users['area_managers'][$i - 1]->assignRole('area_manager');
        }

        // Create 2 HR users
        for ($i = 1; $i <= 2; $i++) {
            $this->users['hr'][] = User::create([
                    'name' => "HR User {$i}",
                'email' => "hr{$i}@test.com",
                'phone' => '+971505234' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'password' => $password,
                    'role' => 'hr',
                    'status' => 'active',
                'email_verified_at' => now(),
            ]);
            $this->users['hr'][$i - 1]->assignRole('hr');
        }

        // Assign technicians and supervisors to areas
        foreach ($this->areas as $index => $area) {
            if (isset($this->users['technicians'][$index % count($this->users['technicians'])])) {
                $area->technicians()->attach($this->users['technicians'][$index % count($this->users['technicians'])]->id);
            }
            if (isset($this->users['supervisors'][$index % count($this->users['supervisors'])])) {
                $area->supervisors()->attach($this->users['supervisors'][$index % count($this->users['supervisors'])]->id);
            }
        }

        $totalUsers = count($this->users['clients']) + count($this->users['technicians']) + 
                     count($this->users['supervisors']) + count($this->users['area_managers']) + 
                     count($this->users['hr']) + (isset($this->users['admin']) ? 1 : 0);
        $this->command->info("✅ Created {$totalUsers} users (10 clients, 8 technicians, 5 supervisors, 3 area managers, 2 HR, 1 admin).");
    }

    private function seedCategories(): void
    {
        $this->command->info('📦 Creating categories...');
        $categoryData = [
            ['name' => 'Fertilizers', 'description' => 'Organic and chemical fertilizers for all types of plants'],
            ['name' => 'Seeds', 'description' => 'High-quality seeds for vegetables, fruits, and flowers'],
            ['name' => 'Tools', 'description' => 'Garden tools and equipment for professional and home use'],
            ['name' => 'Irrigation Equipment', 'description' => 'Drip irrigation systems, sprinklers, and watering solutions'],
            ['name' => 'Pesticides', 'description' => 'Safe and effective pest control products'],
            ['name' => 'Garden Supplies', 'description' => 'Essential supplies for gardening and landscaping'],
            ['name' => 'Machinery', 'description' => 'Heavy machinery for large-scale agricultural operations'],
            ['name' => 'Organic Products', 'description' => '100% organic products for sustainable farming'],
        ];

        foreach ($categoryData as $data) {
            $this->categories[] = Category::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'],
            ]);
        }
        $this->command->info('✅ Created ' . count($this->categories) . ' categories.');
    }

    private function seedProducts(): void
    {
        $this->command->info('🛍️ Creating products...');
        $productData = [
            ['name' => 'Premium Organic Fertilizer 5kg', 'price' => 150.00, 'category' => 'Fertilizers'],
            ['name' => 'Tomato Seeds - Hybrid Variety', 'price' => 25.00, 'category' => 'Seeds'],
            ['name' => 'Garden Shovel - Heavy Duty', 'price' => 350.00, 'category' => 'Tools'],
            ['name' => 'Drip Irrigation Kit 50m', 'price' => 450.00, 'category' => 'Irrigation Equipment'],
            ['name' => 'Natural Pesticide Spray 1L', 'price' => 85.00, 'category' => 'Pesticides'],
            ['name' => 'Garden Hose 30m', 'price' => 120.00, 'category' => 'Garden Supplies'],
            ['name' => 'Lawn Mower - Electric', 'price' => 1200.00, 'category' => 'Machinery'],
            ['name' => 'Compost Bin 100L', 'price' => 280.00, 'category' => 'Organic Products'],
            ['name' => 'Pruning Shears - Professional', 'price' => 180.00, 'category' => 'Tools'],
            ['name' => 'Watering Can 10L', 'price' => 65.00, 'category' => 'Garden Supplies'],
            ['name' => 'Garden Gloves - Leather', 'price' => 45.00, 'category' => 'Tools'],
            ['name' => 'Seedling Trays 50 Cells', 'price' => 35.00, 'category' => 'Garden Supplies'],
            ['name' => 'Garden Rake - Steel', 'price' => 220.00, 'category' => 'Tools'],
            ['name' => 'Sprinkler System - Automatic', 'price' => 550.00, 'category' => 'Irrigation Equipment'],
            ['name' => 'Organic Soil Mix 20kg', 'price' => 95.00, 'category' => 'Organic Products'],
        ];

        foreach ($productData as $data) {
            $category = collect($this->categories)->firstWhere('name', $data['category']);
            $this->products[] = Product::create([
                    'category_id' => $category->id,
                'name' => $data['name'],
                'description' => "High-quality {$data['name']} for your gardening needs. Perfect for professional and home gardeners.",
                'price' => $data['price'],
                'compare_at_price' => $data['price'] * 1.2,
                'stock' => rand(10, 500),
                'status' => 'active',
                'sku' => 'SKU-' . str_pad(count($this->products) + 1, 6, '0', STR_PAD_LEFT),
                    'barcode' => 'BAR' . rand(100000, 999999),
                'vendor' => 'Tandil Supplies',
                'type' => 'physical',
                'handle' => Str::slug($data['name']),
                    'track_quantity' => true,
                    'requires_shipping' => true,
                    'taxable' => true,
                    'weight' => rand(1, 50),
                    'weight_unit' => 'kg',
            ]);
        }
        $this->command->info('✅ Created ' . count($this->products) . ' products.');
    }

    private function seedSubscriptions(): void
    {
        $this->command->info('📅 Creating subscriptions...');
        $plans = ['1_month', '3_month', '6_month', '12_month'];
        $planMonths = ['1_month' => 1, '3_month' => 3, '6_month' => 6, '12_month' => 12];
        $planAmounts = ['1_month' => 500, '3_month' => 1450, '6_month' => 2900, '12_month' => 5500];
        
        foreach ($this->users['clients'] as $client) {
            $plan = $plans[array_rand($plans)];
            $months = $planMonths[$plan];
            $amount = $planAmounts[$plan];
            $startDate = Carbon::now()->subMonths(rand(0, 6));
            $endDate = $startDate->copy()->addMonths($months);
            $paymentStatus = rand(0, 1) ? 'paid' : 'pending';
            
            Subscription::create([
                'client_id' => $client->id,
                'plan' => $plan,
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'payment_status' => $paymentStatus,
                'payment_reference' => $paymentStatus === 'paid' ? 'PAY-' . strtoupper(Str::random(10)) : null,
                'paid_at' => $paymentStatus === 'paid' ? $startDate : null,
                'total_visits' => $months,
                'completed_visits' => rand(0, min($months, 3)),
            ]);
        }
        $this->command->info('✅ Created ' . count($this->users['clients']) . ' subscriptions.');
    }

    private function seedVisits(): void
    {
        $this->command->info('🏠 Creating visits...');
        $subscriptions = Subscription::all();
        $visitStatuses = ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'];
        $visitCount = 0;

        foreach ($subscriptions as $subscription) {
            $numVisits = rand(1, min(3, $subscription->total_visits));
            for ($i = 0; $i < $numVisits; $i++) {
                $technician = $this->users['technicians'][array_rand($this->users['technicians'])];
                $supervisor = $this->users['supervisors'][array_rand($this->users['supervisors'])];
                $area = $this->areas[array_rand($this->areas)];
                $status = $visitStatuses[array_rand($visitStatuses)];
                
                $scheduledDate = Carbon::now()->addDays(rand(-30, 30));
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
                ]);
                $visitCount++;

                // Create visit photos for completed visits
                if ($status === 'completed') {
                    VisitPhoto::create([
                        'visit_id' => $visit->id,
                        'photo_path' => 'visits/sample-before-' . $visit->id . '.jpg',
                        'type' => 'before',
                    ]);
                    VisitPhoto::create([
                        'visit_id' => $visit->id,
                        'photo_path' => 'visits/sample-after-' . $visit->id . '.jpg',
                        'type' => 'after',
                    ]);
                }
            }
        }
        $this->command->info("✅ Created {$visitCount} visits.");
    }

    private function seedReports(): void
    {
        $this->command->info('📄 Creating reports...');
        $completedVisits = Visit::where('status', 'completed')->get();
        $reportCount = 0;

        foreach ($completedVisits as $visit) {
            if (rand(0, 1)) {
                // supervisor_id in reports references employees table, not users
                // So we'll set it to null or find/create employee for supervisor
                $supervisorEmployee = null;
                if ($visit->supervisor_id) {
                    $supervisorEmployee = Employee::where('user_id', $visit->supervisor_id)->first();
                }

                Report::create([
                    'visit_id' => $visit->id,
                    'supervisor_id' => $supervisorEmployee ? $supervisorEmployee->id : null,
                    'technician_notes' => 'Service completed successfully. All required tasks performed.',
                    'supervisor_notes' => 'Report reviewed and approved. Quality standards met.',
                    'notes' => 'Final report for visit #' . $visit->id,
                    'recommendations' => ['Continue regular maintenance', 'Monitor plant health'],
                    'recommended_products' => [],
                    'status' => rand(0, 1) ? 'approved' : 'pending',
                    'approved_by' => rand(0, 1) ? ($supervisorEmployee ? $supervisorEmployee->id : null) : null,
                    'approved_at' => rand(0, 1) ? $visit->completed_at : null,
                ]);
                $reportCount++;
            }
        }
        $this->command->info("✅ Created {$reportCount} reports.");
    }

    private function seedComplaints(): void
    {
        $this->command->info('⚠️ Creating complaints...');
        $visits = Visit::all();
        $complaintStatuses = ['open', 'in_progress', 'resolved', 'escalated'];
        $complaintCount = 0;
        
        foreach ($visits->random(min(5, $visits->count())) as $visit) {
            Complaint::create([
                    'visit_id' => $visit->id,
                    'client_id' => $visit->subscription->client_id,
                    'status' => $complaintStatuses[array_rand($complaintStatuses)],
                'notes' => 'Complaint about visit #' . $visit->id . ': Service quality did not meet expectations.',
            ]);
            $complaintCount++;
        }
        $this->command->info("✅ Created {$complaintCount} complaints.");
    }

    private function seedOrders(): void
    {
        $this->command->info('🛒 Creating orders...');
        $paymentMethods = ['paypal', 'stripe', 'cash'];
        $orderStatuses = ['processing', 'shipped', 'delivered'];
        $paymentStatuses = ['pending', 'paid', 'failed'];
        $orderCount = 0;

        foreach ($this->users['clients'] as $client) {
            $numOrders = rand(1, 3);
            for ($i = 0; $i < $numOrders; $i++) {
                $numItems = rand(1, 4);
                $totalAmount = 0;
                $orderItems = [];

                for ($j = 0; $j < $numItems; $j++) {
                    $product = $this->products[array_rand($this->products)];
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

                $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                $orderStatus = $orderStatuses[array_rand($orderStatuses)];
                $createdAt = Carbon::now()->subDays(rand(1, 60));

                $order = Order::create([
                    'user_id' => $client->id,
                    'total_amount' => $totalAmount,
                    'payment_status' => $paymentStatus,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'payment_reference' => $paymentStatus === 'paid' ? 'PAY-' . strtoupper(Str::random(10)) : null,
                    'transaction_id' => $paymentStatus === 'paid' ? 'TXN-' . strtoupper(Str::random(12)) : null,
                    'paid_at' => $paymentStatus === 'paid' ? $createdAt->copy()->addMinutes(rand(5, 60)) : null,
                    'order_status' => $orderStatus,
                    'created_at' => $createdAt,
                ]);

                foreach ($orderItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                        'subtotal' => $item['subtotal'],
                    ]);
                }
                $orderCount++;
            }
        }
        $this->command->info("✅ Created {$orderCount} orders with items.");
    }

    private function seedTransactions(): void
    {
        $this->command->info('💳 Creating transactions...');
        $paidOrders = Order::where('payment_status', 'paid')->get();
        $transactionCount = 0;

        foreach ($paidOrders as $order) {
            Transaction::create([
                'transaction_id' => $order->transaction_id ?? 'TXN-' . strtoupper(Str::random(12)),
                'transactionable_type' => Order::class,
                'transactionable_id' => $order->id,
                'type' => 'payment',
                'gateway' => $order->payment_method ?? 'paypal',
                'payment_method' => 'card',
                'amount' => $order->total_amount,
                'currency' => 'AED',
                'status' => 'completed',
                'gateway_transaction_id' => $order->transaction_id,
                'processed_at' => $order->paid_at ?? $order->created_at,
            ]);
            $transactionCount++;
        }
        $this->command->info("✅ Created {$transactionCount} transactions.");
    }

    private function seedTips(): void
    {
        $this->command->info('💡 Creating tips...');
        $tips = [
            [
                'title' => 'Watering Best Practices',
                'content' => 'Water your plants early in the morning for best absorption. Avoid watering during the hottest part of the day to prevent evaporation.',
                'type' => 'general',
                'status' => 'published',
                'language' => 'en',
                'created_by' => isset($this->users['admin']) ? $this->users['admin']->id : null,
            ],
            [
                'title' => 'Fertilizer Application',
                'content' => 'Apply fertilizer during the growing season for optimal results. Follow the recommended dosage to avoid over-fertilization.',
                'type' => 'seasonal',
                'status' => 'published',
                'language' => 'en',
                'created_by' => isset($this->users['admin']) ? $this->users['admin']->id : null,
            ],
            [
                'title' => 'Pruning Techniques',
                'content' => 'Regular pruning helps maintain plant health and shape. Remove dead or diseased branches to promote new growth.',
                'type' => 'monthly',
                'status' => 'published',
                'language' => 'en',
                'created_by' => isset($this->users['admin']) ? $this->users['admin']->id : null,
            ],
            [
                'title' => 'Pest Control',
                'content' => 'Use organic pesticides to protect beneficial insects. Monitor your plants regularly for early pest detection.',
                'type' => 'weekly',
                'status' => 'published',
                'language' => 'en',
                'created_by' => isset($this->users['admin']) ? $this->users['admin']->id : null,
            ],
            [
                'title' => 'Seasonal Planting',
                'content' => 'Plant according to seasonal recommendations for your region. Different plants thrive in different seasons.',
                'type' => 'seasonal',
                'status' => 'published',
                'language' => 'en',
                'created_by' => isset($this->users['admin']) ? $this->users['admin']->id : null,
            ],
        ];

        foreach ($tips as $tip) {
            Tip::create($tip);
        }
        $this->command->info('✅ Created ' . count($tips) . ' tips.');
    }

    private function seedEmployees(): void
    {
        $this->command->info('👔 Creating employees...');
        // Create employees for HR users
        foreach ($this->users['hr'] as $index => $hrUser) {
            Employee::create([
                'user_id' => $hrUser->id,
                'employee_id' => 'EMP-HR-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'phone' => $hrUser->phone,
                'designation' => 'HR Manager',
                'region' => $this->areas[array_rand($this->areas)]->name,
                'joining_date' => Carbon::now()->subYears(rand(1, 5)),
            ]);
        }
        
        // Create employees for supervisors (needed for reports)
        foreach ($this->users['supervisors'] as $index => $supervisor) {
            Employee::create([
                'user_id' => $supervisor->id,
                'employee_id' => 'EMP-SUP-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'phone' => $supervisor->phone,
                'designation' => 'Supervisor',
                'region' => $this->areas[array_rand($this->areas)]->name,
                'joining_date' => Carbon::now()->subYears(rand(1, 5)),
            ]);
        }
        
        $totalEmployees = count($this->users['hr']) + count($this->users['supervisors']);
        $this->command->info("✅ Created {$totalEmployees} employees (2 HR, 5 supervisors).");
    }

    private function seedEmailTemplates(): void
    {
        $this->command->info('📧 Seeding email templates...');
        try {
            $this->call(EmailTemplateSeeder::class);
            $this->command->info('✅ Email templates seeded.');
        } catch (\Exception $e) {
            $this->command->warn('⚠️ Email templates table not found. Skipping...');
        }
    }

    private function displayUserCredentials(): void
    {
        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('USER CREDENTIALS FOR TESTING');
        $this->command->info('========================================');
        $this->command->info('');
        $this->command->info('Default Password: password123');
        $this->command->info('');

        // Admin
        if (isset($this->users['admin'])) {
            $admin = $this->users['admin'];
            $this->command->info("ADMIN:");
            $this->command->info("  Name: {$admin->name}");
            $this->command->info("  Email: {$admin->email}");
            $this->command->info("  Role: admin");
            $this->command->info("  Password: " . (env('APP_ADMIN_PASSWORD', 'password123')));
            $this->command->info('');
        }

        // Clients
        $this->command->info("CLIENTS (10 users):");
        foreach ($this->users['clients'] as $client) {
            $this->command->info("  - {$client->name} | {$client->email} | client | password123");
        }
        $this->command->info('');

        // Technicians
        $this->command->info("TECHNICIANS (8 users):");
        foreach ($this->users['technicians'] as $tech) {
            $this->command->info("  - {$tech->name} | {$tech->email} | technician | password123");
        }
        $this->command->info('');

        // Supervisors
        $this->command->info("SUPERVISORS (5 users):");
        foreach ($this->users['supervisors'] as $supervisor) {
            $this->command->info("  - {$supervisor->name} | {$supervisor->email} | supervisor | password123");
        }
        $this->command->info('');

        // Area Managers
        $this->command->info("AREA MANAGERS (3 users):");
        foreach ($this->users['area_managers'] as $am) {
            $this->command->info("  - {$am->name} | {$am->email} | area_manager | password123");
        }
        $this->command->info('');

        // HR
        $this->command->info("HR USERS (2 users):");
        foreach ($this->users['hr'] as $hr) {
            $this->command->info("  - {$hr->name} | {$hr->email} | hr | password123");
        }
        $this->command->info('');

        $this->command->info('========================================');
    }
}
