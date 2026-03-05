<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds 20 technicians with full data (name, email, phone, employee_id, service_areas,
 * specializations, zone, supervisor). Also backfills any existing technicians so nothing is empty.
 *
 * Run: php artisan db:seed --class=TechniciansWithZonesSeeder
 */
class TechniciansWithZonesSeeder extends Seeder
{
    protected array $zoneNames = [
        'Green Valley',
        'Dubai Marina',
        'Abu Dhabi Central',
        'Sharjah Industrial',
        'Al Ain Oasis',
        'Ras Al Khaimah North',
        'Fujairah East',
        'Ajman Coastal',
    ];

    protected array $specializationPool = [
        'Tree Watering',
        'Planting',
        'Garden Cleaning',
        'Pest Control',
        'Lawn Maintenance',
        'Irrigation',
        'Pruning',
        'Fertilizing',
        'Landscaping',
        'Palm Care',
        'AC Maintenance',
        'Plumbing',
    ];

    protected array $serviceAreaPool = [
        'Dubai',
        'Abu Dhabi',
        'Sharjah',
        'Al Ain',
        'Ajman',
        'Ras Al Khaimah',
        'Fujairah',
        'Umm Al Quwain',
    ];

    protected array $designations = [
        'Field Technician',
        'Senior Technician',
        'Garden Technician',
        'Maintenance Technician',
    ];

    public function run(): void
    {
        $this->command->info('TechniciansWithZonesSeeder: ensuring no technician has empty data.');

        $roleTech = Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web'], ['name' => 'technician', 'guard_name' => 'web']);
        $roleSup = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web'], ['name' => 'supervisor', 'guard_name' => 'web']);

        // 1) Ensure areas (zones) exist
        $areas = [];
        foreach ($this->zoneNames as $name) {
            $areas[] = Area::firstOrCreate(
                ['name' => $name],
                ['description' => "Zone: {$name}", 'country' => 'UAE']
            );
        }
        $this->command->info('Zones: ' . count($areas));

        // 2) Ensure supervisors exist and every zone has at least one
        $supervisors = [];
        for ($i = 1; $i <= 4; $i++) {
            $email = "seed_supervisor_{$i}@tandil.ae";
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => "Seed Supervisor {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'supervisor',
                    'status' => 'active',
                    'phone' => '7200' . str_pad($i, 4, '0', STR_PAD_LEFT),
                ]
            );
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$roleSup]);
            }
            $supervisors[] = $user;
        }
        foreach ($areas as $index => $area) {
            $area->supervisors()->syncWithoutDetaching([$supervisors[$index % count($supervisors)]->id]);
        }
        $this->command->info('Supervisors attached to zones.');

        // 3) Create 20 technicians with every field filled
        for ($i = 1; $i <= 20; $i++) {
            $email = "seed_tech_" . str_pad($i, 2, '0', STR_PAD_LEFT) . "@tandil.ae";
            $phone = '7100' . str_pad($i, 4, '0', STR_PAD_LEFT);
            $name = "Seed Technician {$i}";
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'role' => 'technician',
                    'status' => 'active',
                    'phone' => $phone,
                ]
            );
            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$roleTech]);
            }
            $user->update(['name' => $name, 'phone' => $phone ?: $user->phone]);

            $employeeId = 'SEED-' . (2000 + $i);
            $serviceAreas = $this->randomSlice($this->serviceAreaPool, 2, 4);
            $specializations = $this->randomSlice($this->specializationPool, 2, 4);
            $region = $this->serviceAreaPool[$i % count($this->serviceAreaPool)];
            $designation = $this->designations[$i % count($this->designations)];

            Employee::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'employee_id' => $employeeId,
                    'phone' => $user->phone ?? $phone,
                    'designation' => $designation,
                    'region' => $region,
                    'service_areas' => $serviceAreas,
                    'specializations' => $specializations,
                    'joining_date' => now()->subDays(rand(30, 500)),
                ]
            );

            $numZones = $i <= 2 ? 2 : 1;
            $selected = $numZones === 1
                ? [collect($areas)->random()]
                : collect($areas)->random(min($numZones, count($areas)))->all();
            $attachAreas = collect($selected)->pluck('id')->unique()->values()->all();
            if (! empty($attachAreas)) {
                $user->assignedAreas()->syncWithoutDetaching($attachAreas);
            }
        }

        $this->command->info('Created/updated 20 technicians with full data.');

        // 4) Backfill ALL existing technicians so nothing is empty (Employee + at least one zone)
        $areasCollection = Area::all();
        if ($areasCollection->isEmpty()) {
            $this->command->warn('No areas found; skipping zone assignment for existing technicians.');
        }
        $allTechnicians = User::role('technician')->with(['employee', 'assignedAreas'])->get();
        $backfilled = 0;
        foreach ($allTechnicians as $user) {
            $emp = $user->employee;
            $needsEmployee = ! $emp || $emp->service_areas === null || $emp->specializations === null
                || (is_array($emp->service_areas) && empty($emp->service_areas))
                || (is_array($emp->specializations) && empty($emp->specializations));
            $needsZone = $user->assignedAreas->isEmpty();

            if ($needsEmployee || $needsZone) {
                $saPool = $this->serviceAreaPool;
                shuffle($saPool);
                $serviceAreas = array_values(array_slice($saPool, 0, rand(2, min(4, count($saPool)))));
                $spPool = $this->specializationPool;
                shuffle($spPool);
                $specializations = array_values(array_slice($spPool, 0, rand(2, min(4, count($spPool)))));

                Employee::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'name' => $user->name ?: 'Technician ' . $user->id,
                        'email' => $user->email ?: 'tech' . $user->id . '@tandil.ae',
                        'employee_id' => $emp && $emp->employee_id ? $emp->employee_id : ('TECH-' . $user->id),
                        'phone' => $user->phone ?: ('7199' . str_pad($user->id, 4, '0', STR_PAD_LEFT)),
                        'designation' => $emp?->designation ?? 'Field Technician',
                        'region' => $emp?->region ?? $this->serviceAreaPool[array_rand($this->serviceAreaPool)],
                        'service_areas' => $serviceAreas,
                        'specializations' => $specializations,
                        'joining_date' => $emp?->joining_date ?? now()->subDays(rand(30, 365)),
                    ]
                );

                if ($needsZone && $areasCollection->isNotEmpty()) {
                    $user->assignedAreas()->syncWithoutDetaching([$areasCollection->random()->id]);
                }
                $backfilled++;
            }
        }
        if ($backfilled > 0) {
            $this->command->info("Backfilled {$backfilled} technician(s) so all fields and zone/supervisor are filled.");
        }

        $this->command->info('Done. All technicians have name, email, employee_id, service_areas, specializations, and at least one zone (and supervisor).');
    }

    protected function randomSlice(array $pool, int $min, int $max): array
    {
        $c = $pool;
        shuffle($c);
        $n = min(max($min, rand($min, $max)), count($c));
        return array_values(array_slice($c, 0, $n));
    }
}
