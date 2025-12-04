<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('APP_ADMIN_EMAIL', 'admin@tandil.com');
        $password = env('APP_ADMIN_PASSWORD', 'password123');
        $phone = '70000000';

        // Try to find user by email or phone
        $user = User::where('email', $email)
            ->orWhere('phone', $phone)
            ->first();

        if (!$user) {
            // Create user if not found
            $user = User::create([
                'name' => 'Administrator',
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
            ]);
        } else {
            // Update user info if exists to ensure consistency
            $user->update([
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
            ]);
        }

        // Assign role if method exists (if you are using spatie/laravel-permission or similar)
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }
    }
}
