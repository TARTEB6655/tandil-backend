<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientOrderReportFlowTest extends TestCase
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

    public function test_client_sees_report_only_after_supervisor_submits_and_can_mark_delivered(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $client = User::factory()->create(['role' => 'client', 'email' => 'client-report@test.com']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $technician = User::factory()->create(['role' => 'technician']);
        foreach ([$client, $supervisor, $technician] as $user) {
            $this->assignRoleIfAvailable($user, $user->role);
        }

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
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
            'full_name' => 'Report Client',
            'phone_number' => '+971501111111',
            'street_address' => 'Office 302, Al Khalidiya',
            'city' => 'Abu Dhabi',
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
        $visit = Visit::query()->where('order_id', $orderId)->first()
            ?? Visit::query()->where('notes', 'like', '%[SHOP-ORDER:'.$orderId.']%')->firstOrFail();
        $visit->update(['technician_id' => $technician->id, 'status' => 'in_progress']);

        $this->actingAs($technician, 'sanctum')
            ->postJson('/api/technician/reports', [
                'visit_id' => $visit->id,
                'technician_notes' => 'Completed watering and pruning.',
            ])
            ->assertCreated();

        $report = Report::where('visit_id', $visit->id)->firstOrFail();
        $clientUnreadBefore = $client->unreadNotifications()->count();

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/track")
            ->assertOk()
            ->assertJsonPath('data.service_report.available', false)
            ->assertJsonPath('data.service_report.can_view_report', false);

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/report")
            ->assertStatus(404);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/supervisor/reports/{$report->id}/accept")
            ->assertOk();

        $order->refresh();
        $this->assertSame('in_progress', $order->order_status);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson("/api/supervisor/visits/{$visit->id}/finalize", [
                'status' => 'sent_to_client',
                'supervisor_notes' => 'Good work overall.',
                'recommendations' => ['Needs Fertilizer', 'Needs Watering'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'sent_to_client');

        $order->refresh();
        $this->assertSame('completed', $order->order_status);
        $this->assertGreaterThan($clientUnreadBefore, $client->fresh()->unreadNotifications()->count());

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/track")
            ->assertOk()
            ->assertJsonPath('data.service_report.available', true)
            ->assertJsonPath('data.service_report.can_view_report', true)
            ->assertJsonPath('data.service_report.can_mark_delivered', true)
            ->assertJsonPath('data.tracking.status', 'completed');

        $this->actingAs($client, 'sanctum')
            ->getJson("/api/orders/{$orderId}/report")
            ->assertOk()
            ->assertJsonPath('data.supervisor_notes', 'Good work overall.')
            ->assertJsonPath('data.field_notes', 'Completed watering and pruning.')
            ->assertJsonFragment(['Needs Fertilizer']);

        $this->actingAs($client, 'sanctum')
            ->postJson("/api/orders/{$orderId}/mark-delivered")
            ->assertOk()
            ->assertJsonPath('data.order_status', 'delivered')
            ->assertJsonPath('data.tracking.status', 'delivered');
    }
}
