<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientOrderDeliveryAndRatingWebTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Order, 1: Product}
     */
    private function makeOrderWithProduct(User $user, string $status = 'completed'): array
    {
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'status' => 'active',
            'price' => 40,
        ]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'package_id' => null,
            'order_status' => $status,
            'payment_status' => 'paid',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 40,
            'subtotal' => 40,
        ]);

        return [$order, $product];
    }

    private function attachVisibleReport(Order $order): void
    {
        $visit = Visit::factory()->create([
            'order_id' => $order->id,
            'status' => 'completed',
            'notes' => '[SHOP-ORDER:'.$order->id.']',
        ]);
        Report::factory()->create([
            'visit_id' => $visit->id,
            'status' => 'sent_to_client',
        ]);
    }

    public function test_completed_order_page_shows_rating_form(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'completed');

        $this->actingAs($user)
            ->get('/client/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Rate Your Service');
    }

    public function test_client_can_rate_service_from_web(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order, $product] = $this->makeOrderWithProduct($user, 'completed');

        $this->actingAs($user)
            ->post('/client/orders/'.$order->id.'/rate', [
                'rating' => 5,
                'review' => 'Great service',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_id' => null,
            'rating' => 5,
        ]);
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'rating' => 5,
        ]);

        $product->refresh();
        $this->assertSame(5.0, (float) $product->rating_average);
        $this->assertSame(1, (int) $product->rating_count);
    }

    public function test_client_cannot_rate_before_completion_from_web(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'in_progress');

        $this->actingAs($user)
            ->post('/client/orders/'.$order->id.'/rate', ['rating' => 5])
            ->assertRedirect();

        $this->assertDatabaseMissing('reviews', ['order_id' => $order->id]);
    }

    public function test_mark_delivered_requires_visible_report(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'completed');

        // No report yet -> must not become delivered.
        $this->actingAs($user)
            ->post('/client/orders/'.$order->id.'/mark-delivered')
            ->assertRedirect();
        $order->refresh();
        $this->assertNotSame('delivered', strtolower((string) $order->order_status));

        // With a client-visible report -> can be marked delivered.
        $this->attachVisibleReport($order);
        $this->actingAs($user)
            ->post('/client/orders/'.$order->id.'/mark-delivered')
            ->assertRedirect();
        $order->refresh();
        $this->assertSame('delivered', strtolower((string) $order->order_status));
    }
}
