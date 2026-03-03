<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Subscription;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummySupervisorAssignedTasksSeeder extends Seeder
{
    /**
     * Temporary demo seeder for mobile UI:
     * - Ensures supervisor/technician/client exist
     * - Creates area assignment (supervisor -> area, technician -> area)
     * - Creates supervisor-assigned visits for technician dashboard/tasks
     *
     * Remove this seeder later when real data is available.
     */
    public function run(): void
    {
        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor1@test.com'],
            [
                'name' => 'Supervisor One',
                'password' => 'password123',
                'role' => 'supervisor',
                'status' => 'active',
                'phone' => '70000021',
                'email_verified_at' => now(),
            ]
        );

        $technician = User::firstOrCreate(
            ['email' => 'technician1@test.com'],
            [
                'name' => 'Ahmed Hassan',
                'password' => 'password123',
                'role' => 'technician',
                'status' => 'active',
                'phone' => '70000011',
                'email_verified_at' => now(),
            ]
        );

        $client = User::firstOrCreate(
            ['email' => 'client1@test.com'],
            [
                'name' => 'Client One',
                'password' => 'password123',
                'role' => 'client',
                'status' => 'active',
                'phone' => '70000001',
                'email_verified_at' => now(),
            ]
        );

        Employee::updateOrCreate(
            ['user_id' => $technician->id],
            [
                'name' => $technician->name,
                'email' => $technician->email,
                'employee_id' => 'EMP-1001',
                'phone' => $technician->phone,
                'designation' => 'Field Worker',
                'region' => 'Al Ain Oasis, Abu Dhabi, UAE',
                'joining_date' => Carbon::now()->subMonths(10)->toDateString(),
                'specializations' => ['Tree Watering', 'Planting', 'Garden Cleaning'],
            ]
        );

        Employee::updateOrCreate(
            ['user_id' => $supervisor->id],
            [
                'name' => $supervisor->name,
                'email' => $supervisor->email,
                'employee_id' => 'SUP-2001',
                'phone' => $supervisor->phone,
                'designation' => 'Team Leader',
                'region' => 'Abu Dhabi',
                'joining_date' => Carbon::now()->subYear()->toDateString(),
                'specializations' => ['Team Management'],
            ]
        );

        TechnicianAvailability::updateOrCreate(
            ['user_id' => $technician->id],
            [
                'is_online' => true,
                'auto_accept_jobs' => false,
                'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                'working_hours_slots' => [
                    ['slot' => 'morning', 'start' => '08:00', 'end' => '12:00'],
                    ['slot' => 'afternoon', 'start' => '13:00', 'end' => '17:00'],
                ],
            ]
        );

        $area = Area::firstOrCreate(
            ['name' => 'Green Valley'],
            ['description' => 'Demo service area for mobile task assignment.']
        );

        DB::table('area_supervisor')->updateOrInsert(
            ['area_id' => $area->id, 'user_id' => $supervisor->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        DB::table('area_technician')->updateOrInsert(
            ['area_id' => $area->id, 'user_id' => $technician->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $subscription = Subscription::firstOrCreate(
            ['client_id' => $client->id, 'plan' => '3_month'],
            [
                'start_date' => Carbon::today()->subMonth()->toDateString(),
                'end_date' => Carbon::today()->addMonths(2)->toDateString(),
                'amount' => 899.00,
                'payment_status' => 'paid',
                'payment_reference' => 'DUMMY-SUB-1001',
                'paid_at' => now()->subMonth(),
                'total_visits' => 12,
                'completed_visits' => 2,
            ]
        );

        // Refresh temporary dummy records each run.
        Visit::where('notes', 'like', '[DUMMY-SUP-ASSIGN]%')->delete();

        $today = Carbon::today();
        $base = [
            'subscription_id' => $subscription->id,
            'technician_id' => $technician->id,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
        ];

        // 5 dummy tasks for today – in_progress and pending only (no "accepted" status); with price so dashboard shows correctly
        Visit::create(array_merge($base, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'in_progress',
            'accepted_at' => $today->copy()->setTime(7, 45),
            'started_at' => $today->copy()->setTime(8, 0),
            'notes' => '[DUMMY-SUP-ASSIGN] Mohammed Ali Farm | Tree Watering Visit | Al Ain Oasis, Abu Dhabi, UAE | 120 min | AED 350.00 | 5/5',
            'price' => 350.00,
        ]));

        Visit::create(array_merge($base, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Palm Grove Estate | Palm Tree Maintenance | Liwa Desert, Abu Dhabi, UAE | 90 min | AED 220.00',
            'price' => 220.00,
        ]));

        Visit::create(array_merge($base, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Al Noor Orchard | Soil Fertilizing | Al Ain, Abu Dhabi, UAE | 45 min | AED 150.00',
            'price' => 150.00,
        ]));

        Visit::create(array_merge($base, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'in_progress',
            'accepted_at' => $today->copy()->setTime(10, 30),
            'started_at' => $today->copy()->setTime(10, 35),
            'notes' => '[DUMMY-SUP-ASSIGN] Oasis Fields | Drip Irrigation Check | Al Faqa, Abu Dhabi, UAE | 60 min | AED 199.99 | 5/5',
            'price' => 199.99,
        ]));

        Visit::create(array_merge($base, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Date Palm Sector B | Tree Pruning | Abu Dhabi, UAE | 75 min | AED 275.50',
            'price' => 275.50,
        ]));

        // 2 completed visits (yesterday and 2 days ago) for recent_visits with price/rating
        Visit::create(array_merge($base, [
            'scheduled_date' => $today->copy()->subDay()->toDateString(),
            'status' => 'completed',
            'accepted_at' => $today->copy()->subDay()->setTime(9, 0),
            'started_at' => $today->copy()->subDay()->setTime(9, 10),
            'completed_at' => $today->copy()->subDay()->setTime(10, 40),
            'completed_date' => $today->copy()->subDay()->toDateString(),
            'notes' => '[DUMMY-SUP-ASSIGN] Green Valley Farm | Planting & Fertilizing | Abu Dhabi, UAE | 90 min | AED 289.99 | 5/5',
            'price' => 289.99,
        ]));

        Visit::create(array_merge($base, [
            'scheduled_date' => $today->copy()->subDays(2)->toDateString(),
            'status' => 'completed',
            'accepted_at' => $today->copy()->subDays(2)->setTime(10, 0),
            'started_at' => $today->copy()->subDays(2)->setTime(10, 10),
            'completed_at' => $today->copy()->subDays(2)->setTime(11, 0),
            'completed_date' => $today->copy()->subDays(2)->toDateString(),
            'notes' => '[DUMMY-SUP-ASSIGN] Desert Palm Resort | Garden Cleaning | Abu Dhabi, UAE | 50 min | AED 145.50 | 4/5',
            'price' => 145.50,
        ]));

        $this->command->info('Dummy supervisor-assigned technician tasks seeded successfully (5 today tasks + 2 completed with price).');
    }
}

