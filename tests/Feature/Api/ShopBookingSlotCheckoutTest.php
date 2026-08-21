<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\JobSchedulingSetting;
use App\Models\JobTimeSlot;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ShopBookingSlotCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const BOOKING_DATE = '2026-08-10';

    private function authHeaders(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ];
    }

    private function seedSchedulingWithSingleSlotCapacity(?Product $forProduct = null): void
    {
        JobTimeSlot::create([
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $settings = JobSchedulingSetting::current();
        $settings->max_bookings_per_slot = 1;
        $settings->max_bookings_per_day = 20;
        $settings->save();

        if ($forProduct !== null) {
            $order = \App\Models\Order::factory()->create([
                'user_id' => User::factory()->create(['role' => 'client'])->id,
                'package_id' => null,
                'payment_status' => 'paid',
                'total_amount' => 50,
            ]);
            $item = \App\Models\OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $forProduct->id,
                'quantity' => 1,
                'price' => 50,
                'subtotal' => 50,
                'booking_date' => self::BOOKING_DATE,
                'booking_slot' => '10:00 AM - 11:00 AM',
            ]);
            Visit::create([
                'scheduled_date' => self::BOOKING_DATE,
                'scheduled_time' => '10:00',
                'duration_minutes' => 60,
                'status' => 'pending',
                'order_id' => $order->id,
                'order_item_id' => $item->id,
                'notes' => 'Existing booking',
            ]);
        } else {
            Visit::create([
                'scheduled_date' => self::BOOKING_DATE,
                'scheduled_time' => '10:00',
                'duration_minutes' => 60,
                'status' => 'pending',
                'notes' => 'Existing booking',
            ]);
        }
    }

    public function test_cart_add_rejects_fully_booked_slot(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'status' => 'active',
            'compare_at_price' => null,
        ]);

        $this->seedSchedulingWithSingleSlotCapacity($product);

        $response = $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
            'booking_date' => self::BOOKING_DATE,
            'booking_slot' => '10:00 AM - 11:00 AM',
        ], $this->authHeaders($user));

        $response->assertStatus(422);
        $this->assertStringContainsString('fully booked', strtolower((string) $response->json('message')));
    }

    public function test_order_summary_includes_per_product_booking_in_items(): void
    {
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '0');

        JobTimeSlot::create([
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        JobTimeSlot::create([
            'start_time' => '15:00',
            'duration_minutes' => 120,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $productA = Product::factory()->create(['category_id' => $category->id, 'price' => 50, 'status' => 'active', 'compare_at_price' => null]);
        $productB = Product::factory()->create(['category_id' => $category->id, 'price' => 80, 'status' => 'active', 'compare_at_price' => null]);

        Cart::create([
            'user_id' => $user->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'booking_date' => '2026-09-10',
            'booking_slot' => '10:00 AM - 11:00 AM',
        ]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'booking_date' => '2026-09-05',
            'booking_slot' => '3:00 PM - 5:00 PM',
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders($user));
        $response->assertOk();

        $items = $response->json('data.items');
        $this->assertCount(2, $items);
        $this->assertSame('2026-09-10', collect($items)->firstWhere('product_id', $productA->id)['booking_date']);
        $this->assertSame('10:00 AM - 11:00 AM', collect($items)->firstWhere('product_id', $productA->id)['booking_slot']);
        $this->assertSame('2026-09-05', collect($items)->firstWhere('product_id', $productB->id)['booking_date']);
        $this->assertSame('3:00 PM - 5:00 PM', collect($items)->firstWhere('product_id', $productB->id)['booking_slot']);
    }

    public function test_payment_intent_accepts_items_array_with_per_product_booking(): void
    {
        Config::set('services.stripe.secret', 'sk_test_booking_items');
        Config::set('services.stripe.key', 'pk_test_booking_items');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '0');

        JobTimeSlot::create(['start_time' => '09:00', 'duration_minutes' => 60, 'is_active' => true, 'sort_order' => 1]);
        JobTimeSlot::create(['start_time' => '15:00', 'duration_minutes' => 120, 'is_active' => true, 'sort_order' => 2]);

        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $productA = Product::factory()->create(['category_id' => $category->id, 'price' => 50, 'status' => 'active', 'compare_at_price' => null]);
        $productB = Product::factory()->create(['category_id' => $category->id, 'price' => 80, 'status' => 'active', 'compare_at_price' => null]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'payment_intents/pi_test_items') && $request->method() === 'GET') {
                return Http::response(['id' => 'pi_test_items', 'status' => 'succeeded', 'amount' => 13650], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_items'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response(['id' => 'pi_test_items', 'client_secret' => 'pi_test_items_secret', 'status' => 'requires_payment_method'], 200);
            }

            return Http::response(['error' => 'unexpected URL '.$url], 500);
        });

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'shipping' => [
                'full_name' => 'Test User',
                'phone' => '+971501234567',
                'street' => 'Sheikh Zayed Road',
                'city' => 'Dubai',
                'state' => 'DXB',
                'zip_code' => '00000',
                'country' => 'UAE',
            ],
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 1,
                    'booking_date' => '2026-09-10',
                    'booking_slot' => '9:00 AM - 10:00 AM',
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 1,
                    'booking_date' => '2026-09-05',
                    'booking_slot' => '3:00 PM - 5:00 PM',
                ],
            ],
        ], $this->authHeaders($user));

        $pi->assertStatus(200);
        $summaryItems = $pi->json('data.order_summary.items');
        $this->assertCount(2, $summaryItems);
        $this->assertSame('2026-09-10', collect($summaryItems)->firstWhere('product_id', $productA->id)['booking_date']);
        $this->assertSame('3:00 PM - 5:00 PM', collect($summaryItems)->firstWhere('product_id', $productB->id)['booking_slot']);

        $this->postJson('/api/shop/checkout/confirm', [
            'payment_intent_id' => 'pi_test_items',
        ], $this->authHeaders($user))->assertStatus(201);

        $order = \App\Models\Order::where('payment_reference', 'pi_test_items')->firstOrFail();
        $itemA = $order->items()->where('product_id', $productA->id)->first();
        $itemB = $order->items()->where('product_id', $productB->id)->first();
        $this->assertSame('2026-09-10', $itemA->booking_date?->toDateString());
        $this->assertSame('9:00 AM - 10:00 AM', $itemA->booking_slot);
        $this->assertSame('2026-09-05', $itemB->booking_date?->toDateString());
    }

    public function test_buy_now_payment_intent_uses_request_booking_on_product_line(): void
    {
        Config::set('services.stripe.secret', 'sk_test_buy_now_slot');
        Config::set('services.stripe.key', 'pk_test_buy_now_slot');
        Setting::set('shop_tax_percent', '5');
        Setting::set('shop_shipping_amount', '10');

        JobTimeSlot::create(['start_time' => '10:00', 'duration_minutes' => 120, 'is_active' => true, 'sort_order' => 1]);

        $user = User::factory()->create(['role' => 'client']);
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 65,
            'status' => 'active',
            'compare_at_price' => null,
        ]);

        Http::fake(function (\Illuminate\Http\Client\Request $request) {
            $url = $request->url();
            if (str_contains($url, 'payment_intents/pi_test_buy_now_slot') && $request->method() === 'GET') {
                return Http::response(['id' => 'pi_test_buy_now_slot', 'status' => 'succeeded', 'amount' => 7825], 200);
            }
            if ($request->method() === 'POST' && str_contains($url, '/v1/customers')) {
                return Http::response(['id' => 'cus_test_buy_now_slot'], 200);
            }
            if (str_contains($url, 'payment_intents') && $request->method() === 'POST') {
                return Http::response(['id' => 'pi_test_buy_now_slot', 'client_secret' => 'secret', 'status' => 'requires_payment_method'], 200);
            }

            return Http::response(['error' => 'unexpected'], 500);
        });

        $pi = $this->postJson('/api/shop/checkout/stripe/payment-intent', [
            'is_buy_now' => true,
            'product_id' => $product->id,
            'quantity' => 1,
            'booking_date' => '2026-09-01',
            'booking_slot' => '10:00 AM - 12:00 PM',
            'shipping' => [
                'full_name' => 'Test User',
                'phone' => '+971501234567',
                'street' => 'Sheikh Zayed Road',
                'city' => 'Dubai',
                'state' => 'DXB',
                'zip_code' => '00000',
                'country' => 'UAE',
            ],
        ], $this->authHeaders($user));

        $pi->assertStatus(200);
        $items = $pi->json('data.order_summary.items');
        $this->assertCount(1, $items);
        $this->assertSame('2026-09-01', $items[0]['booking_date']);
        $this->assertSame('10:00 AM - 12:00 PM', $items[0]['booking_slot']);
    }
}
