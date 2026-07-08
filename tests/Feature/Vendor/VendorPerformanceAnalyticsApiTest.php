<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
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

class VendorPerformanceAnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
    }

    public function test_approved_vendor_can_load_performance_analytics(): void
    {
        ['token' => $token] = $this->makeVendorWithOrderData();

        $this->withToken($token)->getJson('/api/vendor/analytics/performance?period=month')
            ->assertOk()
            ->assertJsonPath('data.analytics.title', 'Analytics')
            ->assertJsonPath('data.analytics.subtitle', 'Sales, orders & performance')
            ->assertJsonPath('data.analytics.period', 'month')
            ->assertJsonPath('data.analytics.overview.total_products.value', 1)
            ->assertJsonPath('data.analytics.overview.total_orders.value', 1)
            ->assertJsonPath('data.analytics.performance_metrics.conversion_rate.subtitle', 'View to Order ratio')
            ->assertJsonPath('data.analytics.trends.daily_performance.data_points_count', 7)
            ->assertJsonPath('data.analytics.trends.weekly_revenue.data_points_count', 7)
            ->assertJsonCount(4, 'data.analytics.filters')
            ->assertJsonStructure([
                'data' => [
                    'analytics' => [
                        'top_products',
                        'recent_activity',
                        'actions',
                    ],
                ],
            ])
            ->assertJsonPath('data.analytics.actions.0.id', 'export_report')
            ->assertJsonPath('data.analytics.actions.0.available', true)
            ->assertJsonPath('data.analytics.actions.0.path', '/api/vendor/analytics/performance/export');
    }

    public function test_approved_vendor_can_export_performance_analytics_csv(): void
    {
        ['token' => $token] = $this->makeVendorWithOrderData();

        $response = $this->withToken($token)->get('/api/vendor/analytics/performance/export?period=month');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Vendor Performance Analytics Report', $response->streamedContent());
        $this->assertStringContainsString('Organic Cherry Tomatoes', $response->streamedContent());
    }

    public function test_unapproved_vendor_cannot_export_performance_analytics(): void
    {
        ['token' => $token] = $this->makeVendorWithOrderData(VendorStatus::UnderReview);

        $this->withToken($token)->get('/api/vendor/analytics/performance/export')
            ->assertForbidden();
    }

    public function test_unapproved_vendor_cannot_access_performance_analytics(): void
    {
        ['token' => $token] = $this->makeVendorWithOrderData(VendorStatus::UnderReview);

        $this->withToken($token)->getJson('/api/vendor/analytics/performance')
            ->assertForbidden();
    }

    public function test_invalid_period_defaults_to_month(): void
    {
        ['token' => $token] = $this->makeVendorWithOrderData();

        $this->withToken($token)->getJson('/api/vendor/analytics/performance?period=invalid')
            ->assertOk()
            ->assertJsonPath('data.analytics.period', 'month');
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorWithOrderData(VendorStatus $status = VendorStatus::Approved): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'name' => 'Analytics Vendor',
            'email' => 'vendor-analytics@test.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => $status->value,
            'approved_at' => now(),
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Fields Agro Supplies',
            'owner_name' => 'Analytics Vendor',
            'email' => $user->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Abu Dhabi',
            'city' => 'Al Ain',
        ]);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Produce',
            'slug' => 'produce-analytics',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Organic Cherry Tomatoes',
            'price' => 149,
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
            'guest_full_name' => 'Ahmed',
            'guest_email' => 'ahmed@test.com',
            'total_amount' => 149,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 149,
            'subtotal' => 149,
        ]);

        VendorOrderMapping::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 149,
            'commission_amount' => 14.9,
        ]);

        $token = $user->createToken('test', ['vendor'])->plainTextToken;

        return [
            'user' => $user,
            'vendor' => $vendor->fresh('profile'),
            'token' => $token,
        ];
    }
}
