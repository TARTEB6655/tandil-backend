<?php

namespace Database\Seeders;

use App\Models\Visit;
use Illuminate\Database\Seeder;

/**
 * Removes every visit (jobs) from the database, then seeds fresh demo tasks.
 *
 * Visit delete cascades (where migrations define it): reports, visit_photos,
 * visit_offers, complaints.
 *
 * Run:
 *   php artisan db:seed --class=ResetVisitsAndSeedDummyJobsSeeder
 *
 * Or:
 *   php artisan visits:reset-dummy
 */
class ResetVisitsAndSeedDummyJobsSeeder extends Seeder
{
    public function run(): void
    {
        $deleted = Visit::query()->count();
        $this->command->info("Deleting {$deleted} visit(s) and dependent rows (cascade)…");
        Visit::query()->delete();

        $this->command->info('Seeding fresh dummy supervisor tasks…');
        $this->call(DummySupervisorAssignedTasksSeeder::class);

        $this->command->info('Done. Use supervisor1@test.com to assign; 5 unassigned [DUMMY-SUP-ASSIGN] visits for today.');
    }
}
