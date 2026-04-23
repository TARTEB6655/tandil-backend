<?php

namespace App\Console\Commands;

use Database\Seeders\ResetVisitsAndSeedDummyJobsSeeder;
use Illuminate\Console\Command;

class VisitsResetDummyCommand extends Command
{
    protected $signature = 'visits:reset-dummy {--force : Skip confirmation (for scripts/CI)}';

    protected $description = 'Delete all visits (jobs) and re-seed dummy supervisor assignment tasks';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('This will DELETE every row in `visits` (cascade to reports, photos, offers, complaints). Continue?', true)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $this->call('db:seed', ['--class' => ResetVisitsAndSeedDummyJobsSeeder::class]);

        return self::SUCCESS;
    }
}
