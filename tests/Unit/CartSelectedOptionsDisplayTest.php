<?php

namespace Tests\Unit;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartSelectedOptionsDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_selected_options_display_includes_group_label_image_and_modifier(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'product_type' => 'variable',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging type',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $option = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'In box',
            'price_modifier' => 5,
            'image_path' => 'product-options/test.jpg',
            'sort_order' => 0,
        ]);

        $product->load('optionGroups.options');
        $lines = Cart::resolveSelectedOptionsDisplay($product, [$option->id]);

        $this->assertCount(1, $lines);
        $this->assertSame('Packaging type', $lines[0]['group_name']);
        $this->assertSame('In box', $lines[0]['label']);
        $this->assertSame(5.0, $lines[0]['price_modifier']);
        $this->assertNotNull($lines[0]['image_url']);
    }

    public function test_calculate_unit_price_includes_option_modifiers(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1030,
            'product_type' => 'variable',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $paid = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'In box',
            'price_modifier' => 5,
            'sort_order' => 0,
        ]);

        $cuttingGroup = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Cutting',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $cut = ProductOption::create([
            'product_option_group_id' => $cuttingGroup->id,
            'label' => 'Arabic cut',
            'price_modifier' => 0,
            'sort_order' => 0,
        ]);

        $unit = Cart::calculateUnitPrice($product, [$paid->id, $cut->id]);

        $this->assertSame(1035.0, $unit);
    }
}
