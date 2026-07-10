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
            ->assertJsonPath('data.items.0.order_number', 'VND-'.now()->year.'-'.str_pad((string) $mapping->id, 4, '0', STR_PAD_LEFT))
            ->assertJsonPath('data.items.0.status', 'pending')
            ->assertJsonPath('data.items.0.customer.name', 'Ahmed Ali')
            ->assertJsonPath('data.items.0.product.name', 'Fresh Tomatoes')
            ->assertJsonPath('data.items.0.actions.can_confirm', true)
            ->assertJsonPath('data.items.0.actions.primary_action', 'confirm')
            ->assertJsonStructure([
                'data' => [
                    'summary',
                    'items' => [[
                        'id',
                        'order_number',
                        'order_date',
                        'status',
                        'status_label',
                        'customer' => ['name', 'phone', 'location'],
                        'product' => ['name', 'qty', 'price', 'currency'],
                        'actions',
                    ]],
                    'pagination',
                ],
            ]);
    }

    public function test_vendor_order_detail_returns_timeline_and_products(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id)
            ->assertOk()
            ->assertJsonPath('data.order.id', $mapping->id)
            ->assertJsonPath('data.order.status', 'confirmed')
            ->assertJsonPath('data.order.payment_status', 'Paid')
            ->assertJsonPath('data.order.products.0.name', 'Fresh Tomatoes')
            ->assertJsonPath('data.order.order_notes', 'Leave at door')
            ->assertJsonPath('data.order.actions.can_ship', true)
            ->assertJsonStructure([
                'data' => [
                    'order' => [
                        'order_number',
                        'status_timeline',
                        'available_statuses',
                        'customer' => ['name', 'phone', 'email', 'address'],
                        'products',
                        'order_info',
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
            ->assertJsonPath('data.order.tracking_number', 'TRK-'.now()->year.'-'.str_pad((string) $mapping->id, 4, '0', STR_PAD_LEFT));
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

    public function test_vendor_order_detail_includes_document_actions(): void
    {
        ['token' => $token, 'vendor' => $vendor] = $this->makeVendorUser();
        $mapping = $this->seedVendorOrder($vendor, VendorOrderStatus::Confirmed);

        $this->withToken($token)->getJson('/api/vendor/orders/'.$mapping->id)
            ->assertOk()
            ->assertJsonPath('data.order.actions.can_contact_customer', true)
            ->assertJsonPath('data.order.actions.can_print_invoice', true)
            ->assertJsonPath('data.order.actions.can_download_order', true)
            ->assertJsonPath('data.order.actions.invoice_endpoint', '/api/vendor/orders/'.$mapping->id.'/invoice');
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
