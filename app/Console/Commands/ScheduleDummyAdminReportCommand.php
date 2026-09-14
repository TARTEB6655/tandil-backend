<?php

namespace App\Console\Commands;

use App\Models\AdminReport;
use App\Models\User;
use App\Support\DubaiTime;
use Illuminate\Console\Command;

class ScheduleDummyAdminReportCommand extends Command
{
    protected $signature = 'reports:schedule-dummy
                            {--minutes=15 : Minutes from now (Asia/Dubai) when the report should generate}
                            {--type=financial : Report type}
                            {--title= : Optional title}';

    protected $description = 'Create a dummy scheduled AdminReport N minutes from now (Asia/Dubai) to verify cron generation.';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $type = (string) $this->option('type');
        if (! in_array($type, AdminReport::TYPES, true)) {
            $this->error('Invalid type. Allowed: '.implode(', ', AdminReport::TYPES));

            return self::FAILURE;
        }

        $admin = User::query()->where('role', 'admin')->orderBy('id')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $admin) {
            $this->error('No user found to set as created_by.');

            return self::FAILURE;
        }

        $when = DubaiTime::now()->addMinutes($minutes);
        $title = trim((string) $this->option('title'));
        if ($title === '') {
            $title = 'Dummy '.$type.' — '.$when->format('j M Y g:i A').' Dubai';
        }

        $report = AdminReport::create([
            'title' => $title,
            'type' => $type,
            'status' => 'scheduled',
            'scheduled_at' => DubaiTime::toStorage($when),
            'recurrence' => null,
            'format' => 'pdf',
            'parameters' => [
                'start_date' => DubaiTime::now()->startOfMonth()->toDateString(),
                'end_date' => DubaiTime::now()->endOfMonth()->toDateString(),
                'format' => 'pdf',
                'include_charts' => true,
                'include_details' => true,
            ],
            'created_by' => $admin->id,
        ]);

        $this->info('Dummy scheduled report created.');
        $this->line('ID:            '.$report->id);
        $this->line('Title:         '.$report->title);
        $this->line('Type:          '.$report->type);
        $this->line('Status:        '.$report->status);
        $this->line('Scheduled at:  '.$report->scheduled_at.' (Asia/Dubai)');
        $this->line('Now (Dubai):   '.DubaiTime::now()->format('Y-m-d H:i:s'));
        $this->newLine();
        $this->comment('After ~'.$minutes.' min (with cron), status should become generated.');
        $this->comment('Check early: php artisan reports:process-scheduled');
        $this->comment('Or wait for cron, then: php artisan reports:process-scheduled --dry-run');

        return self::SUCCESS;
    }
}
