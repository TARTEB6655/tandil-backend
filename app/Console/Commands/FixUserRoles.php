<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class FixUserRoles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-roles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix user roles by assigning Spatie roles to all users based on their role column';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Fixing User Roles ===');
        $this->newLine();

        // Ensure roles exist for both web and sanctum guards
        $roles = ['client', 'technician', 'supervisor', 'area_manager', 'hr', 'admin'];
        $guards = ['web', 'sanctum'];
        
        foreach ($guards as $guard) {
            foreach ($roles as $roleName) {
                Role::firstOrCreate(
                    ['name' => $roleName, 'guard_name' => $guard],
                    ['name' => $roleName, 'guard_name' => $guard]
                );
            }
        }
        
        $this->info('✓ All roles ensured for web and sanctum guards');
        $this->newLine();

        // Get all users
        $users = User::all();

        if ($users->isEmpty()) {
            $this->warn('No users found in database.');
            return 0;
        }

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            if (empty($user->role)) {
                $skipped++;
                $bar->advance();
                continue;
            }

            // Check if user already has the role
            try {
                $hasRole = $user->hasRole($user->role);
                
                if ($hasRole) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // Sync roles (remove old, assign new)
                $user->syncRoles([$user->role]);
                $fixed++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("✗ User #{$user->id} ({$user->email}) failed: {$e->getMessage()}");
                $errors++;
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('=== Summary ===');
        $this->line("Fixed: {$fixed} users");
        $this->line("Skipped: {$skipped} users (already have correct role)");
        if ($errors > 0) {
            $this->warn("Errors: {$errors} users");
        }
        $this->line("Total: " . $users->count() . " users");
        $this->newLine();

        if ($fixed > 0) {
            $this->info('✓ User roles fixed successfully!');
        } else {
            $this->info('✓ All users already have correct roles assigned.');
        }

        return 0;
    }
}
