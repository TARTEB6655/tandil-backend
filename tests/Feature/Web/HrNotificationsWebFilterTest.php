<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrNotificationsWebFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $hr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hr = User::factory()->create(['role' => 'hr', 'name' => 'HR Web User']);
        $this->assignRoleIfAvailable($this->hr, 'hr');
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

    public function test_hr_web_notifications_shows_only_allowed_hr_types(): void
    {
        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\ReportGeneratedNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->hr->id,
            'data' => [
                'type' => 'report_generated',
                'title' => 'Report ready',
            ],
            'read_at' => null,
        ]);

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\AdminNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $this->hr->id,
            'data' => [
                'title' => 'Leave request',
                'message' => 'Technician submitted leave.',
                'type' => 'admin_notification',
                'meta' => ['type' => 'hr_leave_request'],
            ],
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->hr)->get('/hr/notifications');
        $response->assertStatus(200);
        $response->assertSee('Technician submitted leave.');
        $response->assertDontSee('ReportGeneratedNotification');
    }
}

