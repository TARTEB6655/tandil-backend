<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryComingSoonApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    public function test_shop_categories_include_is_active_and_coming_soon(): void
    {
        Category::factory()->create(['name' => 'Active Cat', 'is_active' => true]);
        Category::factory()->create(['name' => 'Coming Soon Cat', 'is_active' => false]);

        $response = $this->getJson('/api/shop/categories', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $active = collect($data)->firstWhere('name', 'Active Cat');
        $coming = collect($data)->firstWhere('name', 'Coming Soon Cat');
        $this->assertNotNull($active);
        $this->assertNotNull($coming);
        $this->assertArrayHasKey('is_active', $active);
        $this->assertArrayHasKey('coming_soon', $active);
        $this->assertTrue($active['is_active']);
        $this->assertFalse($active['coming_soon']);
        $this->assertFalse($coming['is_active']);
        $this->assertTrue($coming['coming_soon']);
    }

    public function test_admin_categories_include_is_active_and_coming_soon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        Category::factory()->create(['name' => 'Admin Active', 'is_active' => true]);
        Category::factory()->create(['name' => 'Admin Disabled', 'is_active' => false]);

        $response = $this->getJson('/api/admin/categories', [
            'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        if (isset($data['data'])) {
            $data = $data['data'];
        }
        $this->assertNotEmpty($data);
        $active = collect($data)->firstWhere('name', 'Admin Active');
        $disabled = collect($data)->firstWhere('name', 'Admin Disabled');
        $this->assertNotNull($active);
        $this->assertNotNull($disabled);
        $this->assertArrayHasKey('is_active', $active);
        $this->assertArrayHasKey('coming_soon', $active);
        $this->assertTrue($active['is_active']);
        $this->assertFalse($active['coming_soon']);
        $this->assertFalse($disabled['is_active']);
        $this->assertTrue($disabled['coming_soon']);
    }
}
