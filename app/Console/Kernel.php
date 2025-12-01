<?php

namespace App\Console;

use App\Jobs\SendVisitReminders;
use App\Jobs\SendTips;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Run the reminder job daily at 08:00 by default
        $schedule->job(new SendVisitReminders(2))->dailyAt('08:00');
        // Run tips job weekly on Mondays at 09:00
        $schedule->job(new SendTips())->weeklyOn(1, '09:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
