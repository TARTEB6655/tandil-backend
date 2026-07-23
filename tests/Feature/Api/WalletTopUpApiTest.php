<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\User;
use App\Models\WalletCredit;
use App\Models\WalletTopUp;
use App\Services\WalletTopUpStripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WalletTopUpApiTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create([
            'role' => 'client',
            'name' => 'Wallet Client',
            'wallet_balance' => 137.02,
        ]);
        $this->assignRoleIfAvailable($this->client, 'client');
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

    private function fakeStripeUsable(): void
    {
        Setting::set('stripe_test_secret_key', 'sk_test_wallet_topup_1234567890', 'text', 'payment');
        Setting::set('stripe_test_public_key', 'pk_test_wallet_topup_1234567890', 'text', 'payment');
        Setting::set('stripe_mode', 'test', 'text', 'payment');
        Setting::set('stripe_enabled', '1', 'text', 'payment');
    }

    public function test_options_returns_balance_presets_and_payment_methods(): void
    {
        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/client/wallet/top-up')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_balance', 137.02)
            ->assertJsonPath('data.currency', 'AED')
            ->assertJsonPath('data.presets', [50, 100, 150, 200]);

        $methods = collect($res->json('data.payment_methods'))->pluck('id')->all();
        $this->assertContains('stripe', $methods);
        $this->assertContains('apple_pay', $methods);
    }

    public function test_payment_intent_rejects_amount_below_minimum(): void
    {
        $this->fakeStripeUsable();

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/wallet/top-up/payment-intent', ['amount' => 1])
            ->assertStatus(422);
    }

    public function test_payment_intent_creates_pending_topup_and_returns_sheet_fields(): void
    {
        $this->fakeStripeUsable();

        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_wallet_topup_1',
                'client_secret' => 'pi_test_wallet_topup_1_secret_abc',
                'status' => 'requires_payment_method',
                'amount' => 10000,
                'currency' => 'aed',
            ], 200),
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/wallet/top-up/payment-intent', [
                'amount' => 100,
                'payment_method' => 'stripe',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_intent_id', 'pi_test_wallet_topup_1')
            ->assertJsonPath('data.client_secret', 'pi_test_wallet_topup_1_secret_abc')
            ->assertJsonPath('data.amount', 100)
            ->assertJsonPath('data.available_balance', 137.02)
            ->assertJsonPath('data.new_balance', 237.02)
            ->assertJsonPath('data.purpose', WalletTopUpStripeService::PURPOSE);

        $this->assertNotEmpty($res->json('data.publishable_key'));
        $this->assertDatabaseHas('wallet_topups', [
            'user_id' => $this->client->id,
            'stripe_payment_intent_id' => 'pi_test_wallet_topup_1',
            'status' => 'pending',
            'amount' => 100,
        ]);
    }

    public function test_confirm_credits_wallet_without_creating_shop_order(): void
    {
        $this->fakeStripeUsable();

        WalletTopUp::create([
            'user_id' => $this->client->id,
            'amount' => 100,
            'amount_minor' => 10000,
            'currency' => 'aed',
            'stripe_payment_intent_id' => 'pi_test_wallet_confirm',
            'status' => 'pending',
            'payment_method' => 'stripe',
            'meta' => ['purpose' => WalletTopUpStripeService::PURPOSE],
        ]);

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_test_wallet_confirm' => Http::response([
                'id' => 'pi_test_wallet_confirm',
                'status' => 'succeeded',
                'amount' => 10000,
                'amount_received' => 10000,
                'currency' => 'aed',
                'metadata' => [
                    'purpose' => WalletTopUpStripeService::PURPOSE,
                    'user_id' => (string) $this->client->id,
                    'amount' => '100',
                ],
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/wallet/top-up/confirm', [
                'payment_intent_id' => 'pi_test_wallet_confirm',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.amount_added', 100)
            ->assertJsonPath('data.available_balance', 237.02)
            ->assertJsonPath('data.purpose', WalletTopUpStripeService::PURPOSE);

        $this->assertEquals(237.02, (float) $this->client->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_credits', [
            'user_id' => $this->client->id,
            'reason' => 'top_up',
            'status' => 'active',
            'amount' => 100,
        ]);
        $this->assertDatabaseHas('wallet_topups', [
            'stripe_payment_intent_id' => 'pi_test_wallet_confirm',
            'status' => 'succeeded',
        ]);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_confirm_rejects_shop_payment_intent(): void
    {
        $this->fakeStripeUsable();

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_shop' => Http::response([
                'id' => 'pi_shop',
                'status' => 'succeeded',
                'metadata' => [
                    'purpose' => 'shop_checkout',
                    'user_id' => (string) $this->client->id,
                ],
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/wallet/top-up/confirm', [
                'payment_intent_id' => 'pi_shop',
            ])
            ->assertStatus(422);
    }

    public function test_webhook_credits_wallet_for_topup_purpose(): void
    {
        WalletTopUp::create([
            'user_id' => $this->client->id,
            'amount' => 50,
            'amount_minor' => 5000,
            'currency' => 'aed',
            'stripe_payment_intent_id' => 'pi_webhook_topup',
            'status' => 'pending',
        ]);

        app(WalletTopUpStripeService::class)->fulfillFromWebhookPaymentIntent([
            'id' => 'pi_webhook_topup',
            'status' => 'succeeded',
            'amount' => 5000,
            'amount_received' => 5000,
            'currency' => 'aed',
            'metadata' => [
                'purpose' => WalletTopUpStripeService::PURPOSE,
                'user_id' => (string) $this->client->id,
            ],
        ]);

        $this->assertEquals(187.02, (float) $this->client->fresh()->wallet_balance);
        $this->assertTrue(
            WalletCredit::query()->where('user_id', $this->client->id)->where('reason', 'top_up')->exists()
        );
    }

    public function test_non_client_cannot_access_top_up(): void
    {
        $tech = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($tech, 'technician');

        $this->actingAs($tech, 'sanctum')
            ->getJson('/api/client/wallet/top-up')
            ->assertForbidden();
    }
}
