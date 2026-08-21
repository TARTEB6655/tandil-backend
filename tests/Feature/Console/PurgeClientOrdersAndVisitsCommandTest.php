<?php

namespace Tests\Feature\Console;

use App\Models\Area;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurgeClientOrdersAndVisitsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_purge_deletes_all_visits_and_orders(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $area = Area::factory()->create(['is_active' => true]);
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'status' => 'active']);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'order_status' => 'processing',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10,
            'subtotal' => 10,
        ]);
        Visit::create([
            'area_id' => $area->id,
            'order_id' => $order->id,
            'status' => 'pending',
            'notes' => 'Recreated from Order #'.$order->id,
            'scheduled_date' => now()->toDateString(),
        ]);

        $this->artisan('clients:purge-orders-visits', ['--force' => true])
            ->assertSuccessful();

        $this->assertSame(0, Visit::query()->count());
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, OrderItem::query()->count());
        $this->assertDatabaseHas('users', ['id' => $client->id]);
    }

    public function test_dry_run_does_not_delete(): void
    {
        Order::factory()->create(['payment_status' => 'paid', 'order_status' => 'processing']);
        Visit::create([
            'status' => 'pending',
            'scheduled_date' => now()->toDateString(),
            'notes' => 'test',
        ]);

        $this->artisan('clients:purge-orders-visits', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(1, Visit::query()->count());
        $this->assertSame(1, Order::query()->count());
    }
}
