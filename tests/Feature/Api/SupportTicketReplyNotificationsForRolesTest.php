<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupportTicketReplyNotificationsForRolesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $areaManager;
    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin User']);
        $this->areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Area Manager User']);
        $this->hr = User::factory()->create(['role' => 'hr', 'name' => 'HR User']);

        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->assignRoleIfAvailable($this->areaManager, 'area_manager');
        $this->assignRoleIfAvailable($this->hr, 'hr');
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

    public function test_area_manager_gets_notification_when_admin_replies_to_support_ticket(): void
    {
        $create = $this->actingAs($this->areaManager, 'sanctum')
            ->postJson('/api/area-manager/support/tickets', [
                'subject' => 'Area support needed',
                'email' => 'area.manager@example.com',
                'description' => 'Issue in zone assignment.',
            ]);

        $create->assertStatus(201)->assertJsonPath('success', true);
        $ticketId = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $ticketId);

        $reply = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/support-tickets/' . $ticketId . '/reply', [
                'message' => 'We are checking this now.',
            ]);

        $reply->assertStatus(201)->assertJsonPath('success', true);

        $list = $this->actingAs($this->areaManager, 'sanctum')
            ->getJson('/api/area-manager/notifications?per_page=20');
        $list->assertStatus(200)->assertJsonPath('success', true);

        $items = collect((array) $list->json('data.notifications.data'));
        $supportReplyNotification = $items->first(function (array $n) use ($ticketId) {
            return (string) ($n['data']['meta']['entity'] ?? '') === 'support_ticket'
                && (int) ($n['data']['meta']['ticket_id'] ?? 0) === $ticketId
                && (string) ($n['data']['meta']['action'] ?? '') === 'open_ticket_reply';
        });
        $this->assertNotNull($supportReplyNotification, 'Area manager must receive support reply notification.');
    }

    public function test_hr_gets_notification_when_admin_replies_to_support_ticket(): void
    {
        $create = $this->actingAs($this->hr, 'sanctum')
            ->postJson('/api/hr/support/tickets', [
                'subject' => 'HR support needed',
                'email' => 'hr.user@example.com',
                'description' => 'Need assistance with leave policy.',
            ]);

        $create->assertStatus(201)->assertJsonPath('success', true);
        $ticketId = (int) $create->json('data.id');
        $this->assertGreaterThan(0, $ticketId);

        $reply = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/support-tickets/' . $ticketId . '/reply', [
                'message' => 'Policy document shared.',
            ]);

        $reply->assertStatus(201)->assertJsonPath('success', true);

        $list = $this->actingAs($this->hr, 'sanctum')
            ->getJson('/api/hr/notifications?per_page=20');
        $list->assertStatus(200)->assertJsonPath('success', true);

        $items = collect((array) $list->json('data.notifications.data'));
        $supportReplyNotification = $items->first(function (array $n) use ($ticketId) {
            return (string) ($n['data']['meta']['entity'] ?? '') === 'support_ticket'
                && (int) ($n['data']['meta']['ticket_id'] ?? 0) === $ticketId
                && (string) ($n['data']['meta']['action'] ?? '') === 'open_ticket_reply';
        });
        $this->assertNotNull($supportReplyNotification, 'HR must receive support reply notification.');
    }
}

