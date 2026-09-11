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
        // NOTE: Laravel 11+ app bootstrap does not call this Kernel schedule.
        // Live schedules live in routes/console.php (Schedule::...).
        // Kept here for reference / older tooling only.
        $schedule->command('reports:process-scheduled')->everyMinute();
        $schedule->job(new SendVisitReminders(2))->dailyAt('08:00');
        $schedule->job(new SendTips())->weeklyOn(1, '09:00');
        $schedule->command('orders:send-to-supplier', ['--days' => 7])->dailyAt('07:00');
        $schedule->command('wallet:forfeit-expired')->dailyAt('01:15');
        $schedule->command('visits:process-offer-timeouts')->everyMinute();
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
