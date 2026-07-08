<?php

namespace Tests\Feature\Vendor;

use App\Enums\VendorStatus;
use App\Models\SupportChatMessage;
use App\Models\SupportChatSession;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VendorSupportChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'vendor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }

    public function test_vendor_can_open_live_chat_and_send_message(): void
    {
        ['token' => $token] = $this->makeVendorUser();

        $open = $this->withToken($token)->getJson('/api/vendor/support/chat')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'chat' => [
                        'session' => ['id', 'token', 'status'],
                        'messages',
                        'polling',
                    ],
                ],
            ]);

        $sessionId = $open->json('data.chat.session.id');

        $this->withToken($token)->postJson('/api/vendor/support/chat/messages', [
            'message' => 'Hello admin, I need help with my store.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.message.message', 'Hello admin, I need help with my store.')
            ->assertJsonPath('data.message.is_admin', false);

        $this->assertDatabaseHas('support_chat_messages', [
            'support_chat_session_id' => $sessionId,
            'message' => 'Hello admin, I need help with my store.',
            'is_admin' => false,
        ]);
    }

    public function test_admin_can_reply_on_vendor_live_chat(): void
    {
        ['token' => $vendorToken, 'user' => $vendorUser] = $this->makeVendorUser();
        $admin = $this->makeAdminUser();

        $sessionId = $this->withToken($vendorToken)->getJson('/api/vendor/support/chat')
            ->assertOk()
            ->json('data.chat.session.id');

        $vendorMessageId = $this->withToken($vendorToken)->postJson('/api/vendor/support/chat/messages', [
            'message' => 'Vendor question here',
        ])
            ->assertCreated()
            ->json('data.message.id');

        $this->actingAs($admin['user'], 'sanctum')
            ->getJson('/api/admin/support-chat/sessions?user_role=vendor')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $sessionId);

        $this->actingAs($admin['user'], 'sanctum')
            ->postJson('/api/admin/support-chat/sessions/'.$sessionId.'/messages', [
                'message' => 'Hi, how can we help you?',
            ])
            ->assertCreated()
            ->assertJsonPath('data.message.is_admin', true);

        $this->actingAs($vendorUser, 'sanctum')
            ->getJson('/api/vendor/support/chat/messages?after_id='.$vendorMessageId)
            ->assertOk()
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.message', 'Hi, how can we help you?');
    }

    public function test_vendor_cannot_access_another_vendors_chat_session(): void
    {
        ['token' => $tokenA] = $this->makeVendorUser('vendor-a@test.com');
        ['user' => $userB] = $this->makeVendorUser('vendor-b@test.com');

        $sessionB = SupportChatSession::create([
            'user_id' => $userB->id,
            'status' => 'open',
            'subject' => 'Live Chat with Admin',
        ]);

        SupportChatMessage::create([
            'support_chat_session_id' => $sessionB->id,
            'user_id' => $userB->id,
            'message' => 'Private',
            'is_admin' => false,
        ]);

        $this->withToken($tokenA)->getJson('/api/vendor/support/chat/messages?session_id='.$sessionB->id)
            ->assertNotFound();
    }

    /**
     * @return array{user: User, vendor: Vendor, token: string}
     */
    private function makeVendorUser(string $email = 'vendor-chat@test.com'): array
    {
        $user = User::factory()->create([
            'role' => 'vendor',
            'name' => 'Chat Vendor',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('vendor');

        $vendor = Vendor::create([
            'user_id' => $user->id,
            'status' => VendorStatus::Approved->value,
            'approved_at' => now(),
        ]);

        VendorProfile::create([
            'vendor_id' => $vendor->id,
            'business_name' => 'Green Farms LLC',
            'owner_name' => 'Chat Vendor',
            'email' => $user->email,
            'vendor_type' => 'fruits',
            'emirate' => 'Abu Dhabi',
            'city' => 'Al Ain',
        ]);

        return [
            'user' => $user,
            'vendor' => $vendor,
            'token' => $user->createToken('api_vendor', ['vendor'])->plainTextToken,
        ];
    }

    /**
     * @return array{user: User, token: string}
     */
    private function makeAdminUser(): array
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin-chat@test.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
        $user->assignRole('admin');

        return [
            'user' => $user,
            'token' => $user->createToken('api_admin', ['admin'])->plainTextToken,
        ];
    }
}
