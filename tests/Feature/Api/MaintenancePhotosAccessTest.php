<?php

namespace Tests\Feature\Api;

use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitPhoto;
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

    public function test_client_can_list_only_admin_published_maintenance_photos(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);

        VisitPhoto::factory()->create([
            'visit_id' => $visit->id,
            'show_on_client_app' => true,
            'photo_path' => 'visit_photos/visible.jpg',
        ]);
        VisitPhoto::factory()->create([
            'visit_id' => $visit->id,
            'show_on_client_app' => false,
            'photo_path' => 'visit_photos/hidden.jpg',
        ]);

        $token = $client->createToken('api_client', ['client'])->plainTextToken;

        $response = $this->getJson('/api/maintenance-photos', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.show_on_client_app', true);
    }

    public function test_client_can_get_maintenance_photos_by_visit(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);

        VisitPhoto::factory()->create([
            'visit_id' => $visit->id,
            'show_on_client_app' => true,
            'type' => 'before',
        ]);

        $token = $client->createToken('api_client', ['client'])->plainTextToken;

        $response = $this->getJson('/api/maintenance-photos/visit/'.$visit->id, [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.visit.id', $visit->id);
        $response->assertJsonCount(1, 'data.photos');
    }

    public function test_client_cannot_upload_visit_maintenance_photo(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);
        $token = $client->createToken('api_client', ['client'])->plainTextToken;

        $response = $this->postJson('/api/visits/'.$visit->id.'/upload-photo', [
            'type' => 'after',
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(404);
    }

    public function test_technician_cannot_upload_via_admin_maintenance_photos_api(): void
    {
        $technician = User::factory()->create(['role' => 'technician']);
        $technician->syncRoles(['technician']);

        $subscription = Subscription::factory()->create([
            'client_id' => User::factory()->create(['role' => 'client'])->id,
        ]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);

        $token = $technician->createToken('api_technician', ['technician'])->plainTextToken;

        $response = $this->post('/api/admin/maintenance-photos', [
            'visit_id' => $visit->id,
            'type' => 'after',
            'photo' => UploadedFile::fake()->image('maintenance.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_maintenance_photos_crud_flow(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles(['admin']);

        $client = User::factory()->create(['role' => 'client']);
        $client->syncRoles(['client']);
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);

        $token = $admin->createToken('api_admin', ['admin'])->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        $upload = $this->post('/api/admin/maintenance-photos', [
            'visit_id' => $visit->id,
            'type' => 'after',
            'show_on_client_app' => 1,
            'photo' => UploadedFile::fake()->image('maintenance.jpg'),
        ], $headers);

        $upload->assertCreated();
        $upload->assertJsonPath('success', true);
        $photoId = $upload->json('data.id');
        $this->assertNotNull($photoId);

        $list = $this->getJson('/api/admin/maintenance-photos?visit_id='.$visit->id, $headers);
        $list->assertOk();
        $list->assertJsonPath('data.pagination.total', 1);

        $update = $this->put('/api/admin/maintenance-photos/'.$photoId, [
            'type' => 'before',
            'show_on_client_app' => 1,
        ], $headers);
        $update->assertOk();
        $update->assertJsonPath('data.type', 'before');

        $this->flushSession();
        Sanctum::actingAs($client, ['client']);
        $clientView = $this->getJson('/api/maintenance-photos');
        $clientView->assertOk();
        $clientView->assertJsonCount(1, 'data.data');
        $clientView->assertJsonPath('data.data.0.type', 'before');

        $this->flushSession();
        Sanctum::actingAs($admin, ['admin']);
        $delete = $this->deleteJson('/api/admin/maintenance-photos/'.$photoId);
        $delete->assertOk();
        $delete->assertJsonPath('success', true);

        $this->assertDatabaseMissing('visit_photos', ['id' => $photoId]);
    }

    public function test_admin_can_upload_maintenance_photo_via_admin_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->syncRoles(['admin']);

        $client = User::factory()->create(['role' => 'client']);
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = Visit::factory()->create(['subscription_id' => $subscription->id]);

        $token = $admin->createToken('api_admin', ['admin'])->plainTextToken;

        $response = $this->post('/api/admin/maintenance-photos', [
            'visit_id' => $visit->id,
            'type' => 'after',
            'show_on_client_app' => 1,
            'photo' => UploadedFile::fake()->image('maintenance.jpg'),
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('visit_photos', [
            'visit_id' => $visit->id,
            'show_on_client_app' => 1,
        ]);
    }
}
