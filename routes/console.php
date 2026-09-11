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
| Server must run: * * * * * php artisan schedule:run
*/

// Due AdminReport rows (Schedule Report) → generate PDF at scheduled_at.
Schedule::command('reports:process-scheduled')->everyMinute();

// Visit reminders daily at 08:00
Schedule::job(new \App\Jobs\SendVisitReminders(2))->dailyAt('08:00');

// Tips weekly on Mondays at 09:00
Schedule::job(new \App\Jobs\SendTips())->weeklyOn(1, '09:00');

// Orders export to supplier daily at 07:00 (last 7 days)
Schedule::command('orders:send-to-supplier', ['--days' => 7])->dailyAt('07:00');

// Forfeit unused wallet refunds after expiry window
Schedule::command('wallet:forfeit-expired')->dailyAt('01:15');

// Job offer timeouts (technician did not accept in time)
Schedule::command('visits:process-offer-timeouts')->everyMinute();
