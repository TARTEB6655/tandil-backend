<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Local-only: dummy category product + dummy service product, then both convert APIs.
 */
class DummyCatalogConvertLocalTest extends TestCase
{
    use RefreshDatabase;

    public function test_dummy_category_product_convert_to_service_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$admin->createToken('local')->plainTextToken,
        ];

        $category = Category::factory()->create([
            'name' => 'Dummy Category Catalog',
            'slug' => 'dummy-category-catalog-'.uniqid(),
            'is_active' => true,
            'shipping_cost' => 25,
            'tax_percentage' => 5,
        ]);

        $product = Product::factory()->create([
            'name' => 'Dummy Category Product',
            'category_id' => $category->id,
            'type' => 'product',
            'requires_shipping' => true,
            'status' => 'active',
            'price' => 99,
            'stock' => 10,
        ]);

        $response = $this->postJson(
            '/api/admin/categories/'.$category->id.'/convert-to-service',
            [],
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Dummy Category Catalog')
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'slug', 'is_active', 'products_count'],
            ]);

        $serviceId = (int) $response->json('data.id');
        $this->assertGreaterThan(0, $serviceId);

        $product->refresh();
        $this->assertSame('service', $product->type);
        $this->assertFalse((bool) $product->requires_shipping);
        $this->assertTrue($product->services()->whereKey($serviceId)->exists());

        $category->refresh();
        $this->assertFalse((bool) $category->is_active);

        $this->getJson('/api/admin/services/'.$serviceId, $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $serviceId)
            ->assertJsonPath('data.products_count', 1);
    }

    public function test_dummy_service_product_convert_to_category_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$admin->createToken('local')->plainTextToken,
        ];

        $service = Service::factory()->create([
            'name' => 'Dummy Service Catalog',
            'slug' => 'dummy-service-catalog-'.uniqid(),
            'is_active' => true,
        ]);

        $oldCategory = Category::factory()->create(['name' => 'Placeholder category for dummy service product']);
        $product = Product::factory()->create([
            'name' => 'Dummy Service Product',
            'category_id' => $oldCategory->id,
            'type' => 'service',
            'requires_shipping' => false,
            'status' => 'active',
            'price' => 149,
            'stock' => 5,
        ]);
        $product->services()->attach($service->id);

        $response = $this->postJson(
            '/api/admin/services/'.$service->id.'/convert-to-category',
            [],
            $headers
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Dummy Service Catalog')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'slug', 'shipping_cost', 'tax_percentage', 'shipping_amount'],
            ]);

        $newCategoryId = (int) $response->json('data.id');
        $this->assertFalse(Service::whereKey($service->id)->exists());

        $product->refresh();
        $this->assertSame('product', $product->type);
        $this->assertSame($newCategoryId, (int) $product->category_id);
        $this->assertTrue((bool) $product->requires_shipping);
        $this->assertCount(0, $product->services);

        $this->getJson('/api/admin/categories/'.$newCategoryId, $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'Dummy Service Catalog');
    }
}
