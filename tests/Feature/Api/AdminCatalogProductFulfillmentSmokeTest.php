<?php

namespace Tests\Feature\Api;

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
use App\Notifications\DeliveryOtpNotification;
use App\Support\OrderPaidSideEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Admin/platform catalog products (vendor_id on simple SKUs) follow the same
 * product vs service fulfillment split as vendor-owned catalog rows.
 */
class AdminCatalogProductFulfillmentSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'vendor', 'client', 'supervisor'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_admin_catalog_simple_and_service_products_use_correct_fulfillment_flows(): void
    {
        Notification::fake();

        ['vendor' => $vendor, 'vendorUser' => $vendorUser] = $this->seedVendor();
        $category = Category::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        // --- Admin creates simple platform product (fulfillment vendor required) ---
        $simpleCreate = $this->withToken($this->adminToken)->postJson('/api/admin/products', [
            'name' => 'Platform Fruit Box',
            'price' => 40,
            'stock' => 20,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
        ]);
        $simpleCreate->assertCreated()->assertJsonPath('data.name', 'Platform Fruit Box');
        $simpleProductId = (int) $simpleCreate->json('data.id');
        $this->assertSame('product', Product::find($simpleProductId)?->type);

        // --- Admin creates service platform product ---
        $serviceCreate = $this->withToken($this->adminToken)->postJson('/api/admin/products', [
            'name' => 'Platform AC Clean',
            'price' => 150,
            'stock' => 10,
            'category_id' => $category->id,
            'service_id' => $service->id,
            'type' => 'service',
            'job_duration' => '60 min',
        ]);
        $serviceCreate->assertCreated()->assertJsonPath('data.name', 'Platform AC Clean');
        $serviceProductId = (int) $serviceCreate->json('data.id');
        $this->assertSame('service', Product::find($serviceProductId)?->type);

        $deleteOnly = $this->withToken($this->adminToken)->postJson('/api/admin/products', [
            'name' => 'Delete Me',
            'price' => 5,
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'type' => 'product',
        ]);
        $deleteOnly->assertCreated();
        $deleteProductId = (int) $deleteOnly->json('data.id');

        // --- Admin update both ---
        $this->withToken($this->adminToken)->putJson("/api/admin/products/{$simpleProductId}", [
            'name' => 'Platform Fruit Box XL',
            'price' => 45,
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'type' => 'product',
        ])->assertOk()->assertJsonPath('data.name', 'Platform Fruit Box XL');

        $this->withToken($this->adminToken)->putJson("/api/admin/products/{$serviceProductId}", [
            'name' => 'Platform AC Clean Pro',
            'price' => 180,
            'category_id' => $category->id,
            'service_id' => $service->id,
            'type' => 'service',
        ])->assertOk()->assertJsonPath('data.name', 'Platform AC Clean Pro');

        $client = User::factory()->create(['role' => 'client']);
        $client->assignRole('client');

        // --- Paid SIMPLE admin-catalog product: vendor path, no supervisor visit ---
        $simpleOrder = $this->placePaidOrder($client, Product::findOrFail($simpleProductId), 45);
        OrderPaidSideEffects::run($simpleOrder->fresh(['items.product.services']), 'Stripe (admin smoke)');

        $this->assertNull(Visit::query()->where('order_id', $simpleOrder->id)->first());
        $mapping = VendorOrderMapping::where('order_id', $simpleOrder->id)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($mapping);
        $this->assertSame('pending', $simpleOrder->fresh()->order_status);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/orders/'.$simpleOrder->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_type', 'product')
            ->assertJsonPath('data.tracking_layout', 'horizontal')
            ->assertJsonPath('data.tracking.timeline.3.key', 'shipped')
            ->assertJsonPath('data.tracking.timeline.1.description', 'Vendor confirmed your order');
        $this->assertFalse(
            collect($this->getJson('/api/orders/'.$simpleOrder->id.'/track')->json('data.tracking.timeline'))
                ->contains(fn ($s) => str_contains(strtolower((string) ($s['description'] ?? '')), 'supervisor'))
        );

        $this->actingAs($vendorUser, 'sanctum');
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'confirmed'])->assertOk();
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'processing'])->assertOk();
        $this->postJson('/api/vendor/orders/'.$mapping->id.'/status', ['status' => 'shipped'])->assertOk();

        $mapping->refresh();
        Notification::assertSentTo($client, DeliveryOtpNotification::class);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/orders/'.$simpleOrder->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.delivery_otp.code', $mapping->delivery_otp)
            ->assertJsonPath('data.delivery_otp.delivery_channel', 'in_app');

        // --- Paid SERVICE admin-catalog product: supervisor visit + service track ---
        $area = Area::factory()->create(['name' => 'Dubai', 'location' => 'Dubai', 'country' => 'UAE', 'is_active' => true]);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $supervisor->assignRole('supervisor');
        $area->supervisors()->attach($supervisor->id);

        $serviceOrder = $this->placePaidOrder($client, Product::findOrFail($serviceProductId), 180);
        OrderPaidSideEffects::run($serviceOrder->fresh(['items.product.services']), 'Stripe (admin smoke)');

        $this->assertNotNull(Visit::query()->where('order_id', $serviceOrder->id)->first());
        $this->assertSame('processing', $serviceOrder->fresh()->order_status);

        $this->actingAs($client, 'sanctum');
        $this->getJson('/api/orders/'.$serviceOrder->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.fulfillment_type', 'service')
            ->assertJsonPath('data.tracking_layout', 'vertical')
            ->assertJsonPath('data.tracking.timeline.1.description', 'Waiting for a supervisor to accept the job')
            ->assertJsonPath('data.delivery_otp', null);

        // --- Admin delete unused simple product ---
        $this->actingAs($this->admin, 'sanctum');
        $this->deleteJson("/api/admin/products/{$deleteProductId}")
            ->assertOk();
    }

    /**
     * @return array{vendor: Vendor, vendorUser: User}
     */
    private function seedVendor(): array
    {
        $vendorUser = User::factory()->create(['role' => 'vendor', 'password' => Hash::make('password')]);
        $vendorUser->assignRole('vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Fulfillment Vendor',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        return ['vendor' => $vendor, 'vendorUser' => $vendorUser];
    }

    private function placePaidOrder(User $client, Product $product, float $amount): Order
    {
        $order = Order::create([
            'user_id' => $client->id,
            'guest_full_name' => $client->name,
            'guest_email' => $client->email,
            'guest_phone' => '+971500000333',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
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
