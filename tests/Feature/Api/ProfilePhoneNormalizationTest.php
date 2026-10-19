<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhoneNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_stores_phone_in_e164(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'client1@test.com',
            'phone' => '+971501111111',
        ]);

        Sanctum::actingAs($user);

        $this->putJson('/api/user/profile', [
            'phone' => '9816025872',
        ])->assertOk()
            ->assertJsonPath('data.phone', '+919816025872');

        $this->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.phone', '+919816025872');
    }
}
