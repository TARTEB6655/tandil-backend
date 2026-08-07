<?php

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\Visit;
use App\Services\SubscriptionPaymentStripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MembershipPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $otherClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client', 'name' => 'Membership Client']);
        $this->assignRoleIfAvailable($this->client, 'client');

        $this->otherClient = User::factory()->create(['role' => 'client', 'name' => 'Other Client']);
        $this->assignRoleIfAvailable($this->otherClient, 'client');
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
        Setting::set('stripe_test_secret_key', 'sk_test_membership_1234567890', 'text', 'payment');
        Setting::set('stripe_test_public_key', 'pk_test_membership_1234567890', 'text', 'payment');
        Setting::set('stripe_mode', 'test', 'text', 'payment');
        Setting::set('stripe_enabled', '1', 'text', 'payment');
    }

    private function makeSubscription(array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'client_id' => $this->client->id,
            'plan' => '6_month',
            'plan_name' => 'VIP 6 Months',
            'start_date' => '2026-02-09',
            'end_date' => '2027-02-09',
            'amount' => 2900.00,
            'payment_status' => 'paid',
            'completed_visits' => 6,
        ], $overrides));
    }

    public function test_membership_screen_returns_active_membership_and_full_list(): void
    {
        $active = $this->makeSubscription([
            'plan' => '6_month',
            'start_date' => '2026-08-05',
            'end_date' => '2027-02-05',
            'amount' => 2900.00,
            'payment_status' => 'paid',
        ]);
        $expired = $this->makeSubscription([
            'plan' => '3_month',
            'start_date' => '2025-01-01',
            'end_date' => '2025-04-01',
            'amount' => 1450.00,
            'payment_status' => 'paid',
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/client/memberships/screen')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.active_membership.current_membership.subscription_id', $active->id)
            ->assertJsonPath('data.active_membership.current_membership.plan', '6_month')
            ->assertJsonPath('data.active_membership.renew_price', 2900);

        $upgradePlans = collect($res->json('data.active_membership.upgrade_plans'))->pluck('plan')->all();
        $this->assertSame(['12_month'], $upgradePlans);

        $membershipIds = collect($res->json('data.memberships'))->pluck('id')->all();
        $this->assertContains($active->id, $membershipIds);
        $this->assertContains($expired->id, $membershipIds);
        $this->assertCount(2, $membershipIds, 'memberships list should include every subscription for the client, active or not');
    }

    public function test_membership_screen_active_membership_is_null_when_none_active(): void
    {
        $this->makeSubscription(['end_date' => '2020-01-01', 'payment_status' => 'paid']);
        $this->makeSubscription(['payment_status' => 'pending']);

        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/client/memberships/screen')
            ->assertOk()
            ->assertJsonPath('data.active_membership', null)
            ->assertJsonCount(2, 'data.memberships');
    }

    public function test_membership_screen_only_shows_own_memberships(): void
    {
        $this->makeSubscription(['client_id' => $this->otherClient->id]);
        $this->makeSubscription();

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/client/memberships/screen')
            ->assertOk();

        $this->assertCount(1, $res->json('data.memberships'));
    }

    public function test_upgrade_options_returns_current_membership_and_eligible_plans(): void
    {
        $sub = $this->makeSubscription();

        $res = $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/client/memberships/'.$sub->id.'/upgrade-options')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_membership.plan', '6_month')
            ->assertJsonPath('data.renew_price', 2900);

        $plans = collect($res->json('data.upgrade_plans'))->pluck('plan')->all();
        $this->assertSame(['12_month'], $plans, 'Only plans priced above the current plan should be offered as upgrades');

        $methods = collect($res->json('data.payment_methods'))->pluck('id')->all();
        $this->assertSame(['stripe', 'apple_pay'], $methods);
    }

    public function test_other_client_cannot_view_upgrade_options(): void
    {
        $sub = $this->makeSubscription();

        $this->actingAs($this->otherClient, 'sanctum')
            ->getJson('/api/client/memberships/'.$sub->id.'/upgrade-options')
            ->assertStatus(403);
    }

    public function test_renew_payment_intent_creates_pending_payment(): void
    {
        $this->fakeStripeUsable();
        $sub = $this->makeSubscription();

        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_renew_1',
                'client_secret' => 'pi_test_renew_1_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/'.$sub->id.'/renew/payment-intent', ['payment_method' => 'stripe'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_intent_id', 'pi_test_renew_1')
            ->assertJsonPath('data.action', 'renew')
            ->assertJsonPath('data.from_plan', '6_month')
            ->assertJsonPath('data.to_plan', '6_month')
            ->assertJsonPath('data.amount', 2900);

        $this->assertNotEmpty($res->json('data.publishable_key'));
        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $sub->id,
            'client_id' => $this->client->id,
            'action' => 'renew',
            'stripe_payment_intent_id' => 'pi_test_renew_1',
            'status' => 'pending',
            'amount' => 2900,
        ]);
    }

    public function test_upgrade_payment_intent_rejects_same_plan(): void
    {
        $this->fakeStripeUsable();
        $sub = $this->makeSubscription();

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/'.$sub->id.'/upgrade/payment-intent', [
                'target_plan' => '6_month',
                'payment_method' => 'stripe',
            ])
            ->assertStatus(422);
    }

    public function test_upgrade_payment_intent_creates_pending_payment_for_target_plan(): void
    {
        $this->fakeStripeUsable();
        $sub = $this->makeSubscription();

        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_test_upgrade_1',
                'client_secret' => 'pi_test_upgrade_1_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/'.$sub->id.'/upgrade/payment-intent', [
                'target_plan' => '12_month',
                'payment_method' => 'apple_pay',
            ])
            ->assertOk()
            ->assertJsonPath('data.action', 'upgrade')
            ->assertJsonPath('data.from_plan', '6_month')
            ->assertJsonPath('data.to_plan', '12_month')
            ->assertJsonPath('data.amount', 5500)
            ->assertJsonPath('data.payment_method', 'apple_pay');

        $this->assertDatabaseHas('subscription_payments', [
            'subscription_id' => $sub->id,
            'action' => 'upgrade',
            'from_plan' => '6_month',
            'to_plan' => '12_month',
            'stripe_payment_intent_id' => 'pi_test_upgrade_1',
            'amount' => 5500,
        ]);
    }

    public function test_confirm_renew_extends_end_date_and_generates_visits(): void
    {
        $this->fakeStripeUsable();
        $sub = $this->makeSubscription(['end_date' => '2027-02-09']);

        SubscriptionPayment::create([
            'subscription_id' => $sub->id,
            'client_id' => $this->client->id,
            'action' => 'renew',
            'from_plan' => '6_month',
            'to_plan' => '6_month',
            'amount' => 2900,
            'amount_minor' => 290000,
            'currency' => 'aed',
            'stripe_payment_intent_id' => 'pi_confirm_renew',
            'status' => 'pending',
            'payment_method' => 'stripe',
            'meta' => ['purpose' => SubscriptionPaymentStripeService::PURPOSE],
        ]);

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_confirm_renew' => Http::response([
                'id' => 'pi_confirm_renew',
                'status' => 'succeeded',
                'metadata' => [
                    'purpose' => SubscriptionPaymentStripeService::PURPOSE,
                    'user_id' => (string) $this->client->id,
                ],
            ], 200),
        ]);

        $res = $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/payment/confirm', ['payment_intent_id' => 'pi_confirm_renew'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.action', 'renew')
            ->assertJsonPath('data.status', 'succeeded')
            ->assertJsonPath('data.subscription.payment_status', 'paid')
            ->assertJsonPath('data.subscription.payment_reference', 'pi_confirm_renew');

        $fresh = $sub->fresh();
        $this->assertSame('2027-08-09', $fresh->end_date->toDateString(), 'End date should extend by one more 6-month cycle');
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertDatabaseHas('subscription_payments', [
            'stripe_payment_intent_id' => 'pi_confirm_renew',
            'status' => 'succeeded',
        ]);
        $this->assertTrue(
            Visit::where('subscription_id', $sub->id)->where('scheduled_date', '>=', '2027-02-10')->exists(),
            'Renew should generate visits for the new cycle'
        );
        $this->assertNotNull($res->json('data.subscription'));
    }

    public function test_confirm_upgrade_changes_plan_and_amount(): void
    {
        $this->fakeStripeUsable();
        $sub = $this->makeSubscription();

        SubscriptionPayment::create([
            'subscription_id' => $sub->id,
            'client_id' => $this->client->id,
            'action' => 'upgrade',
            'from_plan' => '6_month',
            'to_plan' => '12_month',
            'amount' => 5500,
            'amount_minor' => 550000,
            'currency' => 'aed',
            'stripe_payment_intent_id' => 'pi_confirm_upgrade',
            'status' => 'pending',
            'payment_method' => 'stripe',
            'meta' => ['purpose' => SubscriptionPaymentStripeService::PURPOSE],
        ]);

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_confirm_upgrade' => Http::response([
                'id' => 'pi_confirm_upgrade',
                'status' => 'succeeded',
                'metadata' => [
                    'purpose' => SubscriptionPaymentStripeService::PURPOSE,
                    'user_id' => (string) $this->client->id,
                ],
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/payment/confirm', ['payment_intent_id' => 'pi_confirm_upgrade'])
            ->assertOk()
            ->assertJsonPath('data.action', 'upgrade')
            ->assertJsonPath('data.subscription.plan', '12_month')
            ->assertJsonPath('data.subscription.amount', '5500.00');

        $fresh = $sub->fresh();
        $this->assertSame('12_month', $fresh->plan);
        $this->assertEquals(5500.0, (float) $fresh->amount);
        $this->assertSame('paid', $fresh->payment_status);
    }

    public function test_confirm_rejects_payment_intent_from_other_purpose(): void
    {
        $this->fakeStripeUsable();

        Http::fake([
            'api.stripe.com/v1/payment_intents/pi_wallet' => Http::response([
                'id' => 'pi_wallet',
                'status' => 'succeeded',
                'metadata' => [
                    'purpose' => 'wallet_topup',
                    'user_id' => (string) $this->client->id,
                ],
            ], 200),
        ]);

        $this->actingAs($this->client, 'sanctum')
            ->postJson('/api/client/memberships/payment/confirm', ['payment_intent_id' => 'pi_wallet'])
            ->assertStatus(422);
    }

    public function test_webhook_confirms_membership_payment_idempotently(): void
    {
        $sub = $this->makeSubscription(['end_date' => '2027-02-09']);

        SubscriptionPayment::create([
            'subscription_id' => $sub->id,
            'client_id' => $this->client->id,
            'action' => 'renew',
            'from_plan' => '6_month',
            'to_plan' => '6_month',
            'amount' => 2900,
            'amount_minor' => 290000,
            'currency' => 'aed',
            'stripe_payment_intent_id' => 'pi_webhook_membership',
            'status' => 'pending',
        ]);

        $piPayload = [
            'id' => 'pi_webhook_membership',
            'status' => 'succeeded',
            'metadata' => [
                'purpose' => SubscriptionPaymentStripeService::PURPOSE,
                'user_id' => (string) $this->client->id,
            ],
        ];

        app(SubscriptionPaymentStripeService::class)->fulfillFromWebhookPaymentIntent($piPayload);
        // Call twice to prove idempotency (no double-extension of end_date)
        app(SubscriptionPaymentStripeService::class)->fulfillFromWebhookPaymentIntent($piPayload);

        $fresh = $sub->fresh();
        $this->assertSame('2027-08-09', $fresh->end_date->toDateString());
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame('succeeded', SubscriptionPayment::where('stripe_payment_intent_id', 'pi_webhook_membership')->first()->status);
    }
}
