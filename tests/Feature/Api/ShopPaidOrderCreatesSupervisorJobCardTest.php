<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Schema;

class ShopPaidOrderCreatesSupervisorJobCardTest extends TestCase
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

    public function test_paid_paypal_order_creates_visit_and_supervisor_assignment_card(): void
    {
        // Enable PayPal capture placeholder flow (so we can mark order as paid).
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-smoke@example.com']);
        $supervisor = User::factory()->create(['role' => 'supervisor', 'email' => 'sup-smoke@example.com']);
        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $clientFullName = 'Client Smoke';
        $clientEmail = 'client-smoke@example.com';
        $clientPhone = '+971501234567';

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
            'job_duration' => '55 minutes',
        ]);

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => $clientEmail,
            'full_name' => $clientFullName,
            'phone_number' => $clientPhone,
            'street_address' => 'Plot 1, Al Barsha',
            'city' => 'Abu Dhabi',
            'country' => 'United Arab Emirates',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $start->assertStatus(201)->assertJsonPath('success', true);
        $orderId = (int) $start->json('data.order_id');
        $paypalOrderId = (string) $start->json('data.paypal_order_id');

        $beforeAdminUnread = $admin->unreadNotifications()->count();
        $beforeSupervisorUnread = $supervisor->unreadNotifications()->count();

        $capture = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json']);

        $capture->assertOk()->assertJsonPath('success', true);

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->payment_status);

        // 1) Admin + supervisor notifications should be created.
        $admin->refresh();
        $supervisor->refresh();
        $this->assertGreaterThan($beforeAdminUnread, $admin->unreadNotifications()->count());
        $this->assertGreaterThan($beforeSupervisorUnread, $supervisor->unreadNotifications()->count());

        // 2) A Visit (job) should be created and assigned to that supervisor/area.
        $marker = '[SHOP-ORDER:' . $orderId . ']';
        $visit = Visit::query()->where('notes', 'like', '%' . $marker . '%')->first();
        $this->assertNotNull($visit);
        $this->assertSame((int) $area->id, (int) $visit->area_id);
        $this->assertSame((int) $supervisor->id, (int) $visit->supervisor_id);

        // 3) Supervisor assignments endpoint should return the client details so card UI can show it.
        $response = $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/assignments/' . $visit->id);
        $response->assertOk()->assertJsonPath('success', true);

        $json = $response->json();
        $this->assertSame(55, (int) ($json['data']['duration_minutes'] ?? 0));
        $customer = $json['data']['customer'] ?? null;
        $this->assertNotNull($customer);
        $this->assertSame($clientEmail, $customer['email'] ?? null);
        $this->assertSame($clientPhone, $customer['phone'] ?? null);
        $this->assertNotEmpty((string) ($customer['name'] ?? ''));
    }
}

