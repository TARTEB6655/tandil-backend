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

    public function test_admin_can_add_product_specific_time_slot(): void
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
            ->assertJsonPath('data.start_time', '14:00')
            ->assertJsonPath('data.date', '2026-08-12')
            ->assertJsonPath('data.recurring', false);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/job-scheduling/products/'.$product->id.'/time-slots')
            ->assertOk()
            ->assertJsonPath('data.uses_custom_slots', true)
            ->assertJsonPath('data.slots.0.start_time', '14:00');
    }

    public function test_product_detail_shows_only_custom_slots_not_global(): void
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

        $res = $this->getJson('/api/shop/products/'.$product->id.'?date=2026-08-12')
            ->assertOk();

        $this->assertTrue($res->json('data.booking.uses_custom_slots'));
        $this->assertSame('product', $res->json('data.booking.slot_source'));

        $starts = collect($res->json('data.booking.slots'))->pluck('start_time')->all();
        $this->assertContains('14:00', $starts);
        $this->assertNotContains('09:00', $starts, 'Global 09:00 must not appear when product has custom slots');
    }

    public function test_two_products_can_have_different_custom_slots(): void
    {
        $productA = $this->product();
        $productB = $this->product();

        ProductTimeSlot::create([
            'product_id' => $productA->id,
            'date' => null,
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        ProductTimeSlot::create([
            'product_id' => $productB->id,
            'date' => null,
            'start_time' => '16:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $a = $this->getJson('/api/shop/products/'.$productA->id.'?date=2026-08-10')->assertOk();
        $b = $this->getJson('/api/shop/products/'.$productB->id.'?date=2026-08-10')->assertOk();

        $this->assertSame(['10:00'], collect($a->json('data.booking.slots'))->pluck('start_time')->all());
        $this->assertSame(['16:00'], collect($b->json('data.booking.slots'))->pluck('start_time')->all());
    }

    public function test_product_without_custom_slots_falls_back_to_global(): void
    {
        $product = $this->product();

        $res = $this->getJson('/api/shop/products/'.$product->id.'?date=2026-08-10')
            ->assertOk();

        $this->assertFalse($res->json('data.booking.uses_custom_slots'));
        $this->assertSame('global', $res->json('data.booking.slot_source'));
        $this->assertContains('09:00', collect($res->json('data.booking.slots'))->pluck('start_time')->all());
    }

    public function test_cart_rejects_global_slot_when_product_has_custom_only(): void
    {
        $product = $this->product();
        ProductTimeSlot::create([
            'product_id' => $product->id,
            'date' => null,
            'start_time' => '14:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create(['role' => 'client']);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
            'booking_date' => '2026-08-10',
            'booking_slot' => '09:00 AM - 10:00 AM',
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ])->assertStatus(422);

        $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
            'booking_date' => '2026-08-10',
            'booking_slot' => '02:00 PM - 03:00 PM',
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ])->assertSuccessful();
    }
}
