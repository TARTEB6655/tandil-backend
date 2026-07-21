<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ];
    }

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

    public function test_rate_requires_authentication(): void
    {
        $order = Order::factory()->create(['order_status' => 'completed']);
        $this->postJson('/api/orders/'.$order->id.'/rate', ['rating' => 5])->assertStatus(401);
    }

    public function test_client_cannot_rate_before_completion(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'in_progress');

        $this->postJson('/api/orders/'.$order->id.'/rate', ['rating' => 5], $this->bearer($user))
            ->assertStatus(422);
    }

    public function test_client_rates_service_and_product_rating_aggregates(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order, $product] = $this->makeOrderWithProduct($user, 'completed');

        $response = $this->postJson('/api/orders/'.$order->id.'/rate', [
            'rating' => 5,
            'review' => 'Excellent service!',
        ], $this->bearer($user));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_rating', 5)
            ->assertJsonPath('data.product_ratings.0.product_id', $product->id)
            ->assertJsonPath('data.product_ratings.0.rating_average', fn ($v) => (float) $v === 5.0)
            ->assertJsonPath('data.product_ratings.0.rating_count', 1);

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

    public function test_resubmitting_updates_existing_rating_without_duplicates(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order, $product] = $this->makeOrderWithProduct($user, 'completed');

        $this->postJson('/api/orders/'.$order->id.'/rate', ['rating' => 5], $this->bearer($user))->assertOk();
        $this->postJson('/api/orders/'.$order->id.'/rate', ['rating' => 2], $this->bearer($user))->assertOk();

        $this->assertSame(1, Review::where('order_id', $order->id)->whereNull('product_id')->count());
        $this->assertSame(1, Review::where('order_id', $order->id)->where('product_id', $product->id)->count());

        $product->refresh();
        $this->assertSame(2.0, (float) $product->rating_average);
        $this->assertSame(1, (int) $product->rating_count);
    }

    public function test_per_product_rating_rejects_product_not_in_order(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'completed');
        $other = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'status' => 'active']);

        $this->postJson('/api/orders/'.$order->id.'/rate', [
            'rating' => 4,
            'product_ratings' => [
                ['product_id' => $other->id, 'rating' => 3],
            ],
        ], $this->bearer($user))->assertStatus(422);
    }

    public function test_get_rating_returns_existing_and_track_includes_service_rating(): void
    {
        $user = User::factory()->create(['role' => 'client']);
        [$order] = $this->makeOrderWithProduct($user, 'completed');

        $this->getJson('/api/orders/'.$order->id.'/rating', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.can_rate', true)
            ->assertJsonPath('data.has_rated', false);

        $this->postJson('/api/orders/'.$order->id.'/rate', ['rating' => 4, 'review' => 'Good'], $this->bearer($user))->assertOk();

        $this->getJson('/api/orders/'.$order->id.'/rating', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.has_rated', true)
            ->assertJsonPath('data.service_rating.rating', 4);

        $this->getJson('/api/orders/'.$order->id.'/track', $this->bearer($user))
            ->assertOk()
            ->assertJsonPath('data.service_rating.has_rated', true)
            ->assertJsonPath('data.service_rating.service_rating.rating', 4);
    }
}
