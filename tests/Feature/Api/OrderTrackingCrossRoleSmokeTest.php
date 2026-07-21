<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use App\Models\Visit;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke test for client-reported status mismatch:
 * technician/supervisor see visit "completed" while admin/client order tracking stays "in_progress"
 * until supervisor accepts the field report.
 */
class OrderTrackingCrossRoleSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function assignRoleIfAvailable(User $user, string $role): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($role);
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_shop_order_status_stays_in_progress_until_supervisor_accepts_report(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client', 'email' => 'cross-role-client@test.com']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $technician = User::factory()->create(['role' => 'technician']);
        $hr = User::factory()->create(['role' => 'hr']);
        foreach ([$admin, $client, $supervisor, $technician, $hr] as $user) {
            $this->assignRoleIfAvailable($user, $user->role);
        }

        $area = Area::factory()->create([
            'name' => 'Dubai Central',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 45.00,
            'status' => 'active',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => $client->email,
            'full_name' => 'Cross Role Client',
            'phone_number' => '+971501111111',
            'street_address' => 'Test St',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ])->assertCreated();

        $orderId = (int) $start->json('data.order_id');
        $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $start->json('data.paypal_order_id'),
            'order_id' => $orderId,
        ])->assertOk();

        $order = Order::findOrFail($orderId);
        $order->update(['user_id' => $client->id]);
        $visit = Visit::query()
            ->where('notes', 'like', '%[SHOP-ORDER:'.$orderId.']%')
            ->firstOrFail();

        $visit->update([
            'technician_id' => $technician->id,
            'status' => 'in_progress',
        ]);
        $order->refresh();
        $this->assertSame('in_progress', $order->order_status);

        $supervisorUnreadBefore = $supervisor->unreadNotifications()->count();
        $hrUnreadBefore = $hr->unreadNotifications()->count();

        $this->actingAs($technician, 'sanctum')
            ->postJson('/api/technician/reports', [
                'visit_id' => $visit->id,
                'technician_notes' => 'Job done',
            ])
            ->assertCreated();

        $visit->refresh();
        $order->refresh();
        $report = Report::where('visit_id', $visit->id)->firstOrFail();

        // Technician / supervisor read visit status.
        $this->assertSame('completed', $visit->status);
        $this->assertSame('pending', $report->status);

        // Admin + client read order_status — stays in_progress until supervisor accepts.
        $this->assertSame('in_progress', $order->order_status);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/track")
            ->assertOk()
            ->assertJsonPath('data.tracking.status', 'in_progress')
            ->assertJsonPath('data.current_status', 'In Progress');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $orderId,
                'order_status' => 'in_progress',
            ]);

        $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/reports?status=pending')
            ->assertOk()
            ->assertJsonFragment(['id' => $report->id]);

        // Notifications: HR gets visit completed; supervisor does NOT get push on submit.
        $this->assertGreaterThan($hrUnreadBefore, $hr->fresh()->unreadNotifications()->count());
        $this->assertSame($supervisorUnreadBefore, $supervisor->fresh()->unreadNotifications()->count());

        // Supervisor accepts report → order becomes completed for client/admin too.
        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/supervisor/reports/{$report->id}/accept")
            ->assertOk();

        $order->refresh();
        $this->assertSame('completed', $order->order_status);
        $this->assertSame('approved', $report->fresh()->status);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/track")
            ->assertOk()
            ->assertJsonPath('data.tracking.status', 'completed')
            ->assertJsonPath('data.current_status', 'Completed');
    }
}
