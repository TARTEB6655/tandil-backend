<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        // Create Test User only if not exists to avoid duplicate error
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User']
        );

        // Seed roles and initial admin user
        $this->call([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\AdminUserSeeder::class,
        ]);

        // Seed categories first (required for products)
        $this->call([
            \Database\Seeders\CategorySeeder::class,
        ]);

        // Seed products after categories
        $this->call([
            \Database\Seeders\ProductSeeder::class,
        ]);

        // Add sample data for development
        $this->call([
            \Database\Seeders\SampleDataSeeder::class,
        ]);
    }
}
