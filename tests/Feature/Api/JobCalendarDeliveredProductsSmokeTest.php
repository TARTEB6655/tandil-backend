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
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-05')
            ->assertOk()
            ->assertJsonPath('success', true);

        $jobs = collect($res->json('data.jobs'));
        $job = $jobs->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job, 'Delivered vendor product must appear on its delivery day. jobs='.json_encode($jobs->values()));
        $this->assertSame('shop_order', $job['job_source']);
        $this->assertSame('delivered', $job['status']);
        $this->assertSame('Delivered', $job['status_label']);
        $this->assertSame('delivered', $job['vendor_order_status']);
        $this->assertSame('Delivered Tomato Box', $job['title']);
        $this->assertSame('2026-08-05', $job['scheduled_date']);

        // Must not leak onto a later day.
        $later = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-09-16')
            ->assertOk();
        $this->assertNull(
            collect($later->json('data.jobs'))->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id)
        );

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
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-07-01')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job, 'Vendor-mapped delivered line must not vanish when type=service');
        $this->assertSame('Delivered', $job['status_label']);
        $this->assertSame('Mis-typed Delivered Pot', $job['title']);

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
        $soil = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Garden Soil Mix',
            'type' => 'product',
            'price' => 118.95,
            'status' => 'active',
        ]);
        $orphaned = Product::factory()->create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $category->id,
            'name' => 'Orphan Herb Pot',
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
        $o61 = $make($soil, '2026-08-25 10:00:00', 1);

        foreach ([
            ['2026-09-15', $oMango, 'mango'],
            ['2026-08-26', $o63, 'Orphan Herb Pot'],
            ['2026-08-25', $o61, 'Garden Soil Mix'],
        ] as [$day, $order, $title]) {
            $res = $this->actingAs($this->admin, 'sanctum')
                ->getJson('/api/admin/job-scheduling/calendar?view=day&date='.$day)
                ->assertOk();

            $job = collect($res->json('data.jobs'))
                ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

            $this->assertNotNull($job, "Missing {$order->publicOrderNumber()} on {$day}");
            $this->assertSame($order->publicOrderNumber(), $job['order_number']);
            $this->assertSame('Delivered', $job['status_label']);
            $this->assertSame('shop_order', $job['job_source']);
            $this->assertSame($title, $job['title']);
            $this->assertSame($day, $job['scheduled_date']);
        }

        $aug28 = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-28')
            ->assertOk();
        $this->assertNull(
            collect($aug28->json('data.jobs'))->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $o63->id)
        );

        Carbon::setTestNow();
    }

    public function test_delivered_mango_shows_delivered_not_pending_even_with_orphan_visit(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
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

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'processing', // shop lag; vendor mapping is source of truth
            'paid_at' => '2026-09-15 10:00:00',
            'created_at' => '2026-09-15 10:00:00',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $mango->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 100,
            'subtotal' => 100,
            'delivery_otp_confirmed_at' => '2026-09-15 18:00:00',
        ]);

        // Production-shaped visit: order_item set, order_id null, status still pending.
        Visit::create([
            'order_id' => null,
            'order_item_id' => $item->id,
            'scheduled_date' => '2026-09-15',
            'scheduled_time' => '09:00',
            'duration_minutes' => 150,
            'status' => 'pending',
            'notes' => 'mango | Client One',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-09-01')
            ->assertOk();

        $jobs = collect($res->json('data.jobs'))
            ->filter(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id)
            ->values();

        $this->assertGreaterThanOrEqual(1, $jobs->count());
        $this->assertTrue(
            $jobs->every(fn ($j) => ($j['status_label'] ?? null) === 'Delivered'),
            'Expected Delivered, got: '.json_encode($jobs->pluck('status_label'))
        );
        $this->assertTrue(
            $jobs->contains(fn ($j) => ($j['title'] ?? null) === 'mango')
        );

        Carbon::setTestNow();
    }

    public function test_calendar_title_uses_product_name_not_order_number(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
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
            'name' => 'Garden Soil Mix',
            'type' => 'product',
            'price' => 50,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 09:00:00',
            'created_at' => '2026-08-26 09:00:00',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 50,
            'subtotal' => 50,
            'delivery_otp_confirmed_at' => '2026-08-26 11:00:00',
        ]);

        // Unload product relation simulation: visit notes carry the name.
        Visit::create([
            'order_id' => null,
            'order_item_id' => $item->id,
            'scheduled_date' => '2026-08-26',
            'scheduled_time' => '09:00',
            'status' => 'pending',
            'notes' => 'Garden Soil Mix | Client One',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job);
        $this->assertSame('Garden Soil Mix', $job['title']);
        $this->assertSame('Garden Soil Mix', $job['product_name'] ?? $job['title']);
        $this->assertNotSame($order->publicOrderNumber(), $job['title']);
        $this->assertSame('Delivered', $job['status_label']);

        Carbon::setTestNow();
    }

    public function test_calendar_title_from_visit_notes_when_product_row_missing_name_relation(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
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
            'name' => 'August Herb Box',
            'type' => 'product',
            'price' => 40,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 08:00:00',
            'created_at' => '2026-08-26 08:00:00',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 40,
            'subtotal' => 80,
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 80,
            'subtotal' => 80,
            'delivery_otp_confirmed_at' => '2026-08-26 16:00:00',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=month&date=2026-08-01')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job);
        $this->assertSame('August Herb Box', $job['title']);
        $this->assertStringStartsNotWith('order_', (string) $job['title']);

        Carbon::setTestNow();
    }

    public function test_calendar_mapping_only_row_uses_item_product_name_when_vendor_id_mismatched(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        $otherUser = User::factory()->create(['role' => 'vendor']);
        $otherVendor = Vendor::create([
            'user_id' => $otherUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        $category = Category::factory()->create();

        // Product owned by a different vendor — vendor list shows Qty 0 "Product",
        // but calendar must still show the real catalog name.
        $product = Product::factory()->create([
            'vendor_id' => $otherVendor->id,
            'category_id' => $category->id,
            'name' => 'August Lavender Bundle',
            'type' => 'product',
            'price' => 118.95,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 09:00:00',
            'created_at' => '2026-08-26 09:00:00',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 118.95,
            'subtotal' => 118.95,
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 118.95,
            'subtotal' => 118.95,
            'delivery_otp_confirmed_at' => '2026-08-26 09:00:00',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job);
        $this->assertSame('August Lavender Bundle', $job['title']);
        $this->assertSame('August Lavender Bundle', $job['product_name']);
        $this->assertSame($item->id, $job['order_item_id']);
        $this->assertNotSame($order->publicOrderNumber(), $job['title']);
        $this->assertSame('Delivered', $job['status_label']);

        Carbon::setTestNow();
    }

    public function test_calendar_mapping_without_items_does_not_use_order_number_as_title(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 09:00:00',
            'created_at' => '2026-08-26 09:00:00',
        ]);
        // No order_items — same shape as production order_0061 response.
        $mapping = VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 100,
            'subtotal' => 100,
            'delivery_otp_confirmed_at' => '2026-08-26 09:00:00',
        ]);

        Visit::create([
            'order_id' => $order->id,
            'scheduled_date' => '2026-08-26',
            'scheduled_time' => '09:00',
            'status' => 'pending',
            'notes' => 'Desert Rose Pack | Client One | [SHOP-ORDER:'.$order->id.']',
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id
                && (int) ($j['vendor_order_mapping_id'] ?? 0) === $mapping->id);

        $this->assertNotNull($job);
        $this->assertSame('Desert Rose Pack', $job['title']);
        $this->assertSame('Desert Rose Pack', $job['product_name']);
        $this->assertNull($job['order_item_id']);

        Carbon::setTestNow();
    }

    public function test_calendar_service_order_with_visit_does_not_also_emit_shop_order(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor']);
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
            'name' => 'service product',
            'type' => 'service',
            'price' => 100,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'assigned',
            'booking_date' => '2026-08-26',
            'booking_slot' => '10:00 AM – 11:00 AM',
            'paid_at' => '2026-08-26 09:00:00',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'booking_date' => '2026-08-26',
            'booking_slot' => '10:00 AM – 11:00 AM',
        ]);
        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Pending->value,
            'total_amount' => 100,
            'subtotal' => 100,
        ]);
        $visit = Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'supervisor_id' => $supervisor->id,
            'scheduled_date' => '2026-08-29',
            'scheduled_time' => '10:00',
            'duration_minutes' => 60,
            'status' => 'pending',
            'notes' => 'service product | Order Service Visit | [SHOP-ORDER:'.$order->id.'][ITEM:'.$item->id.']',
        ]);

        $week = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=week&date=2026-08-24')
            ->assertOk();

        $jobs = collect($week->json('data.jobs'))
            ->filter(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id)
            ->values();

        $this->assertCount(1, $jobs, 'Service order must appear once (visit only), not shop_order + visit');
        $this->assertSame('visit', $jobs[0]['job_source']);
        $this->assertSame($visit->id, $jobs[0]['id']);
        $this->assertSame('service product', $jobs[0]['title']);
        $this->assertSame('2026-08-29', $jobs[0]['scheduled_date']);

        // Day of the old booking slot must not resurrect a shop_order card.
        $day = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();
        $onBookingDay = collect($day->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);
        $this->assertNull($onBookingDay);

        Carbon::setTestNow();
    }

    public function test_calendar_uses_product_name_snapshot_after_product_deleted(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
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
            'name' => 'Aloe Vera Pot',
            'type' => 'simple',
            'price' => 40,
            'status' => 'active',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 09:00:00',
            'created_at' => '2026-08-26 09:00:00',
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 40,
            'subtotal' => 40,
        ]);
        $this->assertSame('Aloe Vera Pot', $item->fresh()->product_name);

        VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'product_title' => 'Aloe Vera Pot',
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 40,
            'subtotal' => 40,
            'delivery_otp_confirmed_at' => '2026-08-26 09:00:00',
        ]);

        // Simulate catalog purge: product gone, FK nulled, snapshot kept.
        $product->delete();
        $item->refresh();
        if ($item->product_id !== null) {
            // SQLite may still cascade in older FKs — keep the row manually.
            OrderItem::query()->whereKey($item->id)->update([
                'product_id' => null,
                'product_name' => 'Aloe Vera Pot',
            ]);
            $item->refresh();
        }

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id);

        $this->assertNotNull($job);
        $this->assertSame('Aloe Vera Pot', $job['title']);
        $this->assertSame('Aloe Vera Pot', $job['product_name']);

        Carbon::setTestNow();
    }

    public function test_calendar_mapping_only_uses_notification_product_name(): void
    {
        Carbon::setTestNow('2026-08-26 12:00:00');

        $client = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);
        $vendor = Vendor::create([
            'user_id' => $vendorUser->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'order_status' => 'delivered',
            'paid_at' => '2026-08-26 09:00:00',
            'created_at' => '2026-08-26 09:00:00',
        ]);
        $mapping = VendorOrderMapping::create([
            'order_id' => $order->id,
            'vendor_id' => $vendor->id,
            'status' => VendorOrderStatus::Delivered->value,
            'total_amount' => 100,
            'subtotal' => 100,
            'delivery_otp_confirmed_at' => '2026-08-26 09:00:00',
        ]);

        // No order_items (cascade-deleted). Name only survives in notification payload.
        \Illuminate\Support\Facades\DB::table('notifications')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\VendorNewPaidOrderNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $vendorUser->id,
            'data' => json_encode([
                'order_id' => $order->id,
                'order_number' => $order->publicOrderNumber(),
                'message' => $order->publicOrderNumber().' paid for Desert Rose Pack. Status: Delivered. Location: Abu Dhabi. Required: date TBC . Payment confirmed.',
                'products' => [
                    ['name' => 'Desert Rose Pack', 'quantity' => 1],
                ],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $res = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/calendar?view=day&date=2026-08-26')
            ->assertOk();

        $job = collect($res->json('data.jobs'))
            ->first(fn ($j) => (int) ($j['order_id'] ?? 0) === $order->id
                && (int) ($j['vendor_order_mapping_id'] ?? 0) === $mapping->id);

        $this->assertNotNull($job);
        $this->assertSame('Desert Rose Pack', $job['title']);
        $this->assertSame('Desert Rose Pack', $mapping->fresh()->product_title);

        Carbon::setTestNow();
    }
}
