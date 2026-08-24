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
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use App\Notifications\VendorNewPaidOrderNotification;
use App\Support\OrderVendorNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPaidOrderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_is_notified_when_order_payment_is_confirmed(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Notify On Pay LLC',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'price' => 50,
            'status' => 'active',
        ]);
        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'payment_reference' => 'pi_test_vendor_notify',
            'paid_at' => now(),
            'order_status' => 'confirmed',
            'subtotal_amount' => 50,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total_amount' => 50,
            'booking_date' => '2026-09-01',
            'booking_slot' => '14:00-16:00',
            'guest_full_name' => $client->name,
            'guest_email' => $client->email,
            'guest_street_address' => 'Alexanderplatz 1',
            'guest_city' => 'Berlin',
            'guest_state' => 'BE',
            'guest_zip_code' => '10178',
            'guest_country' => 'DE',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
            'booking_date' => '2026-09-01',
            'booking_slot' => '14:00-16:00',
        ]);

        $this->assertSame(0, $vendorUser->notifications()->count());

        OrderVendorNotifier::notifyVendorsForPaidOrder($order->fresh('items.product'));

        $vendorUser->refresh();
        $this->assertSame(1, $vendorUser->notifications()->count());
        $this->assertSame(
            VendorNewPaidOrderNotification::class,
            $vendorUser->notifications()->first()->type
        );

        $mapping = VendorOrderMapping::where('order_id', $order->id)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($mapping);
        $this->assertNotNull($mapping->vendor_notified_at);

        $payload = $vendorUser->notifications()->first()->data;
        $this->assertSame('vendor_new_paid_order', $payload['type']);
        $this->assertSame($order->id, $payload['order_id']);
        $this->assertSame($order->publicOrderNumber(), $payload['order_number']);
        $this->assertSame($mapping->id, $payload['vendor_order_id']);
        $this->assertNotEmpty($payload['product_ordered']);
        $this->assertSame($product->name, $payload['product_ordered'][0]['name']);
        $this->assertSame('Berlin', $payload['customer_location']['city']);
        $this->assertSame('2026-09-01', $payload['required_date']);
        $this->assertSame('14:00-16:00', $payload['required_time']);
        $this->assertTrue($payload['payment_confirmation']['confirmed']);
        $this->assertSame('paid', $payload['payment_confirmation']['status']);
        $this->assertSame('/api/vendor/orders/'.$order->id.'/track', $payload['track_endpoint']);
        $this->assertSame('confirmed', $payload['status']);
        $this->assertSame('Confirmed', $payload['current_status']);

        OrderVendorNotifier::notifyVendorsForPaidOrder($order->fresh());
        $this->assertSame(1, $vendorUser->fresh()->notifications()->count());
    }

    public function test_pending_payment_does_not_notify_vendor(): void
    {
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Pending Pay LLC',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'price' => 40,
            'status' => 'active',
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'pending',
            'total_amount' => 40,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 40,
            'subtotal' => 40,
        ]);

        OrderVendorNotifier::notifyVendorsForPaidOrder($order);

        $this->assertSame(0, $vendorUser->fresh()->notifications()->count());
    }
}
