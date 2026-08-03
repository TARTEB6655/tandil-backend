<?php

namespace Tests\Feature\Api;

use App\Jobs\OptimizePublicDiskImageJob;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AdminNotification;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientDashboardProfileApiTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create(['role' => 'client']);
        if (method_exists($this->client, 'assignRole')) {
            $this->client->assignRole('client');
        }
        $this->token = $this->client->createToken('test')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->token,
        ];
    }

    // ---- Settings sections (GET /api/client/settings/sections) ----
    public function test_settings_sections_requires_auth(): void
    {
        $response = $this->getJson('/api/client/settings/sections');
        $response->assertStatus(401);
    }

    public function test_settings_sections_returns_all_profile_sections(): void
    {
        $response = $this->getJson('/api/client/settings/sections', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Profile settings sections retrieved.');
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(9, count($data));
        $ids = array_column($data, 'id');
        $this->assertContains('memberships', $ids);
        $this->assertContains('personal_information', $ids);
        $this->assertContains('addresses', $ids);
        $this->assertContains('payment_methods', $ids);
        $this->assertContains('notifications', $ids);
        $this->assertContains('help_support', $ids);
        foreach ($data as $section) {
            $this->assertArrayHasKey('id', $section);
            $this->assertArrayHasKey('title', $section);
            $this->assertArrayHasKey('path', $section);
            $this->assertArrayHasKey('method', $section);
        }
    }

    // ---- Memberships (GET /api/client/memberships) ----
    public function test_memberships_requires_auth(): void
    {
        $response = $this->getJson('/api/client/memberships');
        $response->assertStatus(401);
    }

    public function test_memberships_requires_client_role(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }
        $token = $admin->createToken('test')->plainTextToken;
        $response = $this->getJson('/api/client/memberships', [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ]);
        $response->assertStatus(403);
    }

    public function test_memberships_returns_admin_created_packages(): void
    {
        // Memberships API returns same subscription data as GET /api/subscriptions for the client
        Subscription::factory()->count(2)->create(['client_id' => $this->client->id]);

        $response = $this->getJson('/api/client/memberships', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Memberships retrieved successfully.');
        $response->assertJsonPath('total', 2);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'client_id', 'plan', 'start_date', 'end_date', 'amount', 'payment_status', 'total_visits', 'completed_visits', 'visits'],
            ],
        ]);
    }

    // ---- Payment methods (GET /api/user/payment-methods) ----
    public function test_payment_methods_requires_auth(): void
    {
        $response = $this->getJson('/api/user/payment-methods');
        $response->assertStatus(401);
    }

    public function test_payment_methods_returns_success(): void
    {
        $response = $this->getJson('/api/user/payment-methods', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Payment methods retrieved successfully.');
        // ApiResponse omits 'data' when empty; if present it must be array
        $json = $response->json();
        if (array_key_exists('data', $json)) {
            $this->assertIsArray($json['data']);
            $this->assertEmpty($json['data']);
        }
    }

    // ---- Profile (GET/PUT /api/user/profile) ----
    public function test_user_profile_get_requires_auth(): void
    {
        $response = $this->getJson('/api/user/profile');
        $response->assertStatus(401);
    }

    public function test_user_profile_get_returns_user(): void
    {
        $response = $this->getJson('/api/user/profile', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.id', $this->client->id);
        $response->assertJsonPath('data.email', $this->client->email);
    }

    public function test_user_profile_put_updates_name(): void
    {
        $response = $this->putJson('/api/user/profile', [
            'name' => 'Updated Client Name',
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Updated Client Name');
        $this->client->refresh();
        $this->assertSame('Updated Client Name', $this->client->name);
    }

    public function test_user_profile_photo_update_defers_compression_and_stays_fast(): void
    {
        Storage::fake('public');
        Bus::fake([OptimizePublicDiskImageJob::class]);

        $started = microtime(true);
        $response = $this->post('/api/user/profile', [
            'name' => 'devavology12',
            'email' => $this->client->email,
            'phone' => '983434343',
            'profile_picture' => UploadedFile::fake()->image('avatar.jpg', 2400, 2400)->size(4500),
        ], [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$this->token,
        ]);
        $elapsedMs = (microtime(true) - $started) * 1000;

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'devavology12')
            ->assertJsonPath('data.phone', '983434343');

        $this->assertNotEmpty($response->json('data.profile_picture'));
        Bus::assertDispatched(OptimizePublicDiskImageJob::class, function (OptimizePublicDiskImageJob $job) {
            return $job->profile === 'user' && str_contains($job->relativePath, 'profiles/');
        });
        $this->assertLessThan(2500, $elapsedMs, "Client profile update took {$elapsedMs}ms");
    }

    public function test_user_profile_rejects_duplicate_phone_with_friendly_message(): void
    {
        User::factory()->create(['role' => 'client', 'phone' => '0555381810']);

        $this->putJson('/api/user/profile', [
            'phone' => '0555381810',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('message', 'This phone number is already registered. Please use a different phone number.');
    }

    // ---- Phone only (PUT/POST/PATCH /api/user/phone) — post Google/Apple signup popup ----
    public function test_user_phone_update_requires_auth(): void
    {
        $this->putJson('/api/user/phone', ['phone' => '+971501234567'])
            ->assertStatus(401);
    }

    public function test_user_phone_update_success(): void
    {
        $this->client->forceFill(['phone' => null])->save();

        $response = $this->putJson('/api/user/phone', [
            'phone' => '+971501234567',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Phone number updated successfully.')
            ->assertJsonPath('data.phone', '+971501234567')
            ->assertJsonPath('data.needs_phone', false);

        $this->client->refresh();
        $this->assertSame('+971501234567', $this->client->phone);
    }

    public function test_user_phone_update_accepts_phone_number_alias(): void
    {
        $this->client->forceFill(['phone' => null])->save();

        $this->postJson('/api/user/phone', [
            'phone_number' => '+971509998877',
        ], $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.phone', '+971509998877')
            ->assertJsonPath('data.needs_phone', false);
    }

    public function test_user_phone_update_rejects_duplicate(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971501111111',
        ]);
        $this->client->forceFill(['phone' => null])->save();

        $this->putJson('/api/user/phone', [
            'phone' => '+971501111111',
        ], $this->authHeaders())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_user_profile_includes_needs_phone_when_missing(): void
    {
        $this->client->forceFill(['phone' => null])->save();

        $this->getJson('/api/user/profile', $this->authHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.needs_phone', true)
            ->assertJsonPath('data.phone', null);
    }

    // ---- Addresses (GET /api/user/addresses) ----
    public function test_user_addresses_requires_auth(): void
    {
        $response = $this->getJson('/api/user/addresses');
        $response->assertStatus(401);
    }

    public function test_user_addresses_returns_empty_when_none(): void
    {
        $response = $this->getJson('/api/user/addresses', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Addresses retrieved successfully.');
        $json = $response->json();
        if (array_key_exists('data', $json)) {
            $this->assertIsArray($json['data']);
            $this->assertEmpty($json['data']);
        }
    }

    public function test_user_addresses_create_success(): void
    {
        $response = $this->postJson('/api/user/addresses', [
            'full_name' => 'Client One',
            'phone_number' => '+971501234567',
            'street_address' => '123 Main St',
            'city' => 'Dubai',
            'country' => 'UAE',
            'is_default' => true,
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Address created successfully.');
        $response->assertJsonPath('data.full_name', 'Client One');
        $response->assertJsonPath('data.city', 'Dubai');
        $this->assertDatabaseHas('user_addresses', [
            'user_id' => $this->client->id,
            'full_name' => 'Client One',
            'city' => 'Dubai',
        ]);
    }

    public function test_user_addresses_update_success(): void
    {
        $address = UserAddress::create([
            'user_id' => $this->client->id,
            'full_name' => 'Old Name',
            'phone_number' => '+971501234567',
            'street_address' => '123 Main St',
            'city' => 'Dubai',
            'country' => 'UAE',
            'is_default' => false,
        ]);
        $response = $this->putJson("/api/user/addresses/{$address->id}", [
            'full_name' => 'Updated Name',
            'city' => 'Abu Dhabi',
        ], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.full_name', 'Updated Name');
        $response->assertJsonPath('data.city', 'Abu Dhabi');
        $address->refresh();
        $this->assertSame('Updated Name', $address->full_name);
        $this->assertSame('Abu Dhabi', $address->city);
    }

    public function test_user_addresses_delete_success(): void
    {
        $address = UserAddress::create([
            'user_id' => $this->client->id,
            'full_name' => 'To Delete',
            'phone_number' => '+971501234567',
            'street_address' => '123 Main St',
            'city' => 'Dubai',
            'country' => 'UAE',
            'is_default' => false,
        ]);
        $response = $this->deleteJson("/api/user/addresses/{$address->id}", [], $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
    }

    // ---- Notifications (GET /api/user/notifications) ----
    public function test_user_notifications_requires_auth(): void
    {
        $response = $this->getJson('/api/user/notifications');
        $response->assertStatus(401);
    }

    public function test_user_notifications_returns_paginated_inbox(): void
    {
        $response = $this->getJson('/api/user/notifications', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'unread_count',
                'notifications' => ['current_page', 'data', 'per_page', 'total'],
            ],
        ]);
    }

    public function test_user_notifications_returns_database_notifications(): void
    {
        $this->client->notify(new AdminNotification('Order update', 'Your order is on the way.'));

        $response = $this->getJson('/api/user/notifications', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.unread_count', 1);
        $data = $response->json('data.notifications.data');
        $this->assertCount(1, $data);
        $this->assertSame(AdminNotification::class, $data[0]['type']);
        $this->assertSame('Order update', $data[0]['data']['title']);
    }

    // ---- Help & Support: Help Center (GET /api/support/help-center) ----
    public function test_support_help_center_requires_auth(): void
    {
        $response = $this->getJson('/api/support/help-center');
        $response->assertStatus(401);
    }

    public function test_support_help_center_returns_heading_get_support_contact_faqs(): void
    {
        $response = $this->getJson('/api/support/help-center', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.heading', 'How can we help you?');
        $response->assertJsonPath('data.tagline', 'Find answers to common questions or get in touch with our support team');
        $response->assertJsonStructure([
            'data' => [
                'heading',
                'tagline',
                'get_support' => [
                    '*' => ['type', 'title', 'subtitle'],
                ],
                'contact_info' => ['phone', 'email', 'support_hours'],
                'faqs',
            ],
        ]);
        $getSupport = $response->json('data.get_support');
        $this->assertCount(5, $getSupport);
        $types = array_column($getSupport, 'type');
        $this->assertContains('call', $types);
        $this->assertContains('whatsapp', $types);
        $this->assertContains('email', $types);
        $this->assertContains('live_chat', $types);
        $this->assertContains('submit_ticket', $types);
    }

    // ---- Help & Support: FAQs (GET /api/support/faqs) ----
    public function test_support_faqs_requires_auth(): void
    {
        $response = $this->getJson('/api/support/faqs');
        $response->assertStatus(401);
    }

    public function test_support_faqs_returns_data_array(): void
    {
        $response = $this->getJson('/api/support/faqs', $this->authHeaders());
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data']);
    }

    // ---- Help & Support: Submit ticket (POST /api/support/tickets) ----
    public function test_support_submit_ticket_requires_auth(): void
    {
        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'Test',
            'message' => 'Test message',
        ]);
        $response->assertStatus(401);
    }

    public function test_support_submit_ticket_success(): void
    {
        $response = $this->postJson('/api/support/tickets', [
            'subject' => 'Support request',
            'email' => $this->client->email,
            'description' => 'Need help with the app.',
        ], $this->authHeaders());
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Support ticket submitted successfully.');
        $response->assertJsonStructure(['data' => ['id', 'ticket_number', 'subject', 'status', 'created_at']]);
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $this->client->id,
            'subject' => 'Support request',
            'status' => 'open',
        ]);
    }

    public function test_support_submit_ticket_validation(): void
    {
        $response = $this->postJson('/api/support/tickets', [], $this->authHeaders());
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject', 'email', 'description']);
    }
}
