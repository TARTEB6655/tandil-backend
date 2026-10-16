<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsSender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CapturingSmsSender;
use Tests\TestCase;

/**
 * E2E for mobile paths: /api/forgot-password/send-otp, verify-otp, reset.
 */
class ForgotPasswordMobileRoutesE2eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CapturingSmsSender::reset();
        $this->app->instance(SmsSender::class, new CapturingSmsSender);
    }

    public function test_send_otp_verify_otp_and_reset_end_to_end(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
            'email' => 'mobile.routes@example.com',
            'phone' => '+971507777777',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        $send = $this->postJson('/api/forgot-password/send-otp', [
            'phone' => '+971507777777',
            'roles' => 'client',
        ]);

        $send->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent by SMS.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'phone_masked',
                    'expires_in_seconds',
                    'resend_available_in_seconds',
                ],
            ]);

        $otp = CapturingSmsSender::extractOtp();
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $otp);

        $verify = $this->postJson('/api/forgot-password/verify-otp', [
            'phone' => '+971507777777',
            'roles' => 'client',
            'otp' => $otp,
        ]);

        $verify->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Verification code accepted.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'reset_token',
                    'expires_in_seconds',
                ],
            ]);

        $resetToken = $verify->json('data.reset_token');

        $reset = $this->postJson('/api/forgot-password/reset', [
            'reset_token' => $resetToken,
            'password' => 'secure99',
            'password_confirmation' => 'secure99',
        ]);

        $reset->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password updated successfully. You can sign in with your new password.',
            ]);

        $user->refresh();
        $this->assertTrue(Hash::check('secure99', (string) $user->password));
    }

    public function test_send_otp_returns_404_for_unregistered_phone(): void
    {
        $this->postJson('/api/forgot-password/send-otp', [
            'phone' => '+971500000000',
            'roles' => 'client',
        ])->assertNotFound()
            ->assertJson([
                'success' => false,
                'message' => 'No account is registered with this mobile number for the selected role.',
            ]);
    }

    public function test_verify_otp_returns_422_for_wrong_code(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971506666666',
            'status' => 'active',
        ]);

        $this->postJson('/api/forgot-password/send-otp', [
            'phone' => '+971506666666',
            'roles' => 'client',
        ])->assertOk();

        $this->postJson('/api/forgot-password/verify-otp', [
            'phone' => '+971506666666',
            'roles' => 'client',
            'otp' => '000000',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Incorrect verification code.');
    }

    public function test_reset_returns_422_when_validation_fails(): void
    {
        $this->postJson('/api/forgot-password/reset', [
            'reset_token' => 'short',
            'password' => 'abc',
        ])->assertStatus(422);
    }
}
