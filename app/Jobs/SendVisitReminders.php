<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Notifications\VisitReminder;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendVisitReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days ahead to notify (default 2 days)
     * @var int
     */
    public int $daysAhead;

    public function __construct(int $daysAhead = 2)
    {
        $this->daysAhead = $daysAhead;
    }

    public function handle(): void
    {
        $from = Carbon::today();
        $to = Carbon::today()->addDays($this->daysAhead);

        $visits = Visit::with('subscription.client')
            ->whereBetween('scheduled_date', [$from->toDateString(), $to->toDateString()])
            ->where('status', 'pending')
            ->get();

        foreach ($visits as $visit) {
            $client = $visit->subscription->client ?? null;
            if (! $client) {
                continue;
            }

            try {
                $client->notify(new VisitReminder($visit));
            } catch (\Throwable $e) {
                // swallow notification exceptions to avoid stopping the batch
            }
        }
    }
}
