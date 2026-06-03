<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\ProductOption;
use App\Models\ProductOptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Create API: variable product option_groups + options + images persist to DB.
 */
class VariableProductCreateOptionDataTest extends TestCase
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

    public function test_create_api_saves_full_option_group_and_option_data_with_image(): void
    {
        $category = Category::factory()->create();
        $optionFile = UploadedFile::fake()->image('arabic-cut.jpg');

        $response = $this->call(
            'POST',
            '/api/admin/products',
            [
                'name' => 'Najdi Sheep',
                'description' => 'Fresh cut',
                'price' => 149.99,
                'stock' => 20,
                'status' => 'active',
                'category_id' => $category->id,
                'product_type' => 'variable',
                'option_groups_json' => json_encode([
                    [
                        'name' => 'Cutting',
                        'subtitle' => 'Required - Select one',
                        'input_type' => 'single',
                        'is_required' => true,
                        'sort_order' => 0,
                        'options' => [
                            [
                                'temp_key' => 'opt_cut_1',
                                'label' => 'Arabic cut (8 pieces)',
                                'subtitle' => 'Free',
                                'price_modifier' => 0,
                                'sort_order' => 0,
                            ],
                            [
                                'temp_key' => 'opt_cut_2',
                                'label' => 'Standard cut',
                                'subtitle' => '+10 SAR',
                                'price_modifier' => 10,
                                'sort_order' => 1,
                            ],
                        ],
                    ],
                ]),
            ],
            [],
            [
                'option_images' => [
                    'opt_cut_1' => $optionFile,
                ],
            ],
            $this->transformHeadersToServerVars($this->authJson())
        );

        $response->assertCreated();
        $productId = $response->json('data.id');
        $groupId = $response->json('data.option_groups.0.id');
        $option1Id = $response->json('data.option_groups.0.options.0.id');
        $option2Id = $response->json('data.option_groups.0.options.1.id');

        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'product_type' => 'variable',
            'name' => 'Najdi Sheep',
        ]);

        $this->assertDatabaseHas('product_option_groups', [
            'id' => $groupId,
            'product_id' => $productId,
            'name' => 'Cutting',
            'subtitle' => 'Required - Select one',
            'input_type' => 'single',
            'is_required' => true,
            'sort_order' => 0,
        ]);

        $option1 = ProductOption::find($option1Id);
        $this->assertNotNull($option1);
        $this->assertSame($groupId, $option1->product_option_group_id);
        $this->assertSame('Arabic cut (8 pieces)', $option1->label);
        $this->assertSame('Free', $option1->subtitle);
        $this->assertSame(0.0, (float) $option1->price_modifier);
        $this->assertSame(0, $option1->sort_order);
        $this->assertNotNull($option1->image_path);
        Storage::disk('public')->assertExists($option1->image_path);

        $this->assertDatabaseHas('product_options', [
            'id' => $option2Id,
            'product_option_group_id' => $groupId,
            'label' => 'Standard cut',
            'subtitle' => '+10 SAR',
            'price_modifier' => 10,
            'sort_order' => 1,
        ]);
        $this->assertNull(ProductOption::find($option2Id)->image_path);

        $response->assertJsonPath('data.option_groups.0.name', 'Cutting');
        $response->assertJsonPath('data.option_groups.0.subtitle', 'Required - Select one');
        $response->assertJsonPath('data.option_groups.0.options.0.label', 'Arabic cut (8 pieces)');
        $response->assertJsonPath('data.option_groups.0.options.0.subtitle', 'Free');
        $this->assertNotEmpty($response->json('data.option_groups.0.options.0.image_url'));
        $response->assertJsonPath('data.option_groups.0.options.0.temp_key', 'opt_'.$option1Id);

        $groupCount = ProductOptionGroup::where('product_id', $productId)->count();
        $optionCount = ProductOption::whereIn(
            'product_option_group_id',
            ProductOptionGroup::where('product_id', $productId)->pluck('id')
        )->count();
        $this->assertSame(1, $groupCount);
        $this->assertSame(2, $optionCount);
    }

    public function test_create_json_without_files_still_persists_option_text_fields(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/admin/products', [
            'name' => 'Simple variable',
            'price' => 50,
            'stock' => 5,
            'status' => 'active',
            'category_id' => $category->id,
            'product_type' => 'variable',
            'option_groups_json' => json_encode([
                [
                    'name' => 'Packaging',
                    'subtitle' => 'Pick one',
                    'input_type' => 'single',
                    'is_required' => false,
                    'options' => [
                        ['label' => 'Bag', 'subtitle' => 'Free', 'price_modifier' => 0],
                    ],
                ],
            ]),
        ], $this->authJson());

        $response->assertCreated();

        $this->assertDatabaseHas('product_option_groups', [
            'product_id' => $response->json('data.id'),
            'name' => 'Packaging',
            'subtitle' => 'Pick one',
            'is_required' => false,
        ]);

        $this->assertDatabaseHas('product_options', [
            'label' => 'Bag',
            'subtitle' => 'Free',
            'price_modifier' => 0,
            'image_path' => null,
        ]);
    }
}
