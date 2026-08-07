<?php

namespace Tests\Feature\Api;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->client = User::factory()->create();
        $this->client->assignRole('client');
    }

    public function test_public_plans_endpoint_returns_plans_with_picture_and_description_without_total_visits(): void
    {
        $response = $this->getJson('/api/subscriptions/plans');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['plan', 'price', 'label', 'picture', 'description']
                ]
            ]);

        $plans = $response->json('data');
        $this->assertNotEmpty($plans);
        foreach ($plans as $plan) {
            $this->assertArrayNotHasKey('total_visits', $plan);
            $this->assertArrayHasKey('picture', $plan);
            $this->assertArrayHasKey('description', $plan);
        }
    }

    public function test_admin_can_create_subscription_with_picture_and_description(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'plan' => '3_month',
            'plan_name' => 'Premium Package',
            'subtitle' => 'Quarterly Service',
            'picture' => 'https://example.com/images/subscription-3month.jpg',
            'description' => 'Comprehensive 3-month farm inspection and treatment plan.',
            'amount' => 1450.00,
            'payment_status' => 'pending',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picture', 'https://example.com/images/subscription-3month.jpg')
            ->assertJsonPath('data.description', 'Comprehensive 3-month farm inspection and treatment plan.');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);

        $this->assertDatabaseHas('subscriptions', [
            'client_id' => $this->client->id,
            'plan' => '3_month',
            'picture' => 'https://example.com/images/subscription-3month.jpg',
            'description' => 'Comprehensive 3-month farm inspection and treatment plan.',
        ]);
    }

    public function test_client_cannot_create_subscription(): void
    {
        $payload = [
            'plan' => '1_month',
            'picture' => 'https://example.com/pic.jpg',
            'description' => 'Test',
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(403);
    }

    public function test_get_subscriptions_list_returns_picture_and_description_without_total_visits(): void
    {
        Subscription::factory()->create([
            'client_id' => $this->client->id,
            'picture' => 'https://example.com/sub.jpg',
            'description' => 'Existing subscription description',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/subscriptions');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $subs = $response->json('data');
        $this->assertNotEmpty($subs);
        foreach ($subs as $sub) {
            $this->assertArrayNotHasKey('total_visits', $sub);
            $this->assertArrayHasKey('picture', $sub);
            $this->assertArrayHasKey('description', $sub);
        }
    }

    public function test_get_single_subscription_returns_picture_and_description_without_total_visits(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'picture' => 'https://example.com/single.jpg',
            'description' => 'Single subscription details',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/subscriptions/' . $sub->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picture', 'https://example.com/single.jpg')
            ->assertJsonPath('data.description', 'Single subscription details');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);
    }

    public function test_admin_can_update_subscription_picture_and_description(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'picture' => 'https://example.com/old.jpg',
            'description' => 'Old description',
        ]);

        $payload = [
            'picture' => 'https://example.com/new.jpg',
            'description' => 'Updated description content',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/subscriptions/' . $sub->id, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.picture', 'https://example.com/new.jpg')
            ->assertJsonPath('data.description', 'Updated description content');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $sub->id,
            'picture' => 'https://example.com/new.jpg',
            'description' => 'Updated description content',
        ]);
    }

    public function test_mark_paid_endpoint_returns_subscription_without_total_visits(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'payment_status' => 'pending',
            'picture' => 'https://example.com/pay.jpg',
            'description' => 'Pending payment plan',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions/' . $sub->id . '/mark-paid');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_status', 'paid');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);
        $this->assertArrayHasKey('picture', $data);
        $this->assertArrayHasKey('description', $data);
    }

    public function test_admin_can_delete_subscription(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/subscriptions/' . $sub->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('subscriptions', [
            'id' => $sub->id,
        ]);
    }
}
