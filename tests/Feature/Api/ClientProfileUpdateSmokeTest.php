<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * End-to-end smoke for client Personal Information (GET/POST/PUT /api/user/profile).
 */
class ClientProfileUpdateSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        if (class_exists(Role::class)) {
            Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        }
        $this->client = User::factory()->create([
            'role' => 'client',
            'name' => 'devavology12',
            'email' => 'devavology12@gmail.com',
            'phone' => '983434343',
        ]);
        if (method_exists($this->client, 'assignRole')) {
            $this->client->assignRole('client');
        }
        $this->token = $this->client->createToken('smoke')->plainTextToken;
    }

    /** @return array<string, string> */
    private function auth(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ];
    }

    public function test_get_profile_shape_and_speed(): void
    {
        $started = microtime(true);
        $response = $this->getJson('/api/user/profile', $this->auth());
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->client->id)
            ->assertJsonPath('data.email', 'devavology12@gmail.com')
            ->assertJsonPath('data.phone', '983434343')
            ->assertJsonPath('data.needs_phone', false)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id', 'name', 'email', 'phone', 'needs_phone',
                    'profile_picture', 'profile_picture_url', 'role',
                ],
            ]);

        $this->assertLessThan(1500, $elapsedMs, "GET profile took {$elapsedMs}ms");
    }

    public function test_clearing_phone_with_empty_string_removes_it(): void
    {
        $started = microtime(true);
        $response = $this->putJson('/api/user/profile', [
            'name' => 'devavology12',
            'email' => 'devavology12@gmail.com',
            'phone' => '',
        ], $this->auth());
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.needs_phone', true);

        $this->client->refresh();
        $this->assertTrue($this->client->needsPhone());
        $this->assertTrue($this->client->phone === null || $this->client->phone === '');
        $this->assertLessThan(1500, $elapsedMs, "Clear phone took {$elapsedMs}ms");
    }

    public function test_clearing_phone_with_null_json_removes_it(): void
    {
        $this->putJson('/api/user/profile', [
            'phone' => null,
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.needs_phone', true);

        $this->assertDatabaseHas('users', [
            'id' => $this->client->id,
            'phone' => null,
        ]);
    }

    public function test_clearing_phone_via_multipart_form_data(): void
    {
        $this->post('/api/user/profile', [
            'name' => 'devavology12',
            'phone' => '',
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.needs_phone', true);
    }

    public function test_update_name_without_phone_key_keeps_existing_phone(): void
    {
        $this->putJson('/api/user/profile', [
            'name' => 'Updated Name Only',
        ], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Name Only')
            ->assertJsonPath('data.phone', '983434343');
    }

    public function test_profile_photo_update_is_fast_and_under_2mb(): void
    {
        $started = microtime(true);
        $response = $this->post('/api/user/profile', [
            'name' => 'devavology12',
            'phone' => '983434343',
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg', 2400, 2400)->size(5000),
        ], $this->auth());
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertOk()->assertJsonPath('success', true);
        $path = (string) $response->json('data.profile_picture');
        $this->assertNotSame('', $path);
        $this->assertLessThanOrEqual(
            ImageCompressionService::MOBILE_UPLOAD_MAX_BYTES,
            Storage::disk('public')->size($path)
        );
        $this->assertLessThan(2500, $elapsedMs, "Photo profile update took {$elapsedMs}ms");
    }

    public function test_duplicate_phone_returns_clear_422(): void
    {
        User::factory()->create(['role' => 'client', 'phone' => '0555000111']);

        $this->putJson('/api/user/profile', [
            'phone' => '0555000111',
        ], $this->auth())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['phone']);
    }
}
