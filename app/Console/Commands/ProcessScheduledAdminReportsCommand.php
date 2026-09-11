<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessScheduledAdminReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled';

    protected $description = 'Generate AdminReport rows whose scheduled_at time has arrived. Run every minute via scheduler.';

    public function handle(): int
    {
        $due = AdminReport::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No due scheduled reports.');

            return self::SUCCESS;
        }

        $generated = 0;
        $failed = 0;

        foreach ($due as $report) {
            $recurrence = $report->recurrence;
            $scheduledAt = $report->scheduled_at?->copy();
            $params = $report->parameters ?? [];
            $createdBy = $report->created_by;
            $title = $report->title;
            $type = $report->type;
            $format = $report->format ?? 'pdf';

            $report->forceFill([
                'status' => 'pending',
                'failure_reason' => null,
            ])->save();

            try {
                GenerateReportJob::dispatchSync($report);
            } catch (\Throwable $e) {
                $this->error("Report #{$report->id} failed: ".$e->getMessage());
            }

            $fresh = $report->fresh();
            if ($fresh && $fresh->status === 'generated') {
                $generated++;
            } else {
                $failed++;
            }

            if ($recurrence && in_array($recurrence, AdminReport::RECURRENCE, true) && $scheduledAt) {
                $nextAt = $this->nextScheduledAt($scheduledAt, $recurrence);
                AdminReport::create([
                    'title' => $title,
                    'type' => $type,
                    'status' => 'scheduled',
                    'scheduled_at' => $nextAt,
                    'recurrence' => $recurrence,
                    'format' => $format,
                    'parameters' => $params,
                    'created_by' => $createdBy,
                ]);
            }
        }

        $this->info("Processed {$due->count()} scheduled report(s). Generated: {$generated}, failed/pending: {$failed}.");

        return self::SUCCESS;
    }

    protected function nextScheduledAt(Carbon $from, string $recurrence): Carbon
    {
        $next = $from->copy();
        $guard = 0;

        do {
            $next = match ($recurrence) {
                'daily' => $next->copy()->addDay(),
                'weekly' => $next->copy()->addWeek(),
                'monthly' => $next->copy()->addMonth(),
                'yearly' => $next->copy()->addYear(),
                default => $next->copy()->addDay(),
            };
            $guard++;
        } while ($next->lte(now()) && $guard < 400);

        return $next;
    }
}
