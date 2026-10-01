<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CustomUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Add your custom users in the $users array below.
     * Format: ['name', 'email', 'password', 'role', 'phone' (optional)]
     */
    public function run(): void
    {
        $this->command->info('🗑️  Deleting all existing users...');
        
        // First, delete Spatie Permission relationships
        \DB::table('model_has_roles')->truncate();
        \DB::table('model_has_permissions')->truncate();
        
        // Delete related records that might have foreign key constraints
        \DB::table('personal_access_tokens')->whereNotNull('tokenable_id')->delete();
        \DB::table('sessions')->whereNotNull('user_id')->delete();
        \DB::table('password_reset_tokens')->delete();
        
        // Delete users (this will cascade delete related records)
        // Use DB facade to bypass model events if needed
        $deletedCount = \DB::table('users')->delete();
        $this->command->info("✅ Deleted {$deletedCount} user(s).");
        
        $this->command->info('👥 Creating custom users...');
        
        // ============================================
        // ADD YOUR CUSTOM USERS HERE
        // ============================================
        // PERMANENT: Admin credentials (matching login form)
        // These are hardcoded to ensure they always match the login form
        $adminEmail = 'admin@tandil.com';
        $adminPassword = 'password123';
        
        // Allow override via env if needed, but default to correct values
        if (env('APP_ADMIN_EMAIL') && env('APP_ADMIN_EMAIL') !== 'admin@example.com') {
            $adminEmail = env('APP_ADMIN_EMAIL');
        }
        if (env('APP_ADMIN_PASSWORD') && env('APP_ADMIN_PASSWORD') !== 'Password123!') {
            $adminPassword = env('APP_ADMIN_PASSWORD');
        }
        
        $users = [
            // Format: ['name', 'email', 'password', 'role', 'phone' (optional)]
            // Roles: admin, client, technician, supervisor, area_manager, hr
            
            // Admin User (uses env variables or defaults to admin@tandil.com / password123)
            ['Administrator', $adminEmail, $adminPassword, 'admin', '70000000'],
            
            // Client Users
            ['Client One', 'client1@test.com', 'password123', 'client', '70000001'],
            ['Client Two', 'client2@test.com', 'password123', 'client', '70000002'],
            ['Client Three', 'client3@test.com', 'password123', 'client', '70000003'],
            
            // Technician Users
            ['Technician One', 'technician1@test.com', 'password123', 'technician', '70000011'],
            ['Technician Two', 'technician2@test.com', 'password123', 'technician', '70000012'],
            ['Technician Three', 'technician3@test.com', 'password123', 'technician', '70000013'],
            
            // Supervisor Users
            ['Supervisor One', 'supervisor1@test.com', 'password123', 'supervisor', '70000021'],
            ['Supervisor Two', 'supervisor2@test.com', 'password123', 'supervisor', '70000022'],
            ['Supervisor Three', 'supervisor3@test.com', 'password123', 'supervisor', '70000023'],
            
            // Area Manager Users
            ['Area Manager One', 'areamanager1@test.com', 'password123', 'area_manager', '70000031'],
            ['Area Manager Two', 'areamanager2@test.com', 'password123', 'area_manager', '70000032'],
            
            // HR Users
            ['HR One', 'hr1@test.com', 'password123', 'hr', '70000041'],
            ['HR Two', 'hr2@test.com', 'password123', 'hr', '70000042'],
            
        ];
        
        // If no custom users provided, create default admin
        if (empty($users)) {
            $this->command->warn('⚠️  No custom users defined. Creating default admin user...');
            $users = [
                ['Administrator', $adminEmail, $adminPassword, 'admin', '70000000'],
            ];
        }
        
        $created = 0;
        foreach ($users as $userData) {
            $name = $userData[0];
            $email = $userData[1];
            $password = $userData[2];
            $role = $userData[3];
            $phone = $userData[4] ?? null;
            
            try {
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password, // Auto-hashed by model cast
                    'role' => $role,
                    'status' => 'active',
                    'phone' => $phone,
                    'email_verified_at' => now(),
                ]);
                
                // Ensure role exists in Spatie Permission before assigning
                $spatieRole = \Spatie\Permission\Models\Role::firstOrCreate(
                    ['name' => $role, 'guard_name' => 'web'],
                    ['name' => $role, 'guard_name' => 'web']
                );
                
                // Assign Spatie role
                if (method_exists($user, 'assignRole')) {
                    try {
                        // Remove any existing roles first
                        $user->roles()->detach();
                        // Assign the new role
                        $user->assignRole($spatieRole);
                    } catch (\Exception $e) {
                        $this->command->warn("  ⚠️  Role assignment issue for {$name}: " . $e->getMessage());
                    }
                }
                
                $created++;
                $this->command->info("  ✅ Created: {$name} ({$email}) - {$role}");
            } catch (\Exception $e) {
                $this->command->error("  ❌ Failed to create {$name}: " . $e->getMessage());
            }
        }
        
        $this->command->info('');
        $this->command->info("✅ Successfully created {$created} user(s).");
        $this->command->info('');
    }
}

