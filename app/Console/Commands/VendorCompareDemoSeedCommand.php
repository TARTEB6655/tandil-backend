<?php

namespace App\Console\Commands;

use Database\Seeders\VendorCompareDemoSeeder;
use Illuminate\Console\Command;

class VendorCompareDemoSeedCommand extends Command
{
    protected $signature = 'vendor:compare-demo-seed';

    protected $description = 'Seed two approved demo vendors with the same Fruits product for compare-vendors testing';

    public function handle(): int
    {
        $this->call('db:seed', [
            '--class' => VendorCompareDemoSeeder::class,
            '--force' => true,
        ]);

        $this->newLine();
        $this->call('vendor:compare-demo-status');

        return self::SUCCESS;
    }
}
