<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TwilioVerifyForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.sms.driver', 'twilio_verify');
        Config::set('services.sms.twilio.account_sid', 'ACtest');
        Config::set('services.sms.twilio.auth_token', 'test-token');
        Config::set('services.sms.twilio.verify_service_sid', 'VAtestservice');
    }

    public function test_forgot_password_and_verify_otp_via_twilio_verify_api(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971501234567',
            'status' => 'active',
            'password' => Hash::make('old-password'),
        ]);

        Http::fake([
            'verify.twilio.com/v2/Services/VAtestservice/Verifications' => Http::response([
                'status' => 'pending',
                'sid' => 'VE123',
            ], 201),
            'verify.twilio.com/v2/Services/VAtestservice/VerificationCheck' => Http::response([
                'status' => 'approved',
                'sid' => 'VE123',
            ], 200),
        ]);

        $this->postJson('/api/auth/forgot-password', [
            'phone' => '+971501234567',
            'roles' => 'client',
        ])->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://verify.twilio.com/v2/Services/VAtestservice/Verifications'
                && $request['To'] === '+971501234567'
                && $request['Channel'] === 'sms';
        });

        $verify = $this->postJson('/api/auth/verify-otp', [
            'phone' => '+971501234567',
            'roles' => 'client',
            'otp' => '123456',
        ])->assertOk();

        $resetToken = $verify->json('data.reset_token');
        $this->assertNotEmpty($resetToken);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://verify.twilio.com/v2/Services/VAtestservice/VerificationCheck'
                && $request['Code'] === '123456';
        });

        $this->postJson('/api/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertOk();
    }
}
