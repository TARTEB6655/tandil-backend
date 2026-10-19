<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorServicePricingSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    private Vendor $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('vendor');
        }

        $this->vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $this->vendor->id,
            'business_name' => 'Pricing Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        Service::factory()->create(['vendor_id' => $this->vendor->id, 'name' => 'Paving']);
        $category = Category::factory()->create();
        Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $this->vendor->id,
            'type' => 'service',
            'name' => 'Outdoor Service',
        ]);

        $this->token = $user->createToken('test', ['vendor'])->plainTextToken;
    }

    public function test_vendor_service_pricing_get_and_update(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/service-pricing')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applies_to', 'vendor_service_products');

        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/service-pricing', [
                'pricing_type' => 'per_m2',
                'price' => 7,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => true,
                    'delivery' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 7)
            ->assertJsonPath('data.price_includes.labor', true);
    }

    public function test_vendor_tree_palm_pricing_get_and_update(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/vendor/settings/tree-palm-pricing')
            ->assertOk()
            ->assertJsonPath('data.applies_to', 'vendor_service_products');

        $this->withToken($this->token)
            ->postJson('/api/vendor/settings/tree-palm-pricing', [
                'show_tree_options' => true,
                'price_per_tree' => 50,
                'price_per_palm_tree' => 50,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => true,
                    'delivery' => true,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.show_tree_options', true)
            ->assertJsonPath('data.price_per_tree', 50)
            ->assertJsonPath('data.price_per_palm_tree', 50);
    }
}
