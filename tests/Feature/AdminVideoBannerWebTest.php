<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VideoBanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminVideoBannerWebTest extends TestCase
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

    public function test_admin_can_open_video_banners_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.video-banners.index'))
            ->assertOk()
            ->assertSee('Video Banners');
    }

    public function test_admin_can_create_video_banner_from_web(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.video-banners.store'), [
                'title' => 'Featured clip',
                'badge_text' => 'Watch now',
                'button_text' => 'Explore',
                'is_active' => '1',
                'video' => UploadedFile::fake()->create('promo.mp4', 1024, 'video/mp4'),
            ])
            ->assertRedirect(route('admin.video-banners.index'));

        $this->assertDatabaseHas('video_banners', [
            'title' => 'Featured clip',
            'is_active' => true,
        ]);
    }

    public function test_web_rejects_video_over_30mb(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.video-banners.create'))
            ->post(route('admin.video-banners.store'), [
                'title' => 'Too big',
                'video' => UploadedFile::fake()->create('huge.mp4', 30721, 'video/mp4'),
            ])
            ->assertRedirect(route('admin.video-banners.create'))
            ->assertSessionHasErrors('video');
    }

    public function test_admin_can_toggle_and_delete(): void
    {
        $vb = VideoBanner::create([
            'title' => 'Temp',
            'video_path' => 'video_banners/temp.mp4',
            'is_active' => true,
        ]);
        Storage::disk('public')->put('video_banners/temp.mp4', 'fake');

        $this->actingAs($this->admin)
            ->postJson(route('admin.video-banners.toggle-status', $vb->id))
            ->assertOk()
            ->assertJsonPath('is_active', false);

        $this->actingAs($this->admin)
            ->delete(route('admin.video-banners.destroy', $vb->id))
            ->assertRedirect(route('admin.video-banners.index'));

        $this->assertDatabaseMissing('video_banners', ['id' => $vb->id]);
    }
}
