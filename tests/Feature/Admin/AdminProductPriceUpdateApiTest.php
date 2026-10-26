<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Support\ServiceAreaPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProductPriceUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_admin_product_list_and_update_return_catalog_price_not_customer_total(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');

        $category = Category::factory()->create();
        $service = Service::factory()->create([
            'name' => 'Inspection',
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'price' => 257,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'type' => 'service',
            'pricing_type' => ServiceAreaPricing::TYPE_FIXED,
            'price' => 100,
            'status' => 'active',
        ]);
        $product->services()->sync([$service->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('data.0.price', 100)
            ->assertJsonPath('data.0.customer_display_price', 357);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$product->id}", [
                'price' => 400,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', 400);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonPath('data.0.price', 400);
    }

    public function test_admin_product_update_accepts_unit_price_alias(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->assignRole('admin');
        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'type' => 'product',
            'price' => 140,
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/products/{$product->id}", [
                'unit_price' => 225,
            ])
            ->assertOk()
            ->assertJsonPath('data.price', 225);

        $this->assertSame(225.0, (float) $product->fresh()->price);
    }
}
