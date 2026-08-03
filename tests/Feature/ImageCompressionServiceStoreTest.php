<?php

namespace Tests\Feature;

use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageCompressionServiceStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_compressed_public_keeps_output_under_2mb(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('huge-camera.jpg', 4000, 3000)->size(8000);

        $path = ImageCompressionService::storeCompressedPublic($file, 'profiles');

        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertLessThanOrEqual(
            ImageCompressionService::MOBILE_UPLOAD_MAX_BYTES,
            Storage::disk('public')->size($path)
        );
        $this->assertStringEndsWith('.jpg', $path);
    }
}
