<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ResetAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:reset {email?} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset admin user password or create admin user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Default credentials matching the login form
        $defaultEmail = env('APP_ADMIN_EMAIL') ?: 'admin@tandil.com';
        $defaultPassword = env('APP_ADMIN_PASSWORD') ?: 'password123';
        
        $email = $this->argument('email') ?? $this->ask('Enter admin email', $defaultEmail);
        $password = $this->option('password') ?? $this->secret('Enter new password (min 8 characters)', $defaultPassword);

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters long.');
            return 1;
        }

        // Find or create admin user
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Create new admin user
            // Note: User model has 'password' => 'hashed' cast, so we set it directly
            $user = User::create([
                'name' => 'Administrator',
                'email' => $email,
                'phone' => '70000000',
                'password' => $password, // Will be auto-hashed by the 'hashed' cast
                'role' => 'admin',
                'status' => 'active',
            ]);

            $this->info("Admin user created successfully!");
        } else {
            // Update existing user
            // Note: User model has 'password' => 'hashed' cast, so we set it directly
            $user->update([
                'password' => $password, // Will be auto-hashed by the 'hashed' cast
                'role' => 'admin',
                'status' => 'active',
            ]);

            $this->info("Admin password updated successfully!");
        }

        // Assign admin role using Spatie
        if (method_exists($user, 'assignRole')) {
            try {
                $user->assignRole('admin');
                $this->info("Admin role assigned successfully!");
            } catch (\Exception $e) {
                $this->warn("Could not assign role: " . $e->getMessage());
                $this->warn("Make sure roles are seeded. Run: php artisan db:seed --class=RoleSeeder");
            }
        }

        $this->line('');
        $this->line('Admin Credentials:');
        $this->line('Email: ' . $user->email);
        $this->line('Password: ' . ($this->option('password') ? '***' : '[the password you entered]'));
        $this->line('Role: ' . $user->role);
        $this->line('Status: ' . $user->status);

        return 0;
    }
}
