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
use App\Notifications\DeliveryOtpNotification;
use App\Services\Vendor\VendorDeliveryOtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorDeliveryOtpInAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    }

    public function test_shipped_order_sends_delivery_otp_in_app_notification_to_client(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Store',
            'owner_name' => 'Owner',
            'email' => 'v@test.com',
        ]);

        $category = Category::create(['name' => 'Cat', 'slug' => 'cat', 'is_active' => true, 'shipping_cost' => 0, 'tax_percentage' => 0]);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Item', 'price' => 10, 'stock' => 5, 'status' => 'active']);
        VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id, 'status' => 'active', 'approval_status' => 'approved']);

        $order = Order::create([
            'user_id' => $client->id,
            'total_amount' => 10,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'order_status' => 'processing',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $mapping = VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Shipped->value,
            'total_amount' => 10,
            'subtotal' => 10,
        ]);

        $mapping = app(VendorDeliveryOtpService::class)->ensureOtpForShipped(
            $mapping->fresh(['order.user'])
        );

        $this->assertNotEmpty($mapping->delivery_otp);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $client->id,
            'notifiable_type' => User::class,
        ]);

        $notification = $client->fresh()->notifications()->latest()->first();
        $this->assertNotNull($notification);
        $this->assertSame('delivery_otp', $notification->data['type'] ?? null);
        $this->assertSame($mapping->delivery_otp, $notification->data['otp'] ?? null);
        $this->assertSame('in_app', $notification->data['delivery_channel'] ?? null);
        $this->assertSame('/api/orders/'.$order->id.'/track', $notification->data['track_endpoint'] ?? null);
    }

    public function test_vendor_ship_api_sends_in_app_delivery_otp_notification(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Store',
            'owner_name' => 'Owner',
            'email' => 'v@test.com',
        ]);

        $category = Category::create(['name' => 'Cat', 'slug' => 'cat-api', 'is_active' => true, 'shipping_cost' => 0, 'tax_percentage' => 0]);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Item', 'price' => 10, 'stock' => 5, 'status' => 'active']);
        VendorProduct::create(['vendor_id' => $vendor->id, 'product_id' => $product->id, 'status' => 'active', 'approval_status' => 'approved']);

        $order = Order::create([
            'user_id' => $client->id,
            'total_amount' => 10,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'order_status' => 'processing',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $mapping = VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Confirmed->value,
            'total_amount' => 10,
            'subtotal' => 10,
        ]);

        $token = $vendorUser->createToken('vendor')->plainTextToken;
        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'shipped',
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $client->id,
            'notifiable_type' => User::class,
            'type' => DeliveryOtpNotification::class,
        ]);
    }
}
