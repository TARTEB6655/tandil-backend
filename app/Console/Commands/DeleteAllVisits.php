<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Models\Visit;
use App\Models\VisitOffer;
use App\Models\VisitPhoto;
use Illuminate\Console\Command;

class DeleteAllVisits extends Command
{
    protected $signature = 'visits:delete-all';

    protected $description = 'Delete all visits (and related reports, photos, offers). Use before running seeder for fresh data.';

    public function handle(): int
    {
        $visitCount = Visit::count();
        if ($visitCount === 0) {
            $this->info('No visits to delete.');
            return 0;
        }

        VisitPhoto::query()->delete();
        Report::query()->delete();
        VisitOffer::query()->delete();
        Visit::query()->delete();

        $this->info("Deleted {$visitCount} visits and related data. You can run the seeder now.");
        return 0;
    }
}
