<?php

namespace Tests\Feature\Api;

use App\Models\AdminNotificationBroadcast;
use App\Models\User;
use App\Notifications\AdminNotification;
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

    public function test_admin_broadcast_options_includes_vendor_role(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications/broadcast/options');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['value' => 'vendor', 'label' => 'Vendor'])
            ->assertJsonFragment(['value' => 'client', 'label' => 'Client'])
            ->assertJsonFragment(['value' => 'admin', 'label' => 'Admin']);

        $roles = collect($response->json('data.roles'))->pluck('value')->all();
        $this->assertSame(
            ['client', 'technician', 'supervisor', 'area_manager', 'hr', 'vendor', 'admin'],
            $roles
        );
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

        $this->technician->refresh();
        $this->assertSame(1, $this->technician->unreadNotifications()->count());
        $techN = $this->technician->notifications()->first();
        $this->assertSame(AdminNotification::class, $techN->type);
    }

    public function test_technician_notifications_api_lists_admin_broadcast_sent_to_technician_role(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'asdf',
                'message' => 'asdfvb',
                'type' => 'role',
                'role' => 'technician',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->technician->refresh();
        $this->assertGreaterThanOrEqual(1, $this->technician->notifications()->count());

        $list = $this->actingAs($this->technician, 'sanctum')
            ->getJson('/api/technician/notifications?per_page=20');

        $list->assertStatus(200)->assertJsonPath('success', true);

        $items = $list->json('data.notifications.data');
        $this->assertIsArray($items);
        $titles = array_map(fn ($row) => $row['data']['title'] ?? null, $items);
        $this->assertContains('asdf', $titles);

        $alerts = $this->actingAs($this->technician, 'sanctum')
            ->getJson('/api/technician/alerts?per_page=20');
        $alerts->assertStatus(200)->assertJsonPath('success', true);
        $alertTitles = array_map(fn ($row) => $row['title'] ?? null, $alerts->json('data') ?? []);
        $this->assertContains('asdf', $alertTitles);
    }

    public function test_technician_notifications_kind_leave_includes_admin_broadcast_for_compat(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'Broadcast only',
                'message' => 'Admin message',
                'type' => 'role',
                'role' => 'technician',
            ])
            ->assertStatus(201);

        $filtered = $this->actingAs($this->technician, 'sanctum')
            ->getJson('/api/technician/notifications?kind=leave&per_page=20');

        $filtered->assertStatus(200);
        $items = $filtered->json('data.notifications.data') ?? [];
        $titles = array_map(fn ($row) => $row['data']['title'] ?? null, $items);
        $this->assertContains('Broadcast only', $titles);
    }

    public function test_broadcast_type_all_appears_in_hr_and_area_manager_api_inboxes(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $hr = User::factory()->create(['role' => 'hr']);
        $this->assignRoleIfAvailable($hr, 'hr');
        $areaManager = User::factory()->create(['role' => 'area_manager']);
        $this->assignRoleIfAvailable($areaManager, 'area_manager');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'All hands',
                'message' => 'Read this',
                'type' => 'all',
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $hr->refresh();
        $areaManager->refresh();
        $this->assertGreaterThanOrEqual(1, $hr->notifications()->count());
        $this->assertGreaterThanOrEqual(1, $areaManager->notifications()->count());

        $hrList = $this->actingAs($hr, 'sanctum')->getJson('/api/hr/notifications?per_page=20');
        $hrList->assertStatus(200)->assertJsonPath('success', true);
        $hrTitles = array_map(
            fn ($row) => $row['data']['title'] ?? null,
            $hrList->json('data.notifications.data') ?? []
        );
        $this->assertContains('All hands', $hrTitles);

        $amList = $this->actingAs($areaManager, 'sanctum')->getJson('/api/area-manager/notifications?per_page=20');
        $amList->assertStatus(200)->assertJsonPath('success', true);
        $amTitles = array_map(
            fn ($row) => $row['data']['title'] ?? null,
            $amList->json('data.notifications.data') ?? []
        );
        $this->assertContains('All hands', $amTitles);
    }

    public function test_admin_stats_notifications_kind_leave_stays_strict_without_broadcasts(): void
    {
        if (! Schema::hasTable('admin_notification_broadcasts')) {
            $this->markTestSkipped('admin_notification_broadcasts migration not applied.');
        }

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/notifications/broadcast', [
                'title' => 'Only broadcast',
                'message' => 'm',
                'type' => 'role',
                'role' => 'technician',
            ])
            ->assertStatus(201);

        $r = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/notifications?kind=leave&audience_role=technician&per_page=20&q=Only%20broadcast');

        $r->assertStatus(200)->assertJsonPath('success', true);
        $this->assertSame(0, (int) $r->json('data.total_count'));
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
