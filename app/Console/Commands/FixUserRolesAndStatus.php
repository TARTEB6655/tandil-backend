<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Spatie\Permission\Models\Role;

class FixUserRolesAndStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:fix-all';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix all users: ensure roles exist, assign Spatie roles, and set status to active if null';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Fixing All Users ===');
        $this->newLine();

        // Ensure all roles exist
        $roles = ['client', 'technician', 'supervisor', 'area_manager', 'hr', 'admin'];
        foreach ($roles as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['name' => $roleName, 'guard_name' => 'web']
            );
        }
        $this->info('✓ All roles ensured');
        $this->newLine();

        // Get all users
        $users = User::all();

        if ($users->isEmpty()) {
            $this->warn('No users found in database.');
            return 0;
        }

        $fixed = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                // Fix status if null
                if (empty($user->status)) {
                    $user->status = 'active';
                    $user->save();
                }

                // Fix role assignment if role field exists but Spatie role doesn't
                if ($user->role && !$user->hasRole($user->role)) {
                    $role = Role::where('name', $user->role)->first();
                    if ($role) {
                        $user->assignRole($role);
                    } else {
                        // Create role if it doesn't exist
                        $role = Role::create(['name' => $user->role, 'guard_name' => 'web']);
                        $user->assignRole($role);
                    }
                }

                $fixed++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error fixing user {$user->email}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ Fixed: {$fixed} users");
        if ($errors > 0) {
            $this->warn("⚠️  Errors: {$errors} users");
        }

        $this->newLine();
        $this->info('=== Done ===');

        return 0;
    }
}

