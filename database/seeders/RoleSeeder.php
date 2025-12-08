<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'client',
            'technician',
            'supervisor',
            'area_manager',
            'hr',
            'admin',
        ];

        // Create roles for both 'web' and 'sanctum' guards
        // API routes use Sanctum, web routes use web guard
        $guards = ['web', 'sanctum'];
        
        foreach ($guards as $guard) {
            foreach ($roles as $role) {
                Role::firstOrCreate(
                    ['name' => $role, 'guard_name' => $guard],
                    ['name' => $role, 'guard_name' => $guard]
                );
            }
        }
    }
}
