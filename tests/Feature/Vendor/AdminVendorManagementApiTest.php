<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVendorManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_admin_vendor_list_includes_metrics_per_item(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson('/api/admin/vendors')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'id',
                            'business_name',
                            'metrics' => [
                                'total_products',
                                'active_products',
                                'low_stock_products',
                                'total_orders',
                                'pending_orders',
                                'revenue',
                            ],
                        ],
                    ],
                    'pagination',
                ],
            ])
            ->assertJsonPath('data.items.0.id', $vendor->id)
            ->assertJsonPath('data.items.0.metrics.total_products', 1)
            ->assertJsonPath('data.items.0.metrics.total_orders', 1)
            ->assertJsonPath('data.items.0.metrics.pending_orders', 1)
            ->assertJsonPath('data.items.0.metrics.revenue', 120);
    }

    public function test_admin_vendor_show_includes_metrics(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}")
            ->assertOk()
            ->assertJsonPath('data.metrics.total_products', 1)
            ->assertJsonPath('data.metrics.revenue', 120)
            ->assertJsonStructure(['data' => ['metrics', 'statistics', 'analytics']]);
    }

    public function test_admin_can_list_vendor_products(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/products")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['data' => ['items', 'pagination']]);
    }

    public function test_admin_can_list_vendor_orders(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/orders")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonStructure(['data' => ['items', 'pagination']]);
    }

    public function test_admin_mobile_management_returns_summary_and_vendor_cards(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson('/api/admin/vendors/management')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => ['vendors', 'products', 'revenue', 'revenue_formatted'],
                    'items' => [
                        [
                            'vendor_id',
                            'business_name',
                            'owner_name',
                            'email',
                            'logo_url',
                            'profile_picture_url',
                            'profile_url',
                            'products_count',
                            'active_count',
                            'revenue',
                            'revenue_formatted',
                            'detail' => ['method', 'endpoint'],
                        ],
                    ],
                    'pagination',
                ],
            ])
            ->assertJsonPath('data.items.0.vendor_id', $vendor->id)
            ->assertJsonPath('data.items.0.products_count', 1)
            ->assertJsonPath('data.items.0.revenue', 120);
    }

    public function test_admin_mobile_management_includes_vendor_profile_image_urls(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $vendor->profile->update([
            'logo_path' => 'vendors/logos/test-logo.jpg',
            'profile_picture_path' => 'vendors/profile-pictures/test-profile.jpg',
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/admin/vendors/management')
            ->assertOk();

        $item = $response->json('data.items.0');
        $this->assertStringContainsString('vendors/logos/test-logo.jpg', (string) ($item['logo_url'] ?? ''));
        $this->assertStringContainsString('vendors/profile-pictures/test-profile.jpg', (string) ($item['profile_picture_url'] ?? ''));
        $this->assertStringContainsString('vendors/logos/test-logo.jpg', (string) ($item['profile_url'] ?? ''));
    }

    public function test_admin_mobile_management_supports_search(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson('/api/admin/vendors/management?search=Metrics')
            ->assertOk()
            ->assertJsonPath('data.items.0.business_name', 'Metrics Store');

        $this->withToken($token)
            ->getJson('/api/admin/vendors/management?search=missing-vendor')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_admin_mobile_vendor_detail_returns_products_with_toggle_metadata(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'vendor' => ['id', 'email', 'phone', 'business_name'],
                    'summary' => [
                        'total_revenue',
                        'total_revenue_formatted',
                        'total_products',
                        'enabled_products',
                    ],
                    'products' => [
                        'count',
                        'items' => [
                            [
                                'vendor_product_id',
                                'name',
                                'price_formatted',
                                'stock',
                                'is_enabled',
                                'status_label',
                                'image_url',
                                'actions' => ['toggle' => ['method', 'endpoint']],
                            ],
                        ],
                        'pagination',
                    ],
                ],
            ])
            ->assertJsonPath('data.summary.total_products', 1)
            ->assertJsonPath('data.summary.enabled_products', 1)
            ->assertJsonPath('data.products.items.0.is_enabled', true);
    }

    public function test_admin_can_toggle_vendor_product_from_mobile_api(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', false)
            ->assertJsonPath('data.product.disabled_by_admin', true);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', true)
            ->assertJsonPath('data.product.disabled_by_admin', false);
    }

    /**
     * @return array{adminToken: string, vendor: Vendor}
     */
    private function seedVendorWithMetrics(): array
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
            'business_name' => 'Metrics Store',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $category = Category::create([
            'name' => 'Produce',
            'slug' => 'produce-metrics',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Apples',
            'price' => 20,
            'stock' => 10,
            'status' => 'active',
        ]);

        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'guest_full_name' => 'Customer',
            'guest_email' => 'customer@test.com',
            'total_amount' => 120,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);

        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Pending->value,
            'total_amount' => 120,
            'subtotal' => 120,
        ]);

        return [
            'adminToken' => $adminToken,
            'vendor' => $vendor->fresh(),
        ];
    }
}
