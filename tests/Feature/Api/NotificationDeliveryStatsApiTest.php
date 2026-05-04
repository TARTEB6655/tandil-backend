<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationDeliveryStatsApiTest extends TestCase
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
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            if (method_exists($user, 'assignRole')) {
                $user->assignRole($role);
            }
        }
    }

    public function test_delivery_stats_requires_admin(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/admin/notifications/delivery-stats')
            ->assertStatus(403);
    }

    public function test_delivery_stats_returns_audience_breakdown(): void
    {
        $this->client->notify(new AdminNotification('C', 'Client message'));
        $this->technician->notify(new AdminNotification('T', 'Tech message'));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications/delivery-stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.grand_total', 2)
            ->assertJsonPath('data.audience_role', null)
            ->assertJsonPath('data.tracking.tracked', 2)
            ->assertJsonPath('data.tracking.untracked', 0);

        $byAudience = $response->json('data.by_audience');
        $this->assertSame(1, $byAudience['client'] ?? 0);
        $this->assertSame(1, $byAudience['technician'] ?? 0);

        $byType = $response->json('data.by_notification_type');
        $this->assertNotEmpty($byType);
        $row = collect($byType)->firstWhere('notification_type_short', 'AdminNotification');
        $this->assertNotNull($row);
        $this->assertSame(2, $row['total_deliveries']);
    }

    public function test_delivery_stats_counts_meta_only_audience_role_not_untracked(): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'type' => AdminNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $this->technician->id,
            'data' => json_encode([
                'message' => 'legacy meta path',
                'meta' => ['audience_role' => 'technician'],
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications/delivery-stats');

        $response->assertStatus(200)->assertJsonPath('data.grand_total', 1);
        $byAudience = $response->json('data.by_audience');
        $this->assertSame(1, $byAudience['technician'] ?? 0);
        $this->assertSame(0, $byAudience['untracked'] ?? -1);
    }

    public function test_delivery_stats_audience_role_query_scopes_grand_total_and_tracking(): void
    {
        $this->client->notify(new AdminNotification('C', 'Client only'));
        $this->technician->notify(new AdminNotification('T', 'Tech only'));

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications/delivery-stats?audience_role=client');

        $response->assertStatus(200)
            ->assertJsonPath('data.audience_role', 'client')
            ->assertJsonPath('data.grand_total', 1)
            ->assertJsonPath('data.tracking.tracked', 1)
            ->assertJsonPath('data.tracking.untracked', 0);

        $byAudience = $response->json('data.by_audience');
        $this->assertSame(1, $byAudience['client'] ?? 0);
        $this->assertSame(0, $byAudience['technician'] ?? -1);
    }
}
