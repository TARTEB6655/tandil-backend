<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPhoneWebTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = User::factory()->create([
            'role' => 'client',
            'phone' => null,
            'name' => 'Phone Web Client',
        ]);
        $this->assignRoleIfAvailable($this->client, 'client');
    }

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

    public function test_phone_page_requires_auth(): void
    {
        $this->get(route('client.phone.edit'))->assertRedirect();
    }

    public function test_phone_page_loads_for_client_when_missing(): void
    {
        $this->actingAs($this->client)
            ->get(route('client.phone.edit'))
            ->assertOk()
            ->assertSee('Phone number')
            ->assertSee('Add your phone number')
            ->assertSee('Save phone number');
    }

    public function test_client_can_save_phone_number(): void
    {
        $this->actingAs($this->client)
            ->put(route('client.phone.update'), [
                'phone' => '+971501234567',
            ])
            ->assertRedirect(route('client.phone.edit'))
            ->assertSessionHas('status', 'phone-updated');

        $this->client->refresh();
        $this->assertSame('+971501234567', $this->client->phone);
        $this->assertFalse($this->client->needsPhone());
    }

    public function test_phone_update_rejects_duplicate(): void
    {
        User::factory()->create([
            'role' => 'client',
            'phone' => '+971501111111',
        ]);

        $this->actingAs($this->client)
            ->from(route('client.phone.edit'))
            ->put(route('client.phone.update'), [
                'phone' => '+971501111111',
            ])
            ->assertRedirect(route('client.phone.edit'))
            ->assertSessionHasErrors('phone');
    }

    public function test_dashboard_shows_phone_prompt_when_missing(): void
    {
        $this->actingAs($this->client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Add your phone number')
            ->assertSee(route('client.phone.edit'), false);
    }

    public function test_dashboard_hides_phone_prompt_when_phone_saved(): void
    {
        $this->client->forceFill(['phone' => '+971509998877'])->save();

        $this->actingAs($this->client)
            ->get(route('client.dashboard'))
            ->assertOk()
            ->assertDontSee('Google / Apple sign-in often skips this step');
    }
}
