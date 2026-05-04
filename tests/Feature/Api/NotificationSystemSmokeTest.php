<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test: every notification API surface returns 200 + expected JSON envelope (success, message, data).
 */
final class NotificationSystemSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function seedRole(string $name): void
    {
        if (class_exists(Role::class) && Schema::hasTable('roles')) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->seedRole($role);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function assertInboxListOk($response): void
    {
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'notifications',
                    'unread_count',
                ],
            ]);
    }

    public function test_smoke_notification_get_lists_all_role_prefixes_and_shared_routes(): void
    {
        $matrix = [
            'client' => '/api/client/notifications',
            'technician' => '/api/technician/notifications',
            'supervisor' => '/api/supervisor/notifications',
            'area_manager' => '/api/area-manager/notifications',
            'hr' => '/api/hr/notifications',
            'admin' => '/api/admin/notifications',
        ];

        foreach ($matrix as $role => $url) {
            $user = $this->makeUserWithRole($role);
            $this->assertInboxListOk(
                $this->actingAs($user, 'sanctum')->getJson($url . '?per_page=5')
            );
        }

        $client = $this->makeUserWithRole('client');
        $this->assertInboxListOk(
            $this->actingAs($client, 'sanctum')->getJson('/api/notifications')
        );

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/user/notifications?per_page=10')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'notifications',
                    'unread_count',
                ],
            ]);
    }

    public function test_smoke_client_notification_mutations_delete_and_clear_all(): void
    {
        $client = $this->makeUserWithRole('client');
        $client->notify(new AdminNotification('Smoke', 'Body'));
        $id = $client->notifications()->first()->id;

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/client/notifications/{$id}/mark-read")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($client, 'sanctum')
            ->deleteJson("/api/client/notifications/{$id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSame(0, $client->fresh()->notifications()->count());

        $client->notify(new AdminNotification('A', '1'));
        $client->notify(new AdminNotification('B', '2'));

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/client/notifications/clear-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2);
    }

    public function test_smoke_user_prefix_notification_mutations(): void
    {
        $client = $this->makeUserWithRole('client');
        $client->notify(new AdminNotification('U', 'M'));
        $id = $client->notifications()->first()->id;

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/user/notifications/{$id}/read")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/user/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/user/notifications/clear-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['deleted_count']]);
    }

    public function test_smoke_admin_delivery_stats_and_broadcasts_and_inbox(): void
    {
        $admin = $this->makeUserWithRole('admin');
        $admin->notify(new AdminNotification('Admin smoke', 'x'));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications/delivery-stats')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'grand_total',
                    'by_audience',
                    'by_audience_labeled',
                    'by_notification_type',
                ],
            ]);

        $this->assertInboxListOk(
            $this->actingAs($admin, 'sanctum')->getJson('/api/admin/notifications?per_page=5')
        );

        if (! Schema::hasTable('admin_notification_broadcasts')) {
            return;
        }

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications/broadcasts?per_page=5')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'broadcasts',
                    'pagination',
                ],
            ]);

        $broadcast = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'Smoke broadcast',
                'message' => 'Hello from smoke test.',
                'type' => 'role',
                'role' => 'admin',
            ]);

        $broadcast->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'broadcast_id',
                    'total_recipients',
                    'recipient_counts',
                ],
            ]);

        $bid = $broadcast->json('data.broadcast_id');
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/notifications/broadcasts/' . $bid)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'recipient_counts',
                ],
            ]);
    }
}
