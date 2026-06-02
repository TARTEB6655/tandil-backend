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

    public function test_variable_product_admin_flow_sync_groups_create_variant_and_list(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $sync = $this->postJson("/api/admin/products/{$product->id}/option-groups/sync", [
            'groups' => [
                [
                    'name' => 'Packaging type',
                    'input_type' => 'single',
                    'is_required' => true,
                    'sort_order' => 0,
                    'options' => [
                        ['label' => 'In bag', 'price_modifier' => 0, 'sort_order' => 0],
                        ['label' => 'In box', 'price_modifier' => 5, 'sort_order' => 1],
                    ],
                ],
                [
                    'name' => 'Cutting',
                    'input_type' => 'single',
                    'is_required' => true,
                    'sort_order' => 1,
                    'options' => [
                        ['label' => 'Arabic 8 cuts', 'price_modifier' => 0, 'sort_order' => 0],
                        ['label' => 'Biryani large', 'price_modifier' => 0, 'sort_order' => 1],
                    ],
                ],
            ],
        ], $this->authJson());

        $sync->assertStatus(200);
        $sync->assertJsonPath('success', true);
        $sync->assertJsonCount(2, 'data');

        $listGroups = $this->getJson("/api/admin/products/{$product->id}/option-groups", $this->authJson());
        $listGroups->assertStatus(200);
        $listGroups->assertJsonPath('success', true);
        $listGroups->assertJsonCount(2, 'data');

        $groupsData = $listGroups->json('data');
        $packagingOptionId = $groupsData[0]['options'][0]['id'] ?? null;
        $cuttingOptionId = $groupsData[1]['options'][0]['id'] ?? null;
        $this->assertNotNull($packagingOptionId);
        $this->assertNotNull($cuttingOptionId);

        $createVariant = $this->postJson("/api/admin/products/{$product->id}/variants", [
            'sku' => 'SMOKE-VAR-001',
            'price' => 120.50,
            'stock' => 10,
            'is_default' => true,
            'label' => 'In bag + Arabic 8 cuts',
            'option_ids' => [$packagingOptionId, $cuttingOptionId],
        ], $this->authJson());

        $createVariant->assertStatus(201);
        $createVariant->assertJsonPath('success', true);
        $variantId = $createVariant->json('data.id');
        $this->assertNotNull($variantId);

        $listVariants = $this->getJson("/api/admin/products/{$product->id}/variants", $this->authJson());
        $listVariants->assertStatus(200);
        $listVariants->assertJsonPath('success', true);
        $listVariants->assertJsonCount(1, 'data');
        $listVariants->assertJsonPath('data.0.id', $variantId);
    }

    public function test_public_product_details_include_variable_option_groups_field(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $this->postJson("/api/admin/products/{$product->id}/option-groups/sync", [
            'groups' => [
                [
                    'name' => 'Packing',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        ['label' => 'Foam', 'price_modifier' => 10],
                    ],
                ],
            ],
        ], $this->authJson())->assertStatus(200);

        $show = $this->getJson("/api/shop/products/{$product->id}", ['Accept' => 'application/json']);
        $show->assertStatus(200);
        $show->assertJsonPath('success', true);
        $show->assertJsonPath('data.product_type', 'variable');
        $show->assertJsonCount(1, 'data.option_groups');
    }
}

