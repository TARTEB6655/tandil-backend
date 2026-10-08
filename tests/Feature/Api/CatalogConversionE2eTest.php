<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end checks for category ↔ service catalog conversion APIs.
 */
class CatalogConversionE2eTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('e2e')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    /** @return array<string> */
    private function adminServiceResponseKeys(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'image',
            'image_url',
            'icon',
            'is_active',
            'coming_soon',
            'category_id',
            'category',
            'sort_order',
            'pricing_type',
            'price',
            'price_includes',
            'created_at',
            'updated_at',
            'products_count',
        ];
    }

    /** @return array<string> */
    private function adminCategoryResponseKeys(): array
    {
        return [
            'id',
            'name',
            'slug',
            'description',
            'image',
            'image_url',
            'is_active',
            'coming_soon',
            'sort_order',
            'shipping_cost',
            'tax_percentage',
            'shipping_amount',
            'created_at',
            'updated_at',
        ];
    }

    public function test_category_convert_to_service_e2e_response_and_side_effects(): void
    {
        $category = Category::factory()->create([
            'name' => 'Irrigation Parts',
            'slug' => 'irrigation-parts',
            'description' => 'Hoses and sprinklers',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'product',
            'requires_shipping' => true,
        ]);

        $response = $this->postJson(
            '/api/admin/categories/'.$category->id.'/convert-to-service',
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Category converted to service successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => $this->adminServiceResponseKeys(),
            ])
            ->assertJsonPath('data.name', 'Irrigation Parts')
            ->assertJsonPath('data.slug', 'irrigation-parts')
            ->assertJsonPath('data.description', 'Hoses and sprinklers')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.coming_soon', false)
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.products_count', 1);

        $serviceId = (int) $response->json('data.id');
        $this->assertGreaterThan(0, $serviceId);

        $getService = $this->getJson('/api/admin/services/'.$serviceId, $this->authHeaders());
        $getService->assertOk()
            ->assertJsonPath('data.id', $serviceId)
            ->assertJsonPath('data.slug', 'irrigation-parts');

        $product->refresh();
        $this->assertSame('service', $product->type);
        $this->assertFalse((bool) $product->requires_shipping);
        $this->assertTrue(
            $product->services()->whereKey($serviceId)->exists()
        );

        $category->refresh();
        $this->assertFalse((bool) $category->is_active);
    }

    public function test_service_convert_to_category_e2e_response_and_shop_defaults(): void
    {
        Setting::set('shop_shipping_amount', '18.5', 'text', 'shop');
        Setting::set('shop_tax_percent', '7', 'text', 'shop');

        $service = Service::factory()->create([
            'name' => 'Pool Cleaning',
            'slug' => 'pool-cleaning',
            'description' => 'Weekly pool service',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $oldCategory = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $oldCategory->id,
            'type' => 'service',
            'requires_shipping' => false,
        ]);
        $product->services()->attach($service->id);

        $response = $this->postJson(
            '/api/admin/services/'.$service->id.'/convert-to-category',
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Service converted to category successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => $this->adminCategoryResponseKeys(),
            ])
            ->assertJsonPath('data.name', 'Pool Cleaning')
            ->assertJsonPath('data.slug', 'pool-cleaning')
            ->assertJsonPath('data.description', 'Weekly pool service')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.coming_soon', false)
            ->assertJsonPath('data.sort_order', 2)
            ->assertJsonPath('data.shipping_cost', 18.5)
            ->assertJsonPath('data.tax_percentage', 7)
            ->assertJsonPath('data.shipping_amount', 18.5);

        $newCategoryId = (int) $response->json('data.id');
        $this->assertFalse(Service::whereKey($service->id)->exists());

        $getCategory = $this->getJson('/api/admin/categories/'.$newCategoryId, $this->authHeaders());
        $getCategory->assertOk()
            ->assertJsonPath('data.id', $newCategoryId)
            ->assertJsonPath('data.shipping_cost', 18.5);

        $product->refresh();
        $this->assertSame('product', $product->type);
        $this->assertSame($newCategoryId, (int) $product->category_id);
        $this->assertTrue((bool) $product->requires_shipping);
        $this->assertCount(0, $product->services);
    }

    public function test_convert_endpoints_require_admin_auth(): void
    {
        $category = Category::factory()->create();
        $service = Service::factory()->create();

        $this->postJson('/api/admin/categories/'.$category->id.'/convert-to-service', [])
            ->assertUnauthorized();

        $this->postJson('/api/admin/services/'.$service->id.'/convert-to-category', [])
            ->assertUnauthorized();
    }

    public function test_convert_returns_404_for_missing_ids(): void
    {
        $this->postJson('/api/admin/categories/99999/convert-to-service', [], $this->authHeaders())
            ->assertNotFound();

        $this->postJson('/api/admin/services/99999/convert-to-category', [], $this->authHeaders())
            ->assertNotFound();
    }
}
