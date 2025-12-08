<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test anyone can view subscription plans
     */
    public function test_anyone_can_view_subscription_plans()
    {
        $response = $this->getJson('/api/subscriptions/plans');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['plan', 'price', 'label']
                ]
            ]);
    }

    /**
     * Test client can create subscription
     */
    public function test_client_can_create_subscription()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/subscriptions', [
            'plan' => '1_month',
            'start_date' => now()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'plan', 'client_id']
            ]);
    }

    /**
     * Test client can view own subscriptions
     */
    public function test_client_can_view_own_subscriptions()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $subscription = $this->createSubscription(['client_id' => $client->id]);

        $response = $this->getJson('/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test admin can view all subscriptions
     */
    public function test_admin_can_view_all_subscriptions()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->createSubscription();
        $this->createSubscription();

        $response = $this->getJson('/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data'
            ]);
    }

    /**
     * Test client can view own subscription details
     */
    public function test_client_can_view_subscription_details()
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $subscription = $this->createSubscription(['client_id' => $client->id]);

        $response = $this->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'plan', 'client_id']
            ]);
    }

    /**
     * Test client cannot view other client's subscription
     */
    public function test_client_cannot_view_other_client_subscription()
    {
        $client1 = $this->createCustomer();
        $client2 = $this->createCustomer();
        Sanctum::actingAs($client1);

        $subscription = $this->createSubscription(['client_id' => $client2->id]);

        $response = $this->getJson("/api/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
    }

    /**
     * Test admin can mark subscription as paid
     */
    public function test_admin_can_mark_subscription_paid()
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $subscription = $this->createSubscription(['payment_status' => 'pending']);

        $response = $this->postJson("/api/subscriptions/{$subscription->id}/mark-paid");

        $response->assertStatus(200)
            ->assertJson(['status' => true]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'payment_status' => 'paid',
        ]);
    }

    /**
     * Test subscription creation requires authentication
     */
    public function test_subscription_creation_requires_authentication()
    {
        $response = $this->postJson('/api/subscriptions', []);

        $response->assertStatus(401);
    }
}




