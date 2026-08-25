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

class VendorOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        }
    }

    public function test_vendor_orders_list_returns_mobile_ready_cards(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Pending);

        $response = $this->withToken($token)->getJson('/api/vendor/orders');

        $response->assertOk()
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonPath('data.items.0.id', $mapping->id)
            ->assertJsonPath('data.items.0.order_id', $mapping->order_id)
            ->assertJsonPath('data.items.0.order_number', $mapping->order->publicOrderNumber())
            ->assertJsonPath('data.items.0.status', 'processing')
            ->assertJsonPath('data.items.0.status_label', 'Processing')
            ->assertJsonPath('data.items.0.current_status', 'Processing')
            ->assertJsonPath('data.items.0.vendor_status', 'pending')
            ->assertJsonPath('data.items.0.is_demo', false)
            ->assertJsonPath('data.items.0.customer.name', 'Ahmed Ali')
            ->assertJsonPath('data.items.0.product.name', 'Fresh Tomatoes')
            ->assertJsonPath('data.items.0.actions.can_confirm', true)
            ->assertJsonPath('data.items.0.actions.primary_action', 'confirm')
            ->assertJsonPath('data.items.0.track_endpoint', '/api/vendor/orders/'.$mapping->order_id.'/track')
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'items' => [[
                        'id',
                        'order_id',
                        'order_number',
                        'order_date',
                        'status',
                        'status_label',
                        'current_status',
                        'vendor_status',
                        'is_demo',
                        'customer' => ['name', 'phone', 'location'],
                        'product' => ['name', 'qty', 'price', 'currency'],
                        'actions',
                        'track_endpoint',
                    ]],
                    'pagination',
                ],
            ]);
    }

    public function test_vendor_orders_list_status_follows_shop_order_not_vendor_fulfillment(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Shipped);
        $mapping->order->update(['order_status' => 'processing', 'paid_at' => now()]);

        $this->withToken($token)->getJson('/api/vendor/orders')
            ->assertOk()
            ->assertJsonPath('data.items.0.status', 'processing')
            ->assertJsonPath('data.items.0.status_label', 'Processing')
            ->assertJsonPath('data.items.0.vendor_status', 'shipped')
            ->assertJsonPath('data.items.0.actions.can_mark_delivered', true);
    }

    public function test_vendor_orders_list_accepts_empty_status_query(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Pending);

        $this->withToken($token)
            ->getJson('/api/vendor/orders?status=&per_page=15')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $mapping->id)
            ->assertJsonPath('data.pagination.per_page', 15);
    }

    public function test_vendor_orders_list_rejects_invalid_status(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)
            ->getJson('/api/vendor/orders?status=foo')
            ->assertStatus(422);
    }

    public function test_vendor_order_detail_returns_timeline_and_products(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id)
            ->assertOk()
            ->assertJsonPath('data.order.id', $mapping->id)
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.status_label', 'Confirmed')
            ->assertJsonPath('data.order.status_icon', 'check')
            ->assertJsonPath('data.order.status_color', 'blue')
            ->assertJsonPath('data.order.payment_status', 'Paid')
            ->assertJsonPath('data.order.products.0.name', 'Fresh Tomatoes')
            ->assertJsonPath('data.order.products.0.qty_label', 'Qty 2')
            ->assertJsonPath('data.order.order_notes', 'Leave at door')
            ->assertJsonPath('data.order.actions.can_ship', true)
            ->assertJsonPath('data.order.status_timeline.0.key', 'pending')
            ->assertJsonPath('data.order.status_timeline.0.label', 'Pending')
            ->assertJsonPath('data.order.status_timeline.0.status', 'completed')
            ->assertJsonPath('data.order.status_timeline.1.key', 'confirmed')
            ->assertJsonPath('data.order.status_timeline.1.label', 'Confirmed')
            ->assertJsonPath('data.order.status_timeline.1.current', true)
            ->assertJsonPath('data.order.status_timeline.2.label', 'Processing')
            ->assertJsonPath('data.order.status_options.0.value', 'pending')
            ->assertJsonPath('data.order.status_options.1.value', 'confirmed')
            ->assertJsonPath('data.order.status_options.1.selected', true)
            ->assertJsonPath('data.order.status_options.1.icon', 'check')
            ->assertJsonPath('data.order.status_options.3.enabled', true)
            ->assertJsonPath('data.order.order_info.tracking', '—')
            ->assertJsonPath('data.order.customer.phone_display', '+971500000001')
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'order_number',
                        'status_timeline',
                        'status_options',
                        'available_statuses',
                        'customer' => ['name', 'phone', 'email', 'address', 'phone_display', 'email_display', 'address_display'],
                        'products',
                        'order_info' => ['order_date', 'delivery_date', 'tracking'],
                    ],
                ],
            ]);
    }

    public function test_vendor_can_confirm_pending_order(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Pending);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'confirmed',
            'note' => 'Accepted',
        ])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.status_label', 'Confirmed');
    }

    public function test_vendor_ship_sets_tracking_number_when_missing(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'shipped',
        ])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'shipped')
            ->assertJsonPath('data.order.tracking_number', 'TRK-'.now()->year.'-'.str_pad((string) $mapping->id, 4, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.order.status_options.3.selected', true)
            ->assertJsonPath('data.order.status_options.3.color', 'green')
            ->assertJsonPath('data.order.status_options.4.enabled', true)
            ->assertJsonPath('data.order.order_info.tracking', 'TRK-'.now()->year.'-'.str_pad((string) $mapping->id, 4, '0', STR_PAD_LEFT));
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Pending);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'delivered',
        ])->assertStatus(422);
    }

    public function test_vendor_can_get_customer_contact_actions(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id.'/contact')
            ->assertOk()
            ->assertJsonPath('data.contact.customer.name', 'Ahmed Ali')
            ->assertJsonPath('data.contact.customer.phone', '+971500000001')
            ->assertJsonPath('data.contact.can_contact', true)
            ->assertJsonPath('data.contact.contact_actions.0.type', 'call')
            ->assertJsonStructure([
                'data' => [
                    'contact' => [
                        'order_number',
                        'customer',
                        'contact_actions',
                        'preferred_action',
                    ],
                ],
            ]);
    }

    public function test_vendor_can_track_order(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        // Product-only order: timeline follows vendor fulfillment, not supervisor job copy.
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);
        $mapping->order->update(['order_status' => 'processing', 'paid_at' => now()]);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id.'/track')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $mapping->order_id)
            ->assertJsonPath('data.vendor_order_id', $mapping->id)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.current_status', 'Processing')
            ->assertJsonPath('data.vendor_status', 'confirmed')
            ->assertJsonPath('data.order.products.0.name', 'Fresh Tomatoes')
            ->assertJsonPath('data.tracking.status', 'processing')
            ->assertJsonPath('data.tracking.timeline.0.key', 'pending')
            ->assertJsonPath('data.tracking.timeline.0.label', 'Pending')
            ->assertJsonPath('data.tracking.timeline.0.description', 'Order placed successfully')
            ->assertJsonPath('data.tracking.timeline.0.completed', true)
            ->assertJsonPath('data.tracking.timeline.0.current', false)
            ->assertJsonPath('data.tracking.timeline.1.key', 'processing')
            ->assertJsonPath('data.tracking.timeline.1.description', 'Order sent to the vendor')
            ->assertJsonPath('data.tracking.timeline.1.completed', true)
            ->assertJsonPath('data.tracking.timeline.2.key', 'confirmed')
            ->assertJsonPath('data.tracking.timeline.2.description', 'Vendor confirmed your order')
            ->assertJsonPath('data.tracking.timeline.2.completed', true)
            ->assertJsonPath('data.tracking.timeline.2.current', true)
            ->assertJsonPath('data.tracking.timeline.3.key', 'shipped')
            ->assertJsonPath('data.tracking.timeline.3.completed', false)
            ->assertJsonPath('data.tracking.timeline.4.key', 'delivered')
            ->assertJsonPath('data.tracking.timeline.4.completed', false)
            ->assertJsonStructure([
                'data' => [
                    'order_id',
                    'vendor_order_id',
                    'order_number',
                    'current_status',
                    'vendor_status',
                    'order',
                    'order_summary',
                    'tracking' => [
                        'timeline' => [
                            ['key', 'label', 'icon', 'color', 'description', 'completed', 'current', 'timestamp', 'date'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_vendor_ship_generates_otp_and_confirm_delivery_completes_order(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'delivered',
        ])->assertStatus(422);

        $ship = $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/status', [
            'status' => 'shipped',
        ]);
        $ship->assertOk()->assertJsonPath('data.order.status', 'shipped');

        $mapping->refresh();
        $this->assertNotEmpty($mapping->delivery_otp);
        $this->assertSame('completed', $mapping->order->fresh()->order_status);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/confirm-delivery', [
            'otp' => '000000',
        ])->assertStatus(422);

        $this->withToken($token)->postJson('/api/vendor/orders/'.$mapping->id.'/confirm-delivery', [
            'otp' => $mapping->delivery_otp,
        ])
            ->assertOk()
            ->assertJsonPath('data.order.status', 'delivered');

        $this->assertSame('delivered', $mapping->order->fresh()->order_status);
        $this->assertNotNull($mapping->fresh()->delivery_otp_confirmed_at);
    }

    public function test_vendor_track_product_timeline_follows_vendor_fulfillment(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Shipped);
        $mapping->order->update(['order_status' => 'processing', 'paid_at' => now()]);

        $response = $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->order_id.'/track');
        $response->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.vendor_status', 'shipped')
            ->assertJsonPath('data.tracking.timeline.2.completed', true)
            ->assertJsonPath('data.tracking.timeline.3.key', 'shipped')
            ->assertJsonPath('data.tracking.timeline.3.current', true)
            ->assertJsonPath('data.tracking.timeline.3.completed', true)
            ->assertJsonPath('data.tracking.timeline.4.completed', false);

        $mapping->update(['status' => VendorOrderStatus::Delivered->value]);
        $mapping->order->update(['order_status' => 'delivered']);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->order_id.'/track')
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered')
            ->assertJsonPath('data.current_status', 'Delivered')
            ->assertJsonPath('data.tracking.timeline.4.key', 'delivered')
            ->assertJsonPath('data.tracking.timeline.4.current', true)
            ->assertJsonPath('data.tracking.timeline.4.completed', true);
    }

    public function test_vendor_track_smoke_uses_list_order_id_not_mapping_id(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();

        // Live case: mapping id=6, order_id=58. Force ids to differ.
        Order::create([
            'total_amount' => 1,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);

        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Shipped);
        $this->assertNotSame((int) $mapping->id, (int) $mapping->order_id);
        $mapping->order->update(['order_status' => 'processing', 'paid_at' => now()]);

        $list = $this->withToken($token)->getJson('/api/vendor/orders?status=&per_page=15');
        $list->assertOk();
        $orderId = (int) $list->json('data.items.0.order_id');
        $mappingId = (int) $list->json('data.items.0.id');

        $this->assertSame((int) $mapping->order_id, $orderId);
        $this->assertSame((int) $mapping->id, $mappingId);
        $this->assertNotSame($orderId, $mappingId);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$orderId.'/track')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.vendor_order_id', $mappingId)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.vendor_status', 'shipped')
            ->assertJsonPath('data.tracking.status', 'processing')
            ->assertJsonPath('data.tracking.timeline.3.key', 'shipped')
            ->assertJsonPath('data.tracking.timeline.3.current', true)
            ->assertJsonPath('data.tracking.timeline.3.completed', true);
    }

    public function test_vendor_track_requires_authentication(): void
    {
        $this->getJson('/api/vendor/orders/1/track')->assertStatus(401);
    }

    public function test_vendor_track_returns_404_for_unknown_order(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $this->withToken($token)->getJson('/api/vendor/orders/999999/track')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_vendor_cannot_track_another_vendors_order(): void
    {
        ['vendor' => $owner] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($owner, VendorOrderStatus::Confirmed);
        ['token' => $otherToken] = $this->makeVendorUser();

        $this->withToken($otherToken)->getJson('/api/vendor/orders/'.$mapping->id.'/track')
            ->assertStatus(404);

        $this->withToken($otherToken)->getJson('/api/vendor/orders/'.$mapping->order_id.'/track')
            ->assertStatus(404);
    }

    public function test_vendor_track_cancelled_order_returns_cancelled_timeline(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Cancelled);
        $mapping->update([
            'cancellation_reason' => 'Out of stock',
            'cancelled_at' => now(),
        ]);
        $mapping->order->update(['order_status' => 'cancelled']);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.tracking.status', 'cancelled')
            ->assertJsonPath('data.tracking.cancellation_reason', 'Out of stock')
            ->assertJsonPath('data.tracking.timeline.0.key', 'pending')
            ->assertJsonPath('data.tracking.timeline.1.key', 'cancel_order')
            ->assertJsonPath('data.tracking.timeline.1.label', 'Cancel order')
            ->assertJsonPath('data.tracking.timeline.1.current', true)
            ->assertJsonPath('data.tracking.timeline.1.completed', true);
    }

    public function test_vendor_order_detail_includes_document_actions(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id)
            ->assertOk()
            ->assertJsonPath('data.order.actions.can_contact_customer', true)
            ->assertJsonPath('data.order.actions.can_print_invoice', true)
            ->assertJsonPath('data.order.actions.can_download_order', true)
            ->assertJsonPath('data.order.actions.invoice_endpoint', '/api/vendor/orders/'.$mapping->order_id.'/invoice')
            ->assertJsonPath('data.order.actions.track_endpoint', '/api/vendor/orders/'.$mapping->order_id.'/track');
    }

    public function test_vendor_can_print_invoice_pdf(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $response = $this->withToken($token)->get('/api/vendor/orders/'.$mapping->id.'/invoice');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_vendor_can_download_order_pdf(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $response = $this->withToken($token)->get('/api/vendor/orders/'.$mapping->id.'/download');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', strtolower((string) $response->headers->get('Content-Disposition')));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    private function seedVendorOrder(Vendor $vendor, VendorOrderStatus $status): VendorOrderMapping
    {
        $category = Category::create([
            'name' => 'Produce',
            'slug' => 'produce-orders',
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Fresh Tomatoes',
            'price' => 35,
            'stock' => 20,
            'status' => 'active',
        ]);

        VendorProduct::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $order = Order::create([
            'guest_full_name' => 'Ahmed Ali',
            'guest_email' => 'ahmed@test.com',
            'guest_phone' => '+971500000001',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
            'total_amount' => 35,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'order_status' => 'processing',
            'special_instructions' => 'Leave at door',
            'estimated_arrival' => now()->addDays(3),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 17.5,
            'subtotal' => 35,
        ]);

        return VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => $status->value,
            'total_amount' => 35,
            'subtotal' => 35,
        ]);
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorUser(): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Test Store',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        return [
            'user' => $user,
            'vendor' => $vendor,
            'token' => $user->createToken('test', ['vendor'])->plainTextToken,
        ];
    }
}
