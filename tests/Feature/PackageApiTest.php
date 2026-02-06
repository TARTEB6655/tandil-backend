<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    public function test_customer_can_list_active_packages(): void
    {
        Package::factory()->count(2)->create(['is_active' => true]);
        Package::factory()->create(['is_active' => false]);

        $response = $this->getJson('/api/packages', ['Accept' => 'application/json']);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        foreach ($data as $p) {
            $this->assertArrayHasKey('id', $p);
            $this->assertArrayHasKey('name', $p);
            $this->assertArrayHasKey('type', $p);
            $this->assertArrayHasKey('price', $p);
            $this->assertArrayHasKey('image_url', $p);
        }
    }

    public function test_shop_packages_alias_returns_same_as_packages(): void
    {
        Package::factory()->create(['name' => 'Test Package', 'is_active' => true]);

        $r1 = $this->getJson('/api/packages', ['Accept' => 'application/json']);
        $r2 = $this->getJson('/api/shop/packages', ['Accept' => 'application/json']);

        $r1->assertStatus(200);
        $r2->assertStatus(200);
        $this->assertSame($r1->json('data'), $r2->json('data'));
    }

    public function test_admin_can_list_packages_with_orders_count(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $package = Package::factory()->create(['name' => 'Combined']);
        $user = User::factory()->create();
        Order::factory()->count(3)->create(['package_id' => $package->id, 'user_id' => $user->id]);

        $response = $this->getJson('/api/admin/packages', [
            'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $found = collect($data)->firstWhere('id', $package->id);
        $this->assertNotNull($found);
        $this->assertArrayHasKey('orders_count', $found);
        $this->assertSame(3, $found['orders_count']);
    }

    public function test_admin_can_create_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        $response = $this->postJson('/api/admin/packages', [
            'name' => 'Fruit Basket',
            'type' => 'fruit',
            'price' => 29.99,
            'description' => 'Fresh fruits',
        ], [
            'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Fruit Basket');
        $response->assertJsonPath('data.type', 'fruit');
        $response->assertJsonPath('data.price', 29.99);
        $this->assertDatabaseHas('packages', ['name' => 'Fruit Basket']);
    }

    public function test_admin_can_update_package(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $package = Package::factory()->create(['price' => 20, 'name' => 'Veg']);

        $response = $this->putJson('/api/admin/packages/' . $package->id, [
            'name' => 'Vegetable Basket',
            'price' => 24.50,
        ], [
            'Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Vegetable Basket');
        $response->assertJsonPath('data.price', 24.50);
        $package->refresh();
        $this->assertSame(24.50, (float) $package->price);
    }

    public function test_admin_packages_require_auth(): void
    {
        $response = $this->getJson('/api/admin/packages', ['Accept' => 'application/json']);
        $response->assertStatus(401);
    }
}
