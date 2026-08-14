<?php

namespace Tests\Feature\Api;

use App\Enums\VendorStatus;
use App\Models\Area;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use App\Services\Auth\AppleIdTokenVerifier;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => 'technician', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                Role::firstOrCreate(['name' => 'area_manager', 'guard_name' => 'web']);
            }
        } catch (\Throwable $e) {
            // keep test resilient when permission tables are unavailable
        }

        $this->area = Area::factory()->create(['name' => 'Dubai Marina']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        try {
            if (method_exists($supervisor, 'assignRole')) {
                $supervisor->assignRole('supervisor');
            }
        } catch (\Throwable $e) {
            // no-op
        }
        $supervisor->supervisedAreas()->attach($this->area->id);
    }

    public function test_register_technician_success(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'tech.signup@example.com',
            'phone' => '+971500009999',
            'service_area' => 'Dubai Marina',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.status', 'pending');
        $response->assertJsonPath('data.service_area', 'Dubai Marina');
        $this->assertNotEmpty($response->json('data.request_id'));

        $this->assertDatabaseMissing('users', [
            'email' => 'tech.signup@example.com',
        ]);
        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'tech.signup@example.com',
            'phone' => '+971500009999',
            'status' => 'pending',
            'area_id' => $this->area->id,
        ]);
    }

    public function test_register_technician_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'tech.signup@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'phone',
            'service_area',
            'password',
        ]);
    }

    public function test_technician_signup_areas_returns_supervised_zones(): void
    {
        $response = $this->getJson('/api/auth/technician-signup-areas');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertSame($this->area->id, $rows[0]['id']);

        $alias = $this->getJson('/api/technician-signup-areas');
        $alias->assertStatus(200)->assertJsonPath('success', true);
        $this->assertEquals($rows, $alias->json('data'));
    }

    public function test_register_technician_success_with_area_id(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech By Area Id',
            'email' => 'tech.byareaid@example.com',
            'phone' => '+971500008888',
            'area_id' => $this->area->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_area', 'Dubai Marina');

        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'tech.byareaid@example.com',
            'area_id' => $this->area->id,
        ]);
    }

    public function test_register_technician_accepts_any_free_text_location_without_zone_match(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'World Tech',
            'email' => 'world.tech@example.com',
            'phone' => '+971500007777',
            'service_area' => 'San Francisco, CA, United States',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.service_area', 'San Francisco, CA, United States')
            ->assertJsonPath('data.area_id', null);

        $this->assertDatabaseHas('technician_signup_requests', [
            'email' => 'world.tech@example.com',
            'area_id' => null,
        ]);
    }

    public function test_register_technician_resolves_zone_name_inside_map_style_address(): void
    {
        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Map Style',
            'email' => 'map.style@example.com',
            'phone' => '+971500006666',
            'service_area' => 'Plot 12, Dubai Marina, United Arab Emirates',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.area_id', $this->area->id)
            ->assertJsonPath('data.service_area', 'Plot 12, Dubai Marina, United Arab Emirates');
    }

    public function test_register_technician_rejects_duplicate_email_or_phone(): void
    {
        User::factory()->create([
            'email' => 'exists.tech@example.com',
            'phone' => '+971500001111',
            'role' => 'technician',
        ]);

        $response = $this->postJson('/api/auth/register-technician', [
            'name' => 'Tech Signup',
            'email' => 'exists.tech@example.com',
            'phone' => '+971500001111',
            'service_area' => 'Abu Dhabi',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_login_resolves_portal_from_spatie_when_users_role_column_is_stale(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'portal-mismatch@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['area_manager']);

        $this->postJson('/api/auth/login', [
            'email' => 'portal-mismatch@example.com',
            'password' => 'password',
            'roles' => 'area_manager',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'area_manager');
    }

    public function test_login_requires_roles_parameter(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'any@example.com',
            'password' => 'password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_app_login_roles_returns_ordered_slugs(): void
    {
        $response = $this->getJson('/api/auth/app-roles');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $rows = $response->json('data');
        $this->assertIsArray($rows);
        $this->assertCount(count(User::LOGIN_PORTALS), $rows);
        $this->assertSame('client', $rows[0]['slug']);
        $this->assertSame('Client (Customer)', $rows[0]['role']);
        $this->assertSame('Client (Customer)', $rows[0]['title']);
        $this->assertStringContainsString('Subscribe to plans', $rows[0]['description']);
        $this->assertSame($rows[0]['description'], $rows[0]['subtitle']);
        $this->assertArrayHasKey('icon', $rows[0]);
        $this->assertSame('vendor', $rows[6]['slug']);
    }

    /**
     * @dataProvider loginPortalProvider
     */
    public function test_login_succeeds_for_each_portal_role(string $portal): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Role::firstOrCreate(['name' => $portal, 'guard_name' => 'web']);

        $email = $portal.'-login@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'role' => $portal,
            'status' => 'active',
        ]);
        $user->syncRoles([$portal]);

        if ($portal === 'vendor') {
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'status' => VendorStatus::Approved->value,
                'approved_at' => now(),
            ]);
            VendorProfile::create([
                'vendor_id' => $vendor->id,
                'business_name' => 'Login Test',
                'owner_name' => $user->name,
                'email' => $user->email,
            ]);
        }

        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password',
            'roles' => $portal,
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $portal)
            ->assertJsonPath('data.user.email', $email)
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email', 'role']]]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function loginPortalProvider(): array
    {
        $cases = [];
        foreach (User::LOGIN_PORTALS as $portal) {
            $cases[$portal] = [$portal];
        }

        return $cases;
    }

    public function test_login_keeps_previous_portal_tokens_valid(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'token-prune@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $payload = [
            'email' => 'token-prune@example.com',
            'password' => 'password',
            'roles' => 'client',
        ];

        $first = $this->postJson('/api/auth/login', $payload)->assertStatus(200);
        $firstToken = $first->json('data.token');

        $this->postJson('/api/auth/login', $payload)->assertStatus(200);

        $this->withToken($firstToken)
            ->getJson('/api/user/profile')
            ->assertOk();

        $this->assertGreaterThanOrEqual(
            2,
            $user->fresh()->tokens()->where('name', 'api_client')->count()
        );
    }

    public function test_vendor_login_route_uses_same_auth_controller(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'vendor.route@example.com',
            'password' => 'password',
            'role' => 'vendor',
            'status' => 'active',
        ]);
        $user->syncRoles(['vendor']);

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);
        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Route Test',
            'owner_name' => 'Owner',
            'email' => $user->email,
        ]);

        $this->postJson('/api/vendor/auth/login', [
            'email' => 'vendor.route@example.com',
            'password' => 'password',
            'roles' => 'vendor',
        ])->assertStatus(200)
            ->assertJsonPath('data.slug', 'vendor');
    }

    /**
     * @dataProvider loginPortalProvider
     */
    public function test_login_accepts_all_numeric_password_sent_as_json_number(string $portal): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        Role::firstOrCreate(['name' => $portal, 'guard_name' => 'web']);

        $email = $portal.'-numeric-pass@example.com';
        $user = User::factory()->create([
            'email' => $email,
            'password' => '13572468',
            'role' => $portal,
            'status' => 'active',
        ]);
        $user->syncRoles([$portal]);

        if ($portal === 'vendor') {
            $vendor = Vendor::create([
                'user_id' => $user->id,
                'status' => VendorStatus::Approved->value,
                'approved_at' => now(),
            ]);
            VendorProfile::create([
                'vendor_id' => $vendor->id,
                'business_name' => 'Numeric Password Vendor',
                'owner_name' => $user->name,
                'email' => $user->email,
            ]);
        }

        // Sent as a bare JSON number (13572468), not a quoted string — some mobile
        // clients serialize all-digit text fields this way by mistake. The API must
        // still accept it instead of rejecting with "password must be a string".
        $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 13572468,
            'roles' => $portal,
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $portal);
    }

    public function test_login_accepts_role_field_alias(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'role-alias@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->postJson('/api/auth/login', [
            'email' => 'role-alias@example.com',
            'password' => 'password',
            'role' => 'client',
        ])->assertStatus(200)
            ->assertJsonPath('data.slug', 'client');
    }

    public function test_login_succeeds_with_roles_slug(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'roles-param@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->postJson('/api/auth/login', [
            'email' => 'roles-param@example.com',
            'password' => 'password',
            'roles' => 'client',
        ])->assertStatus(200)
            ->assertJsonPath('data.slug', 'client');
    }

    public function test_login_rejects_invalid_roles_value(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'any@example.com',
            'password' => 'password',
            'roles' => 'not-a-real-role',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['roles']);
    }

    public function test_login_roles_must_match_account(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'roles-wrong@example.com',
            'password' => 'password',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->postJson('/api/auth/login', [
            'email' => 'roles-wrong@example.com',
            'password' => 'password',
            'roles' => 'admin',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_google_auth_creates_client_and_returns_login_shape(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $this->mock(GoogleIdTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('valid-google-token')
                ->andReturn([
                    'sub' => 'google-sub-123',
                    'email' => 'google.new@example.com',
                    'name' => 'Google User',
                    'email_verified' => true,
                ]);
        });

        $response = $this->postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
            'roles' => 'client',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.slug', 'client')
            ->assertJsonPath('data.role', 'client')
            ->assertJsonPath('data.user.email', 'google.new@example.com')
            ->assertJsonPath('data.user.needs_phone', true)
            ->assertJsonStructure(['data' => ['token', 'role', 'slug', 'user' => ['id', 'name', 'email', 'needs_phone']]]);

        $this->assertDatabaseHas('users', [
            'email' => 'google.new@example.com',
            'google_id' => 'google-sub-123',
            'role' => 'client',
        ]);
    }

    public function test_google_auth_logs_in_existing_user_by_google_id(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $user = User::factory()->create([
            'email' => 'existing.google@example.com',
            'google_id' => 'google-sub-existing',
            'role' => 'client',
            'status' => 'active',
        ]);
        $user->syncRoles(['client']);

        $this->mock(GoogleIdTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'google-sub-existing',
                    'email' => 'existing.google@example.com',
                    'name' => 'Existing Google',
                    'email_verified' => true,
                ]);
        });

        $this->postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
            'roles' => 'client',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_google_auth_rejects_invalid_token(): void
    {
        $this->mock(GoogleIdTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andThrow(new \RuntimeException('Invalid Google ID token.'));
        });

        $this->postJson('/api/auth/google', [
            'id_token' => 'bad-token',
            'roles' => 'client',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_google_auth_requires_id_token_and_roles(): void
    {
        $this->postJson('/api/auth/google', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id_token', 'roles']);
    }

    public function test_apple_auth_creates_client_with_optional_name_and_email(): void
    {
        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $this->mock(AppleIdTokenVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')
                ->once()
                ->with('valid-apple-token')
                ->andReturn([
                    'sub' => 'apple-sub-456',
                    'email' => null,
                    'email_verified' => false,
                ]);
        });

        $this->postJson('/api/auth/apple', [
            'id_token' => 'valid-apple-token',
            'roles' => 'client',
            'name' => 'Apple Client',
            'email' => 'apple.new@example.com',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.email', 'apple.new@example.com')
            ->assertJsonPath('data.user.name', 'Apple Client');

        $this->assertDatabaseHas('users', [
            'apple_id' => 'apple-sub-456',
            'email' => 'apple.new@example.com',
        ]);
    }

    public function test_social_auth_endpoints_are_registered(): void
    {
        $this->postJson('/api/auth/google', ['roles' => 'client'])
            ->assertStatus(422);

        $this->postJson('/api/auth/apple', ['roles' => 'client'])
            ->assertStatus(422);
    }

    public function test_google_tokeninfo_verifier_accepts_payload_when_audience_matches(): void
    {
        $clientId = '805072500013-5unlc40tcam3lidd9vb4hkhmausbnoep.apps.googleusercontent.com';
        config(['services.google.client_id' => $clientId]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'live-google-sub',
                'email' => 'live.google@example.com',
                'name' => 'Live Google',
                'email_verified' => 'true',
                'aud' => $clientId,
            ]),
        ]);

        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $this->postJson('/api/auth/google', [
            'id_token' => 'real-shaped-token',
            'roles' => 'client',
        ])->assertStatus(200)
            ->assertJsonPath('data.user.google_id', 'live-google-sub');
    }

    public function test_apple_auth_accepts_token_when_aud_matches_tandil_bundle_id(): void
    {
        $bundleId = 'com.tandilapp.tandil';
        config(['services.apple.client_id' => $bundleId]);

        $this->mock(AppleIdTokenVerifier::class, function ($mock) use ($bundleId): void {
            $mock->shouldReceive('verify')
                ->once()
                ->andReturn([
                    'sub' => 'apple-sub-tandil',
                    'email' => 'apple.tandil@privaterelay.appleid.com',
                    'email_verified' => true,
                ]);
        });

        if (! class_exists(Role::class) || ! Schema::hasTable('roles')) {
            $this->markTestSkipped('Spatie permission tables unavailable.');
        }

        $this->postJson('/api/auth/apple', [
            'id_token' => 'valid-apple-token',
            'roles' => 'client',
            'name' => 'Tandil User',
        ])->assertStatus(200)
            ->assertJsonPath('data.slug', 'client')
            ->assertJsonPath('data.user.apple_id', 'apple-sub-tandil')
            ->assertJsonPath('data.user.email', 'apple.tandil@privaterelay.appleid.com');

        $this->assertSame($bundleId, config('services.apple.client_id'));
    }

    public function test_google_auth_rejects_token_with_wrong_audience_for_public_client_id(): void
    {
        config([
            'services.google.client_id' => '805072500013-5unlc40tcam3lidd9vb4hkhmausbnoep.apps.googleusercontent.com',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => 'wrong-aud-sub',
                'email' => 'wrong.aud@example.com',
                'name' => 'Wrong Aud',
                'email_verified' => 'true',
                'aud' => 'other-client-id.apps.googleusercontent.com',
            ]),
        ]);

        $this->postJson('/api/auth/google', [
            'id_token' => 'token-wrong-aud',
            'roles' => 'client',
        ])->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', [
            'email' => 'wrong.aud@example.com',
        ]);
    }
}
