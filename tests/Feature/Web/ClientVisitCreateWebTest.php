<?php

namespace Tests\Feature\Web;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\AdminNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientVisitCreateWebTest extends TestCase
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

    public function test_client_web_create_visit_auto_assigns_supervisor_and_sends_notification(): void
    {
        Notification::fake();

        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');
        $this->assignRoleIfAvailable($admin, 'admin');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'Abu Dhabi City',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'client_id' => $client->id,
            'payment_status' => 'paid',
        ]);

        $response = $this->actingAs($client)->post('/client/visits', [
            'subscription_id' => $subscription->id,
            'scheduled_date' => now()->addDay()->toDateString(),
            'city' => 'Abu Dhabi',
            'country' => 'UAE',
            'notes' => 'Garden Clean-up | Abu Dhabi | AED 120',
        ]);

        $response->assertRedirect('/client/visits');
        $response->assertSessionHas('success');

        $visit = Visit::query()->latest('id')->first();
        $this->assertNotNull($visit);
        $this->assertSame($area->id, (int) $visit->area_id);
        $this->assertSame($supervisor->id, (int) $visit->supervisor_id);

        Notification::assertSentTo($supervisor, AdminNotification::class);
        Notification::assertSentTo($admin, AdminNotification::class);
    }
}

