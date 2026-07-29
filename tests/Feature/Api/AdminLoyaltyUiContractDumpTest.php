<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Full response dump + strict UI field checks for Admin Loyalty APIs.
 */
class AdminLoyaltyUiContractDumpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $this->admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }
        $this->token = $this->admin->createToken('test', ['admin'])->plainTextToken;
    }

    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    private function assertExactKeys(array $actual, array $expectedKeys, string $label): void
    {
        $keys = array_keys($actual);
        sort($keys);
        $expected = $expectedKeys;
        sort($expected);
        $this->assertSame($expected, $keys, $label.' has unexpected or missing keys. Got: '.implode(', ', $keys));
    }

    public function test_all_admin_loyalty_endpoints_match_ui_contract(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'name' => 'Client One',
            'email' => 'client1@test.com',
            'loyalty_points_balance' => 820,
        ]);

        // ---- 1) Dashboard ----
        echo "\n========== 1) GET /api/admin/loyalty ==========\n";
        $dash = $this->getJson('/api/admin/loyalty', $this->headers());
        echo json_encode($dash->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $dash->assertOk()->assertJsonPath('success', true);
        $this->assertExactKeys($dash->json('data'), [
            'loyalty_system_enabled', 'status', 'status_label', 'points_per_aed',
            'activities', 'expiry_months',
        ], 'dashboard');
        $this->assertIsBool($dash->json('data.loyalty_system_enabled'));
        $this->assertArrayNotHasKey('manage', $dash->json('data'));

        // ---- 2) Toggle OFF / ON ----
        echo "\n========== 2) PUT /api/admin/loyalty/toggle false ==========\n";
        $off = $this->putJson('/api/admin/loyalty/toggle', ['loyalty_system_enabled' => false], $this->headers());
        echo json_encode($off->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $off->assertOk()
            ->assertJsonPath('data.loyalty_system_enabled', false)
            ->assertJsonPath('data.status', 'Inactive');
        $this->assertFalse($off->json('data.loyalty_system_enabled'));

        echo "\n========== 3) PUT /api/admin/loyalty/toggle true ==========\n";
        $on = $this->putJson('/api/admin/loyalty/toggle', ['loyalty_system_enabled' => true], $this->headers());
        echo json_encode($on->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $on->assertOk()
            ->assertJsonPath('data.loyalty_system_enabled', true)
            ->assertJsonPath('data.status', 'Active');
        $this->assertTrue($on->json('data.loyalty_system_enabled'));

        // ---- 4) Settings save (checkboxes + toggles) ----
        $settingsBody = [
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

        echo "\n========== 4) PUT /api/admin/loyalty/settings ==========\n";
        $save = $this->putJson('/api/admin/loyalty/settings', $settingsBody, $this->headers());
        echo json_encode($save->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $save->assertOk();
        $this->assertExactKeys($save->json('data'), [
            'loyalty_system_enabled', 'points_per_aed', 'eligible_activities',
            'points_expiry_months', 'rewards_expiry_months', 'cities',
            'customer_targeting', 'customer_targeting_label', 'specific_customer_ids', 'specific_customers',
            'campaign_periods_only',
            'activities_selected', 'status',
        ], 'settings');
        $this->assertExactKeys($save->json('data.eligible_activities'), [
            'shop_orders', 'service_orders', 'memberships',
        ], 'eligible_activities');
        $this->assertTrue($save->json('data.eligible_activities.shop_orders'));
        $this->assertTrue($save->json('data.eligible_activities.service_orders'));
        $this->assertTrue($save->json('data.eligible_activities.memberships'));
        $this->assertFalse($save->json('data.campaign_periods_only'));
        $this->assertTrue($save->json('data.loyalty_system_enabled'));
        $this->assertSame(3, $save->json('data.activities_selected'));

        echo "\n========== 5) GET /api/admin/loyalty/settings ==========\n";
        $getSettings = $this->getJson('/api/admin/loyalty/settings', $this->headers());
        echo json_encode($getSettings->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $getSettings->assertOk()
            ->assertJsonPath('data.cities', 'Abu Dhabi, Dubai')
            ->assertJsonPath('data.customer_targeting', 'all');

        // ---- 5) Rewards ----
        echo "\n========== 6) POST /api/admin/loyalty/rewards ==========\n";
        $reward = $this->postJson('/api/admin/loyalty/rewards', [
            'title' => 'AED 10 wallet credit',
            'description' => 'What the customer gets',
            'points_required' => 500,
            'is_active' => true,
            'expires_at' => '2026-12-31',
            'cities' => 'Abu Dhabi, Dubai',
            'customer_targeting' => 'all',
        ], $this->headers());
        echo json_encode($reward->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $reward->assertCreated();
        $this->assertExactKeys($reward->json('data'), [
            'id', 'title', 'description', 'points_required', 'points_label',
            'is_active', 'status', 'expires_at', 'cities', 'customer_targeting',
            'customer_targeting_label', 'specific_customer_ids', 'specific_customers',
        ], 'reward');
        $this->assertTrue($reward->json('data.is_active'));
        $this->assertSame('Active', $reward->json('data.status'));
        $this->assertSame('all', $reward->json('data.customer_targeting'));
        $this->assertSame('All customers', $reward->json('data.customer_targeting_label'));
        $this->assertSame([], $reward->json('data.specific_customer_ids'));
        $this->assertSame([], $reward->json('data.specific_customers'));
        $rewardId = (int) $reward->json('data.id');

        echo "\n========== 7) GET /api/admin/loyalty/rewards ==========\n";
        $listRewards = $this->getJson('/api/admin/loyalty/rewards', $this->headers());
        echo json_encode($listRewards->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $listRewards->assertOk();
        $this->assertExactKeys($listRewards->json('data'), ['summary', 'rewards'], 'rewards index');
        $this->assertExactKeys($listRewards->json('data.summary'), ['total', 'active', 'starts_at'], 'rewards summary');

        echo "\n========== 8) POST rewards/{id}/toggle is_active=false ==========\n";
        $toggleOff = $this->postJson("/api/admin/loyalty/rewards/{$rewardId}/toggle", ['is_active' => false], $this->headers());
        echo json_encode($toggleOff->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $toggleOff->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.status', 'Inactive');
        $this->assertFalse($toggleOff->json('data.is_active'));

        echo "\n========== 9) POST rewards/{id}/toggle is_active=true ==========\n";
        $toggleOn = $this->postJson("/api/admin/loyalty/rewards/{$rewardId}/toggle", ['is_active' => true], $this->headers());
        echo json_encode($toggleOn->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $toggleOn->assertOk()
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.status', 'Active');
        $this->assertTrue($toggleOn->json('data.is_active'));

        // ---- 6) Customers ----
        echo "\n========== 10) GET /api/admin/loyalty/customers ==========\n";
        $customers = $this->getJson('/api/admin/loyalty/customers?search=Client', $this->headers());
        echo json_encode($customers->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $customers->assertOk();
        $this->assertExactKeys($customers->json('data'), ['summary', 'customers'], 'customers index');
        $this->assertExactKeys($customers->json('data.summary'), ['visible', 'points_pool', 'holders'], 'customers summary');
        $this->assertExactKeys($customers->json('data.customers.0'), [
            'id', 'name', 'email', 'city', 'points',
        ], 'customer row');

        echo "\n========== 11) GET /api/admin/loyalty/customers/{id} ==========\n";
        $points = $this->getJson('/api/admin/loyalty/customers/'.$client->id, $this->headers());
        echo json_encode($points->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $points->assertOk();
        $this->assertExactKeys($points->json('data'), [
            'name', 'email', 'city', 'balance', 'earned', 'redeemed', 'history',
        ], 'customer points');

        echo "\n========== 12) POST customers/{id}/adjust +50 ==========\n";
        $adj = $this->postJson('/api/admin/loyalty/customers/'.$client->id.'/adjust', [
            'amount' => 50,
            'reason' => 'Manual credit test',
        ], $this->headers());
        echo json_encode($adj->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $adj->assertOk()
            ->assertJsonPath('data.balance', 870)
            ->assertJsonPath('data.history.0.points_display', '+50');
        $this->assertExactKeys($adj->json('data.history.0'), [
            'id', 'title', 'type', 'type_label', 'datetime', 'points_display',
        ], 'history item');

        echo "\n========== 13) POST customers/{id}/adjust -20 ==========\n";
        $deduct = $this->postJson('/api/admin/loyalty/customers/'.$client->id.'/adjust', [
            'amount' => -20,
            'reason' => 'Manual deduction',
        ], $this->headers());
        echo json_encode($deduct->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $deduct->assertOk()
            ->assertJsonPath('data.balance', 850)
            ->assertJsonPath('data.history.0.points_display', '-20');

        // ---- 7) Campaigns ----
        echo "\n========== 14) POST /api/admin/loyalty/campaigns ==========\n";
        $campaign = $this->postJson('/api/admin/loyalty/campaigns', [
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
            'notes' => 'Optional internal notes',
            'is_enabled' => true,
        ], $this->headers());
        echo json_encode($campaign->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $campaign->assertCreated();
        $this->assertExactKeys($campaign->json('data'), [
            'id', 'title', 'multiplier', 'boost_label', 'start_date', 'end_date',
            'date_range', 'cities', 'customer_targeting', 'customer_targeting_label',
            'specific_customer_ids', 'specific_customers', 'eligible_activities',
            'notes', 'is_enabled', 'status',
        ], 'campaign');
        $this->assertTrue($campaign->json('data.is_enabled'));
        $this->assertSame('all', $campaign->json('data.customer_targeting'));
        $this->assertSame('All customers', $campaign->json('data.customer_targeting_label'));
        $this->assertSame([], $campaign->json('data.specific_customer_ids'));
        $this->assertSame([], $campaign->json('data.specific_customers'));
        $this->assertTrue($campaign->json('data.eligible_activities.shop_orders'));
        $this->assertFalse($campaign->json('data.eligible_activities.memberships'));
        $campaignId = (int) $campaign->json('data.id');

        echo "\n========== 15) GET /api/admin/loyalty/campaigns ==========\n";
        $listCamps = $this->getJson('/api/admin/loyalty/campaigns', $this->headers());
        echo json_encode($listCamps->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $listCamps->assertOk();
        $this->assertExactKeys($listCamps->json('data'), ['summary', 'campaigns'], 'campaigns index');
        $this->assertExactKeys($listCamps->json('data.summary'), ['total', 'live', 'top_boost'], 'campaigns summary');

        echo "\n========== 16) POST campaigns/{id}/toggle is_enabled=false ==========\n";
        $campOff = $this->postJson("/api/admin/loyalty/campaigns/{$campaignId}/toggle", ['is_enabled' => false], $this->headers());
        echo json_encode($campOff->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $campOff->assertOk()->assertJsonPath('data.is_enabled', false);
        $this->assertFalse($campOff->json('data.is_enabled'));

        echo "\n========== 17) GET /api/admin/loyalty/reports ==========\n";
        $reports = $this->getJson('/api/admin/loyalty/reports?period=month&customer_scope=all', $this->headers());
        echo json_encode($reports->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $reports->assertOk();
        $this->assertExactKeys($reports->json('data'), [
            'health', 'filters', 'summary', 'export',
        ], 'reports');
        $this->assertExactKeys($reports->json('data.health'), [
            'outstanding', 'redeemed', 'campaigns', 'export_ready', 'status_label',
        ], 'reports.health');
        $this->assertExactKeys($reports->json('data.summary'), [
            'customers_with_points', 'points_outstanding', 'points_earned', 'points_redeemed', 'rewards_redeemed', 'active_campaigns',
        ], 'reports.summary');
        $this->assertExactKeys($reports->json('data.export'), [
            'format', 'ready', 'label',
        ], 'reports.export');
        $this->assertSame('pdf', $reports->json('data.export.format'));

        echo "\n========== 18) GET /api/admin/loyalty/export (PDF) ==========\n";
        $export = $this->get('/api/admin/loyalty/export?period=month&customer_scope=all', $this->headers());
        $export->assertOk();
        $pdfBody = $export->getContent();
        echo json_encode([
            'content_type' => $export->headers->get('Content-Type'),
            'is_pdf' => str_starts_with($pdfBody, '%PDF'),
            'bytes' => strlen($pdfBody),
        ], JSON_PRETTY_PRINT)."\n";
        $this->assertStringContainsString('application/pdf', (string) $export->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdfBody);

        // cleanup deletes
        $this->deleteJson("/api/admin/loyalty/rewards/{$rewardId}", [], $this->headers())->assertOk();
        $this->deleteJson("/api/admin/loyalty/campaigns/{$campaignId}", [], $this->headers())->assertOk();
    }
}
