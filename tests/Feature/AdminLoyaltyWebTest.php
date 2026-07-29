<?php

namespace Tests\Feature;

use App\Models\LoyaltyReward;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminLoyaltyWebTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

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
    }

    public function test_admin_loyalty_pages_load(): void
    {
        $this->actingAs($this->admin)->get(route('admin.loyalty.index'))
            ->assertOk()
            ->assertSee('Loyalty control center')
            ->assertSee('System settings')
            ->assertSee('Rewards');

        $this->actingAs($this->admin)->get(route('admin.loyalty.settings'))
            ->assertOk()
            ->assertSee('Loyalty settings')
            ->assertSee('Points per AED')
            ->assertSee('Shop orders');

        $this->actingAs($this->admin)->get(route('admin.loyalty.rewards'))
            ->assertOk()
            ->assertSee('Rewards');

        $this->actingAs($this->admin)->get(route('admin.loyalty.customers'))
            ->assertOk()
            ->assertSee('Loyalty customers');

        $this->actingAs($this->admin)->get(route('admin.loyalty.campaigns'))
            ->assertOk()
            ->assertSee('Campaigns')
            ->assertSee('New campaign');

        $this->actingAs($this->admin)->get(route('admin.loyalty.reports'))
            ->assertOk()
            ->assertSee('Reports & export', false)
            ->assertSee('Apply filters')
            ->assertSee('Export PDF')
            ->assertSee('Customers with points');
    }

    public function test_admin_can_save_settings_and_create_reward_via_web(): void
    {
        $this->actingAs($this->admin)->post(route('admin.loyalty.settings.save'), [
            'loyalty_system_enabled' => '1',
            'points_per_aed' => 2,
            'eligible_activities' => [
                'shop_orders' => '1',
                'service_orders' => '1',
                'memberships' => '1',
            ],
            'points_expiry_months' => 12,
            'rewards_expiry_months' => 6,
            'cities' => 'Dubai',
            'customer_targeting' => 'all',
            'campaign_periods_only' => '0',
        ])->assertRedirect(route('admin.loyalty.settings'));

        $this->actingAs($this->admin)->post(route('admin.loyalty.rewards.store'), [
            'title' => 'Free delivery',
            'description' => 'Free delivery reward',
            'points_required' => 300,
            'is_active' => '1',
            'expires_at' => '2026-12-31',
            'cities' => 'Abu Dhabi, Dubai',
            'customer_targeting' => 'all',
        ])->assertRedirect(route('admin.loyalty.rewards'));

        $this->assertDatabaseHas('loyalty_rewards', [
            'title' => 'Free delivery',
            'points_required' => 300,
            'is_active' => 1,
        ]);
    }

    public function test_admin_can_adjust_customer_points_via_web(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'loyalty_points_balance' => 100,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.loyalty.customers.adjust', $client->id), [
                'amount' => 50,
                'reason' => 'Bonus',
            ])
            ->assertRedirect();

        $this->assertSame(150, (int) $client->fresh()->loyalty_points_balance);
    }

    public function test_smoke_toggle_reward_and_loyalty_system(): void
    {
        $reward = LoyaltyReward::query()->create([
            'title' => 'Smoke Reward',
            'points_required' => 100,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.loyalty.rewards.toggle', $reward->id), ['is_active' => '0'])
            ->assertRedirect();

        $this->assertFalse((bool) $reward->fresh()->is_active);

        $this->actingAs($this->admin)
            ->post(route('admin.loyalty.toggle'), ['loyalty_system_enabled' => '0'])
            ->assertRedirect();
    }
}
