<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('APP_ADMIN_EMAIL', 'admin@example.com');
        $password = env('APP_ADMIN_PASSWORD', 'Password123!');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator',
                'phone' => '70000000',
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
            ]
        );

        if (method_exists($user, 'assignRole')) {
            $user->assignRole('admin');
        }
    }
}
