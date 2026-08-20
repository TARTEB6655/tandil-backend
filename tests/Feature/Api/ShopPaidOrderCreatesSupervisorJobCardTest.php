<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
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
        $areaManager = User::factory()->create(['role' => 'area_manager', 'email' => 'am-smoke@example.com']);
        $this->assignRoleIfAvailable($admin, 'admin');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($areaManager, 'area_manager');

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
        $beforeAreaManagerUnread = $areaManager->unreadNotifications()->count();

        $capture = $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json']);

        $capture->assertOk()->assertJsonPath('success', true);

        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertSame('paid', $order->payment_status);

        // 1) Admin + supervisor + area manager notifications should be created.
        $admin->refresh();
        $supervisor->refresh();
        $areaManager->refresh();
        $this->assertGreaterThan($beforeAdminUnread, $admin->unreadNotifications()->count());
        $this->assertGreaterThan($beforeSupervisorUnread, $supervisor->unreadNotifications()->count());
        $this->assertGreaterThan($beforeAreaManagerUnread, $areaManager->unreadNotifications()->count());

        // 2) A Visit (job) should be created and assigned to that supervisor/area.
        $marker = '[SHOP-ORDER:' . $orderId . ']';
        $visit = Visit::query()->where('notes', 'like', '%' . $marker . '%')->first();
        $this->assertNotNull($visit);
        $this->assertSame((int) $area->id, (int) $visit->area_id);
        $this->assertSame((int) $supervisor->id, (int) $visit->supervisor_id);
        // Simulate older records where duration was persisted as "-- min" in notes.
        $visit->notes = str_replace('55 min', '-- min', (string) $visit->notes);
        $visit->save();

        // 3) Supervisor assignments endpoint should return the client details so card UI can show it.
        $response = $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/assignments/' . $visit->id);
        $response->assertOk()->assertJsonPath('success', true);

        $json = $response->json();
        // Fallback should resolve duration from [SHOP-ORDER:id] -> order item product.job_duration.
        $this->assertSame(55, (int) ($json['data']['duration_minutes'] ?? 0));
        $customer = $json['data']['customer'] ?? null;
        $this->assertNotNull($customer);
        $this->assertSame($clientEmail, $customer['email'] ?? null);
        $this->assertSame($clientPhone, $customer['phone'] ?? null);
        $this->assertNotEmpty((string) ($customer['name'] ?? ''));

        // 2b) Order tracking should auto-progress with visit lifecycle.
        $order->refresh();
        $this->assertSame('confirmed', (string) $order->order_status);

        $technician = User::factory()->create(['role' => 'technician', 'email' => 'tech-smoke@example.com']);
        $visit->technician_id = $technician->id;
        $visit->status = 'pending_acceptance';
        $visit->save();
        $order->refresh();
        $this->assertSame('assigned', (string) $order->order_status);

        $visit->status = 'in_progress';
        $visit->save();
        $order->refresh();
        $this->assertSame('in_progress', (string) $order->order_status);

        $report = Report::factory()->create([
            'visit_id' => $visit->id,
            'supervisor_id' => $supervisor->id,
            'status' => 'pending',
        ]);
        $visit->status = 'completed';
        $visit->save();
        $order->refresh();
        // Completed requires report sent to client, so keep in_progress until then.
        $this->assertSame('in_progress', (string) $order->order_status);

        $this->actingAs($supervisor, 'sanctum')
            ->postJson('/api/supervisor/reports/' . $report->id . '/accept')
            ->assertOk()
            ->assertJsonPath('success', true);
        $order->refresh();
        $this->assertSame('in_progress', (string) $order->order_status);

        $report->refresh();
        $this->assertSame('approved', $report->status);
        $report->status = 'sent_to_client';
        $report->save();
        \App\Support\VisitOrderTrackingSync::syncFromVisit($visit->fresh());
        $order->refresh();
        $this->assertSame('completed', (string) $order->order_status);
    }

    public function test_paid_order_with_booking_slot_carries_date_and_time_to_visit_and_supervisor_card(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Dubai Central',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
        ]);

        $bookingDate = now()->addDays(3)->toDateString();

        $start = $this->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'email' => 'booking-slot-client@example.com',
            'full_name' => 'Booking Slot Client',
            'phone_number' => '+971501112222',
            'street_address' => 'Marina Walk',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'booking_date' => $bookingDate,
            'booking_slot' => '10:00 AM - 12:00 PM',
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $start->assertStatus(201)->assertJsonPath('success', true);
        $orderId = (int) $start->json('data.order_id');
        $paypalOrderId = (string) $start->json('data.paypal_order_id');

        $this->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json'])->assertOk();

        $marker = '[SHOP-ORDER:' . $orderId . ']';
        $visit = Visit::query()->where('notes', 'like', '%' . $marker . '%')->first();
        $this->assertNotNull($visit);
        $this->assertSame($bookingDate, $visit->scheduled_date?->toDateString());
        $this->assertSame('10:00', $visit->scheduled_time);
        $this->assertSame(120, $visit->duration_minutes);

        $response = $this->actingAs($supervisor, 'sanctum')
            ->getJson('/api/supervisor/assignments/' . $visit->id);
        $response->assertOk()->assertJsonPath('success', true);
        $response->assertJsonPath('data.date', $bookingDate);
        $response->assertJsonPath('data.time_slot', '10:00 AM - 12:00 PM');
    }

    public function test_logged_in_cart_per_product_booking_slot_is_preserved_for_visits_and_assignments(): void
    {
        Setting::set('paypal_enabled', '1');
        Config::set('payments.paypal.client_id', '');
        Config::set('payments.paypal.secret', '');

        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Dubai Central',
            'location' => 'Dubai',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach($supervisor->id);

        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');

        $category = Category::factory()->create();
        $productA = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'status' => 'active',
            'job_duration' => '55 minutes',
        ]);
        $productB = Product::factory()->create([
            'category_id' => $category->id,
            'price' => 20.00,
            'status' => 'active',
            'job_duration' => '60 minutes',
        ]);

        $bookingDateA = now()->addDays(3)->toDateString();
        $bookingSlotA = '10:00 AM - 12:00 PM';

        $bookingDateB = now()->addDays(5)->toDateString();
        $bookingSlotB = '2:00 PM - 4:00 PM';

        Cart::create([
            'user_id' => $client->id,
            'product_id' => $productA->id,
            'quantity' => 1,
            'booking_date' => $bookingDateA,
            'booking_slot' => $bookingSlotA,
        ]);
        Cart::create([
            'user_id' => $client->id,
            'product_id' => $productB->id,
            'quantity' => 1,
            'booking_date' => $bookingDateB,
            'booking_slot' => $bookingSlotB,
        ]);

        $start = $this->actingAs($client, 'sanctum')->postJson('/api/shop/checkout/start', [
            'payment_method' => 'paypal',
            'full_name' => 'Cart Booking Client',
            'phone_number' => '+971501112222',
            'street_address' => 'Marina Walk',
            'city' => 'Dubai',
            'country' => 'United Arab Emirates',
            // Intentionally omit `items` so backend uses cart lines (with
            // booking_date/booking_slot per cart row).
            'success_url' => 'https://example.com/ok',
            'cancel_url' => 'https://example.com/cancel',
        ], ['Accept' => 'application/json']);

        $start->assertStatus(201)->assertJsonPath('success', true);
        $orderId = (int) $start->json('data.order_id');
        $paypalOrderId = (string) $start->json('data.paypal_order_id');

        $this->actingAs($client, 'sanctum')->postJson('/api/shop/paypal/capture', [
            'paypal_order_id' => $paypalOrderId,
            'order_id' => $orderId,
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('success', true);

        // 1) Track response should include booking info for each product line.
        $track = $this->actingAs($client, 'sanctum')->getJson('/api/orders/' . $orderId . '/track', ['Accept' => 'application/json']);
        $track->assertOk()->assertJsonPath('success', true);
        $this->assertCount(2, $track->json('data.order.items'));
        $itemDates = collect($track->json('data.order.items'))->pluck('booking_date')->all();
        $itemSlots = collect($track->json('data.order.items'))->pluck('booking_slot')->all();
        $this->assertContains($bookingDateA, $itemDates);
        $this->assertContains($bookingDateB, $itemDates);
        $this->assertContains($bookingSlotA, $itemSlots);
        $this->assertContains($bookingSlotB, $itemSlots);

        // 2) Visits should have per-item scheduled date/time, and supervisor card
        // should return the exact time_slot strings.
        $visits = Visit::query()->where('order_id', $orderId)->get();
        $this->assertCount(2, $visits);

        $expected = [
            $bookingDateA => $bookingSlotA,
            $bookingDateB => $bookingSlotB,
        ];

        foreach ($visits as $visit) {
            $response = $this->actingAs($supervisor, 'sanctum')
                ->getJson('/api/supervisor/assignments/' . $visit->id, ['Accept' => 'application/json']);
            $response->assertOk()->assertJsonPath('success', true);

            $this->assertArrayHasKey((string) ($response->json('data.date') ?? ''), $expected);
            $date = (string) $response->json('data.date');
            $timeSlot = (string) $response->json('data.time_slot');
            $this->assertSame($expected[$date], $timeSlot);
        }
    }
}

