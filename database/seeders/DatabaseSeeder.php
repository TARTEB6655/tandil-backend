<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('========================================');
        $this->command->info('Starting Database Seeding');
        $this->command->info('========================================');
        $this->command->info('');

        // Roles + admin user + dummy subscriptions & reports (so admin Subscriptions/Reports pages show data)
        $this->call([
            RoleSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            SubscriptionAndReportDummySeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Database Seeding Completed!');
        $this->command->info('========================================');
    }
}
