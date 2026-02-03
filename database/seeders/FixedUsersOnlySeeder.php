<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\Complaint;
use Spatie\Permission\Models\Role;

/**
 * Ensures only the 6 fixed dashboard users exist. Creates/updates them, then
 * removes all other users and their dependent data.
 *
 * Users kept:
 * - admin@tandil.com / password123 (Admin)
 * - client1@test.com / password123 (Client)
 * - technician1@test.com / password123 (Technician)
 * - supervisor1@test.com / password123 (Supervisor)
 * - areamanager1@test.com / password123 (Area Manager)
 * - hr1@test.com / password123 (HR)
 */
class FixedUsersOnlySeeder extends Seeder
{
    protected array $fixedUsers = [
        ['Administrator', 'admin@tandil.com', 'password123', 'admin', '70000000'],
        ['Client One', 'client1@test.com', 'password123', 'client', '70000001'],
        ['Technician One', 'technician1@test.com', 'password123', 'technician', '70000011'],
        ['Supervisor One', 'supervisor1@test.com', 'password123', 'supervisor', '70000021'],
        ['Area Manager One', 'areamanager1@test.com', 'password123', 'area_manager', '70000031'],
        ['HR One', 'hr1@test.com', 'password123', 'hr', '70000041'],
    ];

    public function run(): void
    {
        $this->command->info('Ensuring fixed users (6 only)...');

        // 1) Create or update the 6 fixed users and collect their IDs
        $keepIds = [];
        $adminId = null;

        foreach ($this->fixedUsers as $row) {
            [$name, $email, $password, $role, $phone] = $row;
            $spatieRole = Role::firstOrCreate(
                ['name' => $role, 'guard_name' => 'web'],
                ['name' => $role, 'guard_name' => 'web']
            );

            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'status' => 'active',
                    'phone' => $phone,
                    'email_verified_at' => now(),
                ]
            );

            if (method_exists($user, 'syncRoles')) {
                $user->syncRoles([$spatieRole]);
            }

            $keepIds[] = $user->id;
            if ($role === 'admin') {
                $adminId = $user->id;
            }
        }

        $keepIds = array_values(array_unique($keepIds));
        if (count($keepIds) === 0) {
            $this->command->warn('No fixed user IDs found.');
            return;
        }

        // 2) Delete dependent data for users we are about to remove
        $userIdsToRemove = User::whereNotIn('id', $keepIds)->pluck('id')->all();
        if (count($userIdsToRemove) === 0) {
            $this->command->info('No extra users to remove.');
            return;
        }

        // Subscriptions (and their visits) for other clients
        $subscriptionIdsToDelete = Subscription::whereNotIn('client_id', $keepIds)->pluck('id');
        $visitIdsToDelete = Visit::whereIn('subscription_id', $subscriptionIdsToDelete)->pluck('id');

        if ($visitIdsToDelete->isNotEmpty()) {
            DB::table('reports')->whereIn('visit_id', $visitIdsToDelete)->delete();
            DB::table('visit_photos')->whereIn('visit_id', $visitIdsToDelete)->delete();
            Complaint::whereIn('visit_id', $visitIdsToDelete)->delete();
            Visit::whereIn('id', $visitIdsToDelete)->delete();
        }
        Subscription::whereNotIn('client_id', $keepIds)->delete();

        // Notifications for other users (Laravel polymorphic)
        DB::table('notifications')
            ->where('notifiable_type', 'App\Models\User')
            ->whereNotIn('notifiable_id', $keepIds)
            ->delete();

        // Complaints by other clients (any remaining)
        Complaint::whereNotIn('client_id', $keepIds)->delete();

        // Orders and carts for other users
        DB::table('orders')->whereNotIn('user_id', $keepIds)->delete();
        DB::table('carts')->whereNotIn('user_id', $keepIds)->delete();

        // Admin reports: reassign created_by to admin if possible, else delete
        if ($adminId !== null) {
            DB::table('admin_reports')->whereNotIn('created_by', $keepIds)->update(['created_by' => $adminId]);
        } else {
            DB::table('admin_reports')->whereNotIn('created_by', $keepIds)->delete();
        }

        // Tips: reassign created_by to admin
        if ($adminId !== null) {
            DB::table('tips')->whereNotNull('created_by')->whereNotIn('created_by', $keepIds)->update(['created_by' => $adminId]);
        }

        // Spatie permission tables
        DB::table('model_has_roles')->where('model_type', 'App\Models\User')->whereNotIn('model_id', $keepIds)->delete();
        DB::table('model_has_permissions')->where('model_type', 'App\Models\User')->whereNotIn('model_id', $keepIds)->delete();

        // Sanctum tokens and sessions
        DB::table('personal_access_tokens')->where('tokenable_type', 'App\Models\User')->whereNotIn('tokenable_id', $keepIds)->delete();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->whereNotIn('user_id', $keepIds)->delete();
        }

        // Area pivots (supervisor/technician)
        if (Schema::hasTable('area_supervisor')) {
            DB::table('area_supervisor')->whereNotIn('user_id', $keepIds)->delete();
        }
        if (Schema::hasTable('area_technician')) {
            DB::table('area_technician')->whereNotIn('user_id', $keepIds)->delete();
        }

        // Employees: set user_id to null for removed users (FK allows null)
        DB::table('employees')->whereNotIn('user_id', $keepIds)->update(['user_id' => null]);

        // 3) Delete users that are not in the fixed list
        $deleted = User::whereNotIn('id', $keepIds)->delete();
        $this->command->info("Removed {$deleted} user(s). Only the 6 fixed users remain.");
    }
}
