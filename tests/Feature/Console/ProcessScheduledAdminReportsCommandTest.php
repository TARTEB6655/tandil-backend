<?php

namespace Tests\Feature\Console;

use App\Models\AdminReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessScheduledAdminReportsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_due_scheduled_report_is_generated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $report = AdminReport::create([
            'title' => 'التقرير المالي',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'format' => 'pdf',
            'parameters' => [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'format' => 'pdf',
            ],
            'created_by' => $admin->id,
        ]);

        $this->artisan('reports:process-scheduled')
            ->assertSuccessful();

        $report->refresh();
        $this->assertSame('generated', $report->status);
        $this->assertNotEmpty($report->file_path);
    }

    public function test_future_scheduled_report_is_not_generated_yet(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $report = AdminReport::create([
            'title' => 'Future Financial',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour(),
            'format' => 'pdf',
            'parameters' => ['format' => 'pdf'],
            'created_by' => $admin->id,
        ]);

        $this->artisan('reports:process-scheduled')
            ->assertSuccessful();

        $this->assertSame('scheduled', $report->fresh()->status);
    }

    public function test_recurring_scheduled_report_creates_next_row(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $report = AdminReport::create([
            'title' => 'Weekly Performance',
            'type' => 'performance',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
            'recurrence' => 'weekly',
            'format' => 'pdf',
            'parameters' => ['format' => 'pdf'],
            'created_by' => $admin->id,
        ]);

        $this->artisan('reports:process-scheduled')
            ->assertSuccessful();

        $this->assertSame('generated', $report->fresh()->status);
        $this->assertDatabaseHas('admin_reports', [
            'title' => 'Weekly Performance',
            'status' => 'scheduled',
            'recurrence' => 'weekly',
        ]);
        $this->assertSame(2, AdminReport::where('title', 'Weekly Performance')->count());
    }

    public function test_process_uses_dubai_now_for_due_check(): void
    {
        config(['app.timezone' => 'Asia/Dubai']);
        date_default_timezone_set('Asia/Dubai');

        $admin = User::factory()->create(['role' => 'admin']);
        $dubaiNow = \App\Support\DubaiTime::now();

        $due = AdminReport::create([
            'title' => 'Due Dubai',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => $dubaiNow->copy()->subMinutes(2)->format('Y-m-d H:i:s'),
            'format' => 'pdf',
            'parameters' => ['format' => 'pdf'],
            'created_by' => $admin->id,
        ]);

        $this->artisan('reports:process-scheduled')->assertSuccessful();
        $this->assertSame('generated', $due->fresh()->status);
    }

    public function test_force_id_generates_even_if_still_future_scheduled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $report = AdminReport::create([
            'title' => 'Dummy financial — force',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'format' => 'pdf',
            'parameters' => ['format' => 'pdf'],
            'created_by' => $admin->id,
        ]);

        $this->artisan('reports:process-scheduled', ['--id' => $report->id])
            ->assertSuccessful();

        $this->assertSame('generated', $report->fresh()->status);
    }

    public function test_admin_reports_list_processes_overdue_scheduled(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assignAdminRole($admin);

        $report = AdminReport::create([
            'title' => 'Dummy financial — overdue list',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinutes(5)->format('Y-m-d H:i:s'),
            'format' => 'pdf',
            'parameters' => ['format' => 'pdf'],
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reports?per_page=10')
            ->assertOk();

        $this->assertSame('generated', $report->fresh()->status);
    }

    private function assignAdminRole(User $admin): void
    {
        try {
            if (class_exists(\Spatie\Permission\Models\Role::class)) {
                \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
                $admin->assignRole('admin');
            }
        } catch (\Throwable) {
            //
        }
    }
}
