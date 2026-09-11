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
}
