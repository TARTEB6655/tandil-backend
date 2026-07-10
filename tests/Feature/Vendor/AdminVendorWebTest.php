<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_can_access_vendor_management_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create(['user_id' => $vendorUser->id, 'status' => 'approved', 'approved_at' => now()]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Store',
            'owner_name' => 'Owner',
            'email' => 'vendor@test.com',
        ]);

        $this->actingAs($admin)->get(route('admin.vendors.overview'))->assertOk()->assertSee('Vendor Overview');
        $this->actingAs($admin)->get(route('admin.vendors.index'))->assertOk()->assertSee('All Vendors');
        $this->actingAs($admin)->get(route('admin.vendors.pending'))->assertOk();
        $this->actingAs($admin)->get(route('admin.vendors.active'))->assertOk();
        $this->actingAs($admin)->get(route('admin.vendors.insights'))->assertOk()->assertSee('Vendor Analytics');
        $this->actingAs($admin)->get(route('admin.vendors.revenue'))->assertOk()->assertSee('Revenue Management');
        $this->actingAs($admin)->get(route('admin.vendors.show', $vendor))->assertOk()->assertSee('Test Store');
        $this->actingAs($admin)->get(route('admin.vendors.products', $vendor))->assertOk();
        $this->actingAs($admin)->get(route('admin.vendors.orders', $vendor))->assertOk();
        $this->actingAs($admin)->get(route('admin.vendors.activity', $vendor))->assertOk();
    }
}
