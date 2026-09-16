<?php

namespace Tests\Feature\Api;

use App\Enums\VendorOrderStatus;
use App\Enums\VendorStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorOrderMapping;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * HTTP smoke: delivered vendor products must appear on the jobs calendar
 * even when an old/orphan visit exists outside the selected day window.
 */
class JobCalendarDeliveredProductsSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $this->admin->assignRole('admin');
        }
    }

    public function test_smoke_delivered_vendor_product_with_orphan_visit_still_on_calendar(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $category = Category::factory()->create();
        // Demo/seed style: type null (common production pattern)
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Delivered Tomato Box',
            'status' => 'active',
            'price' => 40,
            'type' => null,
        ]);

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'order_status' => 'processing', // shop status may lag; mapping is delivered
            'booking_date' => null,
            'paid_at' => '2026-08-01 09:00:00',
            'created_at' => '2026-08-01 09:00:00',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 40,
            'subtotal' => 40,
        ]);

        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 40,
            'subtotal' => 40,
            'delivery_otp_confirmed_at' => '2026-08-05 16:30:00',
        ]);

        // Orphan/legacy visit on pay day — outside today's day-view window.
        // Old bug: shop path skipped this item (has_visit) and visit path
        // filtered it out by scheduled_date → product vanished entirely.
        Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'scheduled_date' => '2026-08-01',
            'scheduled_time' => '09:00',
            'status' => 'completed',
            'notes' => 'Legacy product visit',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-09-16')
            ->assertOk()
            ->assertJsonPath('success', true);

        $jobs = collect($res->json('data.jobs'));
        $job = $jobs->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job, 'Delivered vendor product must appear on calendar. jobs='.json_encode($jobs->values()));
        $this->assertSame('shop_order', $job['job_source']);
        $this->assertSame('delivered', $job['status']);
        $this->assertSame('Delivered', $job['status_label']);
        $this->assertSame('delivered', $job['vendor_order_status']);
        $this->assertSame('Delivered Tomato Box', $job['title']);

        Carbon::setTestNow();
    }

    public function test_smoke_delivered_mapping_shows_even_when_type_wrongly_service(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Mis-typed Delivered Pot',
            'status' => 'active',
            'price' => 22,
            'type' => 'service', // bad catalog data — still a vendor mapping product
        ]);

        $order = Order::factory()->create([
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-07-10 11:00:00',
            'created_at' => '2026-07-10 11:00:00',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 22,
            'subtotal' => 22,
        ]);

        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 22,
            'subtotal' => 22,
            'delivery_otp_confirmed_at' => '2026-07-12 10:00:00',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-09-01')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job, 'Vendor-mapped delivered line must not vanish when type=service');
        $this->assertSame('Delivered', $job['status_label']);

        Carbon::setTestNow();
    }

    public function test_smoke_screenshot_delivered_orders_appear_on_calendar(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $client = User::factory()->create([
            'role' => 'client',
            'name' => 'Client One',
            'phone' => '2212122121',
        ]);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        $otherVendorUser = User::factory()->create(['role' => 'vendor']);
        $otherVendor = Vendor::create([
            'user_id' => $otherVendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $category = Category::factory()->create();
        $mango = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'mango',
            'type' => 'product',
            'price' => 100,
            'status' => 'active',
        ]);
        // Product reassigned away from mapping vendor → vendor list shows "Product" Qty 0
        $orphaned = Product::factory()->create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $category->id,
            'name' => 'Product',
            'type' => 'product',
            'price' => 118.95,
            'status' => 'active',
        ]);

        $make = function (Product $product, string $when, int $qty) use ($client, $vendor) {
            $order = Order::factory()->create([
                'user_id' => $client->id,
                'payment_status' => 'paid',
                'order_status' => 'delivered',
                'paid_at' => $when,
                'created_at' => $when,
            ]);
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'price' => $product->price,
                'subtotal' => (float) $product->price * max($qty, 0),
            ]);
            VendorOrderMapping::create([
                'order_id' => $order->id,
                'vendor_id' => $vendor->id,
                'status' => VendorOrderStatus::Delivered->value,
                'total_amount' => (float) $product->price * max($qty, 1),
                'subtotal' => (float) $product->price * max($qty, 0),
                'delivery_otp_confirmed_at' => $when,
            ]);

            return $order->fresh();
        };

        $oMango = $make($mango, '2026-09-15 10:00:00', 1);
        $o63 = $make($orphaned, '2026-08-26 10:00:00', 0);
        $o61 = $make($orphaned, '2026-08-25 10:00:00', 0);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-09-16')
            ->assertOk();

        $jobs = collect($res->json('data.jobs'));

        foreach ([
            [$oMango, 'mango', 'Delivered'],
            [$o63, 'Product', 'Delivered'],
            [$o61, 'Product', 'Delivered'],
        ] as [$order, $title, $label]) {
            $job = $jobs->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);
            $this->assertNotNull($job, "Missing {$order->publicOrderNumber()} on calendar");
            $this->assertSame($order->publicOrderNumber(), $job['order_number']);
            $this->assertSame($label, $job['status_label']);
            $this->assertSame('shop_order', $job['job_source']);
            $this->assertSame($title, $job['title']);
        }

        Carbon::setTestNow();
    }
}
