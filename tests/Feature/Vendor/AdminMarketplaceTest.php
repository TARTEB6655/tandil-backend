<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\MarketplaceSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_view_marketplace_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('admin.marketplace.dashboard'))
            ->assertOk()
            ->assertSee('Marketplace Control Center');
    }

    public function test_admin_marketplace_analytics_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/marketplace/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['overview', 'top_vendors']]);
    }

    public function test_marketplace_settings_persist(): void
    {
        MarketplaceSettings::setCommissionPercent(12.5);
        $this->assertSame(12.5, MarketplaceSettings::commissionPercent());
    }

    public function test_admin_can_permanently_delete_vendor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $user = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $user->id, 'status' => 'pending']);
        VendorProfile::create(['vendor_id' => $vendor->id, 'business_name' => 'X', 'owner_name' => 'Y', 'email' => 'z@test.com']);

        $this->actingAs($admin)
            ->delete(route('admin.vendors.destroy', $vendor), ['confirm' => 'DELETE'])
            ->assertRedirect(route('admin.vendors.index'));

        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }
}
