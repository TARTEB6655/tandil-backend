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

    /** Pull the /media/... path from a full URL. */
    private function mediaPathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $this->assertNotNull($path, 'URL path missing: '.$url);
        $this->assertStringStartsWith('/media/', $path, 'URL must use /media/: '.$url);

        return ltrim(substr($path, strlen('/media/')), '/');
    }

    public function test_video_and_poster_urls_are_served_by_media_route(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'See Tandil in action',
            'badge_text' => 'Watch now',
            'button_text' => 'Explore services',
            'button_link' => 'services',
            'is_active' => '1',
            'video' => UploadedFile::fake()->create('promo.mp4', 1200, 'video/mp4'),
            'poster' => UploadedFile::fake()->image('poster.jpg', 640, 360),
        ]);

        $create->assertCreated();
        $videoUrl = $create->json('data.video_url');
        $posterUrl = $create->json('data.poster_url');

        $this->assertIsString($videoUrl);
        $this->assertIsString($posterUrl);
        $this->assertStringContainsString('/media/video_banners/', $videoUrl);
        $this->assertStringContainsString('/media/video_banners/posters/', $posterUrl);
        $this->assertStringEndsWith('.mp4', parse_url($videoUrl, PHP_URL_PATH));
        $this->assertMatchesRegularExpression('/\.(jpe?g|png|webp|gif)$/i', parse_url($posterUrl, PHP_URL_PATH));

        $videoRel = $this->mediaPathFromUrl($videoUrl);
        $posterRel = $this->mediaPathFromUrl($posterUrl);

        Storage::disk('public')->assertExists($videoRel);
        Storage::disk('public')->assertExists($posterRel);

        $videoResp = $this->get('/media/'.$videoRel);
        $videoResp->assertOk();
        $this->assertNotEmpty((string) $videoResp->headers->get('Content-Type'));

        $posterResp = $this->get('/media/'.$posterRel);
        $posterResp->assertOk();
        $posterCt = (string) $posterResp->headers->get('Content-Type');
        $this->assertNotEmpty($posterCt);
        $this->assertTrue(
            str_starts_with($posterCt, 'image/') || str_contains($posterCt, 'octet-stream'),
            'Poster Content-Type should be an image, got: '.$posterCt
        );

        $pub = $this->getJson('/api/video-banners')->assertOk();
        $this->assertSame($videoUrl, $pub->json('data.0.video_url'));
        $this->assertSame($posterUrl, $pub->json('data.0.poster_url'));
    }

    public function test_poster_url_is_null_when_no_poster_uploaded(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'Video only',
            'video' => UploadedFile::fake()->create('only.mp4', 200, 'video/mp4'),
        ]);

        $create->assertCreated();
        $this->assertNull($create->json('data.poster_url'));
        $this->assertNotNull($create->json('data.video_url'));

        $videoRel = $this->mediaPathFromUrl($create->json('data.video_url'));
        Storage::disk('public')->assertExists($videoRel);
        $this->get('/media/'.$videoRel)->assertOk();
    }

    public function test_missing_media_file_returns_404(): void
    {
        $this->get('/media/video_banners/does-not-exist.mp4')->assertNotFound();
    }

    public function test_model_url_accessors_match_media_convention(): void
    {
        $vb = VideoBanner::create([
            'title' => 'Accessor check',
            'video_path' => 'video_banners/sample.mp4',
            'poster_path' => 'video_banners/posters/sample.jpg',
            'is_active' => true,
        ]);

        $this->assertSame(asset('media/video_banners/sample.mp4'), $vb->video_url);
        $this->assertSame(asset('media/video_banners/posters/sample.jpg'), $vb->poster_url);
        $this->assertStringContainsString('/media/video_banners/sample.mp4', $vb->video_url);
        $this->assertStringContainsString('/media/video_banners/posters/sample.jpg', $vb->poster_url);
    }
}
