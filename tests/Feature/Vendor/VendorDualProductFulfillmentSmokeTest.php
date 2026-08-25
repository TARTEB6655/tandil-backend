<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProfile;
use App\Models\Visit;
use App\Support\OrderPaidSideEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Full smoke coverage for dual catalog + fulfillment:
 * - vendor/admin create simple vs service products
 * - paid product → vendor + OTP track (no supervisor visit)
 * - paid service → supervisor visit + service track
 */
class VendorDualProductFulfillmentSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'vendor', 'client', 'supervisor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_vendor_and_admin_can_manage_both_product_types_and_orders_track_correctly(): void
    {
        Notification::fake();

        ['vendor' => $vendor, 'vendorUser' => $vendorUser, 'admin' => $admin]
            = $this->seedVendorAndAdmin();

        $category = Category::create([
            'name' => 'Smoke Cat',
            'slug' => 'smoke-cat-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 0,
            'tax_percentage' => 0,
        ]);
        $service = Service::create([
            'name' => 'AC Service',
            'slug' => 'ac-service-'.uniqid(),
            'is_active' => true,
            'category_id' => $category->id,
        ]);

        $this->actingAs($vendorUser, 'sanctum');
        // --- Vendor creates simple product (no service_id) ---
        $simpleCreate = $this->postJson('/api/vendor/products', [
            'name' => 'Fresh Fruits Box',
            'price' => 45,
            'stock' => 10,
            'category_id' => $category->id,
        ]);
        $simpleCreate->assertCreated()
            ->assertJsonPath('data.vendor_product.product.name', 'Fresh Fruits Box');
        $simpleProductId = (int) $simpleCreate->json('data.vendor_product.product.id');
        $simpleVpId = (int) $simpleCreate->json('data.vendor_product.id');
        $this->assertSame('product', Product::find($simpleProductId)?->type);
        $this->assertDatabaseMissing('product_service', ['product_id' => $simpleProductId]);

        // --- Vendor creates service product (with service_id) ---
        $serviceCreate = $this->postJson('/api/vendor/products', [
            'name' => 'AC Deep Cleaning',
            'price' => 120,
            'stock' => 50,
            'category_id' => $category->id,
            'service_id' => $service->id,
            'job_duration' => '60 min',
        ]);
        $serviceCreate->assertCreated();
        $serviceProductId = (int) $serviceCreate->json('data.vendor_product.product.id');
        $serviceVpId = (int) $serviceCreate->json('data.vendor_product.id');
        $this->assertSame('service', Product::find($serviceProductId)?->type);
        $this->assertDatabaseHas('product_service', [
            'product_id' => $serviceProductId,
            'service_id' => $service->id,
        ]);

        // --- Admin update both via vendor management ---
        $this->actingAs($admin, 'sanctum');

        $adminUpdate = $this->postJson(
            "/api/admin/vendors/{$vendor->id}/products/{$simpleVpId}",
            [
                'name' => 'Fresh Fruits Box XL',
                'price' => 55,
                'stock' => 8,
                'category_id' => $category->id,
            ]
        );
        $adminUpdate->assertOk()->assertJsonPath('data.product.name', 'Fresh Fruits Box XL');

        $this->postJson(
            "/api/admin/vendors/{$vendor->id}/products/{$serviceVpId}",
            [
                'name' => 'AC Deep Cleaning Pro',
                'price' => 150,
                'stock' => 40,
                'category_id' => $category->id,
                'service_id' => $service->id,
                'job_duration' => '90 min',
            ]
        )->assertOk()->assertJsonPath('data.product.name', 'AC Deep Cleaning Pro');

        $this->assertSame('service', Product::find($serviceProductId)?->fresh()->type);

        // --- Admin can create another simple product for vendor ---
        $adminCreate = $this->postJson(
            "/api/admin/vendors/{$vendor->id}/products",
            [
                'name' => 'Meat Pack',
                'price' => 90,
                'stock' => 5,
                'category_id' => $category->id,
            ]
        );
        $adminCreate->assertCreated();
        $adminSimpleId = (int) $adminCreate->json('data.vendor_product.id');

        // --- Paid SIMPLE product order: vendor path + OTP ---
        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        $simpleOrder = $this->placePaidOrder($client, Product::findOrFail($simpleProductId), 55);
        OrderPaidSideEffects::run($simpleOrder->fresh(['items.product.services']), 'Stripe (smoke)');

        $this->assertNull(Visit::query()->where('order_id', $simpleOrder->id)->first());
        $mapping = VendorOrderMapping::where('order_id', $simpleOrder->id)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($mapping);
        $this->assertSame('pending', $simpleOrder->fresh()->order_status);

        $this->actingAs($client, 'sanctum');
        $track = $this->getJson('/api/orders/'.$simpleOrder->id.'/track');
        $track->assertOk()
            ->assertJsonPath('data.fulfillment_type', 'product')
            ->assertJsonPath('data.tracking_layout', 'horizontal')
            ->assertJsonPath('data.tracking.timeline.0.key', 'pending')
            ->assertJsonPath('data.tracking.timeline.1.key', 'confirmed')
            ->assertJsonPath('data.tracking.timeline.2.key', 'processing')
            ->assertJsonPath('data.tracking.timeline.3.key', 'shipped')
            ->assertJsonPath('data.tracking.timeline.4.key', 'delivered');
        $this->assertCount(5, $track->json('data.tracking.timeline'));
        $this->assertFalse(collect($track->json('data.tracking.timeline'))->contains(fn ($s) => ($s['key'] ?? '') === 'assigned'));

        $this->actingAs($vendorUser, 'sanctum');
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'confirmed'])->assertOk();
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'processing'])->assertOk();
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'shipped'])->assertOk();

        $mapping->refresh();
        $this->assertNotEmpty($mapping->delivery_otp);
        $this->assertSame('shipped', $mapping->order->fresh()->order_status);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/orders/'.$simpleOrder->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.status', 'shipped')
            ->assertJsonPath('data.delivery_otp.code', $mapping->delivery_otp)
            ->assertJsonPath('data.tracking.timeline.3.current', true);

        $this->actingAs($vendorUser, 'sanctum');
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/confirm-delivery', [
            'otp' => $mapping->delivery_otp,
        ])->assertOk()->assertJsonPath('data.order.status', 'delivered');

        $this->assertSame('delivered', $simpleOrder->fresh()->order_status);

        // --- Paid SERVICE product order: supervisor visit + service track ---
        $area = Area::factory()->create([
            'name' => 'Dubai',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');
        $area->supervisors()->attach($supervisor->id);

        $serviceOrder = $this->placePaidOrder($client, Product::findOrFail($serviceProductId), 150);
        OrderPaidSideEffects::run($serviceOrder->fresh(['items.product.services']), 'Stripe (smoke)');

        $visit = Visit::query()->where('order_id', $serviceOrder->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame('processing', $serviceOrder->fresh()->order_status);
        $this->assertNull(
            VendorOrderMapping::where('order_id', $serviceOrder->id)->first(),
            'Service lines must not create vendor fulfillment mappings'
        );

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/orders/'.$serviceOrder->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_type', 'service')
            ->assertJsonPath('data.tracking_layout', 'vertical')
            ->assertJsonPath('data.tracking.timeline.1.key', 'processing')
            ->assertJsonPath('data.tracking.timeline.1.description', 'Waiting for a supervisor to accept the job')
            ->assertJsonPath('data.tracking.timeline.3.key', 'assigned')
            ->assertJsonPath('data.tracking.timeline.4.key', 'in_progress')
            ->assertJsonPath('data.delivery_otp', null);

        // --- Admin delete product ---
        $this->actingAs($admin, 'sanctum');
        $this->deleteJson("/api/admin/vendors/{$vendor->id}/products/{$adminSimpleId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    /**
     * @return array{admin: User, vendor: Vendor, vendorUser: User}
     */
    private function seedVendorAndAdmin(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => Hash::make('password')]);
        $admin->assignRole('admin');

        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Smoke Vendor Co',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
            'phone' => '+971500000111',
        ]);

        return [
            'admin' => $admin,
            'vendor' => $vendor,
            'vendorUser' => $vendorUser,
        ];
    }

    private function placePaidOrder(User $client, Product $product, float $amount): Order
    {
        $order = Order::create([
            'user_id' => $client->id,
            'guest_full_name' => $client->name,
            'guest_email' => $client->email,
            'guest_phone' => '+971500000222',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
            'guest_street_address' => 'Marina Walk',
            'total_amount' => $amount,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'paid_at' => now(),
            'order_status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $amount,
            'subtotal' => $amount,
            'booking_date' => $product->type === 'service' ? now()->addDay()->toDateString() : null,
            'booking_slot' => $product->type === 'service' ? '10:00 AM - 12:00 PM' : null,
        ]);

        return $order;
    }
}
