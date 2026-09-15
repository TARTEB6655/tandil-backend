<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Models\User;
use App\Support\PdfBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminOperationalReportPdfBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_pdf_uses_tandil_logo_and_forest_green_shell(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin']);
        $report = AdminReport::create([
            'title' => 'Ops Weekly Check',
            'type' => 'operational',
            'status' => 'pending',
            'format' => 'pdf',
            'parameters' => [
                'start_date' => now()->startOfWeek()->toDateString(),
                'end_date' => now()->endOfWeek()->toDateString(),
            ],
            'created_by' => $admin->id,
        ]);

        GenerateReportJob::dispatchSync($report);
        $report->refresh();

        $this->assertSame('generated', $report->status);
        $this->assertNotEmpty($report->file_path);
        $this->assertTrue(Storage::disk('local')->exists($report->file_path));

        $binary = Storage::disk('local')->get($report->file_path);
        $this->assertStringStartsWith('%PDF', $binary);

        // Rebuild HTML path directly to assert brand markers.
        $job = new GenerateReportJob($report);
        $ref = new \ReflectionClass($job);
        $wrap = $ref->getMethod('wrapContentAsHtml');
        $wrap->setAccessible(true);
        $build = $ref->getMethod('buildReportContent');
        $build->setAccessible(true);

        $text = $build->invoke(
            $job,
            $report,
            $report->parameters['start_date'],
            $report->parameters['end_date'],
            $report->parameters
        );
        $html = $wrap->invoke($job, $text, $report);

        $this->assertStringContainsString(PdfBrand::PRIMARY, $html);
        $this->assertStringContainsString('pdf-brand-header', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringContainsString('Ops Weekly Check', $html);
        $this->assertStringContainsString('height: 50px', $html);
    }
}
