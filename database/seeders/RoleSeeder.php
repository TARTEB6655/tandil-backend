<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'client' => 'Regular customers who can purchase subscriptions, place orders, and manage their service visits.',
            'technician' => 'Field service technicians who perform on-site visits, complete service reports, and upload visit photos.',
            'supervisor' => 'Supervisors who oversee technicians, manage visit schedules, and approve service reports in their assigned areas.',
            'area_manager' => 'Area managers responsible for managing multiple areas, coordinating supervisors, and overseeing regional operations.',
            'hr' => 'Human resources personnel who manage employee records, handle HR-related tasks, and maintain staff information.',
            'admin' => 'System administrators with full access to manage users, roles, permissions, products, orders, and all system settings.',
            'vendor' => 'Marketplace vendors who manage their own products, inventory, pricing, and fulfil vendor-specific orders after admin approval.',
        ];

        foreach ($roles as $roleName => $description) {
            Role::updateOrCreate(
                ['name' => $roleName],
                ['description' => $description]
            );
        }
    }
}
