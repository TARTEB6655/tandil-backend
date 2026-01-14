<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class EnsureAllRoleUsers extends Command
{
    protected $signature = 'users:ensure-all-roles';
    protected $description = 'Ensure all role users exist with correct credentials';

    public function handle()
    {
        $this->info('=== Ensuring All Role Users ===');
        $this->newLine();

        // Ensure all roles exist
        $roles = ['admin', 'client', 'technician', 'supervisor', 'area_manager', 'hr'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['name' => $roleName, 'guard_name' => 'web']
            );
        }
        $this->info('✅ All roles ensured');
        $this->newLine();

        // Define all users with their credentials
        $users = [
            ['Administrator', 'admin@tandil.com', 'password123', 'admin', '70000000'],
            ['Client One', 'client1@test.com', 'password123', 'client', '70000001'],
            ['Technician One', 'technician1@test.com', 'password123', 'technician', '70000011'],
            ['Supervisor One', 'supervisor1@test.com', 'password123', 'supervisor', '70000021'],
            ['Area Manager One', 'areamanager1@test.com', 'password123', 'area_manager', '70000031'],
            ['HR One', 'hr1@test.com', 'password123', 'hr', '70000041'],
        ];

        $created = 0;
        $updated = 0;

        foreach ($users as $userData) {
            $name = $userData[0];
            $email = $userData[1];
            $password = $userData[2];
            $role = $userData[3];
            $phone = $userData[4] ?? null;

            try {
                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => $password, // Auto-hashed by model cast
                        'role' => $role,
                        'status' => 'active',
                        'phone' => $phone,
                        'email_verified_at' => now(),
                    ]
                );

                // Ensure role exists
                $spatieRole = Role::firstOrCreate(
                    ['name' => $role, 'guard_name' => 'web'],
                    ['name' => $role, 'guard_name' => 'web']
                );

                // Assign Spatie role
                if (!$user->hasRole($role)) {
                    $user->syncRoles([$spatieRole]);
                }

                if ($user->wasRecentlyCreated) {
                    $created++;
                    $this->info("  ✅ Created: {$name} ({$email}) - {$role}");
                } else {
                    $updated++;
                    $this->info("  ✅ Updated: {$name} ({$email}) - {$role}");
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Failed for {$name}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✅ Created: {$created} user(s)");
        $this->info("✅ Updated: {$updated} user(s)");
        $this->newLine();
        $this->info('=== All Role Users Ready ===');
        $this->newLine();
        $this->info('Login Credentials:');
        $this->line('  Admin: admin@tandil.com / password123');
        $this->line('  Client: client1@test.com / password123');
        $this->line('  Technician: technician1@test.com / password123');
        $this->line('  Supervisor: supervisor1@test.com / password123');
        $this->line('  Area Manager: areamanager1@test.com / password123');
        $this->line('  HR: hr1@test.com / password123');
        $this->newLine();

        return 0;
    }
}

