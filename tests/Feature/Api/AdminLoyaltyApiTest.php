<?php

namespace Tests\Feature\Api;

use App\Models\LoyaltyCampaign;
use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLoyaltyApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->assignRole($this->admin, 'admin');
        $this->token = $this->admin->createToken('test', ['admin'])->plainTextToken;
    }

    private function assignRole(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                $user->assignRole($role);
            }
        } catch (\Throwable $e) {
            //
        }
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_dashboard_and_toggle_match_ui_fields(): void
    {
        $res = $this->getJson('/api/admin/loyalty', $this->headers());
        $res->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'loyalty_system_enabled',
                    'status',
                    'status_label',
                    'points_per_aed',
                    'activities',
                    'expiry_months',
                    'manage' => [['id', 'title', 'description', 'open_label']],
                ],
            ]);

        $this->putJson('/api/admin/loyalty/toggle', [
            'loyalty_system_enabled' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.loyalty_system_enabled', false)
            ->assertJsonPath('data.status', 'Inactive');

        $this->putJson('/api/admin/loyalty/toggle', [
            'loyalty_system_enabled' => true,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.loyalty_system_enabled', true)
            ->assertJsonPath('data.status', 'Active');
    }

    public function test_settings_save_checkboxes_and_toggles(): void
    {
        $payload = [
            'loyalty_system_enabled' => true,
            'points_per_aed' => 1,
            'eligible_activities' => [
                'shop_orders' => true,
                'service_orders' => true,
                'memberships' => true,
                'referrals' => false,
                'reviews' => false,
            ],
            'points_expiry_months' => 12,
            'rewards_expiry_months' => 6,
            'cities' => 'Abu Dhabi, Dubai',
            'customer_targeting' => 'all',
            'campaign_periods_only' => false,
        ];

        $this->putJson('/api/admin/loyalty/settings', $payload, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.loyalty_system_enabled', true)
            ->assertJsonPath('data.points_per_aed', 1)
            ->assertJsonPath('data.eligible_activities.shop_orders', true)
            ->assertJsonPath('data.eligible_activities.memberships', true)
            ->assertJsonPath('data.eligible_activities.referrals', false)
            ->assertJsonPath('data.points_expiry_months', 12)
            ->assertJsonPath('data.rewards_expiry_months', 6)
            ->assertJsonPath('data.cities', 'Abu Dhabi, Dubai')
            ->assertJsonPath('data.customer_targeting', 'all')
            ->assertJsonPath('data.campaign_periods_only', false)
            ->assertJsonPath('data.activities_selected', 3)
            ->assertJsonPath('data.status', 'Live');

        $this->getJson('/api/admin/loyalty/settings', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.cities', 'Abu Dhabi, Dubai');
    }

    public function test_rewards_crud_and_toggle(): void
    {
        $create = $this->postJson('/api/admin/loyalty/rewards', [
            'title' => 'AED 10 wallet credit',
            'description' => 'Wallet credit reward',
            'points_required' => 500,
            'is_active' => true,
            'expires_at' => '2026-12-31',
            'cities' => 'Abu Dhabi, Dubai',
            'customer_targeting' => 'all',
        ], $this->headers());

        $create->assertCreated()
            ->assertJsonPath('data.title', 'AED 10 wallet credit')
            ->assertJsonPath('data.points_required', 500)
            ->assertJsonPath('data.points_label', '500 pts')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.expires_at', '2026-12-31');

        $id = (int) $create->json('data.id');

        $this->getJson('/api/admin/loyalty/rewards', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.active', 1)
            ->assertJsonPath('data.summary.starts_at', 500);

        $this->postJson("/api/admin/loyalty/rewards/{$id}/toggle", [
            'is_active' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'Inactive');

        $this->putJson("/api/admin/loyalty/rewards/{$id}", [
            'title' => 'Free delivery',
            'points_required' => 300,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.title', 'Free delivery')
            ->assertJsonPath('data.points_required', 300);

        $this->deleteJson("/api/admin/loyalty/rewards/{$id}", [], $this->headers())
            ->assertOk();

        $this->assertDatabaseMissing('loyalty_rewards', ['id' => $id]);
    }

    public function test_customers_list_and_manual_adjust(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'name' => 'Client One',
            'email' => 'client1@test.com',
            'loyalty_points_balance' => 820,
        ]);

        $this->getJson('/api/admin/loyalty/customers?search=Client', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.summary.visible', 1)
            ->assertJsonPath('data.summary.points_pool', 820)
            ->assertJsonPath('data.summary.holders', 1)
            ->assertJsonPath('data.customers.0.name', 'Client One')
            ->assertJsonPath('data.customers.0.points', 820);

        $this->getJson('/api/admin/loyalty/customers/'.$client->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.balance', 820)
            ->assertJsonPath('data.name', 'Client One');

        $this->postJson('/api/admin/loyalty/customers/'.$client->id.'/adjust', [
            'amount' => 50,
            'reason' => 'Manual credit test',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.balance', 870)
            ->assertJsonPath('data.history.0.points_display', '+50');

        $this->postJson('/api/admin/loyalty/customers/'.$client->id.'/adjust', [
            'amount' => -20,
            'reason' => 'Manual deduction',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.balance', 850)
            ->assertJsonPath('data.history.0.points_display', '-20');
    }

    public function test_campaigns_crud_and_toggle(): void
    {
        $create = $this->postJson('/api/admin/loyalty/campaigns', [
            'title' => 'Ramadan Double Points',
            'multiplier' => 2,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'cities' => 'Abu Dhabi, Dubai',
            'customer_targeting' => 'all',
            'eligible_activities' => [
                'shop_orders' => true,
                'service_orders' => true,
                'memberships' => false,
                'referrals' => false,
                'reviews' => false,
            ],
            'notes' => 'Seasonal boost',
            'is_enabled' => true,
        ], $this->headers());

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Ramadan Double Points')
            ->assertJsonPath('data.boost_label', '2x points')
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.eligible_activities.shop_orders', true)
            ->assertJsonPath('data.eligible_activities.memberships', false);

        $id = (int) $create->json('data.id');

        $this->getJson('/api/admin/loyalty/campaigns', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.top_boost', '2x');

        $this->postJson("/api/admin/loyalty/campaigns/{$id}/toggle", [
            'is_enabled' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false);

        $this->deleteJson("/api/admin/loyalty/campaigns/{$id}", [], $this->headers())
            ->assertOk();

        $this->assertDatabaseMissing('loyalty_campaigns', ['id' => $id]);
    }

    public function test_export_and_auth_guard(): void
    {
        $this->getJson('/api/admin/loyalty/export', $this->headers())
            ->assertOk()
            ->assertJsonStructure(['data' => ['generated_at', 'settings', 'rewards', 'customers', 'campaigns']]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => ''])
            ->getJson('/api/admin/loyalty')
            ->assertUnauthorized();
    }
}
