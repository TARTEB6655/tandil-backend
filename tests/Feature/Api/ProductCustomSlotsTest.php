<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Product;
use App\Models\ProductTimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Shop booking always uses GLOBAL folder-K slots.
 * Product custom slot rows (admin C2) must not change the times shown on product detail.
 */
class ProductCustomSlotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));

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

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                if (method_exists($admin, 'assignRole')) {
                    $admin->assignRole('admin');
                }
            }
        } catch (\Throwable $e) {
            //
        }

        return $admin;
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'status' => 'active',
            'compare_at_price' => null,
            'price' => 40,
        ]);
    }

    public function test_admin_can_manage_product_time_slot_rows(): void
    {
        $admin = $this->admin();
        $product = $this->product();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/job-scheduling/products/'.$product->id.'/time-slots', [
                'start_time' => '14:00',
                'duration_minutes' => 90,
                'date' => '2026-08-12',
            ])
            ->assertCreated()
            ->assertJsonPath('data.start_time', '14:00');
    }

    public function test_product_detail_always_shows_global_slots_even_if_product_rows_exist(): void
    {
        $product = $this->product();

        ProductTimeSlot::create([
            'product_id' => $product->id,
            'date' => '2026-08-12',
            'start_time' => '14:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $res = $this->getJson('/api/shop/products/'.$product->id.'?date=2026-08-10')
            ->assertOk();

        $this->assertSame('global', $res->json('data.booking.slot_source'));
        $this->assertSame('product', $res->json('data.booking.capacity_scope'));
        $starts = collect($res->json('data.booking.slots'))->pluck('start_time')->all();
        $this->assertContains('09:00', $starts);
        $this->assertNotContains('14:00', $starts);
    }

    public function test_two_products_share_same_global_times_with_independent_capacity(): void
    {
        JobTimeSlot::create([
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $settings = JobSchedulingSetting::current();
        $settings->max_bookings_per_slot = 1;
        $settings->save();

        $productA = $this->product();
        $productB = $this->product();

        $order = \App\Models\Order::factory()->create([
            'user_id' => User::factory()->create(['role' => 'client'])->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'total_amount' => 50,
        ]);
        $item = \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'price' => 50,
            'subtotal' => 50,
            'booking_date' => '2026-08-10',
            'booking_slot' => '10:00 AM - 11:00 AM',
        ]);
        \App\Models\Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'scheduled_date' => '2026-08-10',
            'scheduled_time' => '10:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);

        $a = $this->getJson('/api/shop/products/'.$productA->id.'?date=2026-08-10')->assertOk();
        $b = $this->getJson('/api/shop/products/'.$productB->id.'?date=2026-08-10')->assertOk();

        $tenA = collect($a->json('data.booking.slots'))->firstWhere('start_time', '10:00');
        $tenB = collect($b->json('data.booking.slots'))->firstWhere('start_time', '10:00');

        $this->assertFalse($tenA['available']);
        $this->assertTrue($tenB['available']);
    }
}
