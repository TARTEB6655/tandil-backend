<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\VendorTestUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorMarketplaceE2ETest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $vendorApproved;

    protected User $vendorPending;

    protected User $vendorSuspended;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VendorTestUsersSeeder::class);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::updateOrCreate(
            ['email' => 'admin@tandil.com'],
            ['name' => 'Admin', 'password' => 'password123', 'role' => 'admin', 'status' => 'active']
        );
        $this->admin->syncRoles(['admin']);

        $this->vendorApproved = User::where('email', 'vendor1@test.com')->firstOrFail();
        $this->vendorPending = User::where('email', 'vendor2@test.com')->firstOrFail();
        $this->vendorSuspended = User::where('email', 'vendor3@test.com')->firstOrFail();
    }

    public function test_seeded_vendor_statuses(): void
    {
        $this->assertEquals(VendorStatus::Approved->value, $this->vendorApproved->vendor->status);
        $this->assertEquals(VendorStatus::Pending->value, $this->vendorPending->vendor->status);
        $this->assertEquals(VendorStatus::Suspended->value, $this->vendorSuspended->vendor->status);
    }

    public function test_approved_vendor_can_login_and_access_dashboard_api(): void
    {
        $token = $this->vendorApproved->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendor/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'revenue',
                    'analytics' => [
                        'orders_by_status',
                        'monthly_revenue',
                        'top_products',
                    ],
                ],
            ]);
    }

    public function test_approved_vendor_can_view_web_dashboard(): void
    {
        $this->actingAs($this->vendorApproved)
            ->get(route('vendor.dashboard'))
            ->assertOk()
            ->assertSee('Vendor Dashboard', false)
            ->assertSee('Sales Overview', false);
    }

    public function test_pending_vendor_blocked_from_approved_routes(): void
    {
        $token = $this->vendorPending->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendor/dashboard/stats')
            ->assertStatus(403);
    }

    public function test_suspended_vendor_blocked_from_approved_routes(): void
    {
        $token = $this->vendorSuspended->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/vendor/products')
            ->assertStatus(403);
    }

    public function test_vendor_can_create_and_list_products(): void
    {
        $categoryId = \App\Models\Category::query()->value('id')
            ?? \App\Models\Category::factory()->create(['name' => 'E2E Cat', 'shipping_cost' => 10, 'tax_percentage' => 5])->id;

        $token = $this->vendorApproved->createToken('test', ['vendor'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/vendor/products', [
                'name' => 'E2E Test Melon',
                'category_id' => $categoryId,
                'price' => 22,
                'stock' => 40,
                'status' => 'active',
            ])
            ->assertCreated();

        $this->withToken($token)
            ->getJson('/api/vendor/products')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_vendor_cannot_access_other_vendor_product(): void
    {
        $other = VendorProduct::where('vendor_id', '!=', $this->vendorApproved->vendor->id)->first();
        if ($other === null) {
            $this->markTestSkipped('No other vendor product in seed data.');
        }

        $token = $this->vendorApproved->createToken('test', ['vendor'])->plainTextToken;
        $this->withToken($token)->getJson("/api/vendor/products/{$other->id}")->assertStatus(404);
    }

    public function test_admin_marketplace_analytics_and_vendor_list(): void
    {
        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/marketplace/analytics')
            ->assertOk()
            ->assertJsonStructure(['data' => ['overview', 'top_vendors']]);

        $this->withToken($token)
            ->getJson('/api/admin/vendors')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_approve_pending_vendor(): void
    {
        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;
        $vendor = $this->vendorPending->vendor;

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/approve", ['notes' => 'E2E approve'])
            ->assertOk()
            ->assertJsonPath('data.vendor.status', VendorStatus::Approved->value);
    }

    public function test_admin_can_suspend_and_reactivate_vendor(): void
    {
        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;
        $vendor = $this->vendorApproved->vendor->fresh();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/suspend")
            ->assertOk();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.vendor.status', VendorStatus::Approved->value);
    }

    public function test_admin_vendor_orders_and_status_update(): void
    {
        $mapping = VendorOrderMapping::where('vendor_id', $this->vendorApproved->vendor->id)->first();
        if ($mapping === null) {
            $this->markTestSkipped('No seeded vendor order mapping.');
        }

        $token = $this->admin->createToken('test', ['admin'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/marketplace/orders')
            ->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.marketplace.orders.status', $mapping), [
                'status' => 'confirmed',
                'note' => 'E2E confirmed',
            ])
            ->assertRedirect();

        $this->assertEquals('confirmed', $mapping->fresh()->status);
    }

    public function test_admin_marketplace_web_pages_load(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.marketplace.dashboard'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.marketplace.products.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.marketplace.orders.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.marketplace.inventory.index'))
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(route('admin.marketplace.settings'))
            ->assertOk();
    }

    public function test_vendor_login_via_auth_endpoint(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'vendor1@test.com',
            'password' => 'password123',
            'roles' => 'vendor',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_unauthenticated_vendor_api_returns_401(): void
    {
        $this->getJson('/api/vendor/products')->assertStatus(401);
    }

    public function test_client_role_cannot_access_vendor_products_api(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $token = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($token)->getJson('/api/vendor/products')->assertStatus(403);
    }
}
