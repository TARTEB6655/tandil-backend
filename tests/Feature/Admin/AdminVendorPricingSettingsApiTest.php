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

class AdminVendorPricingSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        ServiceTreePricing::clearSchemaCache();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_admin_vendor_pricing_list_and_split_detail_apis(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        $this->withToken($token)
            ->getJson('/api/admin/vendors/pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.detail.service_pricing.endpoint', "/api/admin/vendors/{$vendor->id}/pricing/service");

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing/service")
            ->assertOk()
            ->assertJsonPath('data.screen_section', 'Area-Based Pricing')
            ->assertJsonPath('data.vendor.vendor_id', $vendor->id)
            ->assertJsonPath('data.applies_to', 'vendor_catalog_products')
            ->assertJsonStructure([
                'data' => [
                    'vendor',
                    'show_on_client_products_services',
                    'toggle_label',
                    'toggle_description',
                    'pricing_type',
                    'pricing_type_options',
                    'price',
                    'price_includes',
                    'price_includes_options',
                ],
            ])
            ->assertJsonPath('data.show_on_client_products_services', true);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm")
            ->assertOk()
            ->assertJsonPath('data.screen_section', 'Price per Tree & Palm')
            ->assertJsonStructure([
                'data' => [
                    'vendor',
                    'show_on_client_products_services',
                    'price_per_tree',
                    'price_per_palm_tree',
                    'price_includes',
                    'price_includes_options',
                ],
            ])
            ->assertJsonMissingPath('data.show_tree_options');
    }

    public function test_admin_can_update_service_and_tree_pricing_separately(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/service", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_on_client_products_services' => true,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => false,
                    'delivery' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 70)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonPath('data.price_includes.installation', true)
            ->assertJsonPath('data.show_on_client_products_services', true);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm", [
                'show_on_client_products_services' => true,
                'price_per_tree' => 25,
                'price_per_palm_tree' => 40,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => false,
                    'transportation' => false,
                    'delivery' => false,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonPath('data.price_per_tree', 25)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonMissingPath('data.show_tree_options');

        $this->withToken($token)
            ->getJson('/api/admin/vendors/pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.pricing_configured', true);
    }

    public function test_tree_palm_update_ignores_removed_show_tree_options_body_field(): void
    {
        ['adminToken' => $adminToken, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/tree-palm", [
                'show_on_client_products_services' => true,
                'show_tree_options' => false,
                'price_per_tree' => 50,
                'price_per_palm_tree' => 50,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.show_on_client_products_services', true)
            ->assertJsonMissingPath('data.show_tree_options');
    }

    public function test_admin_multiselect_fixed_price_updates_with_legacy_fixed_body(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        ServiceAreaPricing::saveVendorMultiselect(
            $vendor->id,
            true,
            true,
            300,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/service", [
                'pricing_type' => 'fixed',
                'price' => 350,
                'show_on_client_products_services' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.fixed_price', 350)
            ->assertJsonPath('data.per_m2_price', 70)
            ->assertJsonPath('data.pricing_type', 'multi');
    }

    public function test_admin_multiselect_legacy_price_with_pricing_type_multi_updates_fixed(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        ServiceAreaPricing::saveVendorMultiselect(
            $vendor->id,
            true,
            true,
            300,
            70,
            ServiceAreaPricing::emptyIncludes(),
            true
        );

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing/service", [
                'pricing_type' => 'multi',
                'price' => 400,
                'show_on_client_products_services' => true,
                'price_includes' => ServiceAreaPricing::emptyIncludes(),
            ])
            ->assertOk()
            ->assertJsonPath('data.fixed_price', 400)
            ->assertJsonPath('data.per_m2_price', 70);
    }

    public function test_non_admin_cannot_access_vendor_pricing_apis(): void
    {
        ['vendor' => $vendor] = $this->seedAdminAndVendor();
        $client = User::factory()->create(['role' => 'client']);
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($clientToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing/service")
            ->assertForbidden();
    }

    /**
     * @return array{adminToken: string, vendor: Vendor}
     */
    private function seedAdminAndVendor(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('test', ['admin'])->plainTextToken;

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Acme Nursery',
            'owner_name' => 'Jane Vendor',
            'email' => $user->email,
        ]);

        Service::factory()->create(['vendor_id' => $vendor->id, 'name' => 'Landscaping']);
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'service',
            'name' => 'Garden Service',
            'price' => 100,
            'status' => 'active',
        ]);

        return [
            'adminToken' => $adminToken,
            'vendor' => $vendor->fresh(),
        ];
    }
}
