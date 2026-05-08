<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\WalletCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopOrderTrackAndCancelTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ];
    }

    public function test_get_orders_track_requires_authentication(): void
    {
        $order = Order::factory()->create(['order_status' => 'pending', 'payment_status' => 'pending']);

        $this->getJson('/api/orders/'.$order->id.'/track')->assertStatus(401);
    }

    public function test_get_orders_track_returns_timeline_order_summary_and_short_number(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => null,
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'special_instructions' => 'Please be careful with stitching',
            'total_amount' => 45.00,
        ]);

        $response = $this->getJson('/api/orders/'.$order->id.'/track', $this->bearer($user));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_id', $order->id);
        $response->assertJsonPath('data.order_number', $order->publicOrderNumber());
        $response->assertJsonPath('data.order_number_short', $order->publicOrderNumberDigits());
        $response->assertJsonPath('data.order_summary.special_instructions', 'Please be careful with stitching');
        $this->assertSame(45.0, (float) $response->json('data.order_summary.total'));
        $response->assertJsonPath('data.order_summary.payment_method', 'Credit card');
        $response->assertJsonPath('data.can_cancel', false);
        $response->assertJsonStructure([
            'data' => [
                'refund_policy' => ['grace_minutes', 'wallet_validity_months', 'rules'],
                'wallet',
            ],
        ]);
        $response->assertJsonStructure([
            'data' => [
                'tracking' => [
                    'timeline' => [
                        ['key', 'label', 'description', 'completed', 'timestamp'],
                    ],
                ],
                'order_summary' => [
                    'placed_at', 'delivery_address', 'payment_method', 'total', 'currency', 'special_instructions',
                    'estimated_arrival', 'job_duration',
                ],
            ],
        ]);
    }

    public function test_track_falls_back_to_product_service_timing_when_order_fields_are_null(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'estimated_arrival' => 'within 2 hour',
            'job_duration' => '55 minutes',
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => null,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'estimated_arrival' => null,
            'job_duration' => null,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10,
            'subtotal' => 10,
        ]);

        $response = $this->getJson('/api/orders/'.$order->id.'/track', $this->bearer($user));
        $response->assertOk();
        $response->assertJsonPath('data.order_summary.estimated_arrival', 'within 2 hour');
        $response->assertJsonPath('data.order_summary.job_duration', '55 minutes');
    }

    public function test_get_orders_track_forbidden_for_other_user_order(): void
    {
        $owner = User::factory()->create(['role' => 'client']);
        $other = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $owner->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->getJson('/api/orders/'.$order->id.'/track', $this->bearer($other))
            ->assertStatus(403);
    }

    public function test_get_orders_index_includes_guest_orders_for_same_email_client(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'client1@test.com',
        ]);

        $owned = Order::factory()->create([
            'user_id' => $user->id,
            'guest_email' => null,
        ]);
        $guestSameEmail = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'client1@test.com',
        ]);
        Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'other@test.com',
        ]);

        $response = $this->getJson('/api/orders', $this->bearer($user));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 2);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($owned->id, $ids);
        $this->assertContains($guestSameEmail->id, $ids);
    }

    public function test_get_orders_index_returns_all_orders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Order::factory()->count(3)->create();

        $response = $this->getJson('/api/orders', $this->bearer($admin));

        $response->assertOk();
        $response->assertJsonPath('pagination.total', 3);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_get_orders_index_falls_back_timing_from_first_product(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'estimated_arrival' => 'within 2 hour',
            'job_duration' => '55 minutes',
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'estimated_arrival' => null,
            'job_duration' => null,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10,
            'subtotal' => 10,
        ]);

        $response = $this->getJson('/api/orders', $this->bearer($user));

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $order->id);
        $response->assertJsonPath('data.0.estimated_arrival', 'within 2 hour');
        $response->assertJsonPath('data.0.job_duration', '55 minutes');
    }

    public function test_post_orders_cancel_succeeds_for_pending_order(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel', [], $this->bearer($user));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $order->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('pending', $order->payment_status);
    }

    public function test_post_orders_cancel_applies_refund_policy_and_credits_wallet_for_paid_unassigned_order(): void
    {
        $user = User::factory()->create(['role' => 'client', 'wallet_balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'total_amount' => 100,
            'created_at' => now()->subHours(2),
        ]);

        \App\Models\Setting::set('refund_partial_percent', '50', 'number', 'payment');
        \App\Models\Setting::set('refund_wallet_validity_months', '6', 'number', 'payment');

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel', [], $this->bearer($user));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('refund.stage', 'before_assignment');
        $response->assertJsonPath('refund.refund_percent', 100);
        $response->assertJsonPath('refund.refund_amount', 100);
        $response->assertJsonPath('refund.wallet_credited', 100);

        $order->refresh();
        $user->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertEquals(100.0, (float) $order->refund_amount);
        $this->assertEquals(100.0, (float) $user->wallet_balance);

        $credit = WalletCredit::where('order_id', $order->id)->first();
        $this->assertNotNull($credit);
        $this->assertEquals(100.0, (float) $credit->amount);
    }

    public function test_post_orders_cancel_fails_when_technician_assignment_started(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'assigned',
            'payment_status' => 'paid',
        ]);

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel', [], $this->bearer($user));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'Order cannot be cancelled after technician assignment.');
        $order->refresh();
        $this->assertSame('assigned', $order->order_status);
    }

    public function test_post_orders_cancel_fails_when_already_delivered(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel', [], $this->bearer($user));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $order->refresh();
        $this->assertSame('delivered', $order->order_status);
    }

    public function test_guest_track_returns_same_shape_and_order_summary(): void
    {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'guest-track@example.com',
            'guest_full_name' => 'Guest User',
            'guest_phone' => '+971500000001',
            'guest_street_address' => 'Sheikh Zayed Road',
            'guest_city' => 'Dubai',
            'guest_state' => 'DXB',
            'guest_zip_code' => '12345',
            'guest_country' => 'UAE',
            'package_id' => null,
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'paypal',
            'special_instructions' => 'Leave at reception',
            'total_amount' => 99.50,
        ]);

        $q = http_build_query([
            'order_number' => $order->publicOrderNumber(),
            'email' => 'guest-track@example.com',
        ]);

        $response = $this->getJson('/api/shop/orders/guest/track?'.$q);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_id', $order->id);
        $response->assertJsonPath('data.order_summary.special_instructions', 'Leave at reception');
        $response->assertJsonPath('data.can_cancel', true);
    }

    public function test_get_shop_order_detail_allows_client_when_guest_email_matches(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email' => 'same@example.com',
        ]);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'same@example.com',
            'package_id' => null,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        $response = $this->getJson('/api/shop/orders/'.$order->id, $this->bearer($client));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_get_shop_order_detail_forbidden_when_guest_email_does_not_match(): void
    {
        $client = User::factory()->create([
            'role' => 'client',
            'email' => 'client-a@example.com',
        ]);
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_email' => 'client-b@example.com',
            'package_id' => null,
            'payment_status' => 'pending',
            'order_status' => 'pending',
        ]);

        $this->getJson('/api/shop/orders/'.$order->id, $this->bearer($client))
            ->assertStatus(403);
    }

    public function test_get_orders_track_does_not_fail_when_wallet_credits_table_missing(): void
    {
        if (Schema::hasTable('wallet_credits')) {
            Schema::drop('wallet_credits');
        }

        $user = User::factory()->create(['role' => 'client', 'wallet_balance' => 12.5]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/orders/'.$order->id.'/track', $this->bearer($user));
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.balance', 12.5)
            ->assertJsonPath('data.wallet.last_refund_credit', null);
    }

    public function test_get_cancelled_orders_list_returns_only_cancelled_orders(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $cancelled = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
        Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/orders/cancelled', $this->bearer($user));
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($cancelled->id, $response->json('data.0.id'));
        $this->assertSame('cancelled', $response->json('data.0.order_status'));
    }

    public function test_get_cancelled_orders_list_returns_all_for_admin_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $a = Order::factory()->create([
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
        ]);
        $b = Order::factory()->create([
            'order_status' => 'cancelled',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/orders/cancelled', $this->bearer($admin));
        $response->assertOk();
        $response->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
    }

    public function test_get_cancel_track_returns_cancelled_order_detail_and_tracking(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
            'refund_amount' => 25,
        ]);

        $response = $this->getJson('/api/orders/' . $order->id . '/cancel-track', $this->bearer($user));
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_id', $order->id);
        $response->assertJsonPath('data.tracking.status', 'cancelled');
        $response->assertJsonPath('data.order_summary.refund_amount', 25);
        $response->assertJsonPath('data.can_cancel', false);
        $response->assertJsonPath('data.tracking.timeline.0.key', 'pending');
        $response->assertJsonPath('data.tracking.timeline.1.key', 'cancelled');
        $response->assertJsonPath('data.tracking.timeline.3.key', 'refund_complete');
    }

    public function test_get_cancel_track_rejects_non_cancelled_order(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->getJson('/api/orders/' . $order->id . '/cancel-track', $this->bearer($user));
        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $response->assertJsonPath('message', 'This order is not cancelled.');
    }
}
