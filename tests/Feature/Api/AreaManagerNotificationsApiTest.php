<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AreaManagerNotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $areaManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Area Manager']);
        $this->assignRoleIfAvailable($this->areaManager, 'area_manager');
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

    public function test_area_manager_notifications_dedicated_routes_smoke(): void
    {
        $notification = DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->areaManager->id,
            'data' => ['title' => 'Area Alert', 'message' => 'A new team issue is pending.'],
            'read_at' => null,
        ]);

        $list = $this->actingAs($this->areaManager, 'sanctum')
            ->getJson('/api/area-manager/notifications?per_page=20');
        $list->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notifications' => ['data', 'current_page', 'per_page', 'total'],
                    'unread_count',
                ],
            ]);

        $markOne = $this->actingAs($this->areaManager, 'sanctum')
            ->postJson('/api/area-manager/notifications/' . $notification->id . '/mark-read');
        $markOne->assertStatus(200)->assertJsonPath('success', true);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->areaManager->id,
            'data' => ['title' => 'Area Alert 2', 'message' => 'Another pending item.'],
            'read_at' => null,
        ]);

        $markAll = $this->actingAs($this->areaManager, 'sanctum')
            ->postJson('/api/area-manager/notifications/mark-all-read');
        $markAll->assertStatus(200)->assertJsonPath('success', true);

        $deleteOne = $this->actingAs($this->areaManager, 'sanctum')
            ->deleteJson('/api/area-manager/notifications/' . $notification->id);
        $deleteOne->assertStatus(200)->assertJsonPath('success', true);

        $clearAll = $this->actingAs($this->areaManager, 'sanctum')
            ->postJson('/api/area-manager/notifications/clear-all');
        $clearAll->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_area_manager_notifications_excludes_report_generated_type(): void
    {
        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\ReportGeneratedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->areaManager->id,
            'data' => ['type' => 'report_generated', 'title' => 'Generated report'],
            'read_at' => null,
        ]);

        DatabaseNotification::create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\AdminNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->areaManager->id,
            'data' => ['type' => 'admin_notification', 'message' => 'Team leave submitted.'],
            'read_at' => null,
        ]);

        $list = $this->actingAs($this->areaManager, 'sanctum')
            ->getJson('/api/area-manager/notifications?per_page=20');

        $list->assertStatus(200)->assertJsonPath('success', true);
        $items = (array) $list->json('data.notifications.data');
        $this->assertCount(1, $items);
        $this->assertSame('App\\Notifications\\AdminNotification', (string) ($items[0]['type'] ?? ''));
    }
}

