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

/**
 * End-to-end smoke tests for variable product update API (option images).
 */
class VariableProductUpdateE2ESmokeTest extends TestCase
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

    public function test_e2e_create_update_and_preserve_option_images_via_api(): void
    {
        $category = Category::factory()->create();
        $optionFile = UploadedFile::fake()->image('foam.jpg');

        $create = $this->call(
            'POST',
            '/api/admin/products',
            [
                'name' => 'E2E Variable',
                'price' => 500,
                'stock' => 10,
                'status' => 'active',
                'category_id' => $category->id,
                'product_type' => 'variable',
                'option_groups_json' => json_encode([
                    [
                        'name' => 'Cutting',
                        'input_type' => 'single',
                        'is_required' => true,
                        'options' => [
                            [
                                'temp_key' => 'opt_new_foam',
                                'label' => 'Foam',
                                'price_modifier' => 0,
                            ],
                        ],
                    ],
                ]),
            ],
            [],
            ['option_images' => ['opt_new_foam' => $optionFile]],
            $this->transformHeadersToServerVars($this->authJson())
        );

        $create->assertCreated();
        $productId = $create->json('data.id');
        $this->assertNotNull($productId);

        $show = $this->getJson("/api/admin/products/{$productId}", $this->authJson());
        $show->assertOk();
        $optionFromShow = $show->json('data.option_groups.0.options.0');
        $this->assertSame('Foam', $optionFromShow['label']);
        $this->assertNotEmpty($optionFromShow['image_url']);
        $this->assertNotEmpty($optionFromShow['image_path']);
        $this->assertNotEmpty($optionFromShow['temp_key']);

        $optionId = $optionFromShow['id'];
        $groupId = $show->json('data.option_groups.0.id');
        $savedImagePath = $optionFromShow['image_path'];

        // Partial update (no option JSON) must keep image
        $this->putJson("/api/admin/products/{$productId}", [
            'name' => 'E2E Variable Renamed',
        ], $this->authJson())->assertOk();

        $this->assertDatabaseHas('product_options', [
            'id' => $optionId,
            'label' => 'Foam',
            'image_path' => $savedImagePath,
        ]);

        // Update options JSON without image_path must keep image (upsert by id)
        $this->putJson("/api/admin/products/{$productId}", [
            'option_groups_json' => json_encode([
                [
                    'id' => $groupId,
                    'name' => 'Cutting',
                    'input_type' => 'single',
                    'is_required' => true,
                    'options' => [
                        [
                            'id' => $optionId,
                            'label' => 'Foam',
                            'price_modifier' => 10,
                        ],
                    ],
                ],
            ]),
        ], $this->authJson())->assertOk();

        $this->assertDatabaseHas('product_options', [
            'id' => $optionId,
            'image_path' => $savedImagePath,
            'price_modifier' => 10,
        ]);

        // Replace option image via POST multipart (recommended for files)
        $newFile = UploadedFile::fake()->image('foam-updated.jpg');
        $this->call(
            'POST',
            "/api/admin/products/{$productId}",
            [
                'option_groups_json' => json_encode([
                    [
                        'id' => $groupId,
                        'name' => 'Cutting',
                        'input_type' => 'single',
                        'is_required' => true,
                        'options' => [
                            [
                                'id' => $optionId,
                                'temp_key' => 'opt_'.$optionId,
                                'label' => 'Foam',
                                'price_modifier' => 10,
                            ],
                        ],
                    ],
                ]),
            ],
            [],
            ['option_images' => ['opt_'.$optionId => $newFile]],
            $this->transformHeadersToServerVars($this->authJson())
        )->assertOk();

        $newPath = ProductOption::find($optionId)->image_path;
        $this->assertNotSame($savedImagePath, $newPath);
        Storage::disk('public')->assertExists($newPath);

        $final = $this->getJson("/api/admin/products/{$productId}", $this->authJson());
        $final->assertOk();
        $this->assertNotEmpty($final->json('data.option_groups.0.options.0.image_url'));
        $final->assertJsonPath('data.option_groups.0.options.0.id', $optionId);
    }

    public function test_e2e_add_new_option_with_image_keeps_existing_option_images(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'product_type' => 'variable',
            'status' => 'active',
        ]);

        $group = ProductOptionGroup::create([
            'product_id' => $product->id,
            'name' => 'Packaging',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $existingPath = 'product-options/bag.jpg';
        Storage::disk('public')->put($existingPath, 'x');

        $existing = ProductOption::create([
            'product_option_group_id' => $group->id,
            'label' => 'In bag',
            'price_modifier' => 0,
            'image_path' => $existingPath,
            'sort_order' => 0,
        ]);

        $newFile = UploadedFile::fake()->image('box.jpg');

        $this->call(
            'POST',
            "/api/admin/products/{$product->id}",
            [
                'product_type' => 'variable',
                'option_groups_json' => json_encode([
                    [
                        'id' => $group->id,
                        'name' => 'Packaging',
                        'input_type' => 'single',
                        'is_required' => true,
                        'options' => [
                            [
                                'id' => $existing->id,
                                'label' => 'In bag',
                                'price_modifier' => 0,
                                'image_path' => $existingPath,
                            ],
                            [
                                'temp_key' => 'opt_new_box',
                                'label' => 'In box',
                                'price_modifier' => 5,
                            ],
                        ],
                    ],
                ]),
            ],
            [],
            ['option_images' => ['opt_new_box' => $newFile]],
            $this->transformHeadersToServerVars($this->authJson())
        )->assertOk();

        $this->assertDatabaseHas('product_options', [
            'id' => $existing->id,
            'image_path' => $existingPath,
        ]);

        $newOption = ProductOption::where('label', 'In box')->first();
        $this->assertNotNull($newOption);
        $this->assertNotNull($newOption->image_path);
        $this->assertNotSame($existingPath, $newOption->image_path);
    }
}
