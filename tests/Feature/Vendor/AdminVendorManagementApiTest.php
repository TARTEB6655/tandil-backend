<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
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
            ->assertJsonStructure([
                'data' => [
                    'metrics',
                    'statistics',
                    'revenue' => ['total_revenue', 'wallet_balance', 'monthly'],
                    'analytics',
                ],
            ]);
    }

    public function test_admin_can_list_vendor_products(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/products")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'stats' => ['total', 'active', 'disabled', 'draft', 'out_of_stock'],
                    'items' => [
                        [
                            'vendor_product_id',
                            'name',
                            'stock',
                            'stock_quantity',
                            'sales',
                            'is_enabled',
                            'is_live',
                        ],
                    ],
                    'pagination',
                ],
            ])
            ->assertJsonPath('data.items.0.stock', 10)
            ->assertJsonPath('data.items.0.stock_quantity', 10)
            ->assertJsonPath('data.stats.total', 1)
            ->assertJsonPath('data.stats.active', 1);
    }

    public function test_admin_can_list_vendor_orders(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/orders")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'stats' => ['total', 'pending', 'processing', 'delivered', 'total_revenue'],
                    'items',
                    'pagination',
                ],
            ])
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.stats.total', 1);
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

    public function test_admin_mobile_management_returns_business_logo_url_only(): void
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
        $this->assertArrayNotHasKey('profile_picture_url', $item);
        $this->assertArrayNotHasKey('profile_url', $item);
    }

    public function test_admin_mobile_management_lists_approved_and_suspended_vendors(): void
    {
        ['adminToken' => $token, 'vendor' => $approvedVendor] = $this->seedVendorWithMetrics();

        $pendingUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $pendingUser->assignRole('vendor');
        $pendingVendor = Vendor::create([
            'user_id' => $pendingUser->id,
            'status' => VendorStatus::Pending->value,
        ]);
        VendorProfile::create([
            'vendor_id' => $pendingVendor->id,
            'business_name' => 'Pending Only Store',
            'owner_name' => 'Pending Owner',
            'email' => $pendingUser->email,
        ]);

        $suspendedUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $suspendedUser->assignRole('vendor');
        $suspendedVendor = Vendor::create([
            'user_id' => $suspendedUser->id,
            'status' => VendorStatus::Suspended->value,
            'suspended_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $suspendedVendor->id,
            'business_name' => 'Suspended Store',
            'owner_name' => 'Suspended Owner',
            'email' => $suspendedUser->email,
        ]);

        $response = $this->withToken($token)
            ->getJson('/api/admin/vendors/management')
            ->assertOk();

        $ids = collect($response->json('data.items'))->pluck('vendor_id')->all();
        $this->assertContains($approvedVendor->id, $ids);
        $this->assertContains($suspendedVendor->id, $ids);
        $this->assertNotContains($pendingVendor->id, $ids);
        $this->assertSame(2, $response->json('data.summary.vendors'));

        $suspendedItem = collect($response->json('data.items'))
            ->firstWhere('vendor_id', $suspendedVendor->id);
        $this->assertSame('suspended', $suspendedItem['status']);
        $this->assertSame('Suspended', $suspendedItem['status_label']);
        $this->assertSame('SUSPENDED', $suspendedItem['status_display']);
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

    public function test_admin_mobile_vendor_detail_returns_business_logo_url_only(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $vendor->profile->update([
            'logo_path' => 'vendors/logos/detail-logo.jpg',
            'profile_picture_path' => 'vendors/profile-pictures/detail-profile.jpg',
        ]);

        $response = $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk();

        $vendorPayload = $response->json('data.vendor');
        $this->assertStringContainsString('vendors/logos/detail-logo.jpg', (string) ($vendorPayload['logo_url'] ?? ''));
        $this->assertArrayNotHasKey('profile_picture_url', $vendorPayload);
        $this->assertArrayNotHasKey('profile_url', $vendorPayload);
    }

    public function test_admin_mobile_vendor_detail_returns_product_image_from_gallery_without_primary(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();
        $product = $vendorProduct->product;
        $product->update(['image' => null]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'gallery-only.jpg',
            'sort_order' => 0,
            'is_primary' => false,
        ]);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath(
                'data.products.items.0.image_url',
                fn ($url) => is_string($url) && str_contains($url, 'gallery-only.jpg')
            );
    }

    public function test_admin_mobile_vendor_detail_returns_products_with_toggle_metadata(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'vendor' => ['id', 'email', 'phone', 'business_name', 'logo_url'],
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
                                'image_url',
                                'actions' => [
                                    'toggle' => ['method', 'endpoint'],
                                    'delete' => ['method', 'endpoint'],
                                ],
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
            ->assertJsonPath('data.product.is_enabled', false);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', true);
    }

    public function test_admin_can_toggle_pending_product_from_mobile_api(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();
        $vendorProduct->update(['approval_status' => 'pending']);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath('data.products.items.0.can_toggle', true)
            ->assertJsonPath('data.products.items.0.is_enabled', false);

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', true);

        $vendorProduct->refresh();
        $this->assertSame('approved', $vendorProduct->approval_status);
    }

    public function test_disabled_product_status_matches_across_toggle_and_detail_api(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', false)
            ->assertJsonPath('data.product.is_live', false)
            ->assertJsonPath('data.product.disabled_by_admin', true)
            ->assertJsonPath('data.product.status', 'inactive')
            ->assertJsonPath('data.product.product_status', 'archived');

        $vendorProduct->refresh();
        $this->assertTrue($vendorProduct->disabled_by_admin);
        $this->assertSame('inactive', $vendorProduct->status);
        $this->assertSame('archived', $vendorProduct->product?->fresh()->status);
        $this->assertFalse($vendorProduct->isAdminLive());

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath('data.summary.enabled_products', 0)
            ->assertJsonPath('data.products.items.0.is_enabled', false)
            ->assertJsonPath('data.products.items.0.disabled_by_admin', true)
            ->assertJsonPath('data.products.items.0.status', 'inactive');
    }

    public function test_toggle_accepts_catalog_product_id(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();

        $this->withToken($token)
            ->postJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->product_id}/toggle")
            ->assertOk()
            ->assertJsonPath('data.product.is_enabled', false)
            ->assertJsonPath('data.product.vendor_product_id', $vendorProduct->id);
    }

    public function test_admin_can_delete_vendor_product_from_mobile_api(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();
        $catalogProduct = $vendorProduct->product;

        $this->withToken($token)
            ->deleteJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.vendor_product_id', $vendorProduct->id)
            ->assertJsonPath('data.product_id', $catalogProduct->id);

        $this->assertNull(VendorProduct::find($vendorProduct->id));
        $this->assertSame('archived', $catalogProduct->fresh()->status);

        $this->withToken($token)
            ->getJson("/api/admin/vendors/{$vendor->id}/management")
            ->assertOk()
            ->assertJsonPath('data.summary.total_products', 0)
            ->assertJsonCount(0, 'data.products.items');
    }

    public function test_delete_accepts_catalog_product_id(): void
    {
        ['adminToken' => $token, 'vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();

        $this->withToken($token)
            ->deleteJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->product_id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true)
            ->assertJsonPath('data.vendor_product_id', $vendorProduct->id);

        $this->assertNull(VendorProduct::find($vendorProduct->id));
    }

    public function test_non_admin_cannot_delete_vendor_product(): void
    {
        ['vendor' => $vendor] = $this->seedVendorWithMetrics();
        $vendorProduct = VendorProduct::where('vendor_id', $vendor->id)->firstOrFail();
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');
        $clientToken = $client->createToken('test', ['client'])->plainTextToken;

        $this->withToken($clientToken)
            ->deleteJson("/api/admin/vendors/{$vendor->id}/products/{$vendorProduct->id}")
            ->assertForbidden();

        $this->assertNotNull(VendorProduct::find($vendorProduct->id));
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
