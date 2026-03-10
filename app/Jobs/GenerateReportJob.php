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
use Barryvdh\DomPDF\Facade\Pdf;
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
                Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($fullPath);
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
            return "Report: {$report->title}\nPeriod: {$startDate} to {$endDate}\nGenerated at: " . now()->toIso8601String() . "\n---\nNo areas configured. Add areas in the system to see report data.";
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

    /** Weekly Summary: jobs (visits) completed, revenue generated, completion %, by day, by area. Auto from DB. */
    protected function buildWeeklySummaryContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $visitQuery = Visit::whereIn('area_id', $areaIds)->whereBetween('created_at', [$start, $end]);
        $total = (clone $visitQuery)->count();
        $completed = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
        $completionPercent = $total > 0 ? round(($completed / $total) * 100, 0) : 0;

        $revenueQuery = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('completed_at', [$start, $end])
            ->whereNotNull('price');
        $revenueGenerated = (clone $revenueQuery)->sum('price');

        $lines = [
            'WEEKLY SUMMARY',
            'Report: ' . $report->title,
            'Period: ' . $start->toDateString() . ' to ' . $end->toDateString(),
            'Generated at: ' . now()->toIso8601String(),
            '---',
            'Total jobs (visits): ' . $total,
            'Jobs completed: ' . $completed,
            'Completion %: ' . $completionPercent . '%',
            'Revenue generated: ' . number_format((float) $revenueGenerated, 2),
            '',
            'By day:',
        ];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $count = Visit::whereIn('area_id', $areaIds)->whereDate('created_at', $d)->count();
            $lines[] = '  ' . $d->toDateString() . ': ' . $count;
        }
        $lines[] = '';
        $lines[] = 'By area:';
        foreach (Area::whereIn('id', $areaIds)->get() as $area) {
            $areaVisitCount = Visit::where('area_id', $area->id)->whereBetween('created_at', [$start, $end])->count();
            $lines[] = '  ' . ($area->name ?? 'Area #' . $area->id) . ': ' . $areaVisitCount;
        }
        return implode("\n", $lines);
    }

    /** Team Performance: supervisor-wise performance (each supervisor's stats). */
    protected function buildTeamPerformanceContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $supervisorIds = DB::table('area_supervisor')->whereIn('area_id', $areaIds)->distinct()->pluck('user_id');
        $supervisors = User::role('supervisor')->whereIn('id', $supervisorIds)->with('employee')->get();

        $lines = [
            'TEAM PERFORMANCE (by Supervisor)',
            'Report: ' . $report->title,
            'Period: ' . $start->toDateString() . ' to ' . $end->toDateString(),
            'Generated at: ' . now()->toIso8601String(),
            '---',
        ];
        foreach ($supervisors as $u) {
            $supAreaIds = $u->supervisedAreaIds();
            $visitQuery = Visit::whereBetween('created_at', [$start, $end])
                ->where(function ($q) use ($u, $supAreaIds) {
                    $q->where('supervisor_id', $u->id);
                    if (! empty($supAreaIds)) {
                        $q->orWhereIn('area_id', $supAreaIds);
                    }
                });
            $active = (clone $visitQuery)->whereIn('status', ['pending', 'scheduled', 'in_progress'])->count();
            $done = (clone $visitQuery)->whereIn('status', ['completed', 'approved'])->count();
            $total = $active + $done;
            $performance = $total > 0 ? round(($done / $total) * 100, 0) : 0;
            $teamCount = empty($supAreaIds) ? 0 : DB::table('area_technician')->whereIn('area_id', $supAreaIds)->distinct()->count('user_id');
            $lines[] = '';
            $lines[] = 'Supervisor: ' . $u->name . ' (ID: ' . ($u->employee?->employee_id ?? 'SUP-' . $u->id) . ')';
            $lines[] = '  Team size: ' . $teamCount . ' | Active: ' . $active . ' | Done: ' . $done . ' | Performance: ' . $performance . '%';
        }
        return implode("\n", $lines);
    }

    /** Customer Satisfaction: customers who did NOT send satisfaction feedback — show their info and message. */
    protected function buildCustomerSatisfactionContent(AdminReport $report, Carbon $start, Carbon $end, array $areaIds): string
    {
        $completedVisitIds = Visit::whereIn('area_id', $areaIds)
            ->whereIn('status', ['completed', 'approved'])
            ->whereBetween('completed_at', [$start, $end])
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
        $lines = [
            'CUSTOMER SATISFACTION',
            'Report: ' . $report->title,
            'Period: ' . $start->toDateString() . ' to ' . $end->toDateString(),
            'Generated at: ' . now()->toIso8601String(),
            '---',
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
        $lines = [
            'Report: ' . $report->title,
            'Type: ' . $report->type,
            'Period: ' . $startDate . ' to ' . $endDate,
            'Generated at: ' . now()->toIso8601String(),
            '---',
            'Summary data would be generated here based on report type.',
        ];
        return implode("\n", $lines);
    }
}
