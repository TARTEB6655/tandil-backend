<?php

namespace Tests\Feature\Web;

use App\Http\Controllers\Client\CheckoutController;
use App\Models\Area;
use App\Models\Order;
use App\Models\User;
use App\Models\Visit;
use App\Services\VisitOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Laravel web dashboards must show the same paid-order alert waves as the API:
 * wave 1 → supervisor + area manager; wave 2 → technician (job offer).
 */
class PaidOrderAlertWavesWebDashboardTest extends TestCase
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

    public function test_web_checkout_notify_shows_alerts_on_role_dashboards(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Web']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Web']);
        $areaManager = User::factory()->create(['role' => 'area_manager', 'name' => 'Area Manager Web']);
        $technician = User::factory()->create(['role' => 'technician', 'name' => 'Technician Web']);
        $client = User::factory()->create(['role' => 'client', 'name' => 'Client Web']);

        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');
        $this->assignRoleIfAvailable($technician, 'technician');
        $this->assignRoleIfAvailable($client, 'client');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);
        $technician->assignedAreas()->attach($area->id);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'payment_status' => 'paid',
            'payment_method' => 'paypal',
            'total_amount' => 99.50,
            'guest_full_name' => 'Client Web',
            'guest_email' => $client->email,
            'guest_phone' => '+971500000111',
            'guest_street_address' => 'Corniche Road',
            'guest_city' => 'Abu Dhabi',
            'guest_state' => '',
            'guest_zip_code' => '',
            'guest_country' => 'United Arab Emirates',
        ]);

        $product = \App\Models\Product::factory()->create(['category_id' => \App\Models\Category::factory(), 'status' => 'active']);
        \App\Models\OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 99.50,
            'subtotal' => 99.50,
        ]);

        // Simulate Laravel web checkout success notify path
        $method = new ReflectionMethod(CheckoutController::class, 'notifyAdminsShopOrder');
        $method->setAccessible(true);
        $method->invoke(app(CheckoutController::class), $order, 'PayPal (web)');

        $supervisor->refresh();
        $areaManager->refresh();
        $technician->refresh();
        $admin->refresh();

        $this->assertGreaterThanOrEqual(1, $supervisor->unreadNotifications()->count());
        $this->assertGreaterThanOrEqual(1, $areaManager->unreadNotifications()->count());
        $this->assertGreaterThanOrEqual(1, $admin->unreadNotifications()->count());
        $this->assertSame(0, $technician->unreadNotifications()->count(), 'Tech must stay silent until wave 2');

        $supNote = $supervisor->notifications()->latest()->first();
        $this->assertSame(1, (int) ($supNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('new_paid_order', (string) ($supNote->data['meta']['type'] ?? ''));

        $amNote = $areaManager->notifications()->latest()->first();
        $this->assertSame(1, (int) ($amNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('area_manager', (string) ($amNote->data['meta']['recipient_role'] ?? ''));

        // Wave 1 visible on Laravel notification inboxes + header bells
        $this->actingAs($supervisor)
            ->get(route('supervisor.notifications.index'))
            ->assertOk()
            ->assertSee('New Order in Your Area', false)
            ->assertSee('Order alert', false)
            ->assertSee((string) $order->id, false);

        $this->actingAs($supervisor)
            ->get(route('supervisor.dashboard'))
            ->assertOk()
            ->assertSee('New Order in Your Area', false);

        $this->actingAs($areaManager)
            ->get(route('areamanager.notifications.index'))
            ->assertOk()
            ->assertSee('New Order in Your Region', false)
            ->assertSee('Order alert', false)
            ->assertSee((string) $order->id, false);

        $this->actingAs($areaManager)
            ->get(route('areamanager.dashboard'))
            ->assertOk()
            ->assertSee('New Order in Your Region', false);

        $visit = Visit::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($visit, 'Web checkout must create supervisor job visit');
        $this->assertSame($supervisor->id, (int) $visit->supervisor_id);
        $this->assertNull($visit->technician_id);

        // Wave 2: offer job → technician dashboard/inbox
        VisitOfferService::offerToTechnician($visit->fresh(), $technician->id);
        $technician->refresh();
        $this->assertGreaterThanOrEqual(1, $technician->unreadNotifications()->count());

        $techNote = $technician->notifications()->latest()->first();
        $this->assertSame(2, (int) ($techNote->data['meta']['alert_wave'] ?? 0));
        $this->assertSame('job_offer', (string) ($techNote->data['meta']['type'] ?? ''));

        $this->actingAs($technician)
            ->get(route('technician.notifications.index'))
            ->assertOk()
            ->assertSee('New Job Offer', false)
            ->assertSee('Job offer', false)
            ->assertSee((string) $visit->id, false);

        $this->actingAs($technician)
            ->get(route('technician.dashboard'))
            ->assertOk()
            ->assertSee('New Job Offer', false);
    }
}
