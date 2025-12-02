<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // 1) create roles
        $roles = [
            'client',
            'technician',
            'supervisor',
            'area_manager',
            'hr',
            'admin',
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // 2) create permissions (adjust names as you like)
        $permissions = [
            // subscription/visits
            'view subscriptions', 'create subscriptions', 'mark subscription paid',
            'view visits', 'update visits', 'upload photos', 'manage visits', // Added 'manage visits' here
            // reports
            'create reports', 'view reports', 'review reports', 'finalize reports',
            // products/orders
            'view products', 'create orders', 'manage orders',
            // employee/area
            'manage employees', 'manage areas',
            // admin
            'manage users', 'manage settings',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p]);
        }

        // 3) assign sensible permission sets
        Role::findByName('admin')->givePermissionTo(Permission::all());
        Role::findByName('supervisor')->givePermissionTo(['view visits','view reports','review reports','finalize reports','upload photos']);
        Role::findByName('technician')->givePermissionTo(['view visits','upload photos','create reports','manage visits']); // Added 'manage visits' here
        Role::findByName('hr')->givePermissionTo(['manage employees']);
        Role::findByName('area_manager')->givePermissionTo(['manage areas','view visits','view reports']);
        Role::findByName('client')->givePermissionTo(['view subscriptions','create subscriptions','view visits','view reports','view products','create orders']);
    }
}
