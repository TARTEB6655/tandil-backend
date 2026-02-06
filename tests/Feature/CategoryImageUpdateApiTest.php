<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryImageUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    public function test_admin_can_update_category_image_via_post_multipart(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create(['name' => 'With Image', 'image' => null]);

        $file = UploadedFile::fake()->image('cat.jpg', 100, 100);

        $response = $this->post(
            '/api/admin/categories/' . $category->id,
            [
                'name' => 'With Image',
                'image' => $file,
            ],
            [
                'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertNotEmpty($data['image']);
        $this->assertStringContainsString('categories/', $data['image']);
        $this->assertArrayHasKey('image_url', $data);
        $this->assertNotEmpty($data['image_url']);
        $category->refresh();
        $this->assertNotNull($category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_admin_can_remove_category_image(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $category = Category::factory()->create([
            'name' => 'Remove Image',
            'image' => 'categories/existing.png',
        ]);
        Storage::disk('public')->put('categories/existing.png', 'fake');

        $response = $this->postJson(
            '/api/admin/categories/' . $category->id,
            ['image_remove' => true],
            [
                'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.image', null);
        $category->refresh();
        $this->assertNull($category->image);
    }

    public function test_admin_category_update_requires_auth(): void
    {
        $category = Category::factory()->create(['name' => 'Public']);

        $response = $this->postJson('/api/admin/categories/' . $category->id, [
            'name' => 'Updated',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }
}
