<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorSuspendApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_management_detail_includes_suspend_action_for_approved_vendor(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedApprovedVendorWithProduct();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath('data.vendor.status', 'approved')
            ->assertJsonPath('data.vendor.status_label', 'Approved')
            ->assertJsonPath('data.vendor.account_actions.can_suspend', true)
            ->assertJsonPath('data.vendor.account_actions.can_activate', false)
            ->assertJsonPath(
                'data.vendor.account_actions.suspend.confirmation_message',
                'Are you sure you want to suspend this vendor account?'
            )
            ->assertJsonPath(
                'data.vendor.account_actions.suspend.endpoint',
                "/api/admin/vendors/{$vendor->id}/suspend"
            );
    }

    public function test_admin_can_suspend_vendor_and_blocks_login_and_shop_visibility(): void
    {
        [
            'adminToken' => $adminToken,
            'vendor' => $vendor,
            'vendorUser' => $vendorUser,
            'product' => $product,
            'vendorToken' => $vendorToken,
        ] = $this->seedApprovedVendorWithProduct();

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/suspend", [
                'notes' => 'Policy violation',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor.status', 'suspended');

        $this->assertSame('suspended', $vendor->fresh()->status);
        $this->assertNotNull($vendor->fresh()->suspended_at);

        $this->postJson('/api/vendor/auth/login', [
            'email' => $vendorUser->email,
            'password' => 'secret12',
            'roles' => 'vendor',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->withToken($vendorToken)
            ->getJson('/api/vendor/profile')
            ->assertForbidden();

        $this->getJson("/api/shop/products/{$product->id}")
            ->assertNotFound();

        $this->withToken($adminToken)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath('data.vendor.status', 'suspended')
            ->assertJsonPath('data.vendor.status_label', 'Suspended')
            ->assertJsonPath('data.vendor.account_actions.can_suspend', false)
            ->assertJsonPath('data.vendor.account_actions.can_activate', true)
            ->assertJsonPath(
                'data.vendor.account_actions.activate.label',
                'Reactivate Vendor Account'
            );
    }

    public function test_admin_can_reactivate_suspended_vendor(): void
    {
        [
            'adminToken' => $adminToken,
            'vendor' => $vendor,
            'vendorUser' => $vendorUser,
            'product' => $product,
        ] = $this->seedApprovedVendorWithProduct();

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/suspend")
            ->assertOk();

        $this->withToken($adminToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/activate", [
                'notes' => 'Issue resolved',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.vendor.status', 'approved');

        $this->assertSame('approved', $vendor->fresh()->status);
        $this->assertNull($vendor->fresh()->suspended_at);

        $this->postJson('/api/vendor/auth/login', [
            'email' => $vendorUser->email,
            'password' => 'secret12',
            'roles' => 'vendor',
        ])
            ->assertOk()
            ->assertJsonPath('data.vendor.is_approved', true);

        $this->getJson("/api/shop/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_admin_cannot_suspend_vendor(): void
    {
        ['vendor' => $vendor, 'vendorUser' => $vendorUser] = $this->seedApprovedVendorWithProduct();
        $vendorToken = $vendorUser->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($vendorToken)
            ->postJson("/api/admin/vendors/{$vendor->id}/suspend")
            ->assertForbidden();

        $this->assertSame('approved', $vendor->fresh()->status);
    }

    /**
     * @return array{
     *     adminToken: string,
     *     vendor: Vendor,
     *     vendorUser: User,
     *     product: Product,
     *     vendorToken: string
     * }
     */
    private function seedApprovedVendorWithProduct(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');
        $adminToken = $admin->createToken('test', ['admin'])->plainTextToken;

        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'email' => 'suspend-vendor@test.com',
            'password' => Hash::make('secret12'),
            'status' => 'active',
        ]);
        $vendorUser->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Suspend Test Store',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::create([
            'name' => 'Fruits',
            'slug' => 'fruits-suspend-test',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Fresh Seasonal Fruits Box',
            'price' => 42,
            'stock' => 85,
            'status' => 'active',
        ]);

        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $vendorToken = $vendorUser->createToken('api_vendor', ['vendor'])->plainTextToken;

        return [
            'adminToken' => $adminToken,
            'vendor' => $vendor->fresh(),
            'vendorUser' => $vendorUser,
            'product' => $product,
            'vendorToken' => $vendorToken,
        ];
    }
}
