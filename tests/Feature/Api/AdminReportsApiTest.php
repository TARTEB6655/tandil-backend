<?php

namespace Tests\Feature\Api;

use App\Models\AdminReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Reports']);
        $this->assignRoleIfAvailable($this->admin, 'admin');
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

    public function test_admin_reports_generate_list_show_and_delete_smoke(): void
    {
        $generate = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/reports/generate', [
            'type' => 'financial',
            'title' => 'Monthly Financial Report',
            'parameters' => [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'format' => 'pdf',
                'include_charts' => true,
                'include_details' => true,
            ],
        ]);

        $generate->assertStatus(201)->assertJsonPath('success', true);
        $id = (int) $generate->json('data.id');
        $this->assertGreaterThan(0, $id);

        $list = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/reports?per_page=10');
        $list->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total', 'last_page']]);

        $show = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/reports/' . $id);
        $show->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $id);

        $delete = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/admin/reports/' . $id);
        $delete->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('admin_reports', ['id' => $id]);
    }

    public function test_admin_reports_schedule_and_cancel_smoke(): void
    {
        $schedule = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/reports/schedule', [
            'type' => 'performance',
            'title' => 'Weekly Team Performance',
            'scheduled_at' => now()->addHour()->toIso8601String(),
            'recurrence' => 'weekly',
            'parameters' => [
                'format' => 'csv',
            ],
        ]);

        $schedule->assertStatus(201)->assertJsonPath('success', true)->assertJsonPath('data.status', 'scheduled');
        $id = (int) $schedule->json('data.id');

        $cancel = $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/admin/reports/' . $id . '/cancel');
        $cancel->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseMissing('admin_reports', ['id' => $id]);
    }

    public function test_admin_reports_download_share_and_statistics_smoke(): void
    {
        $report = AdminReport::create([
            'title' => 'Generated PDF',
            'type' => 'financial',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/900/generated.pdf',
            'parameters' => [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'include_charts' => true,
                'include_details' => true,
            ],
            'created_by' => $this->admin->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake admin report');

        $download = $this->actingAs($this->admin, 'sanctum')->get('/api/admin/reports/' . $report->id . '/download');
        $download->assertStatus(200);
        $download->assertHeader('content-type', 'application/pdf');

        $share = $this->actingAs($this->admin, 'sanctum')->postJson('/api/admin/reports/' . $report->id . '/share', [
            'method' => 'link',
        ]);
        $share->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['share_link']]);

        AdminReport::create([
            'title' => 'Pending Report',
            'type' => 'user',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $this->admin->id,
        ]);

        $stats = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/reports/statistics');
        $stats->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'pending', 'generated', 'scheduled', 'failed', 'by_type']]);
    }
}

