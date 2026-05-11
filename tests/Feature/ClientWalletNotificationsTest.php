<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use App\Notifications\AdminNotification;
use App\Services\ShopWalletRedemptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClientWalletNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken,
        ];
    }

    public function test_cancel_paid_order_creates_english_wallet_credit_notification_with_balance_meta(): void
    {
        if (! Schema::hasTable('wallet_credits')) {
            $this->markTestSkipped('wallet_credits table not migrated');
        }

        $user = User::factory()->create(['role' => 'client', 'wallet_balance' => 0]);
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('client');
        }

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'pending',
            'payment_status' => 'paid',
            'total_amount' => 100,
            'created_at' => now()->subHours(2),
        ]);

        \App\Models\Setting::set('refund_partial_percent', '50', 'number', 'payment');
        \App\Models\Setting::set('refund_wallet_validity_months', '6', 'number', 'payment');

        $response = $this->postJson('/api/orders/'.$order->id.'/cancel', [], $this->bearer($user));
        $response->assertOk();

        $user->refresh();
        $n = $user->notifications()->latest()->first();
        $this->assertNotNull($n);
        $this->assertSame(AdminNotification::class, $n->type);
        $data = $n->data;
        $this->assertSame('Wallet credit from refund', $data['title'] ?? null);
        $this->assertStringContainsString('A refund of', (string) ($data['message'] ?? ''));
        $this->assertStringContainsString('AED was added to your wallet', (string) ($data['message'] ?? ''));
        $this->assertStringContainsString('Your wallet balance is now', (string) ($data['message'] ?? ''));
        $this->assertStringNotContainsString('रुपये', (string) ($data['message'] ?? ''));
        $this->assertSame('refund_credited', $data['meta']['wallet_event'] ?? null);
        $this->assertEqualsWithDelta(100.0, (float) ($data['meta']['wallet_balance'] ?? 0), 0.01);
        $this->assertEqualsWithDelta(100.0, (float) ($data['meta']['amount'] ?? 0), 0.01);
        $this->assertSame($order->id, (int) ($data['meta']['order_id'] ?? 0));
    }

    public function test_wallet_redeem_creates_english_debit_notification_with_remaining_balance(): void
    {
        if (! Schema::hasTable('wallet_credits')) {
            $this->markTestSkipped('wallet_credits table not migrated');
        }

        $user = User::factory()->create(['role' => 'client', 'wallet_balance' => 80]);
        WalletCredit::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => 80,
            'reason' => 'test',
            'status' => 'active',
            'credited_at' => now(),
            'expires_at' => now()->addYear(),
        ]);

        $order = Order::factory()->create(['user_id' => $user->id]);

        app(ShopWalletRedemptionService::class)->redeem($user, 30.0, $order->id, $order->publicOrderNumber());

        $user->refresh();
        $this->assertEqualsWithDelta(50.0, (float) $user->wallet_balance, 0.01);

        $n = $user->notifications()->latest()->first();
        $this->assertNotNull($n);
        $data = $n->data;
        $this->assertSame('Wallet balance used', $data['title'] ?? null);
        $this->assertStringContainsString('30.00 AED from your wallet', (string) ($data['message'] ?? ''));
        $this->assertStringContainsString('Remaining wallet balance: 50.00 AED', (string) ($data['message'] ?? ''));
        $this->assertSame('wallet_debited', $data['meta']['wallet_event'] ?? null);
        $this->assertEqualsWithDelta(50.0, (float) ($data['meta']['wallet_balance'] ?? 0), 0.01);
    }

    public function test_forfeit_expired_credit_sends_english_notification(): void
    {
        if (! Schema::hasTable('wallet_credits')) {
            $this->markTestSkipped('wallet_credits table not migrated');
        }

        $user = User::factory()->create(['role' => 'client', 'wallet_balance' => 40]);
        WalletCredit::create([
            'user_id' => $user->id,
            'order_id' => null,
            'amount' => 40,
            'reason' => 'test',
            'status' => 'active',
            'credited_at' => now()->subYear(),
            'expires_at' => now()->subDay(),
        ]);

        Artisan::call('wallet:forfeit-expired');

        $user->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $user->wallet_balance, 0.01);

        $n = $user->notifications()->latest()->first();
        $this->assertNotNull($n);
        $data = $n->data;
        $this->assertSame('Wallet credit expired', $data['title'] ?? null);
        $this->assertStringContainsString('expired under store policy', (string) ($data['message'] ?? ''));
        $this->assertStringContainsString('0.00 AED', (string) ($data['message'] ?? ''));
        $this->assertSame('wallet_forfeited', $data['meta']['wallet_event'] ?? null);
    }
}
