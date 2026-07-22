<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\ImageCompressionService;
use App\Services\VideoCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VideoBannerCompressionTest extends TestCase
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

    public function test_create_compresses_large_poster_under_banner_limit(): void
    {
        // Large PNG-ish jpeg from fake image, then force a big file on disk after store via service path.
        $response = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'Compressed poster',
            'video' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
            'poster' => UploadedFile::fake()->image('huge.jpg', 4000, 3000),
        ]);

        $response->assertCreated();
        $posterUrl = $response->json('data.poster_url');
        $this->assertNotNull($posterUrl);

        $rel = ltrim(parse_url($posterUrl, PHP_URL_PATH), '/');
        $rel = preg_replace('#^media/#', '', $rel);
        Storage::disk('public')->assertExists($rel);

        $size = Storage::disk('public')->size($rel);
        $this->assertLessThanOrEqual(
            ImageCompressionService::VIDEO_BANNER_POSTER_MAX_BYTES,
            $size,
            "Poster should be compressed under 400KB, got {$size} bytes"
        );
    }

    public function test_create_rejects_video_over_25mb_limit(): void
    {
        // Laravel validates size in KB; 25601 > 25600.
        $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'Too big',
            'video' => UploadedFile::fake()->create('huge.mp4', 25601, 'video/mp4'),
        ])->assertStatus(422);
    }

    public function test_video_compression_service_shrinks_oversized_mp4_when_ffmpeg_available(): void
    {
        if (! VideoCompressionService::isAvailable()) {
            $this->markTestSkipped('ffmpeg not installed');
        }

        $ffmpeg = VideoCompressionService::resolveFfmpegBinary();
        $this->assertNotNull($ffmpeg);

        // Generate a short but intentionally large uncompressed-ish AVI/MP4 via ffmpeg (color source).
        $rawRel = 'video_banners/raw_test.mp4';
        $rawFull = Storage::disk('public')->path($rawRel);
        @mkdir(dirname($rawFull), 0777, true);

        $gen = \Illuminate\Support\Facades\Process::timeout(90)->run([
            $ffmpeg,
            '-y',
            '-f', 'lavfi',
            '-i', 'testsrc=size=1920x1080:rate=30',
            '-t', '6',
            '-c:v', 'mpeg4',
            '-q:v', '2',
            '-an',
            $rawFull,
        ]);
        $this->assertTrue($gen->successful(), 'Failed to generate test video: '.$gen->errorOutput());
        $this->assertFileExists($rawFull);

        clearstatcache(true, $rawFull);
        $before = filesize($rawFull);
        $this->assertGreaterThan(VideoCompressionService::SKIP_UNDER_BYTES, $before, 'Test video should exceed skip threshold');

        $started = microtime(true);
        $outRel = VideoCompressionService::compressIfNeededFromPublicPath($rawRel);
        $elapsed = microtime(true) - $started;

        $this->assertNotNull($outRel);
        $outFull = Storage::disk('public')->path($outRel);
        $this->assertFileExists($outFull);
        clearstatcache(true, $outFull);
        $after = filesize($outFull);

        $this->assertLessThan($before, $after, "Compressed ({$after}) should be smaller than original ({$before})");
        $this->assertLessThan(20.0, $elapsed, "Compression took too long: {$elapsed}s");
    }

    public function test_create_with_real_compressed_video_completes_quickly(): void
    {
        if (! VideoCompressionService::isAvailable()) {
            $this->markTestSkipped('ffmpeg not installed');
        }

        $ffmpeg = VideoCompressionService::resolveFfmpegBinary();
        $tmp = tempnam(sys_get_temp_dir(), 'vbvid_').'.mp4';
        $gen = \Illuminate\Support\Facades\Process::timeout(30)->run([
            $ffmpeg, '-y',
            '-f', 'lavfi', '-i', 'testsrc=size=1280x720:rate=24',
            '-f', 'lavfi', '-i', 'sine=frequency=440:sample_rate=44100',
            '-t', '2',
            '-c:v', 'libx264', '-preset', 'ultrafast', '-crf', '28',
            '-c:a', 'aac', '-shortest',
            $tmp,
        ]);
        $this->assertTrue($gen->successful(), $gen->errorOutput());

        $upload = new UploadedFile($tmp, 'banner.mp4', 'video/mp4', null, true);
        $poster = UploadedFile::fake()->image('poster.jpg', 2000, 1200);

        $started = microtime(true);
        $response = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'See Tandil in action',
            'badge_text' => 'Watch now',
            'button_text' => 'Explore services',
            'button_link' => 'services',
            'is_active' => '1',
            'video' => $upload,
            'poster' => $poster,
        ]);
        $elapsed = microtime(true) - $started;

        @unlink($tmp);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'See Tandil in action');
        $this->assertNotNull($response->json('data.video_url'));
        $this->assertLessThan(20.0, $elapsed, "Create API took too long: {$elapsed}s");
    }
}
