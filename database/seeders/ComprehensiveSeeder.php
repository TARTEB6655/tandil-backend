<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Area;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Report;
use App\Models\Complaint;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ComprehensiveSeeder extends Seeder
{
    /**
     * Run comprehensive seeding for all tables
     */
    public function run(): void
    {
        $this->command->info('Starting comprehensive seeding...');

        // 1. Create Areas
        $this->command->info('Creating areas...');
        $areas = [];
        $areaNames = ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ajman', 'Ras Al Khaimah', 'Fujairah', 'Umm Al Quwain'];
        foreach ($areaNames as $name) {
            $areas[] = Area::firstOrCreate(
                ['name' => $name],
                ['description' => "Service area for {$name}"]
            );
        }
        $this->command->info('Created ' . count($areas) . ' areas.');

        // 2. Create Users (Clients, Technicians, Supervisors, Area Managers, HR)
        $this->command->info('Creating users...');
        $clients = [];
        $technicians = [];
        $supervisors = [];
        $areaManagers = [];
        $hrUsers = [];

        // Create clients
        for ($i = 1; $i <= 20; $i++) {
            $clients[] = User::firstOrCreate(
                ['email' => "client{$i}@example.com"],
                [
                    'name' => "Client {$i}",
                    'password' => 'password', // Auto-hashed by model
                    'role' => 'client',
                    'status' => 'active',
                    'phone' => '7000000' . str_pad($i, 3, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($clients[count($clients) - 1], 'assignRole')) {
                $clients[count($clients) - 1]->assignRole('client');
            }
        }

        // Create technicians
        for ($i = 1; $i <= 10; $i++) {
            $tech = User::firstOrCreate(
                ['email' => "technician{$i}@example.com"],
                [
                    'name' => "Technician {$i}",
                    'password' => 'password', // Auto-hashed by model
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

        // Create supervisors
        for ($i = 1; $i <= 5; $i++) {
            $supervisor = User::firstOrCreate(
                ['email' => "supervisor{$i}@example.com"],
                [
                    'name' => "Supervisor {$i}",
                    'password' => 'password', // Auto-hashed by model
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

        // Create area managers
        for ($i = 1; $i <= 3; $i++) {
            $am = User::firstOrCreate(
                ['email' => "areamanager{$i}@example.com"],
                [
                    'name' => "Area Manager {$i}",
                    'password' => 'password', // Auto-hashed by model
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

        // Create HR users
        for ($i = 1; $i <= 2; $i++) {
            $hr = User::firstOrCreate(
                ['email' => "hr{$i}@example.com"],
                [
                    'name' => "HR User {$i}",
                    'password' => 'password', // Auto-hashed by model
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

        $this->command->info('Created users: ' . count($clients) . ' clients, ' . count($technicians) . ' technicians, ' . count($supervisors) . ' supervisors.');

        // 3. Assign technicians and supervisors to areas
        $this->command->info('Assigning technicians and supervisors to areas...');
        foreach ($areas as $index => $area) {
            if (isset($technicians[$index % count($technicians)])) {
                $area->technicians()->syncWithoutDetaching([$technicians[$index % count($technicians)]->id]);
            }
            if (isset($supervisors[$index % count($supervisors)])) {
                $area->supervisors()->syncWithoutDetaching([$supervisors[$index % count($supervisors)]->id]);
            }
        }

        // 4. Create Subscriptions (using plan enum directly)
        $this->command->info('Creating subscriptions...');
        $subscriptions = [];
        $plans = ['1_month', '3_month', '6_month', '12_month'];
        
        foreach ($clients as $client) {
            $plan = $plans[array_rand($plans)];
            $planMonths = [
                '1_month' => 1,
                '3_month' => 3,
                '6_month' => 6,
                '12_month' => 12,
            ];
            $months = $planMonths[$plan];
            $startDate = Carbon::now()->subDays(rand(0, 90));
            $endDate = $startDate->copy()->addMonths($months);
            
            $subscriptions[] = Subscription::create([
                'client_id' => $client->id,
                'plan' => $plan,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'amount' => [500, 1450, 2900, 5500][array_rand([500, 1450, 2900, 5500])],
                'payment_status' => ['pending', 'paid', 'failed'][rand(0, 2)],
                'total_visits' => $months,
                'completed_visits' => rand(0, $months),
            ]);
        }
        $this->command->info('Created ' . count($subscriptions) . ' subscriptions.');

        // 6. Create Visits (with area_id)
        $this->command->info('Creating visits...');
        $visits = [];
        foreach ($subscriptions as $subscription) {
            for ($i = 0; $i < rand(2, 8); $i++) {
                $area = $areas[array_rand($areas)];
                $technician = $technicians[array_rand($technicians)];
                $supervisor = $supervisors[array_rand($supervisors)];

                $visit = Visit::create([
                    'subscription_id' => $subscription->id,
                    'technician_id' => $technician->id,
                    'supervisor_id' => $supervisor->id,
                    'area_id' => $area->id,
                    'scheduled_date' => Carbon::now()->subDays(rand(0, 60)),
                    'status' => ['pending', 'accepted', 'in_progress', 'completed', 'cancelled'][rand(0, 4)],
                    'created_at' => Carbon::now()->subDays(rand(0, 60)),
                    'updated_at' => Carbon::now()->subDays(rand(0, 60)),
                ]);
                $visits[] = $visit;

                // Create visit photos
                if (rand(0, 1)) {
                    VisitPhoto::create([
                        'visit_id' => $visit->id,
                        'photo_path' => 'visits/sample-before.jpg',
                        'type' => 'before',
                    ]);
                }
                if (rand(0, 1)) {
                    VisitPhoto::create([
                        'visit_id' => $visit->id,
                        'photo_path' => 'visits/sample-after.jpg',
                        'type' => 'after',
                    ]);
                }
            }
        }
        $this->command->info('Created ' . count($visits) . ' visits.');

        // 7. Create Reports
        $this->command->info('Creating reports...');
        foreach ($visits as $visit) {
            if (rand(0, 1)) {
                Report::create([
                    'visit_id' => $visit->id,
                    'supervisor_id' => null, // Reports table references employees, not users
                    'status' => ['draft', 'pending', 'approved', 'sent_to_client'][rand(0, 3)],
                    'notes' => 'Sample report notes for visit #' . $visit->id,
                    'created_at' => $visit->created_at,
                    'updated_at' => $visit->updated_at,
                ]);
            }
        }
        $this->command->info('Created reports.');

        // 8. Create Complaints
        $this->command->info('Creating complaints...');
        foreach ($visits as $visit) {
            if (rand(0, 10) < 2) { // 20% chance
                Complaint::create([
                    'visit_id' => $visit->id,
                    'client_id' => $visit->subscription->client_id,
                    'notes' => 'Complaint about visit #' . $visit->id . ': Sample complaint description',
                    'status' => ['open', 'in_progress', 'resolved', 'escalated'][rand(0, 3)],
                    'created_at' => $visit->created_at->copy()->addDays(rand(0, 5)),
                ]);
            }
        }
        $this->command->info('Created complaints.');

        // 9. Ensure products and orders exist (if not already seeded)
        if (Product::count() === 0) {
            $this->command->warn('No products found. Please run ProductSeeder first.');
        }
        if (Order::count() === 0) {
            $this->command->warn('No orders found. Please run OrderSeeder first.');
        }

        $this->command->info('Comprehensive seeding completed!');
    }
}

