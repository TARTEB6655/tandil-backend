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

    public function test_admin_vendor_pricing_list_search_and_detail(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        $this->withToken($token)
            ->getJson('/api/admin/vendors/pricing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.screen_title', 'Vendor pricing')
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'vendor_id',
                            'company_name',
                            'vendor_name',
                            'email',
                            'logo_url',
                            'pricing_configured',
                            'pricing_status_label',
                            'detail' => ['method', 'endpoint'],
                        ],
                    ],
                    'pagination',
                ],
            ])
            ->assertJsonPath('data.items.0.vendor_id', $vendor->id)
            ->assertJsonPath('data.items.0.pricing_configured', false)
            ->assertJsonPath('data.items.0.pricing_status_label', 'No vendor pricing set yet');

        $this->withToken($token)
            ->getJson('/api/admin/vendors/pricing?search=Acme')
            ->assertOk()
            ->assertJsonPath('data.items.0.company_name', 'Acme Nursery');

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing")
            ->assertOk()
            ->assertJsonPath('data.screen_title', 'Manage vendor pricing')
            ->assertJsonPath('data.vendor.vendor_id', $vendor->id)
            ->assertJsonPath('data.service_pricing.applies_to', 'vendor_service_products')
            ->assertJsonPath('data.tree_palm_pricing.show_tree_options', false);
    }

    public function test_admin_can_update_vendor_pricing_and_list_reflects_configured(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedAdminAndVendor();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/pricing", [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'show_area_pricing_on_client' => true,
                'show_tree_pricing_on_client' => true,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => false,
                    'delivery' => false,
                ],
                'show_tree_options' => true,
                'price_per_tree' => 25,
                'price_per_palm_tree' => 40,
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_configured', true)
            ->assertJsonPath('data.service_pricing.pricing_type', 'per_m2')
            ->assertJsonPath('data.service_pricing.price', 70)
            ->assertJsonPath('data.tree_palm_pricing.show_tree_options', true)
            ->assertJsonPath('data.tree_palm_pricing.price_per_tree', 25)
            ->assertJsonPath('data.tree_palm_pricing.price_per_palm_tree', 40);

        $this->assertTrue(ServiceAreaPricing::vendorIsConfigured($vendor->id));

        $this->withToken($token)
            ->getJson('/api/admin/vendors/pricing')
            ->assertOk()
            ->assertJsonPath('data.items.0.pricing_configured', true)
            ->assertJsonPath('data.items.0.pricing_status_label', null);
    }

    public function test_non_admin_cannot_access_vendor_pricing_apis(): void
    {
        ['vendor' => $vendor] = $this->seedAdminAndVendor();
        $client = User::factory()->create(['role' => 'client']);
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($clientToken)
            ->getJson('/api/admin/vendors/pricing')
            ->assertForbidden();

        $this->withToken($clientToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/pricing")
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
