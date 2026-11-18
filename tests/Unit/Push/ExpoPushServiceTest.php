<?php

namespace Tests\Unit\Push;

use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\Push\ExpoPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpoPushServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detects_expo_token_format(): void
    {
        $this->assertTrue(ExpoPushService::isExpoToken('ExponentPushToken[abc123]'));
        $this->assertTrue(ExpoPushService::isExpoToken('ExpoPushToken[xyz]'));
        $this->assertFalse(ExpoPushService::isExpoToken('a'.str_repeat('b', 63)));
    }

    public function test_sends_to_exp_host_with_notification_payload(): void
    {
        config([
            'services.expo.enabled' => true,
            'services.expo.driver' => 'http',
        ]);

        Http::fake([
            'https://exp.host/*' => Http::response([
                'data' => [
                    ['status' => 'ok', 'id' => 'ticket-1'],
                ],
            ], 200),
        ]);

        $user = User::factory()->create(['role' => 'admin']);
        $token = 'ExponentPushToken[smoke-test-token-value]';
        DevicePushToken::create([
            'user_id' => $user->id,
            'token' => $token,
            'platform' => 'ios',
        ]);

        $result = app(ExpoPushService::class)->sendToTokens(
            [$token],
            'New Order Received',
            'Order #99 placed',
            ['type' => 'new_paid_order', 'order_id' => '99']
        );

        $this->assertSame(1, $result['sent']);
        $this->assertSame('http', $result['driver']);

        Http::assertSent(function ($request) use ($token) {
            $body = $request->data();
            $first = is_array($body) && isset($body[0]) ? $body[0] : null;

            return str_contains($request->url(), 'exp.host')
                && is_array($first)
                && ($first['to'] ?? null) === $token
                && ($first['title'] ?? null) === 'New Order Received'
                && ($first['body'] ?? null) === 'Order #99 placed'
                && ($first['data']['type'] ?? null) === 'new_paid_order'
                && ($first['data']['order_id'] ?? null) === '99';
        });
    }
}