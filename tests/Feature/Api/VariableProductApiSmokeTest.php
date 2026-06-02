<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VariableProductApiSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function authJson(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ], $headers);
    }

    public function test_variable_product_admin_flow_uses_existing_product_crud_with_optional_fields(): void
    {
        $category = Category::factory()->create();
        $create = $this->postJson('/api/admin/products', [
            'name' => 'Smoke Variable Product',
            'description' => 'Variable through old CRUD endpoint',
            'price' => 100,
            'stock' => 20,
            'status' => 'active',
            'category_id' => $category->id,
            'product_type' => 'variable',
            'option_groups_json' => json_encode([
                [
                    'name' => 'Packaging type',
                    'subtitle' => 'Required - Select one',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'In bag', 'subtitle' => 'Free', 'price_modifier' => 0],
                        ['label' => 'In box', 'subtitle' => 'Premium', 'price_modifier' => 5],
                    ],
                ],
            ]),
        ], $this->authJson());

        $create->assertStatus(201);
        $create->assertJsonPath('status', true);
        $productId = $create->json('data.id');
        $this->assertNotNull($productId);

        // POST response should also include variable option groups (developer reported missing in POST).
        $create->assertJsonPath('data.option_groups.0.name', 'Packaging type');
        $create->assertJsonPath('data.option_groups.0.options.0.label', 'In bag');

        // Admin GET should include variable option groups (group/option name + images + price modifier).
        $adminShow = $this->getJson("/api/admin/products/{$productId}", $this->authJson());
        $adminShow->assertStatus(200);
        $adminShow->assertJsonPath('status', true);
        $adminShow->assertJsonPath('data.option_groups.0.name', 'Packaging type');
        $adminShow->assertJsonPath('data.option_groups.0.options.0.label', 'In bag');

        $show = $this->getJson("/api/shop/products/{$productId}", ['Accept' => 'application/json']);
        $show->assertStatus(200);
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.product_type', 'variable');
        $show->assertJsonPath('data.option_groups.0.name', 'Packaging type');
        $show->assertJsonPath('data.option_groups.0.options.0.label', 'In bag');
    }

    public function test_public_product_details_include_variable_option_groups_field(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $this->putJson("/api/admin/products/{$product->id}", [
            'product_type' => 'variable',
            'option_groups_json' => json_encode([
                [
                    'name' => 'Packing',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'Foam', 'price_modifier' => 10],
                    ],
                ],
            ]),
        ], $this->authJson())->assertStatus(200);

        $show = $this->getJson("/api/shop/products/{$product->id}", ['Accept' => 'application/json']);
        $show->assertStatus(200);
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.product_type', 'variable');
        $show->assertJsonCount(1, 'data.option_groups');

        // Sanity check: admin GET also returns option_groups (app developer reported it missing).
        $adminShow = $this->getJson("/api/admin/products/{$product->id}", $this->authJson());
        $adminShow->assertStatus(200);
        $adminShow->assertJsonPath('status', true);
        $adminShow->assertJsonPath('data.product_type', 'variable');
        $adminShow->assertJsonCount(1, 'data.option_groups');
    }
}

