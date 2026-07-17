<?php

namespace Tests\Feature\Api;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientLoyaltyAutoEarnTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $this->client = User::factory()->create([
            'role' => 'client',
            'loyalty_points_balance' => 0,
        ]);
        $this->client->assignRole('client');
    }

    public function test_paid_order_automatically_awards_loyalty_points(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->client->id,
            'total_amount' => 50.00,
            'payment_status' => 'pending',
        ]);

        $order->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->client->refresh();

        $this->assertSame(50, (int) $this->client->loyalty_points_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $this->client->id,
            'type' => LoyaltyTransaction::TYPE_EARN,
            'points' => 50,
            'reference_type' => 'order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_order_created_as_paid_awards_points_once(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->client->id,
            'total_amount' => 120.00,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $order->update(['order_status' => 'processing']);

        $this->client->refresh();

        $this->assertSame(120, (int) $this->client->loyalty_points_balance);
        $this->assertSame(1, LoyaltyTransaction::query()
            ->where('user_id', $this->client->id)
            ->where('reference_type', 'order')
            ->where('reference_id', $order->id)
            ->count());
    }

    public function test_guest_paid_order_does_not_award_points(): void
    {
        Order::factory()->create([
            'user_id' => null,
            'total_amount' => 80.00,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->client->refresh();
        $this->assertSame(0, (int) $this->client->loyalty_points_balance);
    }

    public function test_paid_subscription_awards_loyalty_points(): void
    {
        $subscription = Subscription::factory()->create([
            'client_id' => $this->client->id,
            'amount' => 75.00,
            'payment_status' => 'pending',
        ]);

        $subscription->update([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->client->refresh();

        $this->assertSame(75, (int) $this->client->loyalty_points_balance);
        $this->assertDatabaseHas('loyalty_transactions', [
            'user_id' => $this->client->id,
            'reference_type' => 'subscription',
            'reference_id' => $subscription->id,
            'points' => 75,
        ]);
    }

    public function test_loyalty_screen_shows_auto_earned_order_transaction(): void
    {
        $token = $this->client->createToken('test', ['client'])->plainTextToken;

        Order::factory()->create([
            'user_id' => $this->client->id,
            'total_amount' => 50.00,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->getJson('/api/client/loyalty', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.balance', 50)
            ->assertJsonPath('data.recent_transactions.0.type', 'earn')
            ->assertJsonPath('data.recent_transactions.0.points_display', '+50');
    }

    public function test_auto_earn_can_be_disabled_via_setting(): void
    {
        \App\Models\Setting::set('loyalty_auto_earn_enabled', '0', 'text', 'loyalty');

        Order::factory()->create([
            'user_id' => $this->client->id,
            'total_amount' => 50.00,
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->client->refresh();
        $this->assertSame(0, (int) $this->client->loyalty_points_balance);

        app(LoyaltyService::class)->ensureDefaultRewards();
    }
}
