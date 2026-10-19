<?php

/**
 * Local HTTP smoke: forgot-password send-otp → verify-otp → reset (log SMS driver).
 * Usage: php scripts/smoke_forgot_password_e2e.php
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CapturingSmsSender;

require __DIR__.'/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['services.sms.driver' => 'log']);
CapturingSmsSender::reset();
app()->instance(\App\Contracts\SmsSender::class, new CapturingSmsSender);

$phone = '+971509988776';
User::query()->where('email', 'smoke.forgot@example.com')->delete();
User::factory()->create([
    'role' => 'client',
    'email' => 'smoke.forgot@example.com',
    'phone' => $phone,
    'status' => 'active',
    'password' => Hash::make('old'),
]);

$kernel = app(\Illuminate\Contracts\Http\Kernel::class);

$send = $kernel->handle(\Illuminate\Http\Request::create('/api/forgot-password/send-otp', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['phone' => $phone, 'roles' => 'client'])));

echo "send-otp HTTP {$send->getStatusCode()}\n";
echo $send->getContent()."\n\n";

$otp = CapturingSmsSender::extractOtp();
if ($otp === null) {
    echo "FAIL: no OTP captured\n";
    exit(1);
}

$verify = $kernel->handle(\Illuminate\Http\Request::create('/api/forgot-password/verify-otp', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode(['phone' => $phone, 'roles' => 'client', 'otp' => $otp])));

echo "verify-otp HTTP {$verify->getStatusCode()}\n";
$data = json_decode($verify->getContent(), true);
$token = $data['data']['reset_token'] ?? null;
echo $verify->getContent()."\n\n";

if (! $token) {
    echo "FAIL: no reset_token\n";
    exit(1);
}

$reset = $kernel->handle(\Illuminate\Http\Request::create('/api/forgot-password/reset', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_ACCEPT' => 'application/json',
], json_encode([
    'reset_token' => $token,
    'password' => 'smokepass99',
    'password_confirmation' => 'smokepass99',
])));

echo "reset HTTP {$reset->getStatusCode()}\n";
echo $reset->getContent()."\n";
echo $reset->isSuccessful() ? "E2E smoke OK\n" : "E2E smoke FAIL\n";
exit($reset->isSuccessful() ? 0 : 1);
