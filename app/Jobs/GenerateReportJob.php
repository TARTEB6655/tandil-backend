<?php

namespace App\Jobs;

use App\Models\AdminReport;
use App\Notifications\ReportGeneratedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public AdminReport $adminReport
    ) {}

    public function handle(): void
    {
        $report = $this->adminReport->fresh();
        if (! $report || $report->status !== 'pending') {
            return;
        }

        try {
            $format = $report->format ?? 'pdf';
            $params = $report->parameters ?? [];
            $startDate = $params['start_date'] ?? now()->startOfMonth()->toDateString();
            $endDate = $params['end_date'] ?? now()->endOfMonth()->toDateString();

            $dir = 'admin_reports/' . $report->id;
            $filename = 'report-' . $report->id . '-' . now()->format('Y-m-d-His');
            $ext = $format === 'csv' ? 'csv' : ($format === 'excel' ? 'xlsx' : 'pdf');
            $path = $dir . '/' . $filename . '.' . $ext;

            Storage::disk('local')->makeDirectory($dir);

            $content = $this->buildReportContent($report, $startDate, $endDate, $params);

            if ($format === 'csv') {
                Storage::disk('local')->put($path, $content);
            } else {
                Storage::disk('local')->put($path, $content);
            }

            $report->update([
                'status' => 'generated',
                'file_path' => $path,
                'file_size' => Storage::disk('local')->size($path),
                'generated_at' => now(),
                'failure_reason' => null,
            ]);

            $report->creator->notify(new ReportGeneratedNotification($report));
        } catch (\Throwable $e) {
            Log::error('Report generation failed: ' . $e->getMessage(), [
                'report_id' => $report->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $report->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);
        }
    }

    protected function buildReportContent(AdminReport $report, string $startDate, string $endDate, array $params): string
    {
        $includeCharts = $params['include_charts'] ?? false;
        $includeDetails = $params['include_details'] ?? true;
        $lines = [
            'Report: ' . $report->title,
            'Type: ' . $report->type,
            'Period: ' . $startDate . ' to ' . $endDate,
            'Generated at: ' . now()->toIso8601String(),
            '---',
            'Summary data would be generated here based on report type.',
            'Include charts: ' . ($includeCharts ? 'yes' : 'no'),
            'Include details: ' . ($includeDetails ? 'yes' : 'no'),
        ];
        return implode("\n", $lines);
    }
}
