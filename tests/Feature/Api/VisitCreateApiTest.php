<?php

namespace Tests\Feature\Api;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitCreateApiTest extends TestCase
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

    public function test_create_visit_requires_resolvable_location_when_area_id_missing(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Visit without area id',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Unable to resolve area from selected location. Send area_id or provide app location fields (street_address/city/state/zip_code/country) or GPS coordinates.');
    }

    public function test_create_visit_auto_assigns_supervisor_from_area_when_not_provided(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create();
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'Abu Dhabi test visit',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    }

    public function test_resolve_area_by_city_name_for_location_step(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

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

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'city' => 'Abu Dhabi',
            'country' => 'UAE',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    }

    public function test_resolve_area_prefers_gps_when_city_is_arabic_script(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'Abu Dhabi City',
            'country' => 'UAE',
            'is_active' => true,
            'latitude' => 24.45,
            'longitude' => 54.37,
            'service_radius_km' => 80,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'city' => 'أبو ظبي',
            'country' => 'UAE',
            'latitude' => 24.453884,
            'longitude' => 54.377344,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.supervisor_id', $supervisor->id);
    }

    public function test_resolve_area_accepts_arabic_uae_country_label_with_english_city(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

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

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'city' => 'Abu Dhabi',
            'country' => 'الإمارات العربية المتحدة',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.area_id', $area->id);
    }

    public function test_resolve_area_matches_urdu_city_without_coordinates_or_geocode(): void
    {
        config(['services.nominatim.forward_geocode_enabled' => false]);
        Http::preventStrayRequests();

        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi City',
            'location' => 'Abu Dhabi',
            'country' => 'UAE',
            'is_active' => true,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'city' => 'ابو ظہبی',
            'country' => 'متحدہ عرب امارات',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id);
    }

    public function test_resolve_area_forward_geocodes_via_nominatim_when_only_multilingual_text(): void
    {
        config(['services.nominatim.forward_geocode_enabled' => true]);

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '24.453884',
                    'lon' => '54.377344',
                ],
            ], 200),
        ]);

        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'UAE',
            'country' => 'UAE',
            'is_active' => true,
            'latitude' => 24.45,
            'longitude' => 54.37,
            'service_radius_km' => 80,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'street_address' => 'آفس 302، الخالدیة، کارنیش روڈ',
            'city' => 'مدينة محمد بن زايد',
            'state' => '',
            'country' => 'متحدہ عرب امارات',
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            return str_contains($request->url(), 'nominatim.openstreetmap.org')
                && str_contains($request->url(), 'search');
        });

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id);
    }

    /**
     * Client-style multilingual text fields + map coordinates: GPS must resolve (no external translation API).
     */
    public function test_resolve_area_multilingual_abu_dhabi_payload_uses_map_coordinates(): void
    {
        Http::preventStrayRequests();

        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'name' => 'Abu Dhabi',
            'location' => 'Al Khalidiya Corniche Road',
            'country' => 'UAE',
            'is_active' => true,
            'latitude' => 24.45,
            'longitude' => 54.37,
            'service_radius_km' => 80,
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits/resolve-area', [
            'full_name' => 'संजीव / سنجیو',
            'street_address' => 'آفس 302، الخالدیة، کارنیش روڈ',
            'city' => 'ابو ظہبی',
            'state' => 'ابو ظہبی',
            'zip_code' => '00000',
            'country' => 'متحدہ عرب امارات (UAE)',
            'latitude' => 24.453884,
            'longitude' => 54.377344,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('serviceable', true)
            ->assertJsonPath('data.area_id', $area->id)
            ->assertJsonPath('data.area_name', 'Abu Dhabi');

        $this->assertNotNull($response->json('data.distance_km'));
        $this->assertLessThan(50.0, (float) $response->json('data.distance_km'));
    }

    public function test_create_visit_rejects_disabled_area(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $this->assignRoleIfAvailable($client, 'client');
        $this->assignRoleIfAvailable($supervisor, 'supervisor');

        $area = Area::factory()->create([
            'is_active' => false,
            'country' => 'UAE',
        ]);
        DB::table('area_supervisor')->insert([
            'area_id' => $area->id,
            'user_id' => $supervisor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subscription = Subscription::factory()->create(['client_id' => $client->id]);

        $response = $this->actingAs($client, 'sanctum')->postJson('/api/visits', [
            'subscription_id' => $subscription->id,
            'area_id' => $area->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    public function test_update_visit_via_real_multipart_put_persists_changes(): void
    {
        // Regression guard: PHP does not populate $_POST for PUT + multipart/form-data,
        // so this simulates the raw wire body a Postman "form-data" PUT actually sends
        // (unlike ->putJson() elsewhere in this file, which bypasses real body parsing).
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignRoleIfAvailable($admin, 'admin');
        $client = User::factory()->create(['role' => 'client']);
        $this->assignRoleIfAvailable($client, 'client');
        $subscription = Subscription::factory()->create(['client_id' => $client->id]);
        $visit = \App\Models\Visit::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => 'pending',
            'notes' => 'Original notes',
        ]);

        $token = $admin->createToken('test', ['admin'])->plainTextToken;

        $boundary = '----VisitUpdateBoundary1';
        $fields = [
            'notes' => 'Updated via real multipart PUT',
            'price' => '150',
            'status' => 'rejected',
        ];
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\n"
                ."Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n"
                ."{$value}\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        $this->call(
            'PUT',
            '/api/visits/'.$visit->id,
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data; boundary='.$boundary,
            ],
            $body
        )
            ->assertOk()
            ->assertJsonPath('data.notes', 'Updated via real multipart PUT')
            ->assertJsonPath('data.status', 'rejected');

        $this->assertEquals('Updated via real multipart PUT', $visit->fresh()->notes);
        $this->assertEquals('rejected', $visit->fresh()->status);
        $this->assertEquals(150.0, (float) $visit->fresh()->price);
    }
}

