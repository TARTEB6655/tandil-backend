<?php

namespace Tests\Feature\Api;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $client;
    private User $client2;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->client = User::factory()->create();
        $this->client->assignRole('client');

        $this->client2 = User::factory()->create();
        $this->client2->assignRole('client');
    }

    // -----------------------------------------------------------------------
    // Plans
    // -----------------------------------------------------------------------

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

    // -----------------------------------------------------------------------
    // target_type: specific_clients (default)
    // -----------------------------------------------------------------------

    public function test_admin_can_create_subscription_for_specific_client(): void
    {
        $payload = [
            'client_id'   => $this->client->id,
            'plan'        => '3_month',
            'plan_name'   => 'Premium Package',
            'subtitle'    => 'Quarterly Service',
            'target_type' => 'specific_clients',
            'picture'     => 'https://example.com/images/subscription-3month.jpg',
            'description' => 'Comprehensive 3-month farm inspection and treatment plan.',
            'amount'      => 1450.00,
            'payment_status' => 'pending',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_type', 'specific_clients')
            ->assertJsonPath('data.client_id', $this->client->id)
            ->assertJsonPath('data.picture', 'https://example.com/images/subscription-3month.jpg')
            ->assertJsonPath('data.description', 'Comprehensive 3-month farm inspection and treatment plan.');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);

        $this->assertDatabaseHas('subscriptions', [
            'client_id'   => $this->client->id,
            'plan'        => '3_month',
            'target_type' => 'specific_clients',
            'picture'     => 'https://example.com/images/subscription-3month.jpg',
        ]);
    }

    public function test_admin_can_create_subscription_for_multiple_specific_clients(): void
    {
        $payload = [
            'plan'        => '1_month',
            'plan_name'   => 'Basic Monthly',
            'target_type' => 'specific_clients',
            'client_ids'  => [$this->client->id, $this->client2->id],
            'amount'      => 500.00,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $message = $response->json('message');
        $this->assertStringContainsString('specific clients', $message);

        // Both clients should have a subscription
        $this->assertDatabaseHas('subscriptions', [
            'client_id'   => $this->client->id,
            'target_type' => 'specific_clients',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'client_id'   => $this->client2->id,
            'target_type' => 'specific_clients',
        ]);
    }

    // -----------------------------------------------------------------------
    // target_type: all_users
    // -----------------------------------------------------------------------

    public function test_admin_can_create_subscription_for_all_users(): void
    {
        $payload = [
            'plan'        => '6_month',
            'plan_name'   => 'VIP 6 Months',
            'subtitle'    => 'VIP half-year plan',
            'target_type' => 'all_users',   // <-- the key flag
            'picture'     => 'https://example.com/vip6.jpg',
            'description' => 'Available to every client in the app.',
            'amount'      => 2900.00,
            'payment_status' => 'pending',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $message = $response->json('message');
        $this->assertStringContainsString('all users', $message);

        // Both clients should have been assigned the subscription
        $this->assertDatabaseHas('subscriptions', [
            'client_id'   => $this->client->id,
            'target_type' => 'all_users',
            'apply_to_all' => true,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'client_id'   => $this->client2->id,
            'target_type' => 'all_users',
            'apply_to_all' => true,
        ]);

        // Check target_type in each returned record
        $created = $response->json('data');
        foreach ($created as $item) {
            $this->assertEquals('all_users', $item['target_type']);
        }
    }

    public function test_target_type_defaults_to_specific_clients_when_not_provided(): void
    {
        $payload = [
            'client_id' => $this->client->id,
            'plan'      => '1_month',
            'amount'    => 500.00,
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.target_type', 'specific_clients');
    }

    public function test_target_type_validation_rejects_invalid_value(): void
    {
        $payload = [
            'client_id'   => $this->client->id,
            'plan'        => '1_month',
            'target_type' => 'invalid_value',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(422);
    }

    // -----------------------------------------------------------------------
    // General CRUD
    // -----------------------------------------------------------------------

    public function test_client_cannot_create_subscription(): void
    {
        $payload = [
            'plan'        => '1_month',
            'picture'     => 'https://example.com/pic.jpg',
            'description' => 'Test',
        ];

        $response = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/subscriptions', $payload);

        $response->assertStatus(403);
    }

    public function test_get_subscriptions_list_returns_target_type_picture_and_description(): void
    {
        Subscription::factory()->create([
            'client_id'   => $this->client->id,
            'target_type' => 'specific_clients',
            'picture'     => 'https://example.com/sub.jpg',
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
            $this->assertArrayHasKey('target_type', $sub);
        }
    }

    public function test_get_single_subscription_returns_target_type_picture_and_description(): void
    {
        $sub = Subscription::factory()->create([
            'client_id'   => $this->client->id,
            'target_type' => 'all_users',
            'picture'     => 'https://example.com/single.jpg',
            'description' => 'Single subscription details',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/subscriptions/' . $sub->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_type', 'all_users')
            ->assertJsonPath('data.picture', 'https://example.com/single.jpg')
            ->assertJsonPath('data.description', 'Single subscription details');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);
    }

    public function test_admin_can_update_subscription_target_type_picture_and_description(): void
    {
        $sub = Subscription::factory()->create([
            'client_id'   => $this->client->id,
            'target_type' => 'specific_clients',
            'picture'     => 'https://example.com/old.jpg',
            'description' => 'Old description',
        ]);

        $payload = [
            'target_type' => 'all_users',
            'picture'     => 'https://example.com/new.jpg',
            'description' => 'Updated description content',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/subscriptions/' . $sub->id, $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_type', 'all_users')
            ->assertJsonPath('data.picture', 'https://example.com/new.jpg')
            ->assertJsonPath('data.description', 'Updated description content');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('total_visits', $data);

        $this->assertDatabaseHas('subscriptions', [
            'id'          => $sub->id,
            'target_type' => 'all_users',
            'apply_to_all' => true,
            'picture'     => 'https://example.com/new.jpg',
        ]);
    }

    /**
     * PHP never populates request fields for PUT requests with a
     * multipart/form-data body (only POST gets that) — a real PUT+file-upload
     * update silently no-ops even though the endpoint returns 200. The route
     * accepts POST as well for this reason; this test guards that alias.
     */
    public function test_admin_can_update_subscription_via_post_route_for_file_uploads(): void
    {
        $sub = Subscription::factory()->create([
            'client_id'  => $this->client->id,
            'plan_name'  => 'Old Name',
            'amount'     => 100,
        ]);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('subscription.jpg');

        $response = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/subscriptions/' . $sub->id, [
                'plan_name' => 'Updated Via POST',
                'amount'    => 250,
                'picture'   => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan_name', 'Updated Via POST')
            ->assertJsonPath('data.amount', '250.00');

        $this->assertDatabaseHas('subscriptions', [
            'id'        => $sub->id,
            'plan_name' => 'Updated Via POST',
        ]);
    }

    public function test_admin_can_update_subscription_plan(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'plan'      => '1_month',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/subscriptions/' . $sub->id, ['plan' => '6_month']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.plan', '6_month');

        $this->assertDatabaseHas('subscriptions', [
            'id'   => $sub->id,
            'plan' => '6_month',
        ]);
    }

    public function test_update_subscription_rejects_invalid_plan(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'plan'      => '1_month',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/subscriptions/' . $sub->id, ['plan' => 'not_a_plan']);

        $response->assertStatus(422);
    }

    public function test_client_cannot_update_own_subscription_plan(): void
    {
        $sub = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'plan'      => '1_month',
        ]);

        $response = $this->actingAs($this->client, 'sanctum')
            ->putJson('/api/subscriptions/' . $sub->id, ['plan' => '6_month']);

        $response->assertStatus(200)
            ->assertJsonPath('data.plan', '1_month');
    }

    public function test_mark_paid_endpoint_returns_subscription_without_total_visits(): void
    {
        $sub = Subscription::factory()->create([
            'client_id'      => $this->client->id,
            'payment_status' => 'pending',
            'target_type'    => 'specific_clients',
            'picture'        => 'https://example.com/pay.jpg',
            'description'    => 'Pending payment plan',
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
        $this->assertArrayHasKey('target_type', $data);
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

    public function test_admin_can_purge_subscription_and_its_visits(): void
    {
        $sub = Subscription::factory()->create(['client_id' => $this->client->id]);
        $visit1 = Visit::create(['subscription_id' => $sub->id, 'scheduled_date' => '2026-09-01', 'status' => 'pending']);
        $visit2 = Visit::create(['subscription_id' => $sub->id, 'scheduled_date' => '2026-10-01', 'status' => 'pending']);

        $otherSub = Subscription::factory()->create(['client_id' => $this->client2->id]);
        $otherVisit = Visit::create(['subscription_id' => $otherSub->id, 'scheduled_date' => '2026-09-01', 'status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/subscriptions/'.$sub->id.'/purge');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_visits', 2);

        $this->assertDatabaseMissing('subscriptions', ['id' => $sub->id]);
        $this->assertDatabaseMissing('visits', ['id' => $visit1->id]);
        $this->assertDatabaseMissing('visits', ['id' => $visit2->id]);

        // Untouched subscription/visit must survive.
        $this->assertDatabaseHas('subscriptions', ['id' => $otherSub->id]);
        $this->assertDatabaseHas('visits', ['id' => $otherVisit->id]);
    }

    public function test_client_cannot_purge_subscription(): void
    {
        $sub = Subscription::factory()->create(['client_id' => $this->client->id]);
        Visit::create(['subscription_id' => $sub->id, 'scheduled_date' => '2026-09-01', 'status' => 'pending']);

        $this->actingAs($this->client, 'sanctum')
            ->deleteJson('/api/subscriptions/'.$sub->id.'/purge')
            ->assertStatus(403);

        $this->assertDatabaseHas('subscriptions', ['id' => $sub->id]);
    }
}
