<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Support\OrderToVisitDispatcher;
use Illuminate\Console\Command;

class BackfillVisitSchedulesFromOrdersCommand extends Command
{
    protected $signature = 'visits:backfill-schedules
                            {--dry-run : Show how many visits would be updated without saving}
                            {--limit=0 : Max visits to process (0 = all)}';

    protected $description = 'Backfill visit scheduled_time/end from linked shop order booking (fixes calendar null slots).';

    public function handle(): int
    {
        $query = Visit::query()
            ->where(function ($q) {
                $q->whereNull('scheduled_time')->orWhere('scheduled_time', '');
            })
            ->orderBy('id');

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Visits with missing scheduled_time: {$total}");

        if ($this->option('dry-run')) {
            $this->info('Dry run only — no updates.');

            return self::SUCCESS;
        }

        $fixed = 0;
        $stillNull = 0;

        $process = function (Visit $visit) use (&$fixed, &$stillNull): void {
            $before = $visit->scheduled_time;
            $synced = OrderToVisitDispatcher::syncVisitScheduleFromLinkedOrder($visit);
            if (($before === null || $before === '')
                && $synced->scheduled_time !== null
                && $synced->scheduled_time !== '') {
                $fixed++;
            } else {
                $stillNull++;
            }
        };

        if ($limit > 0) {
            foreach ($query->get() as $visit) {
                $process($visit);
            }
        } else {
            $query->chunkById(100, function ($visits) use ($process) {
                foreach ($visits as $visit) {
                    $process($visit);
                }
            });
        }

        $this->info("Backfilled scheduled_time on {$fixed} visit(s). Still null: {$stillNull} (no booking on order/item, or not a shop order).");

        return self::SUCCESS;
    }
}
