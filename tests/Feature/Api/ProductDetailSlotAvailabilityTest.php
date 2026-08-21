<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Product detail booking must expose per-product slot capacity clearly:
 * booked_count, remaining, max_bookings, available.
 */
class ProductDetailSlotAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $date = '2026-08-10';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));

        // Admin folder K: one global 09:00–10:00 slot, max 2 bookings.
        JobTimeSlot::create([
            'start_time' => '09:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $settings = JobSchedulingSetting::current();
        $settings->max_bookings_per_slot = 2;
        $settings->max_bookings_per_day = 50;
        $settings->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeProduct(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'status' => 'active',
            'compare_at_price' => null,
            'price' => 50,
        ]);
    }

    private function bookOnce(Product $product): void
    {
        $order = Order::factory()->create([
            'user_id' => User::factory()->create(['role' => 'client'])->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'total_amount' => 50,
        ]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
            'booking_date' => $this->date,
            'booking_slot' => '09:00 AM - 10:00 AM',
        ]);
        Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'scheduled_date' => $this->date,
            'scheduled_time' => '09:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);
    }

    private function nineAmSlot(array $booking): array
    {
        $slot = collect($booking['slots'] ?? [])->firstWhere('start_time', '09:00');
        $this->assertNotNull($slot, '09:00 slot missing from product detail booking.slots');

        return $slot;
    }

    public function test_product_detail_shows_empty_slot_then_partial_then_full(): void
    {
        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        // 0 bookings on A
        $empty = $this->getJson('/api/shop/products/'.$productA->id.'?date='.$this->date)
            ->assertOk()
            ->assertJsonPath('data.booking.product_id', $productA->id)
            ->assertJsonPath('data.booking.slot_source', 'global')
            ->assertJsonPath('data.booking.capacity_scope', 'product')
            ->assertJsonPath('data.booking.max_bookings_per_slot', 2)
            ->json('data.booking');

        $slot0 = $this->nineAmSlot($empty);
        $this->assertSame('09:00 AM', $slot0['time']);
        $this->assertSame('09:00', $slot0['start_time']);
        $this->assertSame('10:00', $slot0['end_time']);
        $this->assertSame(0, $slot0['booked_count']);
        $this->assertSame(2, $slot0['remaining']);
        $this->assertSame(2, $slot0['max_bookings']);
        $this->assertTrue($slot0['available']);

        // 1 booking on A → still available
        $this->bookOnce($productA);

        $partial = $this->getJson('/api/shop/products/'.$productA->id.'?date='.$this->date)
            ->assertOk()
            ->json('data.booking');
        $slot1 = $this->nineAmSlot($partial);
        $this->assertSame(1, $slot1['booked_count']);
        $this->assertSame(1, $slot1['remaining']);
        $this->assertTrue($slot1['available']);

        // 2 bookings on A → full / unavailable
        $this->bookOnce($productA);

        $full = $this->getJson('/api/shop/products/'.$productA->id.'?date='.$this->date)
            ->assertOk()
            ->json('data.booking');
        $slot2 = $this->nineAmSlot($full);
        $this->assertSame(2, $slot2['booked_count']);
        $this->assertSame(0, $slot2['remaining']);
        $this->assertFalse($slot2['available']);

        // Product B still empty on same global 09:00 slot
        $other = $this->getJson('/api/shop/products/'.$productB->id.'?date='.$this->date)
            ->assertOk()
            ->json('data.booking');
        $slotB = $this->nineAmSlot($other);
        $this->assertSame(0, $slotB['booked_count']);
        $this->assertSame(2, $slotB['remaining']);
        $this->assertTrue($slotB['available']);
    }

    public function test_admin_working_hours_capacity_changes_appear_on_product_detail(): void
    {
        $product = $this->makeProduct();

        $before = $this->getJson('/api/shop/products/'.$product->id.'?date='.$this->date)
            ->assertOk()
            ->json('data.booking');

        $this->assertSame(2, $before['max_bookings_per_slot']);
        $this->assertSame(50, $before['max_bookings_per_day']);
        $this->assertSame(15, $before['buffer_minutes']);
        $slotBefore = $this->nineAmSlot($before);
        $this->assertSame(2, $slotBefore['max_bookings']);
        $this->assertSame(2, $slotBefore['remaining']);

        $admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(\Spatie\Permission\Models\Role::class) && \Illuminate\Support\Facades\Schema::hasTable('roles')) {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                if (method_exists($admin, 'assignRole')) {
                    $admin->assignRole('admin');
                }
            }
        } catch (\Throwable $e) {
            //
        }

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/admin/job-scheduling/working-hours', [
                'max_bookings_per_slot' => 3,
                'max_bookings_per_day' => 20,
                'buffer_minutes' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.max_bookings_per_slot', 3)
            ->assertJsonPath('data.max_bookings_per_day', 20)
            ->assertJsonPath('data.buffer_minutes', 30);

        $after = $this->getJson('/api/shop/products/'.$product->id.'?date='.$this->date)
            ->assertOk()
            ->json('data.booking');

        $this->assertSame(3, $after['max_bookings_per_slot']);
        $this->assertSame(20, $after['max_bookings_per_day']);
        $this->assertSame(30, $after['buffer_minutes']);

        $slotAfter = $this->nineAmSlot($after);
        $this->assertSame(3, $slotAfter['max_bookings']);
        $this->assertSame(3, $slotAfter['remaining']);
        $this->assertTrue($slotAfter['available']);
    }
}