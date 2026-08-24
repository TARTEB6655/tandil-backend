<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\VendorProduct;
use App\Models\VendorProfile;
use App\Models\Visit;
use App\Notifications\VendorNewPaidOrderNotification;
use App\Support\OrderPaidSideEffects;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorProductPaidOrderAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }
            }
        } catch (\Throwable) {
            //
        }
    }

    public function test_paid_vendor_product_notifies_vendor_and_supervisor_and_list_shows_processing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $areaManager = User::factory()->create(['role' => 'area_manager']);
        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');

        $area = Area::factory()->create([
            'name' => 'Dubai Marina',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $this->assignRoleIfAvailable($vendorUser, 'vendor');
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Vendor Alert Shop',
            'owner_name' => 'Owner',
            'email' => $vendorUser->email,
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => $vendor->id,
            'price' => 80,
            'status' => 'active',
            'job_duration' => '60 minutes',
            'name' => 'AC Deep Clean',
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
            'paid_at' => now(),
            'order_status' => 'pending',
            'total_amount' => 80,
            'guest_full_name' => 'Client One',
            'guest_email' => 'client-one@test.com',
            'guest_phone' => '+971501111111',
            'guest_street_address' => 'Marina Walk',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
            'booking_date' => '2026-09-10',
            'booking_slot' => '10:00 AM - 12:00 PM',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 80,
            'subtotal' => 80,
            'booking_date' => '2026-09-10',
            'booking_slot' => '10:00 AM - 12:00 PM',
        ]);

        $beforeSupervisor = $supervisor->unreadNotifications()->count();
        $beforeAreaManager = $areaManager->unreadNotifications()->count();
        $beforeAdmin = $admin->unreadNotifications()->count();

        OrderPaidSideEffects::run($order->fresh('items.product'), 'Stripe (test)');

        $order->refresh();
        $this->assertSame('processing', (string) $order->order_status);

        $mapping = VendorOrderMapping::where('order_id', $order->id)->where('vendor_id', $vendor->id)->first();
        $this->assertNotNull($mapping);
        $this->assertNotNull($mapping->vendor_notified_at);

        $vendorUser->refresh();
        $this->assertSame(1, $vendorUser->notifications()->count());
        $payload = $vendorUser->notifications()->first()->data;
        $this->assertSame(VendorNewPaidOrderNotification::class, $vendorUser->notifications()->first()->type);
        $this->assertSame('New paid order', $payload['title']);
        $this->assertSame('processing', $payload['status']);
        $this->assertSame('Processing', $payload['status_label']);
        $this->assertSame('Processing', $payload['current_status']);
        $this->assertSame($product->name, $payload['product_ordered'][0]['name']);
        $this->assertSame('Dubai', $payload['customer_location']['city']);
        $this->assertSame('2026-09-10', $payload['required_date']);
        $this->assertTrue($payload['payment_confirmation']['confirmed']);
        $this->assertSame('/api/vendor/orders/'.$order->id.'/track', $payload['track_endpoint']);

        $this->assertGreaterThan($beforeSupervisor, $supervisor->fresh()->unreadNotifications()->count());
        $this->assertGreaterThan($beforeAreaManager, $areaManager->fresh()->unreadNotifications()->count());
        $this->assertGreaterThan($beforeAdmin, $admin->fresh()->unreadNotifications()->count());

        $visit = Visit::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($visit);
        $this->assertSame((int) $area->id, (int) $visit->area_id);
        $this->assertNull($visit->supervisor_id);

        // Idempotent — no duplicate vendor notification.
        OrderPaidSideEffects::run($order->fresh(), 'Stripe (test)');
        $this->assertSame(1, $vendorUser->fresh()->notifications()->count());

        $token = $vendorUser->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/vendor/orders')
            ->assertOk()
            ->assertJsonPath('data.items.0.order_id', $order->id)
            ->assertJsonPath('data.items.0.status', 'processing')
            ->assertJsonPath('data.items.0.current_status', 'Processing')
            ->assertJsonPath('data.items.0.vendor_status', 'pending');

        $this->withToken($token)->getJson('/api/vendor/orders/'.$order->id.'/track')
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.tracking.timeline.1.key', 'processing')
            ->assertJsonPath('data.tracking.timeline.1.current', true)
            ->assertJsonPath('data.tracking.timeline.2.completed', false);

        $this->withToken($token)->getJson('/api/vendor/notifications')
            ->assertOk();
    }
}
