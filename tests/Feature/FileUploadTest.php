<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test technician can upload visit photo
     */
    public function test_technician_can_upload_visit_photo()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $file = UploadedFile::fake()->image('visit.jpg', 800, 600);

        $response = $this->postJson("/api/visits/{$visit->id}/upload-photo", [
            'photo' => $file,
            'type' => 'after',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'photo_path', 'type']
            ]);

        // Assert file was stored
        Storage::disk('public')->assertExists('visit_photos/' . $file->hashName());
    }

    /**
     * Test photo upload requires valid image
     */
    public function test_photo_upload_requires_valid_image()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $technician = $this->createTechnician();
        Sanctum::actingAs($technician);

        $visit = $this->createVisit(['technician_id' => $technician->id]);

        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson("/api/visits/{$visit->id}/upload-photo", [
            'photo' => $file,
            'type' => 'after',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    /**
     * Test unauthorized user cannot upload photo
     */
    public function test_unauthorized_user_cannot_upload_photo()
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not installed.');
        }

        $customer = $this->createCustomer();
        Sanctum::actingAs($customer);

        $visit = $this->createVisit();

        $file = UploadedFile::fake()->image('visit.jpg');

        $response = $this->postJson("/api/visits/{$visit->id}/upload-photo", [
            'photo' => $file,
            'type' => 'after',
        ]);

        $response->assertStatus(403);
    }
}

