<?php

namespace Tests\Feature\Web;

use App\Models\AdminReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminReportManagementDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function assignRoleIfAvailable(User $user, string $roleName): void
    {
        try {
            if (class_exists(Role::class) && Schema::hasTable('roles')) {
                Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
                if (method_exists($user, 'assignRole')) {
                    $user->assignRole($roleName);
                }
            }
        } catch (\Throwable $e) {
            //
        }
    }

    public function test_admin_report_management_download_streams_attachment(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin.reports@test.com']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $report = AdminReport::create([
            'title' => 'Admin Web Report',
            'type' => 'financial',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/991/admin-web-report.pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake admin web pdf');

        $response = $this->actingAs($admin)->get(route('admin.report-management.download', ['id' => $report->id]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', strtolower((string) $response->headers->get('Content-Disposition')));
    }

    public function test_admin_report_management_store_generates_without_pending_and_redirects_to_download(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin.instant@test.com']);
        $this->assignRoleIfAvailable($admin, 'admin');

        $response = $this->actingAs($admin)->post(route('admin.report-management.store'), [
            'title' => 'Instant CSV',
            'type' => 'financial',
            'parameters' => [
                'format' => 'csv',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
        ]);

        $report = AdminReport::where('created_by', $admin->id)->latest()->first();
        $this->assertNotNull($report);
        $this->assertSame('generated', $report->status);
        $this->assertNotNull($report->file_path);

        $response->assertRedirect(route('admin.report-management.download', ['id' => $report->id]));
    }
}

