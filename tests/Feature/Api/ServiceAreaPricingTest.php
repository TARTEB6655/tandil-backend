<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceAreaPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);
        $this->category = Category::create([
            'name' => 'Outdoor',
            'slug' => 'outdoor-'.uniqid(),
            'status' => 'active',
        ]);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $this->admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_admin_can_set_per_m2_pricing_on_service_only(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/products', [
            'name' => 'Interlock Paving',
            'type' => 'service',
            'price' => 70,
            'pricing_type' => 'per_m2',
            'price_includes' => [
                'materials' => true,
                'installation' => true,
                'labor' => true,
                'transportation' => false,
                'delivery' => false,
            ],
            'status' => 'active',
            'stock' => 100,
            'category_id' => $this->category->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.requires_area', true)
            ->assertJsonPath('data.price', 70)
            ->assertJsonPath('data.price_per_m2', 70)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonPath('data.price_includes.delivery', false);

        $this->assertStringContainsString('/ m²', (string) $response->json('data.price_label'));
    }

    public function test_per_m2_rejected_for_non_service_products(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/products', [
            'name' => 'Garden Hose',
            'type' => 'product',
            'price' => 50,
            'pricing_type' => 'per_m2',
            'status' => 'active',
            'stock' => 10,
            'category_id' => $this->category->id,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['pricing_type']);
    }

    public function test_cart_requires_area_and_calculates_total(): void
    {
        $product = Product::create([
            'name' => 'Outdoor Paving',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 70,
            'pricing_type' => ServiceAreaPricing::TYPE_PER_M2,
            'price_includes' => [
                'materials' => true,
                'installation' => true,
                'labor' => true,
                'transportation' => false,
                'delivery' => false,
            ],
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $add = $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'required_area' => 100,
        ]);

        $add->assertCreated()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.required_area', 100)
            ->assertJsonPath('data.line_total', 7000)
            ->assertJsonPath('data.quantity', 1);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'required_area' => 137.5,
        ])->assertCreated()
            ->assertJsonPath('data.required_area', 137.5)
            ->assertJsonPath('data.line_total', 9625);
    }

    public function test_fixed_service_still_works_without_area(): void
    {
        $product = Product::create([
            'name' => 'Lawn Care Visit',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 7000,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->client, 'sanctum')->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.requires_area', false)
            ->assertJsonPath('data.line_total', 7000);
    }

    public function test_dedicated_product_settings_api_get_and_put(): void
    {
        $product = Product::create([
            'name' => 'Interlock',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 50,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/products/'.$product->id.'/settings')
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.is_service', true)
            ->assertJsonPath('data.settings_available', true)
            ->assertJsonPath('data.price_input_label', 'Fixed price');

        $update = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/products/'.$product->id.'/settings', [
                'pricing_type' => 'per_m2',
                'price' => 70,
                'price_includes' => [
                    'materials' => true,
                    'installation' => true,
                    'labor' => true,
                    'transportation' => false,
                    'delivery' => false,
                ],
            ]);

        $update->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 70)
            ->assertJsonPath('data.requires_area', true)
            ->assertJsonPath('data.price_input_suffix', 'AED / m²')
            ->assertJsonPath('data.example_calculation.total', 7000)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonPath('data.price_includes.delivery', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'pricing_type' => 'per_m2',
            'price' => 70,
        ]);

        // form-data (multipart) via POST — recommended for app / Postman
        $form = $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/products/'.$product->id.'/settings', [
                'pricing_type' => 'fixed',
                'price' => '7000',
                'price_includes' => [
                    'materials' => '1',
                    'installation' => '1',
                    'labor' => '1',
                    'transportation' => '0',
                    'delivery' => '0',
                ],
            ]);

        $form->assertOk()
            ->assertJsonPath('data.pricing_type', 'fixed')
            ->assertJsonPath('data.price', 7000)
            ->assertJsonPath('data.requires_area', false)
            ->assertJsonPath('data.price_includes.materials', true)
            ->assertJsonPath('data.price_includes.delivery', false);
    }

    public function test_service_pricing_settings_form_data_syncs_linked_products(): void
    {
        $service = \App\Models\Service::create([
            'name' => 'Interlock & Outdoor Paving',
            'slug' => 'interlock-'.uniqid(),
            'is_active' => true,
            'pricing_type' => 'fixed',
            'price' => 100,
        ]);

        $product = Product::create([
            'name' => 'Interlock Install',
            'type' => 'service',
            'category_id' => $this->category->id,
            'price' => 100,
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'status' => 'active',
            'stock' => 999,
        ]);
        $service->products()->attach($product->id);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/services/'.$service->id.'/settings')
            ->assertOk()
            ->assertJsonPath('data.service_id', $service->id)
            ->assertJsonPath('data.pricing_type', 'fixed');

        $this->actingAs($this->admin, 'sanctum')
            ->post('/api/admin/services/'.$service->id.'/settings', [
                'pricing_type' => 'per_m2',
                'price' => '70',
                'price_includes' => [
                    'materials' => '1',
                    'installation' => '1',
                    'labor' => '1',
                    'transportation' => '0',
                    'delivery' => '0',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.pricing_type', 'per_m2')
            ->assertJsonPath('data.price', 70)
            ->assertJsonPath('data.synced_products_count', 1)
            ->assertJsonPath('data.example_calculation.total', 7000);

        $product->refresh();
        $this->assertSame('per_m2', $product->pricing_type);
        $this->assertEquals(70.0, (float) $product->price);
        $this->assertTrue((bool) ($product->price_includes['materials'] ?? false));
        $this->assertFalse((bool) ($product->price_includes['delivery'] ?? true));
    }
}
