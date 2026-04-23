<?php

namespace App\Jobs;

use App\Models\AdminReport;
use App\Models\Area;
use App\Models\Report as VisitReport;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\ReportGeneratedNotification;
use App\Services\HrTechnicianMonthlyReportService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
            } elseif ($ext === 'pdf') {
                $html = $this->wrapContentAsHtml($content);
                $fullPath = Storage::disk('local')->path($path);
                // Use dompdf/dompdf directly (no Laravel container) so queue workers and sync both work
                if (! class_exists(\Dompdf\Dompdf::class)) {
                    throw new \RuntimeException('PDF library not available. Run: composer require dompdf/dompdf');
                }
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('a4', 'portrait');
                $basePath = realpath(base_path('public'));
                $dompdf->setBasePath($basePath !== false ? $basePath : sys_get_temp_dir());
                $dompdf->render();
                file_put_contents($fullPath, $dompdf->output());
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

            if ($report->creator) {
            $report->creator->notify(new ReportGeneratedNotification($report));
            }
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

    /** Wrap plain text report content in styled HTML for PDF rendering (colorful, professional). */
    protected function wrapContentAsHtml(string $text): string
    {
        $lines = explode("\n", $text);
        $out = '';
        foreach ($lines as $line) {
            $trimmed = trim($line);
            $escaped = htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($trimmed === '') {
                $out .= '<div class="spacer"></div>';
                continue;
            }
            if ($trimmed === '---') {
                $out .= '<hr class="divider"/>';
                continue;
            }
            if (preg_match('/^(WEEKLY SUMMARY|TEAM PERFORMANCE|CUSTOMER SATISFACTION)$/', $trimmed)) {
                $out .= '<h1 class="report-title">' . htmlspecialchars($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</h1>';
                continue;
            }
            if (preg_match('/^Report:\s|^Period:\s|^Generated at:\s/', $trimmed)) {
                $out .= '<p class="meta">' . $escaped . '</p>';
                continue;
            }
            if (preg_match('/^Supervisor:\s+.+\(ID:/', $trimmed)) {
                $out .= '<h2 class="supervisor-name">' . $escaped . '</h2>';
                continue;
            }
            if (preg_match('/^\s+Team size:|^\s+No areas assigned/', $trimmed)) {
                $out .= '<p class="stat-line">' . $escaped . '</p>';
                continue;
            }
            if (preg_match('/^Visit #\d+/', $trimmed)) {
                $out .= '<p class="visit-detail">' . $escaped . '</p>';
                continue;
            }
            if (preg_match('/^Visit details|^By day \(scheduled|^By area:$|^Customers who had/', $trimmed)) {
                $out .= '<h3 class="section-head">' . $escaped . '</h3>';
                continue;
            }
            if (preg_match('/^Total visits|^Visits completed|^Completion %|^Revenue generated/', $trimmed)) {
                $out .= '<p class="metric-line">' . $escaped . '</p>';
                continue;
            }
            if (preg_match('/^  \d+ \w+ \d{4}:|^  [A-Za-z].+:\s*\d+$/', $trimmed) || preg_match('/^  [A-Za-z0-9 #\-]+:\s*\d+/', $trimmed)) {
                $out .= '<p class="stat-item">' . $escaped . '</p>';
                continue;
            }
            $out .= '<p class="body">' . $escaped . '</p>';
        }

        $css = '
            body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; padding: 24px; line-height: 1.5; color: #374151; }
            .report-title { color: #21409A; font-size: 16pt; font-weight: bold; margin: 0 0 6px 0; padding-bottom: 0; }
            .report-title::after { content: ""; display: block; width: 50%; margin-top: 6px; border-bottom: 3px solid #4285F4; }
            .meta { color: #6b7280; font-size: 9pt; margin: 3px 0; }
            .divider { border: none; border-top: 1px solid #e5e7eb; margin: 14px 0; }
            .supervisor-name { color: #1b5e20; font-size: 11pt; font-weight: bold; margin: 14px 0 4px 0; }
            .stat-line { color: #5d4037; margin: 2px 0 8px 0; padding-left: 8px; }
            .section-head { color: #4285F4; font-size: 10pt; font-weight: bold; margin: 12px 0 6px 0; }
            .stat-item { margin: 2px 0; padding-left: 12px; color: #374151; }
            .metric-line { margin: 4px 0; padding: 6px 10px; background: #e8f0fe; color: #21409A; font-weight: bold; }
            .visit-detail { margin: 4px 0; padding: 6px 8px; background: #f1f8e9; border-left: 3px solid #2e7d32; font-size: 9pt; }
            .body { margin: 4px 0; }
            .spacer { height: 6px; }
            h1, h2, h3 { page-break-after: avoid; }
        ';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report</title><style>' . $css . '</style></head><body>' . $out . '</body></html>';
    }

    /** Build report content for a given report and date range (used by web download-as-CSV). */
    public static function buildReportContentForReport(AdminReport $report, string $startDate, string $endDate): string
    {
        $params = $report->parameters ?? [];

        return (new self($report))->buildReportContent($report, $startDate, $endDate, $params);
    }

    protected function buildReportContent(AdminReport $report, string $startDate, string $endDate, array $params): string
    {
        if ($report->type === 'hr_technician_monthly') {
            return $this->buildHrTechnicianMonthlyContent($report, $params);
        }

        $areaIds = $this->reportAreaIds();
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        // Avoid SQL "IN ()" with empty list and give a clear message when no areas exist.
        if (empty($areaIds)) {
            $genAt = $this->formatReportDateTime(now());
            return "Report: {$report->title}\nPeriod: {$startDate} to {$endDate}\nGenerated at: {$genAt}\n\n---\nNo areas configured. Add areas in the system to see report data.";
        }

        return match ($report->type) {
            'operational' => $this->buildWeeklySummaryContent($report, $start, $end, $areaIds),
            'performance' => $this->buildTeamPerformanceContent($report, $start, $end, $areaIds),
            'customer' => $this->buildCustomerSatisfactionContent($report, $start, $end, $areaIds),
            default => $this->buildDefaultReportContent($report, $startDate, $endDate, $params),
        };
    }

    /** Region areas for area-manager reports (all areas when not scoped). */
    protected function reportAreaIds(): array
    {
        return Area::pluck('id')->toArray();
    }

    /** Only area IDs that are linked to at least one supervisor (area_supervisor). */
    protected function reportAreaIdsWithSupervisor(array $areaIds): array
    {
        if (empty($areaIds)) {
            return [];
        }
        return DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('area_id')->toArray();
    }

    /** Base visit query: area linked to supervisor, scheduled in period, has area_id and technician assigned. */
    protected function reportVisitsQuery(array $areaIds, Carbon $start, Carbon $end)
    {
        $areaIdsSupervised = $this->reportAreaIdsWithSupervisor($areaIds);
        if (empty($areaIdsSupervised)) {
            return Visit::whereRaw('1 = 0');
        }
        return Visit::whereNotNull('area_id')
            ->whereIn('area_id', $areaIdsSupervised)
            ->whereNotNull('technician_id')
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]);
    }

    /** Human-friendly date for report (e.g. 1 Mar 2026). */
    protected function formatReportDate(Carbon $date): string
    {
        return $date->format('j M Y');
    }

    /** Human-friendly date and time for report (e.g. 10 Mar 2026, 12:11 PM). */
    protected function formatReportDateTime(Carbon $date): string
    {
        return $date->format('j M Y, g:i A');
    }

    /** Display status without underscore (e.g. pending_acceptance → Pending Acceptance). */
    protected function formatVisitStatus(?string $status): string
    {
        if ($status === null || $status === '') {
            return '—';
        }
        return \Illuminate\Support\Str::title(str_replace('_', ' ', $status));
    }

    /** Weekly Summary: jobs (visits) in scope (area → supervisor → technician), by scheduled_date. Includes visit details with supervisor and member. */
    protected function buildWeeklySummaryContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $visitQuery = $this->reportVisitsQuery($areaIds, $start, $end);
        $total = (clone $visitQuery)->count();
        $completed = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
        $completionPercent = $total > 0 ? round(($completed / $total) * 100, 0) : 0;

        $revenueQuery = (clone $visitQuery)
            ->whereIn('status', ['completed', 'approved'])
            ->whereNotNull('completed_at')
            ->whereNotNull('price');
        $revenueGenerated = (clone $revenueQuery)->sum('price');

        $periodStart = $this->formatReportDate($start);
        $periodEnd = $this->formatReportDate($end);
        $generatedAt = $this->formatReportDateTime(now());

        $lines = [
            'WEEKLY SUMMARY',
            '',
            'Report: ' . $report->title,
            'Period: ' . $periodStart . ' to ' . $periodEnd,
            'Generated at: ' . $generatedAt,
            '',
            '---',
            '',
            'Total visits (scheduled in period): ' . $total,
            'Visits completed: ' . $completed,
            'Completion %: ' . $completionPercent . '%',
            'Revenue generated: ' . number_format((float) $revenueGenerated, 2),
            '',
            'By day (scheduled date):',
        ];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $count = (clone $visitQuery)->whereDate('scheduled_date', $d)->count();
            $lines[] = '  ' . $this->formatReportDate($d) . ': ' . $count;
        }
        $lines[] = '';
        $lines[] = 'By area:';
        $areaIdsSupervised = $this->reportAreaIdsWithSupervisor($areaIds);
        foreach (Area::whereIn('id', $areaIdsSupervised)->get() as $area) {
            $areaVisitCount = (clone $visitQuery)->where('area_id', $area->id)->count();
            $lines[] = '  ' . ($area->name ?? 'Area #' . $area->id) . ': ' . $areaVisitCount;
        }

        $visits = (clone $visitQuery)->with(['area', 'supervisor', 'technician'])->orderBy('scheduled_date')->orderBy('id')->get();
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';
        $lines[] = 'Visit details (Supervisor and member who completed the visit)';
        $lines[] = '';
        foreach ($visits as $v) {
            $supName = $v->supervisor ? $v->supervisor->name : '—';
            $techName = $v->technician ? $v->technician->name : '—';
            $areaName = $v->area ? ($v->area->name ?? 'Area #' . $v->area_id) : '—';
            $sched = $v->scheduled_date ? $this->formatReportDate(Carbon::parse($v->scheduled_date)) : '—';
            $lines[] = 'Visit #' . $v->id . '  |  Supervisor: ' . $supName . '  |  Member: ' . $techName . '  |  Area: ' . $areaName . '  |  Scheduled: ' . $sched . '  |  Status: ' . $this->formatVisitStatus($v->status);
            $lines[] = '';
        }
        if ($visits->isEmpty()) {
            $lines[] = 'No visits in this period.';
        }

        return implode("\n", $lines);
    }

    /** Team Performance: supervisor-wise. Same logic as API teamLeaders (no date filter) so PDF matches dashboard. */
    protected function buildTeamPerformanceContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $supervisors = User::role('supervisor')->whereIn('id', $supervisorIds)->with('employee')->get();

        $periodStart = $this->formatReportDate($start);
        $periodEnd = $this->formatReportDate($end);
        $generatedAt = $this->formatReportDateTime(now());

        $lines = [
            'TEAM PERFORMANCE (by Supervisor)',
            '',
            'Report: ' . $report->title,
            'Period: ' . $periodStart . ' to ' . $periodEnd,
            'Generated at: ' . $generatedAt,
            '',
            '---',
            '',
        ];
        foreach ($supervisors as $u) {
            $supAreaIds = $u->supervisedAreaIds();
            if (empty($supAreaIds)) {
                $lines[] = 'Supervisor: ' . $u->name . ' (ID: ' . ($u->employee?->employee_id ?? 'SUP-' . $u->id) . ')';
                $lines[] = '  No areas assigned.';
                $lines[] = '';
                continue;
            }
            // Same as API teamLeaders: all visits in supervisor scope (no scheduled_date filter)
            $visitQuery = Visit::where(function ($q) use ($u, $supAreaIds) {
                $q->where('supervisor_id', $u->id);
                $q->orWhereIn('area_id', $supAreaIds);
            });
            $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress', 'started'])->count();
            $done = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            $total = $active + $done;
            $performance = $total > 0 ? round(($done / $total) * 100, 0) : 0;
            $teamCount = DB::table('area_technician')->whereIn('area_id', $supAreaIds)->distinct()->count('user_id');
            $lines[] = 'Supervisor: ' . $u->name . ' (ID: ' . ($u->employee?->employee_id ?? 'SUP-' . $u->id) . ')';
            $lines[] = '  Team size: ' . $teamCount . ' | Active: ' . $active . ' | Done: ' . $done . ' | Performance: ' . $performance . '%';
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    /** Customer Satisfaction: customers who did NOT send satisfaction feedback. Visits scoped by area (with supervisor). */
    protected function buildCustomerSatisfactionContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $areaIdsSupervised = $this->reportAreaIdsWithSupervisor($areaIds);
        $completedVisitIds = Visit::whereNotNull('area_id')->whereIn('area_id', $areaIdsSupervised)
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('completed_at')
            ->pluck('id');

        $visitIdsWithRating = VisitReport::whereIn('visit_id', $completedVisitIds)
            ->where(function ($q) {
                $q->whereNotNull('notes')->where('notes', '!=', '');
            })
            ->get()
            ->filter(function (VisitReport $r) {
                return preg_match('/Rating:\s*\d+/i', (string) $r->notes);
            })
            ->pluck('visit_id')
            ->toArray();

        $visitIdsWithoutFeedback = $completedVisitIds->diff($visitIdsWithRating)->values();
        $visitsNoFeedback = Visit::with(['subscription.client'])->whereIn('id', $visitIdsWithoutFeedback)->get();
        $customersShown = [];

        $periodStart = $this->formatReportDate($start);
        $periodEnd = $this->formatReportDate($end);
        $generatedAt = $this->formatReportDateTime(now());

        $lines = [
            'CUSTOMER SATISFACTION',
            '',
            'Report: ' . $report->title,
            'Period: ' . $periodStart . ' to ' . $periodEnd,
            'Generated at: ' . $generatedAt,
            '',
            '---',
            '',
            'Customers who had a completed visit in this period but did not submit satisfaction feedback:',
            '',
        ];
        foreach ($visitsNoFeedback as $visit) {
            $client = $visit->subscription?->client;
            if (! $client) {
                $lines[] = 'Visit #' . $visit->id . ' — No client linked. Message: No satisfaction feedback submitted.';
                continue;
            }
            $key = $client->id;
            if (isset($customersShown[$key])) {
                continue;
            }
            $customersShown[$key] = true;
            $lines[] = 'Customer: ' . $client->name . ' (ID: ' . $client->id . ')';
            $lines[] = '  Email: ' . ($client->email ?? '—') . ' | Phone: ' . ($client->phone ?? '—') . ' | Message: No satisfaction feedback submitted.';
            $lines[] = '';
        }
        if (count($customersShown) === 0 && $visitsNoFeedback->isEmpty()) {
            $lines[] = 'No such customers in this period.';
        } elseif (count($customersShown) === 0) {
            $lines[] = 'No customer info available for visits without feedback.';
        }
        return implode("\n", $lines);
    }

    /** HR: per-technician monthly PDF/text (parameters: technician_id, year, month). */
    protected function buildHrTechnicianMonthlyContent(AdminReport $report, array $params): string
    {
        $technicianId = (int) ($params['technician_id'] ?? 0);
        $year = (int) ($params['year'] ?? now()->year);
        $month = (int) ($params['month'] ?? now()->month);
        if ($technicianId < 1 || $month < 1 || $month > 12) {
            return "Report: {$report->title}\n---\nInvalid parameters: technician_id, year, and month (1–12) are required.";
        }

        return HrTechnicianMonthlyReportService::buildPlainText($technicianId, $year, $month, $report->title);
    }

    protected function buildDefaultReportContent(AdminReport $report, string $startDate, string $endDate, array $params): string
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        $lines = [
            'Report: ' . $report->title,
            'Type: ' . $report->type,
            'Period: ' . $this->formatReportDate($start) . ' to ' . $this->formatReportDate($end),
            'Generated at: ' . $this->formatReportDateTime(now()),
            '---',
            'Summary data would be generated here based on report type.',
        ];
        return implode("\n", $lines);
    }
}
