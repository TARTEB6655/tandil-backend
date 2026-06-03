<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductDetailOptionGroupsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_product_detail_includes_full_option_groups_with_image_urls(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $imagePath = 'product-options/detail-test.jpg';
        Storage::disk('public')->put($imagePath, 'img');

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Cutting',
            'subtitle' => 'Required - Select one',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $option = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Arabic cut',
            'subtitle' => 'Free',
            'price_modifier' => 5,
            'image_path' => $imagePath,
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/admin/products/{$product->id}", [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.product_type', 'variable');
        $response->assertJsonPath('data.option_groups.0.id', $group->id);
        $response->assertJsonPath('data.option_groups.0.name', 'Cutting');
        $response->assertJsonPath('data.option_groups.0.subtitle', 'Required - Select one');
        $response->assertJsonPath('data.option_groups.0.options.0.id', $option->id);
        $response->assertJsonPath('data.option_groups.0.options.0.temp_key', 'opt_'.$option->id);
        $response->assertJsonPath('data.option_groups.0.options.0.label', 'Arabic cut');
        $response->assertJsonPath('data.option_groups.0.options.0.subtitle', 'Free');
        $response->assertJsonPath('data.option_groups.0.options.0.image_path', $imagePath);
        $this->assertNotEmpty($response->json('data.option_groups.0.options.0.image_url'));
        $this->assertStringContainsString('product-options/', $response->json('data.option_groups.0.options.0.image_url'));
    }

    public function test_shop_product_detail_includes_option_groups_and_images(): void
    {
        Storage::fake('public');

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $imagePath = 'product-options/shop-detail.jpg';
        Storage::disk('public')->put($imagePath, 'img');

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging',
            'subtitle' => 'Pick one',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'In box',
            'price_modifier' => 10,
            'image_path' => $imagePath,
            'sort_order' => 0,
        ]);

        $response = $this->getJson("/api/shop/products/{$product->id}", [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.option_groups.0.name', 'Packaging');
        $response->assertJsonPath('data.option_groups.0.options.0.temp_key', fn ($v) => str_starts_with((string) $v, 'opt_'));
        $this->assertNotEmpty($response->json('data.option_groups.0.options.0.image_url'));
    }
}
