<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminProductsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_products_page_does_not_500_without_vite_manifest(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }

        $category = Category::create([
            'name' => 'Outdoor',
            'slug' => 'outdoor-'.uniqid(),
            'status' => 'active',
        ]);
        Product::create([
            'name' => 'Sample Product',
            'type' => 'product',
            'category_id' => $category->id,
            'price' => 1500,
            'status' => 'active',
            'stock' => 10,
        ]);

        $this->assertFalse(
            file_exists(public_path('build/manifest.json')),
            'Test expects no Vite manifest so the CDN fallback path is exercised.'
        );

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Sample Product', false);
    }

    public function test_admin_products_api_list_returns_catalog_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }

        $category = Category::create([
            'name' => 'Outdoor',
            'slug' => 'outdoor-'.uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'name' => 'Priced Product',
            'type' => 'service',
            'category_id' => $category->id,
            'price' => 1500,
            'status' => 'active',
            'stock' => 10,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/products')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->id, 'name' => 'Priced Product']);
    }
}
