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

class VideoBannerApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin VB']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
    }

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_admin_can_create_video_banner_with_files(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'See Tandil in action',
            'badge_text' => 'Watch now',
            'button_text' => 'Explore services',
            'button_link' => 'services',
            'is_active' => true,
            'video' => UploadedFile::fake()->create('promo.mp4', 500, 'video/mp4'),
            'poster' => UploadedFile::fake()->image('poster.jpg', 600, 400),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'See Tandil in action')
            ->assertJsonPath('data.badge_text', 'Watch now');

        $this->assertNotNull($response->json('data.video_url'));
        $this->assertNotNull($response->json('data.poster_url'));
        $this->assertDatabaseHas('video_banners', ['title' => 'See Tandil in action', 'is_active' => true]);
    }

    public function test_create_requires_video(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-banners', ['title' => 'No video'])
            ->assertStatus(422);
    }

    public function test_admin_can_create_with_external_urls(): void
    {
        $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/video-banners', [
            'title' => 'External',
            'video_url' => 'https://cdn.example.com/promo.mp4',
            'poster_url' => 'https://cdn.example.com/poster.jpg',
        ])->assertCreated()
            ->assertJsonPath('data.video_url', 'https://cdn.example.com/promo.mp4')
            ->assertJsonPath('data.poster_url', 'https://cdn.example.com/poster.jpg');
    }

    public function test_public_get_returns_only_active(): void
    {
        VideoBanner::create(['title' => 'Active', 'video_path' => 'https://x/a.mp4', 'is_active' => true]);
        VideoBanner::create(['title' => 'Hidden', 'video_path' => 'https://x/b.mp4', 'is_active' => false]);

        $response = $this->getJson('/api/video-banners')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Active', $titles);
        $this->assertNotContains('Hidden', $titles);
    }

    public function test_admin_can_update_and_toggle(): void
    {
        $vb = VideoBanner::create(['title' => 'Old', 'video_path' => 'https://x/a.mp4', 'is_active' => true]);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/video-banners/'.$vb->id, ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-banners/'.$vb->id.'/toggle-status')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
    }

    public function test_non_admin_cannot_create(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/admin/video-banners', ['title' => 'x', 'video_url' => 'https://x/a.mp4'])
            ->assertForbidden();
    }
}
