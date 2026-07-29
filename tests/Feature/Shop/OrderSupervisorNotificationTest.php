<?php

namespace Tests\Feature\Shop;

use App\Models\Area;
use App\Models\Order;
use App\Models\User;
use App\Models\Visit;
use App\Support\OrderToVisitDispatcher;
use App\Support\OrderSupervisorNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderSupervisorNotificationTest extends TestCase
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

    public function test_paid_order_notifies_area_supervisor_by_shipping_city(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor', 'email' => 'sup-order@example.com']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $areaManager = User::factory()->create(['role' => 'area_manager', 'email' => 'am-order@example.com', 'name' => 'Area Manager One']);
        $this->assignRoleIfAvailable($areaManager, 'area_manager');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $order = Order::factory()->create([
            'user_id' => null,
            'payment_status' => 'paid',
            'payment_method' => 'stripe',
            'total_amount' => 78.25,
            'guest_full_name' => 'Client One',
            'guest_email' => 'client@example.com',
            'guest_phone' => '+971500000000',
            'guest_street_address' => 'Corniche Road',
            'guest_city' => 'Abu Dhabi',
            'guest_state' => '',
            'guest_zip_code' => '',
            'guest_country' => 'United Arab Emirates',
        ]);

        OrderSupervisorNotifier::notifySupervisorsForPaidOrder($order, 78.25, 'Stripe (webhook)');
        $visit = OrderToVisitDispatcher::createVisitForPaidOrder($order);

        $supervisor->refresh();
        $areaManager->refresh();

        $this->assertGreaterThanOrEqual(1, $supervisor->unreadNotifications()->count());
        $this->assertGreaterThanOrEqual(1, $areaManager->unreadNotifications()->count(), 'Area manager must get first-wave paid-order alert');

        $n = $supervisor->notifications()->latest()->first();
        $this->assertNotNull($n);
        $this->assertStringContainsString((string) $order->id, (string) ($n->data['message'] ?? ''));
        $this->assertStringContainsString('Abu Dhabi Central', (string) ($n->data['message'] ?? ''));
        $this->assertSame(1, (int) ($n->data['meta']['alert_wave'] ?? 0));

        $amN = $areaManager->notifications()->latest()->first();
        $this->assertNotNull($amN);
        $this->assertStringContainsString((string) $order->id, (string) ($amN->data['message'] ?? ''));
        $this->assertSame('area_manager', (string) ($amN->data['meta']['recipient_role'] ?? ''));
        $this->assertSame(1, (int) ($amN->data['meta']['alert_wave'] ?? 0));

        $this->assertNotNull($visit);
        $this->assertSame($supervisor->id, $visit->supervisor_id);
        $this->assertSame($area->id, $visit->area_id);
        $this->assertStringContainsString('[SHOP-ORDER:' . $order->id . ']', (string) ($visit->notes ?? ''));
        $this->assertDatabaseHas('visits', [
            'id' => $visit->id,
            'supervisor_id' => $supervisor->id,
            'area_id' => $area->id,
            'status' => 'pending',
        ]);

        // Idempotent: repeated payment callbacks should not create duplicate visit.
        $visitAgain = OrderToVisitDispatcher::createVisitForPaidOrder($order);
        $this->assertSame($visit->id, $visitAgain?->id);
        $this->assertSame(1, Visit::query()->where('notes', 'like', '%[SHOP-ORDER:' . $order->id . ']%')->count());
    }

    public function test_paid_order_notifies_area_manager_linked_on_same_pivot(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Linked AM']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');

        $area = Area::factory()->create([
            'name' => 'Dubai Marina',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach([$supervisor->id, $areaManager->id]);

        $order = Order::factory()->create([
            'user_id' => null,
            'payment_status' => 'paid',
            'total_amount' => 50,
            'guest_full_name' => 'Client Two',
            'guest_email' => 'client2@example.com',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
            'guest_street_address' => 'Marina Walk',
        ]);

        OrderSupervisorNotifier::notifySupervisorsForPaidOrder($order, 50, 'Wallet');

        $this->assertGreaterThanOrEqual(1, $supervisor->fresh()->notifications()->count());
        $this->assertGreaterThanOrEqual(1, $areaManager->fresh()->notifications()->count());
    }
}
