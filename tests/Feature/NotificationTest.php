<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test authenticated user can view notifications
     */
    public function test_authenticated_user_can_view_notifications()
    {
        $user = $this->createCustomer();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['notifications', 'unread_count']
            ]);
    }

    /**
     * Test notifications require authentication
     */
    public function test_notifications_require_authentication()
    {
        $response = $this->getJson('/api/notifications');

        $response->assertStatus(401);
    }
}

