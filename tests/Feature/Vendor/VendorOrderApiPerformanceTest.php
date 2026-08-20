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
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorOrderApiPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_order_list_show_and_status_update_stay_within_query_budget(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorWithOrders(25);

        $listQueries = $this->countQueries(function () use ($token) {
            $this->withToken($token)->getJson('/api/vendor/orders?per_page=15')->assertOk();
        });

        $showId = VendorOrderMapping::where('vendor_id', $vendor->id)->value('id');
        $showQueries = $this->countQueries(function () use ($token, $showId) {
            $this->withToken($token)->getJson('/api/vendor/orders/'.$showId)->assertOk();
        });

        $updateQueries = $this->countQueries(function () use ($token, $showId) {
            $this->withToken($token)
                ->postJson('/api/vendor/orders/'.$showId.'/status', ['status' => 'confirmed'])
                ->assertOk();
        });

        // Guard rails — catch accidental N+1 (e.g. per-row product/image queries).
        $this->assertLessThanOrEqual(28, $listQueries, "List queries too high: {$listQueries}");
        $this->assertLessThanOrEqual(22, $showQueries, "Show queries too high: {$showQueries}");
        $this->assertLessThanOrEqual(28, $updateQueries, "Update queries too high: {$updateQueries}");
    }

    /**
     * @return array{token: string, vendor: Vendor}
     */
    private function makeVendorWithOrders(int $count): array
    {
        $user = User::factory()->create(['role' => 'vendor']);
        $user->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Perf Vendor',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'name' => 'Perf Product',
            'price' => 20,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client']);

        for ($i = 0; $i < $count; $i++) {
            $order = Order::factory()->create([
                'user_id' => $client->id,
                'package_id' => null,
                'payment_status' => 'paid',
                'order_status' => 'confirmed',
                'total_amount' => 20,
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => 20,
                'subtotal' => 20,
            ]);
            VendorOrderMapping::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'status' => VendorOrderStatus::Pending->value,
                'subtotal' => 20,
                'tax_amount' => 0,
                'shipping_amount' => 0,
                'total_amount' => 20,
                'commission_amount' => 0,
            ]);
        }

        return [
            'token' => $user->createToken('t')->plainTextToken,
            'vendor' => $vendor,
        ];
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        DB::listen(function () use (&$count) {
            $count++;
        });
        $callback();

        return $count;
    }
}
