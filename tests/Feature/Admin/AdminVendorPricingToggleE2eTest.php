<?php

namespace Tests\Feature\Admin;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end: admin vendor pricing APIs + client visibility toggles.
 */
class AdminVendorPricingToggleE2eTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_vendor_pricing_full_flow_and_toggles(): void
    {
        ServiceTreePricing::clearSchemaCache();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('test', ['admin'])->plainTextToken;

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'E2E Nursery',
            'owner_name' => 'E2E Owner',
            'email' => $vendorUser->email,
        ]);

        Service::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Garden']);
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'name' => 'E2E Service',
            'price' => 100,
            'status' => 'active',
        ]);

        $this->withToken($adminToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing/service")
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonPath('data.price_includes_options.0.key', 'materials');

        $this->withToken($adminToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm")
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', false);

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/service", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_on_client_products_services' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 70);

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm", [
                'show_on_client_products_services' => true,
                'price_per_tree' => 30,
                'price_per_palm_tree' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonPath('data.price_per_tree', 30);

        $product->refresh();
        $visible = ServiceAreaPricing::productApiFields($product);
        $this->assertSame('per_m2', $visible['pricing_type']);
        $this->assertTrue($visible['show_tree_options']);

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/service", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_on_client_products_services' => false,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk();

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm", [
                'show_on_client_products_services' => false,
                'price_per_tree' => 30,
                'price_per_palm_tree' => 40,
            ])
            ->assertOk();

        $hidden = ServiceAreaPricing::productApiFields($product->fresh());
        $this->assertFalse($hidden['show_area_pricing_on_client']);
        $this->assertFalse($hidden['show_tree_options']);
    }
}
