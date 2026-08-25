<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProfile;
use Database\Seeders\VendorDemoOrdersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurgeVendorOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_vendor_orders_keeps_platform_orders(): void
    {
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);

        $category = Category::create([
            'name' => 'Cat',
            'slug' => 'cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('x')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'V',
            'owner_name' => 'O',
            'email' => $vendorUser->email,
        ]);

        $vendorProduct = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Vendor Item',
            'price' => 50,
            'status' => 'active',
            'type' => 'product',
        ]);

        $platformProduct = Product::create([
            'vendor_id' => null,
            'category_id' => $category->id,
            'name' => 'Platform Item',
            'price' => 30,
            'status' => 'active',
            'type' => 'product',
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $vendorOrder = Order::create([
            'user_id' => $client->id,
            'total_amount' => 50,
            'payment_status' => 'paid',
            'order_status' => 'pending',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $vendorOrder->id,
            'product_id' => $vendorProduct->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
        ]);
        VendorOrderMapping::create([
            'order_id' => $vendorOrder->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'subtotal' => 50,
            'total_amount' => 50,
            'commission_amount' => 0,
        ]);

        $demoOrder = Order::create([
            'user_id' => $client->id,
            'total_amount' => 40,
            'payment_status' => 'paid',
            'order_status' => 'pending',
            'special_instructions' => VendorDemoOrdersSeeder::DEMO_MARKER.' demo',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $demoOrder->id,
            'product_id' => $vendorProduct->id,
            'quantity' => 1,
            'price' => 40,
            'subtotal' => 40,
        ]);
        VendorOrderMapping::create([
            'order_id' => $demoOrder->id,
            'vendor_id' => $vendor->id,
            'status' => 'pending',
            'subtotal' => 40,
            'total_amount' => 40,
            'commission_amount' => 0,
        ]);

        $platformOrder = Order::create([
            'user_id' => $client->id,
            'total_amount' => 30,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'paid_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $platformOrder->id,
            'product_id' => $platformProduct->id,
            'quantity' => 1,
            'price' => 30,
            'subtotal' => 30,
        ]);

        $this->artisan('vendor:purge-orders', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseMissing('orders', ['id' => $vendorOrder->id]);
        $this->assertDatabaseMissing('orders', ['id' => $demoOrder->id]);
        $this->assertDatabaseHas('orders', ['id' => $platformOrder->id]);
        $this->assertSame(0, VendorOrderMapping::query()->count());
    }
}
