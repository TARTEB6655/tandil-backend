<?php

namespace App\Console\Commands;

use App\Jobs\GenerateReportJob;
use App\Models\AdminReport;
use App\Support\DubaiTime;
use Illuminate\Console\Command;

class ProcessScheduledAdminReportsCommand extends Command
{
    protected $signature = 'reports:process-scheduled
                            {--dry-run : List due reports without generating}
                            {--id= : Force-process one report id (even if still scheduled)}';

    protected $description = 'Generate AdminReport rows whose scheduled_at (Asia/Dubai) has arrived. Run every minute via scheduler.';

    public function handle(): int
    {
        $forceId = (int) $this->option('id');
        if ($forceId > 0) {
            return $this->forceProcess($forceId);
        }

        if ($this->option('dry-run')) {
            $nowStorage = DubaiTime::now()->format('Y-m-d H:i:s');
            $due = AdminReport::query()
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_at')
                ->whereRaw('scheduled_at <= ?', [$nowStorage])
                ->orderBy('scheduled_at')
                ->limit(50)
                ->get();

            $this->info('Now (Asia/Dubai): '.$nowStorage);
            if ($due->isEmpty()) {
                $next = AdminReport::query()
                    ->where('status', 'scheduled')
                    ->whereNotNull('scheduled_at')
                    ->orderBy('scheduled_at')
                    ->first();
                $this->info('No due scheduled reports.');
                if ($next) {
                    $at = $next->scheduled_at
                        ? DubaiTime::parse($next->scheduled_at)->format('Y-m-d H:i:s')
                        : 'null';
                    $this->line('Next scheduled: #'.$next->id.' "'.$next->title.'" at '.$at);
                }

                return self::SUCCESS;
            }

            foreach ($due as $report) {
                $at = $report->scheduled_at
                    ? DubaiTime::parse($report->scheduled_at)->format('Y-m-d H:i:s')
                    : 'null';
                $this->line("#{$report->id} {$report->title} @ {$at}");
            }

            return self::SUCCESS;
        }

        $result = AdminReport::processDueScheduled(50);

        if ($result['processed'] === 0) {
            $next = AdminReport::query()
                ->where('status', 'scheduled')
                ->whereNotNull('scheduled_at')
                ->orderBy('scheduled_at')
                ->first();
            $this->info('No due scheduled reports. Now (Asia/Dubai): '.$result['now']);
            if ($next) {
                $at = $next->scheduled_at
                    ? DubaiTime::parse($next->scheduled_at)->format('Y-m-d H:i:s')
                    : 'null';
                $this->line('Next scheduled: #'.$next->id.' "'.$next->title.'" at '.$at);
            }

            return self::SUCCESS;
        }

        $this->info("Processed {$result['processed']} scheduled report(s). Generated: {$result['generated']}, failed/pending: {$result['failed']}. Now (Asia/Dubai): {$result['now']}");

        return self::SUCCESS;
    }

    protected function forceProcess(int $id): int
    {
        $report = AdminReport::query()->find($id);
        if (! $report) {
            $this->error("Report #{$id} not found.");

            return self::FAILURE;
        }

        $this->line("Forcing #{$id} \"{$report->title}\" (was {$report->status})");
        $report->forceFill([
            'status' => 'pending',
            'failure_reason' => null,
            'file_path' => null,
            'file_size' => null,
            'generated_at' => null,
        ])->save();

        GenerateReportJob::dispatchSync(AdminReport::query()->find($id));
        $fresh = AdminReport::query()->find($id);
        $this->info('Result status: '.($fresh?->status ?? 'missing'));
        if ($fresh?->failure_reason) {
            $this->warn('Failure: '.$fresh->failure_reason);
        }

        return $fresh && $fresh->status === 'generated' ? self::SUCCESS : self::FAILURE;
    }
}
