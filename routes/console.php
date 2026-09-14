<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (Laravel 11+ — Kernel schedule() is not wired)
|--------------------------------------------------------------------------
| Server cron (every minute, Asia/Dubai clocks via app timezone):
| * * * * * cd /path/to/app && /usr/bin/php8.2 artisan schedule:run >> /dev/null 2>&1
*/

$dubai = 'Asia/Dubai';

// Due AdminReport rows (Schedule Report) → generate PDF at scheduled_at (Dubai).
Schedule::command('reports:process-scheduled')->everyMinute()->timezone($dubai);

// Visit reminders daily at 08:00 Dubai
Schedule::job(new \App\Jobs\SendVisitReminders(2))->dailyAt('08:00')->timezone($dubai);

// Tips weekly on Mondays at 09:00 Dubai
Schedule::job(new \App\Jobs\SendTips())->weeklyOn(1, '09:00')->timezone($dubai);

// Orders export to supplier daily at 07:00 Dubai (last 7 days)
Schedule::command('orders:send-to-supplier', ['--days' => 7])->dailyAt('07:00')->timezone($dubai);

// Forfeit unused wallet refunds after expiry window
Schedule::command('wallet:forfeit-expired')->dailyAt('01:15')->timezone($dubai);

// Job offer timeouts (technician did not accept in time)
Schedule::command('visits:process-offer-timeouts')->everyMinute()->timezone($dubai);
