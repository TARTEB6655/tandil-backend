<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Support\DubaiTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessScheduledAdminReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled {--dry-run : List due reports without generating}';

    protected $description = 'Generate AdminReport rows whose scheduled_at (Asia/Dubai) has arrived. Run every minute via scheduler.';

    public function handle(): int
    {
        $now = DubaiTime::now();
        $nowStorage = $now->format('Y-m-d H:i:s');

        $due = AdminReport::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', $nowStorage)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        if ($due->isEmpty()) {
            $next = AdminReport::query()
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')
                ->first();

            $this->info('No due scheduled reports. Now (Asia/Dubai): '.$nowStorage);
            if ($next) {
                $this->line('Next scheduled: #'.$next->id.' "'.$next->title.'" at '.$next->scheduled_at);
            }

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($due as $report) {
                $this->line("#{$report->id} {$report->title} @ {$report->scheduled_at}");
            }

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
                $this->info("Generated #{$report->id} {$title}");
            } else {
                $failed++;
                $reason = $fresh?->failure_reason ?: 'still '.$fresh?->status;
                $this->warn("Not generated #{$report->id}: {$reason}");
            }

            if ($recurrence && in_array($recurrence, AdminReport::RECURRENCE, true) && $scheduledAt) {
                $nextAt = $this->nextScheduledAt($scheduledAt, $recurrence, $now);
                AdminReport::create([
                    'title' => $title,
                    'type' => $type,
                    'status' => 'scheduled',
                    'scheduled_at' => DubaiTime::toStorage($nextAt),
                    'recurrence' => $recurrence,
                    'format' => $format,
                    'parameters' => $params,
                    'created_by' => $createdBy,
                ]);
            }
        }

        $this->info("Processed {$due->count()} scheduled report(s). Generated: {$generated}, failed/pending: {$failed}. Now (Asia/Dubai): {$nowStorage}");

        return self::SUCCESS;
    }

    protected function nextScheduledAt(Carbon $from, string $recurrence, Carbon $now): Carbon
    {
        $next = DubaiTime::parse($from);
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
        } while ($next->lte($now) && $guard < 400);

        return $next;
    }
}
