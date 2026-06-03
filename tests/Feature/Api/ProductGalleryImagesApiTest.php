<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductGalleryImagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_product_show_returns_main_image_and_gallery_images(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'image' => 'primary.jpg',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'primary.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'gallery-one.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'gallery-two.jpg',
            'sort_order' => 2,
            'is_primary' => false,
        ]);

        $response = $this->getJson("/api/shop/products/{$product->id}", ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.main_image.image_path', 'primary.jpg');
        $this->assertNotEmpty($response->json('data.main_image.image_url'));
        $response->assertJsonCount(2, 'data.gallery_images');
        $response->assertJsonPath('data.gallery_images.0.image_path', 'gallery-one.jpg');
        $response->assertJsonPath('data.gallery_images.1.image_path', 'gallery-two.jpg');
        $this->assertNotEmpty($response->json('data.gallery_images.0.image_url'));
        $this->assertNotEmpty($response->json('data.gallery_images.1.image_url'));
    }

    public function test_shop_products_list_includes_gallery_images_on_each_item(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'main-list.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'extra-list.jpg',
            'sort_order' => 1,
            'is_primary' => false,
        ]);

        $response = $this->getJson('/api/shop/products', ['Accept' => 'application/json']);

        $response->assertOk();
        $item = collect($response->json('data'))->firstWhere('id', $product->id);
        $this->assertNotNull($item);
        $this->assertSame('main-list.jpg', $item['main_image']['image_path'] ?? null);
        $this->assertCount(1, $item['gallery_images'] ?? []);
        $this->assertSame('extra-list.jpg', $item['gallery_images'][0]['image_path'] ?? null);
    }
}
