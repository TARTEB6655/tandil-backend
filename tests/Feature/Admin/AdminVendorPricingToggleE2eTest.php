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
            ->getJson('/api/admin/vendors/pricing?search=E2E')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.company_name', 'E2E Nursery')
            ->assertJsonPath('data.items.0.vendor_name', 'E2E Owner')
            ->assertJsonPath('data.items.0.pricing_configured', false)
            ->assertJsonPath('data.items.0.pricing_status_label', 'No vendor pricing set yet');

        $this->withToken($adminToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'vendor' => ['vendor_id', 'company_name', 'vendor_name'],
                    'service_pricing' => ['pricing_type', 'price', 'show_on_client_products_services', 'toggle_label'],
                    'tree_palm_pricing' => ['show_on_client_products_services', 'price_per_tree', 'toggle_label'],
                ],
            ])
            ->assertJsonPath('data.service_pricing.show_on_client_products_services', true)
            ->assertJsonPath('data.tree_palm_pricing.show_on_client_products_services', false);

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_area_pricing_on_client' => true,
                'show_tree_pricing_on_client' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
                'price_per_tree' => 30,
                'price_per_palm_tree' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_configured', true)
            ->assertJsonPath('data.service_pricing.pricing_type', 'per_m2')
            ->assertJsonPath('data.service_pricing.price', 70)
            ->assertJsonPath('data.service_pricing.show_on_client_products_services', true)
            ->assertJsonPath('data.tree_palm_pricing.show_on_client_products_services', true)
            ->assertJsonPath('data.tree_palm_pricing.price_per_tree', 30);

        $product->refresh();
        $visible = ServiceAreaPricing::productApiFields($product);
        $this->assertSame('per_m2', $visible['pricing_type']);
        $this->assertSame(70.0, (float) $visible['price_per_m2']);
        $this->assertTrue($visible['show_tree_options']);

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_area_pricing_on_client' => false,
                'show_tree_pricing_on_client' => false,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
                'price_per_tree' => 30,
                'price_per_palm_tree' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.service_pricing.show_on_client_products_services', false)
            ->assertJsonPath('data.tree_palm_pricing.show_on_client_products_services', false);

        $hidden = ServiceAreaPricing::productApiFields($product->fresh());
        $this->assertSame('fixed', $hidden['pricing_type']);
        $this->assertNull($hidden['price_per_m2']);
        $this->assertTrue($hidden['area_pricing_hidden_on_client'] ?? false);
        $this->assertFalse($hidden['show_tree_options']);

        $this->withToken($adminToken)
            ->getJson('/api/admin/vendors/pricing?search=E2E')
            ->assertOk()
            ->assertJsonPath('data.items.0.pricing_configured', true)
            ->assertJsonPath('data.items.0.pricing_status_label', null);
    }
}
