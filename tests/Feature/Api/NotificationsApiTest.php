<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'client']);
        if (method_exists($this->user, 'assignRole')) {
            $this->user->assignRole('client');
        }

        $token = $this->user->createToken('test')->plainTextToken;
        $this->headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function test_notifications_list_includes_unread_count(): void
    {
        $this->user->notify(new AdminNotification('N1', 'Message 1'));
        $this->user->notify(new AdminNotification('N2', 'Message 2'));

        $response = $this->getJson('/api/notifications', $this->headers);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.unread_count', 2);
        $response->assertJsonStructure([
            'data' => [
                'notifications' => ['data', 'current_page', 'per_page', 'total'],
                'unread_count',
            ],
        ]);
    }

    public function test_can_delete_single_notification(): void
    {
        $this->user->notify(new AdminNotification('Delete me', 'Message'));
        $notification = $this->user->notifications()->latest()->first();

        $response = $this->deleteJson("/api/notifications/{$notification->id}", [], $this->headers);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_cannot_delete_notification_of_another_user(): void
    {
        $other = User::factory()->create(['role' => 'client']);
        if (method_exists($other, 'assignRole')) {
            $other->assignRole('client');
        }
        $other->notify(new AdminNotification('Other', 'Other message'));
        $otherNotification = $other->notifications()->latest()->first();

        $response = $this->deleteJson("/api/notifications/{$otherNotification->id}", [], $this->headers);

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_can_clear_all_notifications(): void
    {
        $this->user->notify(new AdminNotification('A', 'Message A'));
        $this->user->notify(new AdminNotification('B', 'Message B'));

        $response = $this->postJson('/api/notifications/clear-all', [], $this->headers);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.deleted_count', 2);
        $this->assertSame(0, $this->user->fresh()->notifications()->count());
    }
}

