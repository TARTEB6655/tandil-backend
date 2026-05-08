<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrderStatisticsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin-order-stats@test.com',
        ]);
        $this->assignRoleIfAvailable($this->admin, 'admin');
        $this->token = $this->admin->createToken('admin-order-stats-test')->plainTextToken;
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

    public function test_admin_order_statistics_returns_summary_financial_and_rates(): void
    {
        Order::factory()->create([
            'order_status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 100,
            'refund_amount' => 0,
        ]);
        Order::factory()->create([
            'order_status' => 'cancelled',
            'payment_status' => 'refunded',
            'total_amount' => 120,
            'refund_amount' => 20,
        ]);
        Order::factory()->create([
            'order_status' => 'delivered',
            'payment_status' => 'paid',
            'total_amount' => 200,
            'refund_amount' => 0,
        ]);
        Order::factory()->create([
            'order_status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => 150,
            'refund_amount' => 0,
        ]);
        Order::factory()->create([
            'order_status' => 'assigned',
            'payment_status' => 'paid',
            'total_amount' => 50,
            'refund_amount' => 0,
        ]);
        Order::factory()->create([
            'order_status' => 'processing',
            'payment_status' => 'paid',
            'total_amount' => 70,
            'refund_amount' => 0,
        ]);

        $response = $this->getJson('/api/admin/dashboard/order-statistics', $this->authHeaders());

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Admin order statistics retrieved successfully');
        $response->assertJsonPath('data.summary.total_orders', 6);
        $response->assertJsonPath('data.summary.cancelled_orders', 1);
        $response->assertJsonPath('data.summary.completed_orders', 2);
        $response->assertJsonPath('data.summary.refunded_orders', 1);
        $response->assertJsonPath('data.summary.pending_orders', 1);
        $response->assertJsonPath('data.summary.assigned_orders', 1);
        $response->assertJsonPath('data.summary.in_progress_orders', 1);
        $response->assertJsonPath('data.financial.gross_revenue', 470);
        $response->assertJsonPath('data.financial.refunded_amount', 20);
        $response->assertJsonPath('data.financial.net_revenue', 450);
        $response->assertJsonStructure([
            'data' => [
                'period_counts' => ['today', 'this_week', 'this_month'],
                'rates' => ['completion_rate', 'cancellation_rate', 'refund_rate'],
            ],
        ]);
    }
}
