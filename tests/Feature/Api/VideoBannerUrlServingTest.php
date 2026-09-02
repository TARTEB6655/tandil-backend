<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\VideoBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VideoBannerUrlServingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
                $this->admin->assignRole('admin');
            }
        } catch (\Throwable $e) {
            //
        }
    }

    private function mediaPathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $this->assertNotNull($path, 'URL path missing: '.$url);
        $this->assertStringStartsWith('/media/', $path, 'URL must use /media/: '.$url);

        return ltrim(substr($path, strlen('/media/')), '/');
    }

    public function test_video_url_is_served_by_media_route(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'See Tandil in action',
            'badge_text' => 'Watch now',
            'button_text' => 'Explore services',
            'is_active' => '1',
            'video' => UploadedFile::fake()->create('promo.mp4', 1200, 'video/mp4'),
        ]);

        $create->assertCreated();
        $videoUrl = $create->json('data.video_url');

        $this->assertIsString($videoUrl);
        $this->assertStringContainsString('/media/video_banners/', $videoUrl);
        $this->assertStringEndsWith('.mp4', parse_url($videoUrl, PHP_URL_PATH));
        $this->assertArrayHasKey('poster_url', $create->json('data'));
        $this->assertArrayNotHasKey('button_link', $create->json('data'));

        $videoRel = $this->mediaPathFromUrl($videoUrl);
        Storage::disk('public')->assertExists($videoRel);

        $videoResp = $this->get('/media/'.$videoRel);
        $videoResp->assertOk();
        $this->assertNotEmpty((string) $videoResp->headers->get('Content-Type'));
        $this->assertStringContainsString('max-age=', (string) $videoResp->headers->get('Cache-Control'));

        $pub = $this->getJson('/api/video-banners')->assertOk();
        $this->assertSame($videoUrl, $pub->json('data.0.video_url'));
        $this->assertStringContainsString('max-age=', (string) $pub->headers->get('Cache-Control'));
    }

    public function test_missing_media_file_returns_404(): void
    {
        $this->get('/media/video_banners/does-not-exist.mp4')->assertNotFound();
    }

    public function test_model_url_accessor_matches_media_convention(): void
    {
        $vb = VideoBanner::create([
            'title' => 'Accessor check',
            'video_path' => 'video_banners/sample.mp4',
            'is_active' => true,
        ]);

        $this->assertSame(asset('media/video_banners/sample.mp4'), $vb->video_url);
        $this->assertStringContainsString('/media/video_banners/sample.mp4', $vb->video_url);
    }
}
