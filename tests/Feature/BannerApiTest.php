<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\RolePermissionSeeder']);
    }

    protected function createAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-banner@test.com']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        return $admin;
    }

    protected function authHeaders(User $user): array
    {
        $token = $user->createToken('test')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json'];
    }

    // --- Public API: GET /api/banners ---

    public function test_public_banners_returns_200_and_structure(): void
    {
        $response = $this->getJson('/api/banners');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message', 'data']);
        $response->assertJsonPath('success', true);
        $this->assertIsArray($response->json('data'));
    }

    public function test_public_banners_returns_only_active_banners(): void
    {
        Banner::create([
            'title' => 'Active',
            'description' => 'Learn More',
            'button_text' => 'Learn More',
            'image' => 'banners/active.jpg',
            'priority' => 0,
            'is_active' => true,
        ]);
        Banner::create([
            'title' => 'Inactive',
            'image' => 'banners/inactive.jpg',
            'priority' => 1,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/banners');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Active', $data[0]['title']);
        $this->assertSame('Learn More', $data[0]['description']);
        $this->assertSame('Learn More', $data[0]['button_text']);
        $this->assertArrayHasKey('image_url', $data[0]);
        $this->assertArrayHasKey('action_type', $data[0]);
        $this->assertArrayHasKey('action_value', $data[0]);
        $this->assertArrayHasKey('priority', $data[0]);
    }

    // --- Admin API: auth required ---

    public function test_admin_banners_list_requires_auth(): void
    {
        $response = $this->getJson('/api/admin/banners', ['Accept' => 'application/json']);
        $response->assertStatus(401);
    }

    public function test_admin_banners_list_returns_all_banners(): void
    {
        $admin = $this->createAdmin();
        Banner::create([
            'title' => 'B1',
            'image' => 'banners/b1.jpg',
            'is_active' => true,
            'priority' => 0,
        ]);
        Banner::create([
            'title' => 'B2',
            'image' => 'banners/b2.jpg',
            'is_active' => false,
            'priority' => 1,
        ]);

        $response = $this->getJson('/api/admin/banners', $this->authHeaders($admin));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $first = collect($data)->firstWhere('title', 'B1');
        $this->assertNotNull($first);
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('image_url', $first);
        $this->assertArrayHasKey('is_active', $first);
        $this->assertArrayHasKey('priority', $first);
    }

    // --- Admin: Create ---

    public function test_admin_banners_create_requires_image(): void
    {
        $admin = $this->createAdmin();
        $response = $this->postJson('/api/admin/banners', [
            'title' => 'No Image',
        ], $this->authHeaders($admin));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['image']);
    }

    public function test_admin_banners_create_success(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $file = UploadedFile::fake()->image('banner.jpg', 800, 300);

        $response = $this->call(
            'POST',
            '/api/admin/banners',
            [
                'title' => 'Summer Sale',
                'description' => 'Learn More',
                'button_text' => 'Learn More',
                'button_link' => 'https://example.com/sale',
                'priority' => 0,
                'is_active' => 1,
            ],
            [],
            ['image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Banner created successfully.');
        $data = $response->json('data');
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Summer Sale', $data['title']);
        $this->assertSame('Learn More', $data['description']);
        $this->assertSame('Learn More', $data['button_text']);
        $this->assertSame('link', $data['action_type']);
        $this->assertSame('https://example.com/sale', $data['action_value']);
        $this->assertSame(0, $data['priority']);
        $this->assertTrue($data['is_active']);
        $this->assertNotEmpty($data['image']);
        $this->assertNotEmpty($data['image_url']);
        $this->assertStringContainsString('banners/', $data['image']);

        $banner = Banner::find($data['id']);
        $this->assertNotNull($banner);
        $this->assertTrue(Storage::disk('public')->exists($banner->image));
    }

    // --- Admin: Show ---

    public function test_admin_banners_show_success(): void
    {
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'Show Me',
            'image' => 'banners/show.jpg',
            'action_type' => 'none',
            'priority' => 2,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/admin/banners/' . $banner->id, $this->authHeaders($admin));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $banner->id);
        $response->assertJsonPath('data.title', 'Show Me');
        $response->assertJsonPath('data.action_type', 'none');
        $response->assertJsonPath('data.priority', 2);
    }

    public function test_admin_banners_show_404_for_missing(): void
    {
        $admin = $this->createAdmin();
        $response = $this->getJson('/api/admin/banners/99999', $this->authHeaders($admin));
        $response->assertStatus(404);
    }

    // --- Admin: Update (multipart, no new image) ---

    public function test_admin_banners_update_without_image_updates_fields(): void
    {
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'Original',
            'image' => 'banners/original.jpg',
            'link' => 'https://old.com',
            'action_type' => 'link',
            'action_value' => 'https://old.com',
            'priority' => 0,
            'is_active' => true,
        ]);

        $response = $this->call(
            'POST',
            '/api/admin/banners/' . $banner->id,
            [
                '_method' => 'PUT',
                'title' => 'Updated Title',
                'description' => 'Updated desc',
                'button_text' => 'View',
                'button_link' => '',
                'priority' => 5,
                'is_active' => 0,
            ],
            [],
            [],
            [
                'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Banner updated successfully.');
        $response->assertJsonPath('data.title', 'Updated Title');
        $response->assertJsonPath('data.description', 'Updated desc');
        $response->assertJsonPath('data.button_text', 'View');
        $response->assertJsonPath('data.priority', 5);
        $response->assertJsonPath('data.is_active', false);
        $response->assertJsonPath('data.image', 'banners/original.jpg');
        $this->assertNull($response->json('data.action_value'));

        $banner->refresh();
        $this->assertSame('Updated Title', $banner->title);
        $this->assertSame('Updated desc', $banner->description);
        $this->assertSame('View', $banner->button_text);
        $this->assertSame('none', $banner->action_type);
        $this->assertNull($banner->action_value);
        $this->assertSame(5, $banner->priority);
        $this->assertFalse($banner->is_active);
        $this->assertSame('banners/original.jpg', $banner->image);
    }

    // --- Admin: Update (with new image) ---

    public function test_admin_banners_update_with_image_replaces_image(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'With Image',
            'image' => 'banners/old.jpg',
            'priority' => 0,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('banners/old.jpg', 'fake-old-content');

        $file = UploadedFile::fake()->image('new-banner.jpg', 600, 200);

        $response = $this->call(
            'PUT',
            '/api/admin/banners/' . $banner->id,
            [
                'title' => 'With Image',
                'priority' => 0,
                'is_active' => true,
            ],
            [],
            ['image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertNotEmpty($data['image']);
        $this->assertStringContainsString('banners/', $data['image']);
        $this->assertNotSame('banners/old.jpg', $data['image']);

        $banner->refresh();
        $this->assertNotSame('banners/old.jpg', $banner->image);
        $this->assertTrue(Storage::disk('public')->exists($banner->image));
        $this->assertFalse(Storage::disk('public')->exists('banners/old.jpg'));
    }

    // --- Admin: Update via POST (multipart) for image replace ---

    public function test_admin_banners_update_via_post_with_image(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'Post Update',
            'image' => 'banners/post-old.jpg',
            'priority' => 0,
            'is_active' => true,
        ]);
        $file = UploadedFile::fake()->image('post-new.jpg', 400, 150);

        $response = $this->call(
            'POST',
            '/api/admin/banners/' . $banner->id,
            [
                'title' => 'Post Update',
                'priority' => 1,
                'is_active' => true,
            ],
            [],
            ['image' => $file],
            [
                'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
                'HTTP_Accept' => 'application/json',
            ]
        );

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.priority', 1);
        $this->assertStringContainsString('banners/', $response->json('data.image'));

        $banner->refresh();
        $this->assertSame(1, $banner->priority);
        $this->assertTrue(Storage::disk('public')->exists($banner->image));
    }

    // --- Admin: Update order ---

    public function test_admin_banners_update_order_success(): void
    {
        $admin = $this->createAdmin();
        $b1 = Banner::create(['title' => 'A', 'image' => 'banners/a.jpg', 'priority' => 0, 'is_active' => true]);
        $b2 = Banner::create(['title' => 'B', 'image' => 'banners/b.jpg', 'priority' => 1, 'is_active' => true]);

        $response = $this->postJson('/api/admin/banners/update-order', [
            'banners' => [
                ['id' => $b2->id, 'priority' => 0],
                ['id' => $b1->id, 'priority' => 1],
            ],
        ], $this->authHeaders($admin));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Banner order updated successfully.');
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $b1->refresh();
        $b2->refresh();
        $this->assertSame(1, $b1->priority);
        $this->assertSame(0, $b2->priority);
    }

    public function test_admin_banners_update_order_validation(): void
    {
        $admin = $this->createAdmin();
        $response = $this->postJson('/api/admin/banners/update-order', [
            'banners' => [['id' => 99999, 'priority' => 0]],
        ], $this->authHeaders($admin));
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['banners.0.id']);
    }

    // --- Admin: Toggle status ---

    public function test_admin_banners_toggle_status_success(): void
    {
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'Toggle',
            'image' => 'banners/toggle.jpg',
            'priority' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/admin/banners/' . $banner->id . '/toggle-status', [], $this->authHeaders($admin));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $banner->id);
        $response->assertJsonPath('data.is_active', false);

        $banner->refresh();
        $this->assertFalse($banner->is_active);

        $response2 = $this->postJson('/api/admin/banners/' . $banner->id . '/toggle-status', [], $this->authHeaders($admin));
        $response2->assertStatus(200);
        $response2->assertJsonPath('data.is_active', true);
        $banner->refresh();
        $this->assertTrue($banner->is_active);
    }

    // --- Admin: Delete ---

    public function test_admin_banners_delete_success(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $banner = Banner::create([
            'title' => 'To Delete',
            'image' => 'banners/delete.jpg',
            'priority' => 0,
            'is_active' => true,
        ]);
        Storage::disk('public')->put('banners/delete.jpg', 'content');

        $response = $this->deleteJson('/api/admin/banners/' . $banner->id, [], $this->authHeaders($admin));
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Banner deleted successfully.');

        $this->assertNull(Banner::find($banner->id));
        $this->assertFalse(Storage::disk('public')->exists('banners/delete.jpg'));
    }

    public function test_admin_banners_delete_404_for_missing(): void
    {
        $admin = $this->createAdmin();
        $response = $this->deleteJson('/api/admin/banners/99999', [], $this->authHeaders($admin));
        $response->assertStatus(404);
    }

    // --- Full flow: create -> public list -> update -> toggle -> reorder -> delete ---

    public function test_banner_full_flow_create_update_public_list_toggle_reorder_delete(): void
    {
        Storage::fake('public');
        $admin = $this->createAdmin();
        $headers = $this->authHeaders($admin);
        $file = UploadedFile::fake()->image('flow.jpg', 500, 200);

        $create = $this->call('POST', '/api/admin/banners', [
            'title' => 'Flow Banner',
            'description' => 'Flow description',
            'button_text' => 'View',
            'button_link' => 'https://flow.com',
            'priority' => 0,
            'is_active' => true,
        ], [], ['image' => $file], [
            'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'HTTP_Accept' => 'application/json',
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');
        $this->assertGreaterThan(0, $id);

        $public = $this->getJson('/api/banners');
        $public->assertStatus(200);
        $publicData = $public->json('data');
        $this->assertGreaterThanOrEqual(1, count($publicData));
        $found = collect($publicData)->firstWhere('id', $id);
        $this->assertNotNull($found);
        $this->assertSame('Flow Banner', $found['title']);
        $this->assertSame('https://flow.com', $found['action_value']);

        $update = $this->call('POST', '/api/admin/banners/' . $id, [
            '_method' => 'PUT',
            'title' => 'Flow Banner Updated',
            'description' => 'Flow description updated',
            'button_text' => 'Learn More',
            'button_link' => 'https://flow-updated.com',
            'priority' => 0,
            'is_active' => true,
        ], [], [], [
            'HTTP_Authorization' => 'Bearer ' . $admin->createToken('test')->plainTextToken,
            'HTTP_Accept' => 'application/json',
        ]);
        $update->assertStatus(200);
        $update->assertJsonPath('data.title', 'Flow Banner Updated');
        $update->assertJsonPath('data.description', 'Flow description updated');
        $update->assertJsonPath('data.button_text', 'Learn More');
        $update->assertJsonPath('data.action_value', 'https://flow-updated.com');

        $toggle = $this->postJson('/api/admin/banners/' . $id . '/toggle-status', [], $headers);
        $toggle->assertStatus(200);
        $toggle->assertJsonPath('data.is_active', false);

        $publicAfterToggle = $this->getJson('/api/banners');
        $ids = array_column($publicAfterToggle->json('data'), 'id');
        $this->assertNotContains($id, $ids, 'Inactive banner must not appear in public list');

        $toggle2 = $this->postJson('/api/admin/banners/' . $id . '/toggle-status', [], $headers);
        $toggle2->assertJsonPath('data.is_active', true);

        $b2 = Banner::create(['title' => 'Second', 'image' => 'banners/second.jpg', 'priority' => 1, 'is_active' => true]);
        $reorder = $this->postJson('/api/admin/banners/update-order', [
            'banners' => [['id' => $b2->id, 'priority' => 0], ['id' => $id, 'priority' => 1]],
        ], $headers);
        $reorder->assertStatus(200);
        $banner = Banner::find($id);
        $this->assertSame(1, $banner->priority);

        $del = $this->deleteJson('/api/admin/banners/' . $id, [], $headers);
        $del->assertStatus(200);
        $this->assertNull(Banner::find($id));
    }
}
