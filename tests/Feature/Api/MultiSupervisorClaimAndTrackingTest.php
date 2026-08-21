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

        // New Jobs: both supervisors see it. Assignment list: empty until accept.
        $newA = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newB = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newA->assertOk();
        $newB->assertOk();
        $idsNewA = collect($newA->json('data.data') ?? [])->pluck('id')->all();
        $idsNewB = collect($newB->json('data.data') ?? [])->pluck('id')->all();
        $this->assertContains($visit->id, $idsNewA);
        $this->assertContains($visit->id, $idsNewB);
        // Same area_id for every supervisor on the shared zone.
        $rowA = collect($newA->json('data.data') ?? [])->firstWhere('id', $visit->id);
        $rowB = collect($newB->json('data.data') ?? [])->firstWhere('id', $visit->id);
        $this->assertSame((int) $area->id, (int) ($rowA['area_id'] ?? 0));
        $this->assertSame((int) $area->id, (int) ($rowB['area_id'] ?? 0));
        $this->assertSame($rowA['area_id'], $rowB['area_id']);

        $listA = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments');
        $idsA = collect($listA->json('data.data') ?? [])->pluck('id')->all();
        $this->assertNotContains($visit->id, $idsA);

        // Both supervisors received "unclaimed job available" notifications.
        $this->assertGreaterThan(
            0,
            $supA->notifications()
                ->where('data->meta->type', 'supervisor_new_zone_job_unclaimed')
                ->where('data->meta->visit_id', $visit->id)
                ->count()
        );
        $this->assertGreaterThan(
            0,
            $supB->notifications()
                ->where('data->meta->type', 'supervisor_new_zone_job_unclaimed')
                ->where('data->meta->visit_id', $visit->id)
                ->count()
        );

        $this->actingAs($supA, 'sanctum')
            ->postJson('/api/supervisor/assignments/'.$visit->id.'/claim')
            ->assertOk()
            ->assertJsonPath('data.supervisor_id', $supA->id);

        // After accept: in A's assignment list, gone from both New Jobs.
        $listAAfter = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments');
        $this->assertContains($visit->id, collect($listAAfter->json('data.data') ?? [])->pluck('id')->all());

        $newAAfter = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newBAfter = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $this->assertNotContains($visit->id, collect($newAAfter->json('data.data') ?? [])->pluck('id')->all());
        $this->assertNotContains($visit->id, collect($newBAfter->json('data.data') ?? [])->pluck('id')->all());

        $this->actingAs($supB, 'sanctum')
            ->postJson('/api/supervisor/assignments/'.$visit->id.'/claim')
            ->assertStatus(409);

        $listBAfter = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments');
        $idsBAfter = collect($listBAfter->json('data.data') ?? [])->pluck('id')->all();
        $this->assertNotContains($visit->id, $idsBAfter);

        // Unclaimed notifications removed for both after claim.
        $this->assertSame(
            0,
            $supA->fresh()->notifications()
                ->where('data->meta->type', 'supervisor_new_zone_job_unclaimed')
                ->where('data->meta->visit_id', $visit->id)
                ->count()
        );
        $this->assertSame(
            0,
            $supB->fresh()->notifications()
                ->where('data->meta->type', 'supervisor_new_zone_job_unclaimed')
                ->where('data->meta->visit_id', $visit->id)
                ->count()
        );

        $order->refresh();
        $this->assertSame('confirmed', $order->order_status);

        $track = $this->actingAs($client, 'sanctum')->getJson('/api/orders/'.$order->id.'/track');
        $track->assertOk();
        $timeline = collect($track->json('data.tracking.timeline') ?? []);
        $this->assertTrue((bool) $timeline->firstWhere('key', 'processing')['completed']);
        $this->assertTrue((bool) $timeline->firstWhere('key', 'confirmed')['completed']);
    }

    public function test_supervisor_reject_hides_job_only_for_self(): void
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

        $visit = Visit::create([
            'area_id' => $area->id,
            'supervisor_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00',
            'status' => 'pending',
            'notes' => 'Reject test job',
            'price' => 100,
        ]);

        $this->actingAs($supA, 'sanctum')
            ->postJson('/api/supervisor/assignments/'.$visit->id.'/reject')
            ->assertOk()
            ->assertJsonPath('data.declined', true);

        $newA = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newB = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $this->assertNotContains($visit->id, collect($newA->json('data.data') ?? [])->pluck('id')->all());
        $this->assertContains($visit->id, collect($newB->json('data.data') ?? [])->pluck('id')->all());
    }

    public function test_new_jobs_repairs_null_area_id_so_shared_zone_supervisors_see_job(): void
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
        $order = Order::factory()->create([
            'user_id' => $client->id,
            'package_id' => null,
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'shipping_address_id' => $address->id,
            'total_amount' => 100,
        ]);

        // Broken row: unclaimed but area_id missing — previously invisible on New Jobs.
        $visit = Visit::create([
            'order_id' => $order->id,
            'area_id' => null,
            'supervisor_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00',
            'status' => 'pending',
            'notes' => 'Missing area pool job',
            'price' => 100,
        ]);

        $newA = $this->actingAs($supA, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newB = $this->actingAs($supB, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $newA->assertOk();
        $newB->assertOk();
        $this->assertContains($visit->id, collect($newA->json('data.data') ?? [])->pluck('id')->all());
        $this->assertContains($visit->id, collect($newB->json('data.data') ?? [])->pluck('id')->all());

        $visit->refresh();
        $this->assertSame((int) $area->id, (int) $visit->area_id);
    }

    public function test_new_jobs_only_includes_pending_unclaimed_not_completed(): void
    {
        $sup = User::factory()->create(['role' => 'supervisor', 'name' => 'Sup']);
        $this->assignRoleIfAvailable($sup, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        $area->supervisors()->attach([$sup->id]);

        $pending = Visit::create([
            'area_id' => $area->id,
            'supervisor_id' => null,
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '09:00',
            'status' => 'pending',
            'notes' => 'Open new job',
            'price' => 100,
        ]);
        $completed = Visit::create([
            'area_id' => $area->id,
            'supervisor_id' => null,
            'scheduled_date' => now()->subDay()->toDateString(),
            'scheduled_time' => '09:00',
            'status' => 'completed',
            'notes' => 'Old completed should hide',
            'price' => 100,
        ]);
        $inProgress = Visit::create([
            'area_id' => $area->id,
            'supervisor_id' => null,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '10:00',
            'status' => 'in_progress',
            'notes' => 'In progress should hide',
            'price' => 100,
        ]);
        $rejected = Visit::create([
            'area_id' => $area->id,
            'supervisor_id' => null,
            'scheduled_date' => now()->toDateString(),
            'scheduled_time' => '11:00',
            'status' => 'rejected',
            'notes' => 'Rejected should hide',
            'price' => 100,
        ]);

        $new = $this->actingAs($sup, 'sanctum')->getJson('/api/supervisor/assignments/new');
        $new->assertOk();
        $ids = collect($new->json('data.data') ?? [])->pluck('id')->all();
        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($completed->id, $ids);
        $this->assertNotContains($inProgress->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }
}
