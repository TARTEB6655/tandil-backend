<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class EnsureAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:ensure';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ensure admin user exists with correct credentials (admin@tandil.com / password123)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // PERMANENT: Correct admin credentials (matching login form)
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

        $this->info('Ensuring admin user exists with correct credentials...');

        // Find admin user with correct email
        $admin = User::where('email', $adminEmail)->first();
        
        // If admin with correct email doesn't exist, delete any other admin users and create new one
        if (!$admin) {
            // Delete any other admin users
            $otherAdmins = User::where('role', 'admin')->where('email', '!=', $adminEmail)->get();
            foreach ($otherAdmins as $otherAdmin) {
                \DB::table('model_has_roles')->where('model_id', $otherAdmin->id)->delete();
                $otherAdmin->delete();
                $this->warn("Deleted admin user with email: {$otherAdmin->email}");
            }
            
            // Create new admin with correct credentials
            $admin = User::create([
                'name' => 'Administrator',
                'email' => $adminEmail,
                'phone' => '70000000',
                'password' => $adminPassword,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ]);
            
            if (method_exists($admin, 'assignRole')) {
                $admin->assignRole('admin');
            }
            
            $this->info("✅ Admin user created.");
        } else {
            // Update existing admin to ensure correct credentials
            $admin->update([
                'name' => 'Administrator',
                'password' => $adminPassword, // Always update password
                'role' => 'admin',
                'status' => 'active',
            ]);
            
            if (method_exists($admin, 'assignRole')) {
                $admin->syncRoles(['admin']);
            }
            
            $this->info("✅ Admin user updated.");
        }

        $this->line('');
        $this->line('Admin Credentials:');
        $this->line('  Email: ' . $admin->email);
        $this->line('  Password: ' . $adminPassword);
        $this->line('  Role: ' . $admin->role);
        $this->line('  Status: ' . $admin->status);
        $this->line('');

        return 0;
    }
}

