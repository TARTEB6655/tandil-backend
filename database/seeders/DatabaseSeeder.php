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

        // Roles + only the 6 fixed users (admin, client1, technician1, supervisor1, areamanager1, hr1) + dummy data for client1
        $this->call([
            RoleSeeder::class,
            RolePermissionSeeder::class,
            FixedUsersOnlySeeder::class,
            SubscriptionAndReportDummySeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Database Seeding Completed!');
        $this->command->info('========================================');
    }
}
