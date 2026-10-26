<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Service;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceProductsListImagesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_products_list_uses_thumb_urls_and_omits_heavy_pricing_blocks(): void
    {
        ServiceAreaPricing::saveGlobal('per_m2', 7, [
            'materials' => true,
            'installation' => true,
            'labor' => false,
            'transportation' => false,
            'delivery' => false,
        ]);

        $category = Category::factory()->create();
        $service = Service::create([
            'name' => 'Landscaping',
            'slug' => 'landscaping-'.uniqid(),
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Paving Service',
            'type' => 'service',
            'category_id' => $category->id,
            'price' => 235,
            'status' => 'active',
            'stock' => 999,
            'description' => str_repeat('Long description. ', 40),
            'image' => 'list-main.jpg',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'list-main.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $service->products()->attach($product->id);

        $response = $this->getJson('/api/services/products?service_id='.$service->id);

        $response->assertOk()
            ->assertHeader('Cache-Control')
            ->assertJsonPath('data.data.0.price_per_m2', 7)
            ->assertJsonPath('data.data.0.price_label', 'AED 235');

        $item = $response->json('data.data.0');
        $this->assertStringContainsString('w=384', (string) ($item['image_url'] ?? ''));
        $this->assertStringContainsString('w=384', (string) ($item['image_thumb_url'] ?? ''));
        $this->assertNotEmpty($item['image_full_url'] ?? null);
        $this->assertStringNotContainsString('w=384', (string) ($item['image_full_url'] ?? 'w=384'));
        $this->assertArrayNotHasKey('customer_preview', $item);
        $this->assertArrayNotHasKey('price_includes', $item);
        $this->assertArrayNotHasKey('tree_pricing', $item);
        $this->assertLessThanOrEqual(203, strlen((string) ($item['description'] ?? '')));
    }

    public function test_services_products_view_detail_includes_full_pricing_blocks(): void
    {
        ServiceAreaPricing::saveGlobal('fixed', 50, ServiceAreaPricing::emptyIncludes());

        $category = Category::factory()->create();
        $service = Service::create([
            'name' => 'Build',
            'slug' => 'build-'.uniqid(),
            'is_active' => true,
        ]);
        $product = Product::create([
            'name' => 'Build Service',
            'type' => 'service',
            'category_id' => $category->id,
            'price' => 100,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($product->id);

        $this->getJson('/api/services/products?service_id='.$service->id.'&view=detail')
            ->assertOk()
            ->assertJsonPath('data.data.0.customer_preview.price_display', fn ($v) => is_string($v) && $v !== '');
    }
}
