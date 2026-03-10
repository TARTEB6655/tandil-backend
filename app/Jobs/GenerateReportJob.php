<?php

namespace App\Jobs;

use App\Models\AdminReport;
use App\Models\Area;
use App\Models\Report as VisitReport;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Notifications\ReportGeneratedNotification;
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

    /** Wrap plain text report content in HTML for PDF rendering. */
    protected function wrapContentAsHtml(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $lines = str_replace("\n", '<br>', $escaped);

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report</title>' .
            '<style>body{ font-family: DejaVu Sans, sans-serif; font-size: 11px; padding: 20px; line-height: 1.4; }</style></head>' .
            '<body><pre style="white-space: pre-wrap; margin:0;">' . $lines . '</pre></body></html>';
    }

    /** Build report content for a given report and date range (used by web download-as-CSV). */
    public static function buildReportContentForReport(AdminReport $report, string $startDate, string $endDate): string
    {
        $params = $report->parameters ?? [];

        return (new self($report))->buildReportContent($report, $startDate, $endDate, $params);
    }

    protected function buildReportContent(AdminReport $report, string $startDate, string $endDate, array $params): string
    {
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

    /** Team Performance: supervisor-wise performance. Visits scoped by area → supervisor → technician, scheduled_date. */
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
            $visitQuery = Visit::whereNotNull('area_id')->whereNotNull('technician_id')
                ->whereIn('area_id', $supAreaIds)
                ->whereBetween('scheduled_date', [$start->toDateString(), $end->toDateString()]);
            $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
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
