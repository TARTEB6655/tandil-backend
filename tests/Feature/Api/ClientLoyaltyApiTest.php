<?php

namespace Tests\Feature\Api;

use App\Models\LoyaltyReward;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientLoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->client = User::factory()->create([
            'role' => 'client',
            'loyalty_points_balance' => 150,
        ]);
        $this->client->assignRole('client');
        $this->token = $this->client->createToken('test', ['client'])->plainTextToken;

        app(LoyaltyService::class)->ensureDefaultRewards();
        app(LoyaltyService::class)->awardPoints($this->client, 50, 'Order #12345 completed');
        $this->client->refresh();
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_loyalty_requires_auth(): void
    {
        $this->getJson('/api/client/loyalty')->assertUnauthorized();
    }

    public function test_loyalty_requires_client_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->getJson('/api/client/loyalty', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->assertForbidden();
    }

    public function test_loyalty_returns_screen_payload_matching_ui(): void
    {
        $response = $this->getJson('/api/client/loyalty', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Loyalty points retrieved.')
            ->assertJsonPath('data.balance', 200)
            ->assertJsonStructure([
                'data' => [
                    'balance',
                    'available_rewards' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'points_required',
                            'can_redeem',
                            'status',
                            'status_label',
                        ],
                    ],
                    'recent_transactions' => [
                        '*' => [
                            'id',
                            'type',
                            'title',
                            'date',
                            'points',
                            'points_display',
                        ],
                    ],
                ],
            ]);

        $rewards = $response->json('data.available_rewards');
        $this->assertGreaterThanOrEqual(4, count($rewards));
        $this->assertSame('Free Cleaning Service', $rewards[0]['title']);
        $this->assertFalse($rewards[0]['can_redeem']);
        $this->assertSame('not_enough_points', $rewards[0]['status']);
        $this->assertSame('Not Enough Points', $rewards[0]['status_label']);

        $express = collect($rewards)->firstWhere('title', 'Express Service');
        $this->assertNotNull($express);
        $this->assertTrue($express['can_redeem']);

        $transactions = $response->json('data.recent_transactions');
        $this->assertCount(1, $transactions);
        $this->assertSame('earn', $transactions[0]['type']);
        $this->assertSame('Order #12345 completed', $transactions[0]['title']);
        $this->assertSame('+50', $transactions[0]['points_display']);
    }

    public function test_user_loyalty_alias_returns_same_payload(): void
    {
        $this->getJson('/api/user/loyalty', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.balance', 200)
            ->assertJsonCount(4, 'data.available_rewards');
    }

    public function test_redeem_reward_success(): void
    {
        $reward = LoyaltyReward::query()->where('title', 'Express Service')->firstOrFail();

        $response = $this->postJson('/api/client/loyalty/rewards/'.$reward->id.'/redeem', [], $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reward redeemed successfully.')
            ->assertJsonPath('data.balance', 100);

        $transactions = $response->json('data.recent_transactions');
        $this->assertSame('redeem', $transactions[0]['type']);
        $this->assertSame('Redeemed Express Service', $transactions[0]['title']);
        $this->assertSame('-100', $transactions[0]['points_display']);

        $this->client->refresh();
        $this->assertSame(100, (int) $this->client->loyalty_points_balance);

        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $this->client->id,
            'type' => LoyaltyTransaction::TYPE_REDEEM,
            'title' => 'Redeemed Express Service',
            'points' => 100,
            'loyalty_reward_id' => $reward->id,
        ]);
    }

    public function test_redeem_reward_fails_when_not_enough_points(): void
    {
        $reward = LoyaltyReward::query()->where('title', 'Free Cleaning Service')->firstOrFail();

        $this->postJson('/api/client/loyalty/rewards/'.$reward->id.'/redeem', [], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Not enough loyalty points to redeem this reward.');
    }

    public function test_redeem_reward_fails_for_missing_reward(): void
    {
        $this->postJson('/api/client/loyalty/rewards/99999/redeem', [], $this->authHeaders())
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_profile_sections_includes_loyalty(): void
    {
        $response = $this->getJson('/api/client/settings/sections', $this->authHeaders());

        $response->assertOk();
        $ids = array_column($response->json('data'), 'id');
        $this->assertContains('loyalty', $ids);
        $this->assertContains('contact_us', $ids);
        $this->assertContains('terms_and_conditions', $ids);
        $this->assertContains('privacy_policy', $ids);
    }
}
