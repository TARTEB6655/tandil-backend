<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLanguageApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('lang')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ];
    }

    public function test_get_language_returns_user_preferred_locale(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'preferred_locale' => 'ur',
        ]);

        $response = $this->getJson('/api/user/language', $this->authHeaders($user));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.locale', 'ur')
            ->assertJsonPath('data.rtl', true);
    }

    public function test_update_language_persists_locale_and_returns_payload(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'preferred_locale' => 'en',
        ]);

        $response = $this->postJson('/api/user/language', [
            'locale' => 'ar',
        ], $this->authHeaders($user));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.locale', 'ar')
            ->assertJsonPath('data.rtl', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'preferred_locale' => 'ar',
        ]);
    }
}

