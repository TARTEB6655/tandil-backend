<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariableProductCheckoutPricingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'client']);
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    /**
     * @return array{product: Product, option_premium: ProductOption}
     */
    private function createVariableProductWithOptions(): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
            'status' => 'active',
            'product_type' => 'variable',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Standard',
            'price_modifier' => 0,
            'sort_order' => 0,
        ]);

        $premium = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'Premium box',
            'price_modifier' => 25,
            'sort_order' => 1,
        ]);

        return ['product' => $product, 'option_premium' => $premium];
    }

    public function test_shop_cart_add_stores_unit_price_with_option_modifier(): void
    {
        ['product' => $product, 'option_premium' => $premium] = $this->createVariableProductWithOptions();

        $response = $this->postJson('/api/shop/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
            'option_ids' => [$premium->id],
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('data.current_price', 125);
        $response->assertJsonPath('data.line_total', 125);

        $this->assertDatabaseHas('carts', [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'unit_price' => 125,
        ]);
    }

    public function test_order_summary_includes_option_price_in_subtotal(): void
    {
        ['product' => $product, 'option_premium' => $premium] = $this->createVariableProductWithOptions();

        Cart::create([
            'user_id' => $this->user->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'selected_options' => [$premium->id],
            'unit_price' => 125,
        ]);

        $response = $this->getJson('/api/shop/order-summary', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertSame(250.0, (float) $response->json('data.subtotal'));
    }

    public function test_buy_now_order_summary_includes_option_ids_in_subtotal(): void
    {
        ['product' => $product, 'option_premium' => $premium] = $this->createVariableProductWithOptions();

        $response = $this->getJson(
            '/api/shop/order-summary?product_id='.$product->id.'&quantity=1&option_ids[]='.$premium->id,
            $this->authHeaders()
        );

        $response->assertStatus(200);
        $this->assertSame(125.0, (float) $response->json('data.subtotal'));
    }

    public function test_buy_now_summary_accepts_selected_option_ids_alias(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 100,
            'status' => 'active',
            'product_type' => 'variable',
        ]);

        $packaging = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging type',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);
        $packOpt = ProductOption::create([
            'product_option_group_id' => $packaging->id,
            'label' => 'In box',
            'price_modifier' => 5,
            'sort_order' => 0,
        ]);

        $cutting = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Cutting',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 1,
        ]);
        $cutOpt = ProductOption::create([
            'product_option_group_id' => $cutting->id,
            'label' => 'Arabic cut',
            'price_modifier' => 0,
            'sort_order' => 0,
        ]);

        $response = $this->postJson('/api/shop/buy-now/summary', [
            'product_id' => $product->id,
            'quantity' => 1,
            'selected_option_ids' => [$packOpt->id, $cutOpt->id],
            'use_wallet' => false,
            'wallet_amount' => 0,
        ], $this->authHeaders());

        $response->assertOk();
        $this->assertSame(105.0, (float) $response->json('data.order_summary.subtotal'));
    }
}
