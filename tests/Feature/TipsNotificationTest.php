<?php

namespace Tests\Feature;

use App\Models\Tip;
use App\Models\User;
use App\Notifications\TipPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TipsNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function posting_tip_sends_tip_published_notification_to_all_roles()
    {
        Notification::fake();

        // Create one user for each role we care about
        $roles = ['client', 'technician', 'supervisor', 'area_manager', 'hr', 'admin'];
        $users = collect();
        foreach ($roles as $role) {
            $users[$role] = User::factory()->create(['role' => $role]);
        }

        // Act as admin and send a tip
        $admin = $users['admin'];
        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/tips', [
            'title' => 'Tip from test',
            'description' => 'Testing tip notifications.',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tips', ['title' => 'Tip from test']);

        // Assert that each role received a TipPublishedNotification
        foreach ($users as $user) {
            Notification::assertSentTo(
                $user,
                TipPublishedNotification::class,
                function (TipPublishedNotification $notification) {
                    return $notification->title === 'Tip from test';
                }
            );
        }
    }
}

