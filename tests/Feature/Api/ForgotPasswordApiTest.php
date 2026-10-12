<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CapturingSmsSender;
use Tests\TestCase;

class ForgotPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CapturingSmsSender::reset();
        $this->app->instance(SmsSender::class, new CapturingSmsSender);
    }

    public function test_forgot_password_sends_otp_only_for_registered_phone(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971501234567',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'phone' => '+971 50 123 4567',
            'roles' => 'client',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone_masked', '*** *** 4567');

        $this->postJson('/api/auth/forgot-password', [
            'phone' => '+971509999999',
            'roles' => 'client',
        ])->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_full_forgot_password_flow_and_login_with_new_password(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'client.reset@example.com',
            'phone' => '971501111111',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'phone_number' => '0501111111',
            'roles' => 'client',
        ])->assertOk();

        $otp = CapturingSmsSender::extractOtp();
        $this->assertNotNull($otp);

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => '0501111111',
            'roles' => 'client',
            'otp' => $otp,
        ])->assertOk();

        $resetToken = $verify->json('data.reset_token');
        $this->assertNotEmpty($resetToken);

        $this->postJson('/api/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'email' => 'client.reset@example.com',
            'password' => 'newpass123',
            'roles' => 'client',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', (string) $user->password));
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971502222222',
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'phone' => '+971502222222',
            'roles' => 'client',
        ])->assertOk();

        $this->postJson('/api/auth/verify-otp', [
            'phone' => '+971502222222',
            'roles' => 'client',
            'otp' => '000000',
        ])->assertStatus(422);
    }
}
