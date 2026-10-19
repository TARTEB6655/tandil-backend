<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProfile;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrderStatisticsFulfillmentDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->token = $this->admin->createToken('admin-fulfillment-test')->plainTextToken;
    }

    private function assignRoleIfAvailable(User $user, string $roleName): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($roleName);
                }
            }
        } catch (\Throwable) {
        }
    }

    public function test_order_statistics_orders_include_vendor_and_supervisor_details(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Ali']);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'name' => 'Vendor Shop']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Garden LLC',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $order = Order::factory()->create([
            'order_status' => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
        ]);

        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => 'confirmed',
            'product_title' => 'Palm fertilizer',
            'subtotal' => 100,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 100,
            'commission_amount' => 10,
        ]);

        Visit::factory()->create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'supervisor_id' => $supervisor->id,
            'status' => 'assigned',
        ]);

        $response = $this->getJson('/api/admin/dashboard/order-statistics/orders?limit=5', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();
        $first = collect($response->json('data.total_orders.orders'))->firstWhere('id', $order->id);
        $this->assertNotNull($first);
        $this->assertSame('Green Garden LLC', $first['vendors'][0]['vendor_name']);
        $this->assertSame('Supervisor Ali', $first['supervisors'][0]['name']);
        $this->assertSame('Supervisor Ali', $first['contractors'][0]['name']);
        $this->assertSame('Supervisor Ali', $first['service_visits'][0]['supervisor']['name']);
    }
}
