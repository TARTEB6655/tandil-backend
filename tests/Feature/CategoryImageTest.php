<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Admin can create category with image; response includes image and image_url.
     * Skipped when GD extension is not installed.
     */
    public function test_admin_can_create_category_with_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for image fake.');
        }
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('category.jpg', 100, 100);

        $response = $this->post('/api/auth/categories', [
            'name' => 'Test Category',
            'description' => 'Test description',
            'image' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Category')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'image',
                    'image_url',
                ],
            ]);

        $category = Category::first();
        $this->assertNotNull($category->image);
        $this->assertStringContainsString('categories/', $category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    /**
     * Admin can create category without image; image and image_url are null.
     */
    public function test_admin_can_create_category_without_image(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/auth/categories', [
            'name' => 'No Image Category',
            'description' => 'No image',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'No Image Category')
            ->assertJsonPath('data.image', null)
            ->assertJsonPath('data.image_url', null);

        $category = Category::first();
        $this->assertNull($category->image);
    }

    /**
     * Admin can update category with new image; old image is removed.
     * Skipped when GD extension is not installed.
     */
    public function test_admin_can_update_category_with_image(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is required for image fake.');
        }
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create([
            'name' => 'Original',
            'slug' => 'original',
            'image' => 'categories/old.jpg',
        ]);
        Storage::disk('public')->put('categories/old.jpg', 'fake');

        $file = UploadedFile::fake()->image('new-category.png', 100, 100);

        $response = $this->post('/api/auth/categories/' . $category->id, [
            '_method' => 'PUT',
            'name' => 'Updated Name',
            'slug' => 'updated-name',
            'image' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');
        $this->assertStringContainsString('categories/', $response->json('data.image') ?? '');

        $category->refresh();
        $this->assertNotNull($category->image);
        $this->assertNotSame('categories/old.jpg', $category->image);
        Storage::disk('public')->assertMissing('categories/old.jpg');
        Storage::disk('public')->assertExists($category->image);
    }

    /**
     * Category list/show includes image_url in response.
     */
    public function test_category_response_includes_image_url(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create([
            'name' => 'With Image',
            'slug' => 'with-image',
            'image' => 'categories/test.jpg',
        ]);
        Storage::disk('public')->put('categories/test.jpg', 'fake');

        $response = $this->getJson('/api/auth/categories/' . $category->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.image', 'categories/test.jpg')
            ->assertJsonStructure(['data' => ['image_url']]);
        $imageUrl = $response->json('data.image_url');
        $this->assertNotNull($imageUrl);
        $this->assertStringContainsString('/media/categories/test.jpg', $imageUrl);
    }

    /**
     * Delete category removes image file from storage.
     */
    public function test_delete_category_removes_image_file(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $category = Category::factory()->create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'image' => 'categories/delete-me.jpg',
        ]);
        Storage::disk('public')->put('categories/delete-me.jpg', 'fake');
        $id = $category->id;

        $response = $this->deleteJson('/api/auth/categories/' . $id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $id]);
        Storage::disk('public')->assertMissing('categories/delete-me.jpg');
    }

    /**
     * Unauthenticated user cannot create category.
     */
    public function test_unauthenticated_cannot_create_category(): void
    {
        $response = $this->postJson('/api/auth/categories', [
            'name' => 'Test',
            'description' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Non-admin cannot create category.
     */
    public function test_non_admin_cannot_create_category(): void
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $response = $this->postJson('/api/auth/categories', [
            'name' => 'Test',
            'description' => 'Test',
        ]);

        $response->assertStatus(403);
    }
}
