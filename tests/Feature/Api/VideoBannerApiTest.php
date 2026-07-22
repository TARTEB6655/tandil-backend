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
            'is_active' => true,
            'video' => UploadedFile::fake()->create('promo.mp4', 500, 'video/mp4'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'See Tandil in action')
            ->assertJsonPath('data.badge_text', 'Watch now')
            ->assertJsonMissingPath('data.poster_url')
            ->assertJsonMissingPath('data.button_link');

        $this->assertNotNull($response->json('data.video_url'));
        $this->assertDatabaseHas('video_banners', ['title' => 'See Tandil in action', 'is_active' => true]);
    }

    public function test_create_requires_video(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-banners', ['title' => 'No video'])
            ->assertStatus(422);
    }

    public function test_create_response_has_exact_fields(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')->post('/api/admin/video-banners', [
            'title' => 'See Tandil in action',
            'video' => UploadedFile::fake()->create('promo.mp4', 500, 'video/mp4'),
        ]);

        $response->assertCreated();
        $this->assertSame(
            ['id', 'title', 'video_url', 'badge_text', 'button_text', 'is_active'],
            array_keys($response->json('data'))
        );
    }

    public function test_public_get_returns_only_active(): void
    {
        VideoBanner::create(['title' => 'Active', 'video_path' => 'video_banners/a.mp4', 'is_active' => true]);
        VideoBanner::create(['title' => 'Hidden', 'video_path' => 'video_banners/b.mp4', 'is_active' => false]);

        $response = $this->getJson('/api/video-banners')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Active', $titles);
        $this->assertNotContains('Hidden', $titles);
        $this->assertSame(
            ['id', 'title', 'video_url', 'badge_text', 'button_text', 'is_active'],
            array_keys($response->json('data.0'))
        );
    }

    public function test_admin_can_update_toggle_and_delete(): void
    {
        $vb = VideoBanner::create(['title' => 'Old', 'video_path' => 'video_banners/a.mp4', 'is_active' => true]);
        Storage::disk('public')->put('video_banners/a.mp4', 'fake-video');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/video-banners/'.$vb->id, ['title' => 'New title'])
            ->assertOk()
            ->assertJsonPath('data.title', 'New title');

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/video-banners/'.$vb->id.'/toggle-status')
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/video-banners/'.$vb->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('video_banners', ['id' => $vb->id]);
        Storage::disk('public')->assertMissing('video_banners/a.mp4');
    }

    public function test_non_admin_cannot_create(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client, 'sanctum')
            ->postJson('/api/admin/video-banners', ['title' => 'x'])
            ->assertForbidden();
    }
}
