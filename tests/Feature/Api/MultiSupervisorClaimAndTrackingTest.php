<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Visit;
use App\Support\OrderToVisitDispatcher;
use App\Support\VisitOrderTrackingSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiSupervisorClaimAndTrackingTest extends TestCase
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

    public function test_unclaimed_job_visible_to_all_area_supervisors_first_claim_wins(): void
    {
        $supA = User::factory()->create(['role' => 'supervisor', 'name' => 'Sup A']);
        $supB = User::factory()->create(['role' => 'supervisor', 'name' => 'Sup B']);
        $this->assignRoleIfAvailable($supA, 'supervisor');
        $this->assignRoleIfAvailable($supB, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi Central',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach([$supA->id, $supB->id]);

        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'price' => 100,
            'job_duration' => '60 min',
        ]);

        $order = Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'order_status' => 'pending',
            'guest_city' => null,
            'total_amount' => 100,
        ]);
        // Force shipping city via address-like fields used by dispatcher.
        $order->forceFill([
            'guest_full_name' => null,
        ])->save();

        // Create a user address so getShippingAddressForApi resolves Abu Dhabi.
        $address = \App\Models\UserAddress::create([
            'user_id' => $client->id,
            'full_name' => 'Client',
            'phone_number' => '+971500000000',
            'street_address' => 'Corniche',
            'city' => 'Abu Dhabi',
            'state' => 'Abu Dhabi',
            'zip_code' => '00000',
            'country' => 'United Arab Emirates',
            'is_default' => true,
        ]);
        $order->update(['shipping_address_id' => $address->id]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
            'subtotal' => 100,
            'booking_date' => now()->addDay()->toDateString(),
            'booking_slot' => '09:00 AM',
        ]);

        $visits = OrderToVisitDispatcher::createVisitsForPaidOrder($order->fresh('items.product'));
        $this->assertCount(1, $visits);
        $visit = $visits->first();
        $this->assertSame((int) $area->id, (int) $visit->area_id);
        $this->assertNull($visit->supervisor_id);

        VisitOrderTrackingSync::syncFromVisit($visit);
        $order->refresh();
        $this->assertSame('processing', $order->order_status);

        $listA = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments');
        $listB = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments');
        $listA->assertOk();
        $listB->assertOk();
        $idsA = collect($listA->json('data.data') ?? [])->pluck('id')->all();
        $idsB = collect($listB->json('data.data') ?? [])->pluck('id')->all();
        $this->assertContains($visit->id, $idsA);
        $this->assertContains($visit->id, $idsB);

        $this->actingAs($supA, 'sanctum')
            ->postJson('/api/supervisor/assignments/'.$visit->id.'/claim')
            ->assertOk()
            ->assertJsonPath('data.supervisor_id', $supA->id);

        $this->actingAs($supB, 'sanctum')
            ->postJson('/api/supervisor/assignments/'.$visit->id.'/claim')
            ->assertStatus(409);

        $listBAfter = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments');
        $idsBAfter = collect($listBAfter->json('data.data') ?? [])->pluck('id')->all();
        $this->assertNotContains($visit->id, $idsBAfter);

        $order->refresh();
        $this->assertSame('confirmed', $order->order_status);

        $track = $this->actingAs($client, 'sanctum')->getJson('/api/orders/'.$order->id.'/track');
        $track->assertOk();
        $timeline = collect($track->json('data.tracking.timeline') ?? []);
        $this->assertTrue((bool) $timeline->firstWhere('key', 'processing')['completed']);
        $this->assertTrue((bool) $timeline->firstWhere('key', 'confirmed')['completed']);
    }
}
