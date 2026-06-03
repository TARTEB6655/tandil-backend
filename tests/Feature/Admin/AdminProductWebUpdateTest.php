<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProductWebUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_recent_products_with_edit_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Dashboard Test Product',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard Test Product')
            ->assertSee(route('admin.products.edit', $product, false));
    }

    public function test_admin_can_update_variable_product_via_web_post_with_option_image(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Cutting',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $option = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Foam',
            'price_modifier' => 0,
            'sort_order' => 0,
        ]);

        $file = UploadedFile::fake()->image('foam-web.jpg');

        $response = $this->actingAs($admin)->post(route('admin.products.update.post', $product), [
            'name' => 'Updated from admin web',
            'price' => 99,
            'stock' => 5,
            'status' => 'active',
            'category_id' => $category->id,
            'product_type' => 'variable',
            'option_groups_json' => json_encode([
                [
                    'id' => $group->id,
                    'name' => 'Cutting',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        [
                            'id' => $option->id,
                            'temp_key' => 'opt_'.$option->id,
                            'label' => 'Foam',
                            'price_modifier' => 0,
                        ],
                    ],
                ],
            ]),
            'option_images' => [
                'opt_'.$option->id => $file,
            ],
        ]);

        $response->assertRedirect(route('admin.products.show', $product));
        $response->assertSessionHas('success');

        $option->refresh();
        $this->assertNotNull($option->image_path);
        Storage::disk('public')->assertExists($option->image_path);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated from admin web',
        ]);
    }

    public function test_products_index_has_edit_link(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Index Edit Product',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Index Edit Product')
            ->assertSee(route('admin.products.edit', $product, false));
    }
}
