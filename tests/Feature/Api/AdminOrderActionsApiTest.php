<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use App\Models\WalletCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrderActionsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin-orders@test.com',
        ]);
        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->token = $this->admin->createToken('admin-orders-test')->plainTextToken;
    }

    private function assignRoleIfAvailable(User $user, string $roleName): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($roleName);
                }
            }
        } catch (\Throwable $e) {
            // Keep tests resilient when role tables are unavailable.
        }
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    public function test_admin_cancel_order_api_cancels_order(): void
    {
        $order = Order::factory()->create([
            'order_status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $response = $this->postJson('/api/admin/orders/' . $order->id . '/cancel', [], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.order_status', 'cancelled');
    }

    public function test_admin_cancel_order_api_applies_partial_refund_when_assigned(): void
    {
        $client = User::factory()->create(['role' => 'client', 'wallet_balance' => 0]);
        $order = Order::factory()->create([
            'user_id' => $client->id,
            'order_status' => 'assigned',
            'payment_status' => 'paid',
            'total_amount' => 200,
            'created_at' => now()->subHour(),
        ]);

        \App\Models\Setting::set('refund_partial_percent', '40', 'number', 'payment');
        \App\Models\Setting::set('refund_wallet_validity_months', '6', 'number', 'payment');

        $response = $this->postJson('/api/admin/orders/' . $order->id . '/cancel', [], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.order_status', 'cancelled');
        $response->assertJsonPath('refund.stage', 'assigned_not_started');
        $response->assertJsonPath('refund.wallet_credited', 80);

        $client->refresh();
        $this->assertEquals(80.0, (float) $client->wallet_balance);
        $this->assertNotNull(WalletCredit::query()->where('order_id', $order->id)->first());
    }

    public function test_admin_cancel_order_api_rejects_delivered_order(): void
    {
        $order = Order::factory()->create([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $response = $this->postJson('/api/admin/orders/' . $order->id . '/cancel', [], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $order->refresh();
        $this->assertSame('delivered', $order->order_status);
    }

    public function test_admin_refund_order_api_marks_order_refunded(): void
    {
        $order = Order::factory()->create([
            'total_amount' => 250.00,
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'payment_method' => 'paypal',
        ]);

        $response = $this->postJson('/api/admin/orders/' . $order->id . '/refund', [
            'refund_amount' => 120.50,
            'refund_reason' => 'Client request',
        ], $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $order->id);
        $response->assertJsonPath('data.payment_status', 'refunded');
        $response->assertJsonPath('data.refund_amount', 120.5);
        $response->assertJsonPath('data.refund_reason', 'Client request');
    }
}

