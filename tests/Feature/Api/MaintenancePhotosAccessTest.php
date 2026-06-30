<?php

namespace Tests\Feature\Api;

use App\Models\MaintenancePhoto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenancePhotosAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
    }

    public function test_client_can_list_only_active_maintenance_photos(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        MaintenancePhoto::factory()->create([
            'title' => 'Visible',
            'priority' => 1,
            'is_active' => true,
        ]);
        MaintenancePhoto::factory()->inactive()->create([
            'title' => 'Hidden',
            'priority' => 0,
        ]);

        $token = $client->createToken('api_client', ['client'])->plainTextToken;

        $response = $this->getJson('/api/maintenance-photos', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.title', 'Visible')
            ->assertJsonPath('data.data.0.active', true)
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        ['id', 'title', 'before_image_url', 'after_image_url', 'priority', 'active'],
                    ],
                ],
            ]);
    }

    public function test_client_list_orders_by_priority(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        MaintenancePhoto::factory()->create(['title' => 'Second', 'priority' => 5, 'is_active' => true]);
        MaintenancePhoto::factory()->create(['title' => 'First', 'priority' => 1, 'is_active' => true]);

        $token = $client->createToken('api_client', ['client'])->plainTextToken;

        $response = $this->getJson('/api/maintenance-photos', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.data.0.title', 'First');
        $response->assertJsonPath('data.data.1.title', 'Second');
    }

    public function test_technician_cannot_upload_via_admin_maintenance_photos_api(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $technician->syncRoles(['technician']);

        $token = $technician->createToken('api_technician', ['technician'])->plainTextToken;

        $response = $this->post('/api/admin/maintenance-photos', [
            'title' => 'Test',
            'priority' => 0,
            'active' => 1,
            'before_image' => UploadedFile::fake()->image('before.jpg'),
            'after_image' => UploadedFile::fake()->image('after.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_maintenance_photos_crud_flow_with_screenshot_fields_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles(['admin']);

        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        $token = $admin->createToken('api_admin', ['admin'])->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        $upload = $this->post('/api/admin/maintenance-photos', [
            'title' => 'Shoe restoration',
            'priority' => 2,
            'active' => 1,
            'before_image' => UploadedFile::fake()->image('before.jpg'),
            'after_image' => UploadedFile::fake()->image('after.jpg'),
        ], $headers);

        $upload->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Shoe restoration')
            ->assertJsonPath('data.priority', 2)
            ->assertJsonPath('data.active', true)
            ->assertJsonPath('data.before_image_url', fn ($url) => is_string($url) && str_contains($url, '/media/'))
            ->assertJsonPath('data.after_image_url', fn ($url) => is_string($url) && str_contains($url, '/media/'));

        $photoId = $upload->json('data.id');
        $this->assertNotNull($photoId);

        $show = $this->getJson('/api/admin/maintenance-photos/'.$photoId, $headers);
        $show->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $photoId)
            ->assertJsonPath('data.title', 'Shoe restoration')
            ->assertJsonPath('data.before_image_url', fn ($url) => is_string($url) && str_contains($url, '/media/'))
            ->assertJsonPath('data.after_image_url', fn ($url) => is_string($url) && str_contains($url, '/media/'));

        $list = $this->getJson('/api/admin/maintenance-photos', $headers);
        $list->assertOk();
        $list->assertJsonPath('data.pagination.total', 1);

        $update = $this->post('/api/admin/maintenance-photos/'.$photoId, [
            'title' => 'Updated title',
            'priority' => 0,
            'active' => 0,
        ], $headers);
        $update->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.priority', 0)
            ->assertJsonPath('data.active', false);

        Sanctum::actingAs($client, ['client']);
        $clientView = $this->getJson('/api/maintenance-photos');
        $clientView->assertOk();
        $clientView->assertJsonCount(0, 'data.data');

        Sanctum::actingAs($admin, ['admin']);
        $delete = $this->deleteJson('/api/admin/maintenance-photos/'.$photoId);
        $delete->assertOk();

        $this->assertDatabaseMissing('maintenance_photos', ['id' => $photoId]);
    }

    public function test_admin_store_requires_before_and_after_images(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles(['admin']);

        $token = $admin->createToken('api_admin', ['admin'])->plainTextToken;

        $response = $this->postJson('/api/admin/maintenance-photos', [
            'title' => 'Incomplete',
            'priority' => 0,
            'active' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['before_image', 'after_image']);
    }

    public function test_admin_store_rejects_extra_fields_only_accepts_five_params(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles(['admin']);

        $token = $admin->createToken('api_admin', ['admin'])->plainTextToken;

        $response = $this->post('/api/admin/maintenance-photos', [
            'title' => 'Test',
            'priority' => 0,
            'active' => 1,
            'before_image' => UploadedFile::fake()->image('before.jpg'),
            'after_image' => UploadedFile::fake()->image('after.jpg'),
            'visit_id' => 999,
            'photo' => UploadedFile::fake()->image('extra.jpg'),
            'type' => 'before',
            'show_on_client_app' => 1,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('maintenance_photos', [
            'title' => 'Test',
            'priority' => 0,
            'is_active' => 1,
        ]);
        $this->assertDatabaseMissing('visit_photos', ['visit_id' => 999]);
    }
}
