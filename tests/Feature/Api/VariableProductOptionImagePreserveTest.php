<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VariableProductOptionImagePreserveTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    private function authJson(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    /**
     * @return array{product: Product, option: ProductOption, image_path: string}
     */
    private function seedVariableProductWithOptionImage(): array
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging type',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $imagePath = 'product-options/existing-box.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image');

        $option = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'In box',
            'price_modifier' => 5,
            'image_path' => $imagePath,
            'sort_order' => 0,
        ]);

        return ['product' => $product, 'option' => $option, 'image_path' => $imagePath];
    }

    public function test_partial_update_without_option_groups_json_preserves_option_images(): void
    {
        ['product' => $product, 'image_path' => $imagePath] = $this->seedVariableProductWithOptionImage();

        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Updated product title only',
        ], $this->authJson())->assertOk();

        $this->assertDatabaseHas('product_options', [
            'product_option_group_id' => ProductOptionGroup::where('product_id', $product->id)->value('id'),
            'label' => 'In box',
            'image_path' => $imagePath,
        ]);
    }

    public function test_update_with_option_ids_but_no_image_path_preserves_existing_images(): void
    {
        ['product' => $product, 'option' => $option, 'image_path' => $imagePath] = $this->seedVariableProductWithOptionImage();
        $groupId = $option->product_option_group_id;

        $this->putJson("/api/admin/products/{$product->id}", [
            'name' => 'Najdi Sheep',
            'price' => 1030,
            'option_groups_json' => json_encode([
                [
                    'id' => $groupId,
                    'name' => 'Packaging type',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        [
                            'id' => $option->id,
                            'label' => 'In box',
                            'price_modifier' => 5,
                        ],
                    ],
                ],
            ]),
        ], $this->authJson())->assertOk();

        $this->assertDatabaseHas('product_options', [
            'label' => 'In box',
            'image_path' => $imagePath,
        ]);
    }

    public function test_update_with_image_url_only_preserves_option_image_path(): void
    {
        ['product' => $product, 'option' => $option, 'image_path' => $imagePath] = $this->seedVariableProductWithOptionImage();

        $imageUrl = 'https://example.com/media/'.$imagePath;

        $this->putJson("/api/admin/products/{$product->id}", [
            'option_groups_json' => json_encode([
                [
                    'name' => 'Packaging type',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        [
                            'id' => $option->id,
                            'label' => 'In box',
                            'image_url' => $imageUrl,
                            'price_modifier' => 5,
                        ],
                    ],
                ],
            ]),
        ], $this->authJson())->assertOk();

        $this->assertDatabaseHas('product_options', [
            'label' => 'In box',
            'image_path' => $imagePath,
        ]);
    }

    public function test_new_option_image_upload_replaces_only_that_option(): void
    {
        ['product' => $product, 'option' => $option, 'image_path' => $oldPath] = $this->seedVariableProductWithOptionImage();

        $newFile = UploadedFile::fake()->image('new-option.jpg');

        $this->call(
            'PUT',
            "/api/admin/products/{$product->id}",
            [
                'option_groups_json' => json_encode([
                    [
                        'name' => 'Packaging type',
                        'input_type' => 'single',
                        'is_required' => true,
                        'options' => [
                            [
                                'id' => $option->id,
                                'temp_key' => 'opt_'.$option->id,
                                'label' => 'In box',
                                'price_modifier' => 5,
                            ],
                        ],
                    ],
                ]),
            ],
            [],
            ['option_images' => ['opt_'.$option->id => $newFile]],
            $this->transformHeadersToServerVars($this->authJson())
        )->assertOk();

        $freshPath = ProductOption::where('label', 'In box')->value('image_path');
        $this->assertNotNull($freshPath);
        $this->assertNotSame($oldPath, $freshPath);
        Storage::disk('public')->assertExists($freshPath);
    }
}
