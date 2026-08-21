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
use App\Services\JobSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerProductSlotCapacityTest extends TestCase
{
    use RefreshDatabase;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze "today" on a Monday so product ?date= after_or_equal:today passes.
        Carbon::setTestNow(Carbon::parse('2026-08-10 09:00:00'));
        $this->date = '2026-08-10';
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedSlot(int $maxPerSlot = 1): void
    {
        JobTimeSlot::create([
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $settings = JobSchedulingSetting::current();
        $settings->max_bookings_per_slot = $maxPerSlot;
        $settings->max_bookings_per_day = 50;
        $settings->save();
    }

    private function bookProduct(Product $product): void
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
            'booking_slot' => '10:00 AM - 11:00 AM',
        ]);
        Visit::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'scheduled_date' => $this->date,
            'scheduled_time' => '10:00',
            'duration_minutes' => 60,
            'status' => 'pending',
        ]);
    }

    public function test_product_a_booking_does_not_fill_product_b_slot(): void
    {
        $this->seedSlot(1);

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);
        $productB = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);

        $this->bookProduct($productA);

        $slotsA = JobSchedulingService::availableSlots($this->date, (int) $productA->id);
        $tenA = collect($slotsA)->firstWhere('start_time', '10:00');
        $this->assertNotNull($tenA);
        $this->assertSame(1, $tenA['booked_count']);
        $this->assertFalse($tenA['available']);

        $slotsB = JobSchedulingService::availableSlots($this->date, (int) $productB->id);
        $tenB = collect($slotsB)->firstWhere('start_time', '10:00');
        $this->assertNotNull($tenB);
        $this->assertSame(0, $tenB['booked_count']);
        $this->assertTrue($tenB['available']);
    }

    public function test_available_slots_api_scopes_by_product_id(): void
    {
        $this->seedSlot(1);

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);
        $productB = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);

        $this->bookProduct($productA);

        $user = User::factory()->create(['role' => 'client']);

        $resA = $this->actingAs($user, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.$this->date.'&product_id='.$productA->id)
            ->assertOk()
            ->assertJsonPath('data.product_id', $productA->id);

        $tenA = collect($resA->json('data.slots'))->firstWhere('start_time', '10:00');
        $this->assertFalse($tenA['available']);

        $resB = $this->actingAs($user, 'sanctum')
            ->getJson('/api/visits/available-slots?date='.$this->date.'&product_id='.$productB->id)
            ->assertOk()
            ->assertJsonPath('data.product_id', $productB->id);

        $tenB = collect($resB->json('data.slots'))->firstWhere('start_time', '10:00');
        $this->assertTrue($tenB['available']);
    }

    public function test_product_show_booking_is_scoped_to_that_product(): void
    {
        $this->seedSlot(1);

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
            'price' => 40,
        ]);
        $productB = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
            'price' => 40,
        ]);

        $this->bookProduct($productA);

        $showA = $this->getJson('/api/shop/products/'.$productA->id.'?date='.$this->date)
            ->assertOk();
        $this->assertSame($productA->id, $showA->json('data.booking.product_id'));
        $tenA = collect($showA->json('data.booking.slots'))->firstWhere('start_time', '10:00');
        $this->assertFalse($tenA['available']);

        $showB = $this->getJson('/api/shop/products/'.$productB->id.'?date='.$this->date)
            ->assertOk();
        $this->assertSame($productB->id, $showB->json('data.booking.product_id'));
        $tenB = collect($showB->json('data.booking.slots'))->firstWhere('start_time', '10:00');
        $this->assertTrue($tenB['available']);
    }

    public function test_cart_allows_same_slot_on_different_product_when_other_is_full(): void
    {
        $this->seedSlot(1);

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);
        $productB = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);

        $this->bookProduct($productA);

        $user = User::factory()->create(['role' => 'client']);
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ];

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $productA->id,
            'quantity' => 1,
            'booking_date' => $this->date,
            'booking_slot' => '10:00 AM - 11:00 AM',
        ], $headers)->assertStatus(422);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $productB->id,
            'quantity' => 1,
            'booking_date' => $this->date,
            'booking_slot' => '10:00 AM - 11:00 AM',
        ], $headers)->assertSuccessful();
    }
}
