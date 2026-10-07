<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ServiceAreaPricing;
use App\Support\ServiceTreePricing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartAddWithoutTreeColumnsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        if (Schema::hasTable('carts') && ! Schema::hasColumn('carts', 'tree_quantity')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->unsignedInteger('tree_quantity')->nullable()->after('required_area');
                $table->unsignedInteger('palm_tree_quantity')->nullable()->after('tree_quantity');
            });
        }
        ServiceTreePricing::clearSchemaCache();
        parent::tearDown();
    }

    public function test_cart_add_succeeds_when_tree_columns_missing(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, ServiceAreaPricing::emptyIncludes());

        if (Schema::hasColumn('carts', 'tree_quantity')) {
            Schema::table('carts', function (Blueprint $table) {
                $table->dropColumn(['tree_quantity', 'palm_tree_quantity']);
            });
        }
        ServiceTreePricing::clearSchemaCache();
        $this->assertFalse(ServiceTreePricing::cartsTableReady());

        $client = User::factory()->create(['role' => 'client']);
        $category = Category::create([
            'name' => 'Outdoor',
            'slug' => 'outdoor-'.uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'name' => 'Service 1500',
            'type' => 'service',
            'category_id' => $category->id,
            'price' => 1500,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/shop/cart/add', [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.line_total', 1500)
            ->assertJsonPath('data.unit_price', 1500);
    }
}
