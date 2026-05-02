<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotificationBroadcast;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminNotificationBroadcastApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->technician = User::factory()->create(['role' => 'technician']);

        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->assignRoleIfAvailable($this->client, 'client');
        $this->assignRoleIfAvailable($this->technician, 'technician');
    }

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

    public function test_admin_broadcast_returns_recipient_counts_and_persists_row(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'System update',
                'message' => 'Please read.',
                'type' => 'all',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_recipients', 3);

        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.recipient_counts.customers'));
        $this->assertGreaterThanOrEqual(1, (int) $response->json('data.recipient_counts.technicians'));

        $this->assertDatabaseHas('admin_notification_broadcasts', [
            'id' => $response->json('data.broadcast_id'),
            'total_recipients' => 3,
        ]);

        $this->client->refresh();
        $this->assertSame(1, $this->client->unreadNotifications()->count());
        $n = $this->client->notifications()->first();
        $this->assertStringContainsString('audience_role', json_encode($n->data));
    }

    public function test_broadcast_history_list_requires_admin(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        AdminNotificationBroadcast::create([
            'sent_by_user_id' => $this->admin->id,
            'title' => 'T',
            'message' => 'M',
            'scope_type' => 'all',
            'scope_role' => null,
            'total_recipients' => 1,
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/admin/notifications/broadcasts')
            ->assertStatus(403);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications/broadcasts')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
