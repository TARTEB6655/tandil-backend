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

        // Roles + one admin user only. No dummy subscriptions, orders, visits, reports, or tips.
        // Recent Activities and dashboard will show only real data from app usage.
        $this->call([
            RoleSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info('========================================');
        $this->command->info('Database Seeding Completed!');
        $this->command->info('========================================');
    }
}
