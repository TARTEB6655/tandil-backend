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
