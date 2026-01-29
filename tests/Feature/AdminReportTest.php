<?php

namespace Tests\Feature;

use App\Models\AdminReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_reports(): void
    {
        // Unauthenticated requests must be rejected (401 Unauthenticated or 403/500 depending on middleware)
        $r1 = $this->getJson('/api/admin/reports');
        $r2 = $this->getJson('/api/admin/reports/statistics');
        $r3 = $this->postJson('/api/admin/reports/generate', [
            'type' => 'financial',
            'title' => 'Test',
            'parameters' => [],
        ]);
        $this->assertFalse($r1->isSuccessful(), 'Expected unauthenticated list to be rejected');
        $this->assertFalse($r2->isSuccessful(), 'Expected unauthenticated statistics to be rejected');
        $this->assertFalse($r3->isSuccessful(), 'Expected unauthenticated generate to be rejected');
    }

    /** @test */
    public function non_admin_user_cannot_access_admin_reports(): void
    {
        $client = $this->createCustomer();
        Sanctum::actingAs($client);

        $this->getJson('/api/admin/reports')->assertStatus(403);
        $this->getJson('/api/admin/reports/statistics')->assertStatus(403);
    }

    /** @test */
    public function admin_can_list_reports(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        AdminReport::create([
            'title' => 'Test Report',
            'type' => 'financial',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson('/api/admin/reports?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'title', 'type', 'status', 'created_at', 'created_by', 'parameters'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 15);
    }

    /** @test */
    public function admin_can_filter_reports_by_status_and_type(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reports?status=pending&type=financial');

        $response->assertStatus(200)->assertJsonPath('success', true);
    }

    /** @test */
    public function admin_can_get_report_statistics(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/reports/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total',
                    'pending',
                    'generated',
                    'scheduled',
                    'failed',
                    'by_type' => [
                        'financial',
                        'performance',
                        'customer',
                        'operational',
                        'user',
                        'subscription',
                    ],
                ],
            ])
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function admin_can_generate_report(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/reports/generate', [
            'type' => 'financial',
            'title' => 'Monthly Financial Report',
            'parameters' => [
                'start_date' => '2024-01-01',
                'end_date' => '2024-01-31',
                'format' => 'pdf',
                'include_charts' => true,
                'include_details' => true,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'type', 'status', 'created_by', 'parameters'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'financial');

        // With sync queue the job may run immediately, so status can be 'pending' or 'generated'
        $this->assertDatabaseHas('admin_reports', [
            'title' => 'Monthly Financial Report',
            'type' => 'financial',
        ]);
        $report = AdminReport::where('title', 'Monthly Financial Report')->first();
        $this->assertContains($report->status, ['pending', 'generated']);
    }

    /** @test */
    public function generate_report_validates_required_fields(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/reports/generate', [
            'type' => 'invalid_type',
            'title' => '',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors']);
    }

    /** @test */
    public function admin_can_schedule_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/reports/schedule', [
            'type' => 'financial',
            'title' => 'Weekly Financial Report',
            'scheduled_at' => '2024-02-01 09:00:00',
            'recurrence' => 'weekly',
            'parameters' => [
                'start_date' => '2024-01-15',
                'end_date' => '2024-01-21',
                'format' => 'pdf',
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'type', 'status', 'scheduled_at', 'recurrence'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.recurrence', 'weekly');

        $this->assertDatabaseHas('admin_reports', [
            'title' => 'Weekly Financial Report',
            'status' => 'scheduled',
        ]);
    }

    /** @test */
    public function admin_can_show_single_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $report = AdminReport::create([
            'title' => 'Test Report',
            'type' => 'customer',
            'status' => 'generated',
            'format' => 'pdf',
            'parameters' => ['start_date' => '2024-01-01'],
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/admin/reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'title', 'type', 'status', 'created_by' => ['id', 'name', 'email'], 'parameters'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $report->id)
            ->assertJsonPath('data.title', 'Test Report');
    }

    /** @test */
    public function admin_can_download_report_when_generated(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $path = 'admin_reports/1/report-1-2024-01-01-120000.pdf';
        Storage::disk('local')->put($path, 'fake pdf content');

        $report = AdminReport::create([
            'title' => 'Downloadable Report',
            'type' => 'financial',
            'status' => 'generated',
            'format' => 'pdf',
            'file_path' => $path,
            'file_size' => 100,
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/admin/reports/{$report->id}/download");

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    /** @test */
    public function download_returns_404_when_report_not_generated(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $report = AdminReport::create([
            'title' => 'Pending Report',
            'type' => 'financial',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->getJson("/api/admin/reports/{$report->id}/download");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function admin_can_cancel_scheduled_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $report = AdminReport::create([
            'title' => 'Scheduled Report',
            'type' => 'financial',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'recurrence' => 'daily',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->deleteJson("/api/admin/reports/{$report->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['message' => 'Scheduled report cancelled successfully']);

        $this->assertDatabaseMissing('admin_reports', ['id' => $report->id]);
    }

    /** @test */
    public function cancel_returns_400_for_non_scheduled_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $report = AdminReport::create([
            'title' => 'Pending Report',
            'type' => 'financial',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->deleteJson("/api/admin/reports/{$report->id}/cancel");

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Only scheduled reports can be cancelled.']);
    }

    /** @test */
    public function admin_can_share_report_via_link(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $path = 'admin_reports/1/report-1.pdf';
        Storage::disk('local')->put($path, 'content');

        $report = AdminReport::create([
            'title' => 'Shareable Report',
            'type' => 'financial',
            'status' => 'generated',
            'file_path' => $path,
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->postJson("/api/admin/reports/{$report->id}/share", [
            'method' => 'link',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['share_link'],
            ])
            ->assertJsonPath('success', true);
    }

    /** @test */
    public function admin_can_share_report_via_email(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $path = 'admin_reports/1/report-1.pdf';
        Storage::disk('local')->put($path, 'content');

        $report = AdminReport::create([
            'title' => 'Shareable Report',
            'type' => 'financial',
            'status' => 'generated',
            'file_path' => $path,
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->postJson("/api/admin/reports/{$report->id}/share", [
            'method' => 'email',
            'recipients' => ['recipient@example.com'],
            'message' => 'Please find the report.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sent_to', ['recipient@example.com']);
    }

    /** @test */
    public function share_returns_400_when_report_not_generated(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $report = AdminReport::create([
            'title' => 'Pending Report',
            'type' => 'financial',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->postJson("/api/admin/reports/{$report->id}/share", [
            'method' => 'link',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Only generated reports can be shared.']);
    }

    /** @test */
    public function admin_can_delete_report(): void
    {
        Storage::fake('local');
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $path = 'admin_reports/1/report-1.pdf';
        Storage::disk('local')->put($path, 'content');

        $report = AdminReport::create([
            'title' => 'Report to Delete',
            'type' => 'financial',
            'status' => 'generated',
            'file_path' => $path,
            'format' => 'pdf',
            'parameters' => [],
            'created_by' => $admin->id,
        ]);

        $response = $this->deleteJson("/api/admin/reports/{$report->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment(['message' => 'Report deleted successfully']);

        $this->assertDatabaseMissing('admin_reports', ['id' => $report->id]);
    }

    /** @test */
    public function show_returns_404_for_missing_report(): void
    {
        $admin = $this->createAdmin();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/reports/99999')->assertStatus(404);
    }
}
