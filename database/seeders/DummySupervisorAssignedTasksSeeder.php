<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Employee;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\TechnicianAvailability;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

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

        if (Schema::hasTable('roles') && method_exists($supervisor, 'syncRoles')) {
            foreach (['supervisor', 'technician', 'client'] as $roleName) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            }
            $supervisor->syncRoles(['supervisor']);
            $technician->syncRoles(['technician']);
            $client->syncRoles(['client']);
        }

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

        // Remove old dummy visits (reports deleted by FK cascade or manually)
        $dummyVisitIds = Visit::where('notes', 'like', '[DUMMY-SUP-ASSIGN]%')->pluck('id');
        Report::whereIn('visit_id', $dummyVisitIds)->delete();
        Visit::where('notes', 'like', '[DUMMY-SUP-ASSIGN]%')->delete();

        $today = Carbon::today();
        $start = $today->copy()->subMonths(2);
        $end = $today->copy()->addMonths(10);
        $subscription = Subscription::firstOrCreate(
            ['client_id' => $client->id],
            [
                'plan' => '6_month',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'amount' => 1999.00,
                'payment_status' => 'paid',
                'total_visits' => 12,
                'completed_visits' => 0,
            ]
        );

        $baseUnassigned = [
            'subscription_id' => $subscription->id,
            'technician_id' => null,
            'supervisor_id' => $supervisor->id,
            'area_id' => null,
        ];

        // 5 dummy tasks assigned TO supervisor (supervisor1@test.com) – unassigned so supervisor sees them in Assignments and can assign to technician
        Visit::create(array_merge($baseUnassigned, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Mohammed Ali Farm | Tree Watering Visit | Al Ain Oasis, Abu Dhabi, UAE | 120 min | AED 350.00',
            'price' => 350.00,
        ]));
        Visit::create(array_merge($baseUnassigned, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Palm Grove Estate | Palm Tree Maintenance | Liwa Desert, Abu Dhabi, UAE | 90 min | AED 220.00',
            'price' => 220.00,
        ]));
        Visit::create(array_merge($baseUnassigned, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Al Noor Orchard | Soil Fertilizing | Al Ain, Abu Dhabi, UAE | 45 min | AED 150.00',
            'price' => 150.00,
        ]));
        Visit::create(array_merge($baseUnassigned, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Oasis Fields | Drip Irrigation Check | Al Faqa, Abu Dhabi, UAE | 60 min | AED 199.99',
            'price' => 199.99,
        ]));
        Visit::create(array_merge($baseUnassigned, [
            'scheduled_date' => $today->toDateString(),
            'status' => 'pending',
            'notes' => '[DUMMY-SUP-ASSIGN] Date Palm Sector B | Tree Pruning | Abu Dhabi, UAE | 75 min | AED 275.50',
            'price' => 275.50,
        ]));

        $this->command->info('Dummy tasks seeded: 5 unassigned tasks for supervisor1@test.com. Assign to technician → technician submits report → supervisor shares to client via POST /api/supervisor/visits/{visit_id}/finalize with status sent_to_client.');
    }
}

