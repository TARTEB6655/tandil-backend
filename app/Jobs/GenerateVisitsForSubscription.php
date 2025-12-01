<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateVisitsForSubscription implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected Subscription $subscription;

    /**
     * Create a new job instance.
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sub = $this->subscription->fresh();

        $planMap = [
            '1_month' => 1,
            '3_month' => 3,
            '6_month' => 6,
            '12_month' => 12,
        ];

        $months = $planMap[$sub->plan] ?? 1;

        $start = Carbon::parse($sub->start_date);

        $visits = [];
        for ($i = 0; $i < $months; $i++) {
            $scheduled = $start->copy()->addMonthsNoOverflow($i)->toDateString();
            $visit = Visit::create([
                'subscription_id' => $sub->id,
                'scheduled_date' => $scheduled,
                'status' => 'pending',
            ]);
            $visits[] = $visit;
        }

        // Update totals on subscription
        $sub->total_visits = count($visits);
        $sub->save();
    }
}
