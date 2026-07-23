<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientWalletAddMoneyWebTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create([
            'role' => 'client',
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

    public function test_add_money_page_loads_for_client(): void
    {
        $this->actingAs($this->client)
            ->get(route('client.wallet.add-money'))
            ->assertOk()
            ->assertSee('Add Money')
            ->assertSee('137.02')
            ->assertSee('Select amount');
    }

    public function test_web_payment_intent_returns_sheet_fields(): void
    {
        Setting::set('stripe_test_secret_key', 'sk_test_wallet_web_1234567890', 'text', 'payment');
        Setting::set('stripe_test_public_key', 'pk_test_wallet_web_1234567890', 'text', 'payment');
        Setting::set('stripe_mode', 'test', 'text', 'payment');
        Setting::set('stripe_enabled', '1', 'text', 'payment');

        Http::fake([
            'api.stripe.com/v1/payment_intents' => Http::response([
                'id' => 'pi_web_wallet_1',
                'client_secret' => 'pi_web_wallet_1_secret',
                'status' => 'requires_payment_method',
                'amount' => 10000,
                'currency' => 'aed',
            ], 200),
        ]);

        $this->actingAs($this->client)
            ->postJson(route('client.wallet.add-money.payment-intent'), [
                'amount' => 100,
                'payment_method' => 'stripe',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_intent_id', 'pi_web_wallet_1')
            ->assertJsonPath('data.client_secret', 'pi_web_wallet_1_secret')
            ->assertJsonPath('data.amount', 100);
    }
}
