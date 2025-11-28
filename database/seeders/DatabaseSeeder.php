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

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Seed roles and initial admin user
        $this->call([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\AdminUserSeeder::class,
        ]);

        // Add sample data for development
        $this->call([
            \Database\Seeders\SampleDataSeeder::class,
        ]);
    }
}
