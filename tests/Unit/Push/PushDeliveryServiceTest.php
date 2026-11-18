<?php

namespace Tests\Unit\Push;

use App\Models\DevicePushToken;
use App\Models\User;
use App\Services\Push\PushDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_expo_token_on_ios_platform_uses_exp_host_not_apns(): void
    {
        config([
            'services.expo.enabled' => true,
            'services.expo.driver' => 'http',
            'services.apns.enabled' => true,
            'services.apns.driver' => 'log',
        ]);

        Http::fake([
            'https://exp.host/*' => Http::response(['data' => [['status' => 'ok']]], 200),
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        DevicePushToken::create([
            'user_id' => $admin->id,
            'token' => 'ExponentPushToken[admin-device-xyz]',
            'platform' => 'ios',
        ]);

        app(PushDeliveryService::class)->sendToUser(
            $admin,
            'New Order Received',
            'A new order was placed',
            ['type' => 'new_paid_order', 'order_id' => 42]
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), 'exp.host'));
    }
}