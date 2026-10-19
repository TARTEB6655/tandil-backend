<?php

namespace Tests\Feature\Api;

use App\Contracts\PasswordResetOtpHandler;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForgotPasswordIndianPhoneE164Test extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_calls_twilio_with_india_e164(): void
    {
        $handler = new class implements PasswordResetOtpHandler
        {
            public static ?string $lastE164 = null;

            public function usesExternalVerification(): bool
            {
                return true;
            }

            public function initiate(string $toE164, int $otpTtlSeconds): array
            {
                self::$lastE164 = $toE164;

                return ['success' => true, 'provider' => 'test', 'error' => null, 'otp_plaintext' => null];
            }

            public function validateCode(string $toE164, string $code, string $storedOtpHash): bool
            {
                return true;
            }
        };

        $this->app->instance(PasswordResetOtpHandler::class, $handler);

        User::factory()->create([
            'role' => 'client',
            'phone' => '9816025872',
            'status' => 'active',
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'phone' => '9816025872',
            'roles' => 'client',
        ])->assertOk();

        $this->assertSame('+919816025872', $handler::$lastE164);
    }
}
