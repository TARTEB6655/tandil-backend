<?php

namespace Tests\Feature\Shop;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitOfferService;
use App\Support\OrderSupervisorNotifier;
use App\Support\OrderToVisitDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Smoke: client pays → wave-1 alerts (supervisor + area manager), tech silent;
 * supervisor offers job → wave-2 alert to technician.
 */
class PaidOrderAlertWaveSmokeTest extends TestCase
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

    public function test_pay_notifies_supervisor_and_area_manager_then_offer_notifies_technician(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $admin = User::factory()->create(['role' => 'admin']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor One']);
        $areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Area Manager One']);
        $technician = User::factory()->create(['role' => 'technician', 'name' => 'Technician One']);
        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');
        $this->assignRoleIfAvailable($technician, 'technician');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);
        $technician->assignedAreas()->attach($area->id);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'status' => 'active',
            'job_duration' => '45 minutes',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'client-alert-smoke@example.com',
            'full_name' => 'Client Alert Smoke',
            'phone_number' => '+971509998877',
            'street_address' => 'Corniche Road',
            'city' => 'Abu Dhabi',
            'country' => 'United Arab Emirates',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $start->assertStatus(201)->assertJsonPath('success', true);
        $orderId = (int) $start->json('data.order_id');
        $paypalOrderId = (string) $start->json('data.paypal_order_id');

        $beforeAdmin = $admin->unreadNotifications()->count();
        $beforeSup = $supervisor->unreadNotifications()->count();
        $beforeAm = $areaManager->unreadNotifications()->count();
        $beforeTech = $technician->unreadNotifications()->count();

        $capture = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json']);
        $capture->assertOk()->assertJsonPath('success', true);

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->payment_status);

        $admin->refresh();
        $supervisor->refresh();
        $areaManager->refresh();
        $technician->refresh();

        // Wave 1: admin + supervisor + area manager
        $this->assertGreaterThan($beforeAdmin, $admin->unreadNotifications()->count());
        $this->assertGreaterThan($beforeSup, $supervisor->unreadNotifications()->count());
        $this->assertGreaterThan($beforeAm, $areaManager->unreadNotifications()->count(), 'Area manager must get first-wave alert on pay');

        $supNote = $supervisor->notifications()
            ->get()
            ->first(fn ($n) => (string) ($n->data['meta']['type'] ?? '') === 'new_paid_order');
        $this->assertNotNull($supNote);
        $this->assertSame(1, (int) ($supNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('new_paid_order', (string) ($supNote->data['meta']['type'] ?? ''));

        $amNote = $areaManager->notifications()
            ->get()
            ->first(fn ($n) => (string) ($n->data['meta']['type'] ?? '') === 'new_paid_order');
        $this->assertNotNull($amNote);
        $this->assertSame(1, (int) ($amNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('area_manager', (string) ($amNote->data['meta']['recipient_role'] ?? ''));

        // Technician must NOT be notified on pay (second wave only)
        $this->assertSame($beforeTech, $technician->unreadNotifications()->count(), 'Technician must stay silent until job offer');

        $visit = Visit::query()->where('order_id', $orderId)->first()
            ?? Visit::query()->where('notes', 'like', '%[SHOP-ORDER:'.$orderId.']%')->first();
        $this->assertNotNull($visit);
        $this->assertNull($visit->supervisor_id);
        $this->assertSame($area->id, (int) $visit->area_id);
        $this->assertNull($visit->technician_id);

        // Wave 2: supervisor offers job to technician
        VisitOfferService::offerToTechnician($visit->fresh(), $technician->id);
        $technician->refresh();
        $this->assertGreaterThan($beforeTech, $technician->unreadNotifications()->count());

        $techNote = $technician->notifications()->latest()->first();
        $this->assertNotNull($techNote);
        $this->assertSame(2, (int) ($techNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('job_offer', (string) ($techNote->data['meta']['type'] ?? ''));
        $this->assertSame('technician', (string) ($techNote->data['meta']['recipient_role'] ?? ''));
        $this->assertStringContainsString((string) $visit->id, (string) ($techNote->data['message'] ?? ''));

        $visit->refresh();
        $this->assertSame($technician->id, (int) $visit->technician_id);
        $this->assertSame('pending_acceptance', $visit->status);
    }

    public function test_direct_notifier_smoke_for_unlinked_area_manager(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $areaManager = User::factory()->create(['role' => 'area_manager']);
        $technician = User::factory()->create(['role' => 'technician']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');
        $this->assignRoleIfAvailable($technician, 'technician');

        $area = Area::factory()->create([
            'name' => 'Dubai Marina',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $order = Order::factory()->create([
            'user_id' => null,
            'payment_status' => 'paid',
            'total_amount' => 99.5,
            'guest_full_name' => 'Guest Client',
            'guest_email' => 'guest-alert@example.com',
            'guest_city' => 'Dubai',
            'guest_country' => 'UAE',
            'guest_street_address' => 'Marina Walk',
        ]);

        $product = Product::factory()->create(['category_id' => Category::factory(), 'status' => 'active']);
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 99.5,
            'subtotal' => 99.5,
        ]);

        OrderSupervisorNotifier::notifySupervisorsForPaidOrder($order, 99.5, 'Wallet');
        $visit = OrderToVisitDispatcher::createVisitForPaidOrder($order);

        $this->assertGreaterThanOrEqual(1, $supervisor->fresh()->notifications()->count());
        $this->assertGreaterThanOrEqual(1, $areaManager->fresh()->notifications()->count());
        $this->assertSame(0, $technician->fresh()->notifications()->count());
        $this->assertNotNull($visit);
    }
}
