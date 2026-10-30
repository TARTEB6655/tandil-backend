<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProductShowPublicKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_shop_product_key_resolves_by_id_not_handle_collision(): void
    {
        $category = Category::factory()->create();

        $canonical = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => null,
            'name' => 'service price',
            'type' => 'service',
            'status' => 'active',
            'handle' => 'service-price-317',
            'sku' => 'SVC-317',
        ]);

        $other = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => null,
            'name' => 'Wrong collision product',
            'type' => 'product',
            'status' => 'active',
            'handle' => (string) $canonical->id,
            'sku' => (string) $canonical->id,
        ]);

        $this->getJson('/api/shop/products/'.$canonical->id)
            ->assertOk()
            ->assertJsonPath('data.id', $canonical->id)
            ->assertJsonPath('data.name', 'service price');

        $this->assertNotSame($other->id, $canonical->id);
    }

    public function test_non_numeric_shop_product_key_resolves_by_handle(): void
    {
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'vendor_id' => null,
            'name' => 'By Handle Product',
            'status' => 'active',
            'handle' => 'my-custom-handle',
        ]);

        $this->getJson('/api/shop/products/my-custom-handle')
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'By Handle Product');
    }
}
