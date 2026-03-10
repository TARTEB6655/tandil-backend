<?php

namespace Tests\Feature\Web;

use App\Models\AdminReport;
use App\Models\Area;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneratedReportTest extends TestCase
{
    use RefreshDatabase;

    private User $areaManager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->areaManager = User::factory()->create(['name' => 'Area Manager', 'email' => 'areamanager@test.com']);
        $this->assignRoleIfAvailable($this->areaManager, 'area_manager');
        Area::factory()->create(['name' => 'Test Area', 'location' => 'Test']);
    }

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
        }
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $response = $this->get(route('areamanager.generated-reports.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_200_for_area_manager(): void
    {
        $response = $this->actingAs($this->areaManager)->get(route('areamanager.generated-reports.index'));
        $response->assertStatus(200);
        $response->assertSee('Generate PDF Reports', false);
        $response->assertSee('Generated reports', false);
    }

    public function test_store_creates_report_and_generates_file_sync(): void
    {
        $response = $this->actingAs($this->areaManager)->post(route('areamanager.generated-reports.store'), [
            'types' => ['weekly_summary'],
            'date_from' => now()->startOfMonth()->format('Y-m-d'),
            'date_to' => now()->endOfMonth()->format('Y-m-d'),
            '_token' => csrf_token(),
        ], ['Accept' => 'text/html']);

        $response->assertRedirect(route('areamanager.generated-reports.index'));
        $response->assertSessionHas('success');

        $report = AdminReport::where('created_by', $this->areaManager->id)->latest()->first();
        $this->assertNotNull($report);
        $this->assertSame('operational', $report->type);
        $this->assertSame('generated', $report->status);
        $this->assertNotNull($report->file_path);
        $this->assertStringContainsString('.pdf', $report->file_path);
    }

    public function test_download_returns_file_when_report_generated(): void
    {
        $report = AdminReport::create([
            'title' => 'Test Report',
            'type' => 'operational',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/1/report-1-test.pdf',
            'parameters' => ['start_date' => now()->toDateString(), 'end_date' => now()->toDateString()],
            'created_by' => $this->areaManager->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake pdf content');

        $response = $this->actingAs($this->areaManager)->get(route('areamanager.generated-reports.download', ['id' => $report->id]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_view_returns_inline_file(): void
    {
        $report = AdminReport::create([
            'title' => 'Test Report',
            'type' => 'operational',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/2/report-2-view.pdf',
            'parameters' => [],
            'created_by' => $this->areaManager->id,
        ]);
        Storage::disk('local')->put($report->file_path, '%PDF-1.4 fake pdf');

        $response = $this->actingAs($this->areaManager)->get(route('areamanager.generated-reports.view', ['id' => $report->id]));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_download_csv_returns_csv_content(): void
    {
        $report = AdminReport::create([
            'title' => 'Test CSV',
            'type' => 'operational',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => null,
            'parameters' => [
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
            ],
            'created_by' => $this->areaManager->id,
        ]);

        $response = $this->actingAs($this->areaManager)->get(route('areamanager.generated-reports.download', ['id' => $report->id]) . '?format=csv');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertSee('WEEKLY SUMMARY', false);
    }

    public function test_destroy_deletes_report_and_redirects(): void
    {
        $report = AdminReport::create([
            'title' => 'To Delete',
            'type' => 'operational',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => 'admin_reports/99/delete-me.pdf',
            'parameters' => [],
            'created_by' => $this->areaManager->id,
        ]);
        Storage::disk('local')->put($report->file_path, 'x');
        $id = $report->id;

        $response = $this->actingAs($this->areaManager)->post(route('areamanager.generated-reports.destroy', ['id' => $id]), [
            '_token' => csrf_token(),
        ]);

        $response->assertRedirect(route('areamanager.generated-reports.index'));
        $response->assertSessionHas('success');
        $this->assertNull(AdminReport::find($id));
    }

    public function test_store_validates_at_least_one_type(): void
    {
        $response = $this->actingAs($this->areaManager)->from(route('areamanager.generated-reports.index'))
            ->post(route('areamanager.generated-reports.store'), [
                'types' => [],
                'date_from' => now()->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
                '_token' => csrf_token(),
            ]);

        $response->assertRedirect(route('areamanager.generated-reports.index'));
        $this->assertSame(0, AdminReport::where('created_by', $this->areaManager->id)->count());
    }
}
