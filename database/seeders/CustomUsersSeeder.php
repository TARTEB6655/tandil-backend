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
            
            // Add your custom users below:
            // Example:
            // ['John Doe', 'john@example.com', 'password123', 'client', '+971501234567'],
            // ['Jane Smith', 'jane@example.com', 'password123', 'technician', '+971501234568'],
            
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
                
                // Assign Spatie role
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
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

