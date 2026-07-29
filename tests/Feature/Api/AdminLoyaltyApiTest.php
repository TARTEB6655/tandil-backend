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
                ],
            ])
            ->assertJsonMissingPath('data.manage');

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
            ->assertJsonPath('data.points_expiry_months', 12)
            ->assertJsonPath('data.rewards_expiry_months', 6)
            ->assertJsonPath('data.cities', 'Abu Dhabi, Dubai')
            ->assertJsonPath('data.customer_targeting', 'all')
            ->assertJsonPath('data.specific_customer_ids', [])
            ->assertJsonPath('data.specific_customers', [])
            ->assertJsonPath('data.campaign_periods_only', false)
            ->assertJsonPath('data.activities_selected', 3)
            ->assertJsonPath('data.status', 'Live')
            ->assertJsonMissingPath('data.pts_per_aed')
            ->assertJsonMissingPath('data.eligible_activities.referrals');

        $clientA = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $clientB = User::factory()->create(['role' => 'client', 'name' => 'Sara Ahmed']);

        $this->putJson('/api/admin/loyalty/settings', [
            'customer_targeting' => 'specific',
            'specific_customer_ids' => [$clientA->id, $clientB->id],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.customer_targeting', 'specific')
            ->assertJsonPath('data.specific_customer_ids.0', $clientA->id)
            ->assertJsonPath('data.specific_customer_ids.1', $clientB->id)
            ->assertJsonPath('data.specific_customers.0', 'Client One')
            ->assertJsonPath('data.specific_customers.1', 'Sara Ahmed');

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
            ->assertJsonPath('data.expires_at', '2026-12-31')
            ->assertJsonPath('data.customer_targeting', 'all')
            ->assertJsonPath('data.specific_customer_ids', [])
            ->assertJsonPath('data.specific_customers', []);

        $id = (int) $create->json('data.id');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Reward Client']);
        $this->putJson("/api/admin/loyalty/rewards/{$id}", [
            'customer_targeting' => 'specific',
            'specific_customer_ids' => [$client->id],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.customer_targeting', 'specific')
            ->assertJsonPath('data.specific_customer_ids.0', $client->id)
            ->assertJsonPath('data.specific_customers.0', 'Reward Client');

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

        $this->putJson('/api/admin/loyalty/customers/'.$client->id.'/adjust', [
            'amount' => -20,
            'reason' => 'Manual deduction',
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.balance', 850)
            ->assertJsonPath('data.history.0.points_display', '-20');
    }

    public function test_campaigns_crud_and_toggle(): void
    {
        $clientA = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $clientB = User::factory()->create(['role' => 'client', 'name' => 'Sara Ahmed']);
        $clientC = User::factory()->create(['role' => 'client', 'name' => 'Omar Khan']);

        // UI: Add campaign — All customers, empty cities, Active on, 2x chip
        $create = $this->postJson('/api/admin/loyalty/campaigns', [
            'title' => 'Ramadan Double Points',
            'multiplier' => '2x',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'cities' => '',
            'customer_targeting' => 'all',
            'specific_customer_ids' => [],
            'eligible_activities' => [
                'shop_orders' => true,
                'service_orders' => true,
                'memberships' => false,
                'referrals' => false,
                'reviews' => false,
            ],
            'notes' => 'Optional internal notes',
            'is_active' => true,
        ], $this->headers());

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Ramadan Double Points')
            ->assertJsonPath('data.multiplier', 2)
            ->assertJsonPath('data.boost_label', '2x points')
            ->assertJsonPath('data.cities', 'All cities')
            ->assertJsonPath('data.customer_targeting', 'all')
            ->assertJsonPath('data.customer_targeting_label', 'All customers')
            ->assertJsonPath('data.specific_customer_ids', [])
            ->assertJsonPath('data.specific_customers', [])
            ->assertJsonPath('data.is_enabled', true)
            ->assertJsonPath('data.status', 'Active')
            ->assertJsonPath('data.eligible_activities.shop_orders', true)
            ->assertJsonPath('data.eligible_activities.memberships', false)
            ->assertJsonPath('data.eligible_activities.reviews', false);

        $id = (int) $create->json('data.id');

        // UI: Specific customer chips
        $this->putJson("/api/admin/loyalty/campaigns/{$id}", [
            'customer_targeting' => 'specific',
            'specific_customer_ids' => [$clientA->id, $clientB->id, $clientC->id],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.customer_targeting', 'specific')
            ->assertJsonPath('data.customer_targeting_label', 'Specific customer')
            ->assertJsonPath('data.specific_customer_ids.0', $clientA->id)
            ->assertJsonPath('data.specific_customer_ids.1', $clientB->id)
            ->assertJsonPath('data.specific_customer_ids.2', $clientC->id)
            ->assertJsonPath('data.specific_customers.0', 'Client One')
            ->assertJsonPath('data.specific_customers.1', 'Sara Ahmed')
            ->assertJsonPath('data.specific_customers.2', 'Omar Khan');

        // List card fields match Campaigns screen
        $list = $this->getJson('/api/admin/loyalty/campaigns', $this->headers());
        $list->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.summary.live', 1)
            ->assertJsonPath('data.summary.top_boost', '2x')
            ->assertJsonPath('data.campaigns.0.title', 'Ramadan Double Points')
            ->assertJsonPath('data.campaigns.0.boost_label', '2x points')
            ->assertJsonPath('data.campaigns.0.cities', 'All cities')
            ->assertJsonPath('data.campaigns.0.customer_targeting', 'specific')
            ->assertJsonPath('data.campaigns.0.customer_targeting_label', 'Specific customer')
            ->assertJsonPath('data.campaigns.0.status', 'Active')
            ->assertJsonPath('data.campaigns.0.is_enabled', true);

        // Switch back to All customers
        $this->putJson("/api/admin/loyalty/campaigns/{$id}", [
            'customer_targeting' => 'all',
            'specific_customer_ids' => [],
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.customer_targeting', 'all')
            ->assertJsonPath('data.customer_targeting_label', 'All customers')
            ->assertJsonPath('data.specific_customer_ids', [])
            ->assertJsonPath('data.specific_customers', []);

        $this->postJson("/api/admin/loyalty/campaigns/{$id}/toggle", [
            'is_enabled' => false,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.is_enabled', false)
            ->assertJsonPath('data.status', 'Inactive');

        $this->deleteJson("/api/admin/loyalty/campaigns/{$id}", [], $this->headers())
            ->assertOk();

        $this->assertDatabaseMissing('loyalty_campaigns', ['id' => $id]);
    }

    public function test_reports_and_csv_export_and_auth_guard(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'loyalty_points_balance' => 250,
        ]);

        $reports = $this->getJson('/api/admin/loyalty/reports?period=month&customer_scope=all', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.export.format', 'csv')
            ->assertJsonPath('data.export.label', 'Export CSV')
            ->assertJsonPath('data.filters.period', 'month')
            ->assertJsonPath('data.filters.customer_scope', 'all')
            ->assertJsonStructure([
                'data' => [
                    'health' => ['outstanding', 'redeemed', 'campaigns', 'export_ready', 'status_label'],
                    'filters' => ['customer_scope', 'specific_customer_ids', 'specific_customers', 'period', 'date_from', 'date_to'],
                    'summary' => [
                        'customers_with_points',
                        'points_outstanding',
                        'points_earned',
                        'points_redeemed',
                        'rewards_redeemed',
                        'active_campaigns',
                    ],
                    'export' => ['format', 'ready', 'label'],
                ],
            ]);

        $this->assertSame(250, (int) $reports->json('data.summary.points_outstanding'));

        $csv = $this->get('/api/admin/loyalty/export?period=month&customer_scope=all', $this->headers());
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));
        $body = method_exists($csv, 'streamedContent') ? $csv->streamedContent() : $csv->getContent();
        $this->assertStringContainsString('customer_id', $body);
        $this->assertStringContainsString((string) $client->id, $body);

        $this->getJson('/api/admin/loyalty/export?format=json&period=week', $this->headers())
            ->assertOk()
            ->assertJsonPath('data.export.format', 'csv')
            ->assertJsonPath('data.filters.period', 'week');

        $this->getJson('/api/admin/loyalty/reports?period=specific&date_from=2026-01-01&date_to=2026-01-31&customer_scope=specific&specific_customer_ids[]='.$client->id, $this->headers())
            ->assertOk()
            ->assertJsonPath('data.filters.customer_scope', 'specific')
            ->assertJsonPath('data.filters.period', 'specific')
            ->assertJsonPath('data.filters.date_from', '2026-01-01')
            ->assertJsonPath('data.filters.date_to', '2026-01-31');

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => ''])
            ->getJson('/api/admin/loyalty')
            ->assertUnauthorized();
    }
}
