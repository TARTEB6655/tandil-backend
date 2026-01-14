<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // PERMANENT: Admin credentials (matching login form)
        // These are hardcoded to ensure they always match the login form
        $email = 'admin@tandil.com';
        $password = 'password123';
        
        // Allow override via env if needed, but default to correct values
        if (env('APP_ADMIN_EMAIL') && env('APP_ADMIN_EMAIL') !== 'admin@example.com') {
            $email = env('APP_ADMIN_EMAIL');
        }
        if (env('APP_ADMIN_PASSWORD') && env('APP_ADMIN_PASSWORD') !== 'Password123!') {
            $password = env('APP_ADMIN_PASSWORD');
        }
        
        $phone = '70000000';

        // Try to find user by email first
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Try to find by phone
            $user = User::where('phone', $phone)->first();
        }

        if (!$user) {
            // Create user if not found
            // Note: User model has 'password' => 'hashed' cast, so we set it directly
            $user = User::create([
                'name' => 'Administrator',
                'email' => $email,
                'phone' => $phone,
                'password' => $password, // Will be auto-hashed by the 'hashed' cast
                'role' => 'admin',
                'status' => 'active',
            ]);
            echo "Admin user created: {$email} / {$password}\n";
        } else {
            // Update user info if exists to ensure consistency
            // Note: User model has 'password' => 'hashed' cast, so we set it directly
            $user->update([
                'email' => $email,
                'phone' => $phone,
                'password' => $password, // Will be auto-hashed by the 'hashed' cast
                'role' => 'admin',
                'status' => 'active',
            ]);
            echo "Admin user updated: {$email} / {$password}\n";
        }

        // Assign role if method exists (if you are using spatie/laravel-permission or similar)
        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('admin');
            } catch (\Exception $e) {
                echo "Warning: Could not assign admin role. Make sure roles are seeded first.\n";
            }
        }

        echo "Admin credentials: Email: {$email}, Password: {$password}\n";
    }
}
