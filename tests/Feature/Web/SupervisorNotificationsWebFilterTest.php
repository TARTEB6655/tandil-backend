<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorNotificationsWebFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supervisor = User::factory()->create(['role' => 'supervisor', 'name' => 'Supervisor Web User']);
        $this->assignRoleIfAvailable($this->supervisor, 'supervisor');
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

    public function test_supervisor_notifications_page_renders_with_default_filter(): void
    {
        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\AdminNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->supervisor->id,
            'data' => [
                'title' => 'Visit assigned',
                'message' => 'A new visit has been assigned.',
                'type' => 'admin_notification',
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->supervisor)->get('/supervisor/notifications');
        $response->assertStatus(200);
        $response->assertSee('A new visit has been assigned.');
        $response->assertSee('Unread');
    }
}
