<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Services\VisitOfferService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessVisitOfferTimeouts extends Command
{
    protected $signature = 'visits:process-offer-timeouts';
    protected $description = 'Process job offers that passed accept_by (no response from technician). Offer to next or escalate. Run every minute via cron.';

    public function handle(): int
    {
        $cutoff = Carbon::now();
        $visits = Visit::where('status', 'pending_acceptance')
            ->whereNotNull('accept_by')
            ->where('accept_by', '<', $cutoff)
            ->get();

        foreach ($visits as $visit) {
            VisitOfferService::markTimeoutAndOfferNextOrEscalate($visit);
        }

        if ($visits->count() > 0) {
            $this->info('Processed ' . $visits->count() . ' timed-out offer(s).');
        }

        return 0;
    }
}
