<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorZoneJobNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_new_zone_job_assignment_notifies_supervisor_and_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'email' => 'sup@example.com']);
        $client = User::factory()->create(['role' => 'client', 'email' => 'client@example.com']);

        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($client, 'client');

        $area = Area::factory()->create();
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
        ]);

        Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => null,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Water Pump Inspection | Irrigation Visit | Al Barsha Farm Cluster | 45 min | AED 113.95',
        ]);

        $admin->refresh();
        $supervisor->refresh();

        $this->assertSame(1, $supervisor->unreadNotifications()->count());
        $this->assertSame(1, $admin->unreadNotifications()->count());

        $supN = $supervisor->notifications()->latest()->first();
        $adminN = $admin->notifications()->latest()->first();

        $this->assertNotNull($supN);
        $this->assertNotNull($adminN);
        $this->assertStringContainsString('Water Pump Inspection', (string) ($supN->data['message'] ?? ''));
        $this->assertStringContainsString($supervisor->name, (string) ($adminN->data['message'] ?? ''));
        $this->assertSame('supervisor_new_zone_job_assigned', (string) ($adminN->data['meta']['type'] ?? ''));
    }

    public function test_zone_job_notifies_when_supervisor_is_assigned_later_on_update(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin2@example.com']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'email' => 'sup2@example.com']);
        $client = User::factory()->create(['role' => 'client', 'email' => 'client2@example.com']);

        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($client, 'client');

        $area = Area::factory()->create();
        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
        ]);

        $visit = Visit::create([
            'subscription_id' => $subscription->id,
            'technician_id' => null,
            'supervisor_id' => null,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Irrigation Pipeline Check | Zone Visit | Mirdif Agriculture Block | 35 min | AED 95.00',
        ]);

        $this->assertSame(0, $supervisor->unreadNotifications()->count());
        $this->assertSame(0, $admin->unreadNotifications()->count());

        $visit->supervisor_id = $supervisor->id;
        $visit->save();

        $admin->refresh();
        $supervisor->refresh();

        $this->assertSame(1, $supervisor->unreadNotifications()->count());
        $this->assertSame(1, $admin->unreadNotifications()->count());
        $supN = $supervisor->notifications()->latest()->first();
        $this->assertStringContainsString('Irrigation Pipeline Check', (string) ($supN->data['message'] ?? ''));
    }
}

