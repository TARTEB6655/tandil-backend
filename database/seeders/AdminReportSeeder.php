<?php

namespace Database\Seeders;

use App\Models\AdminReport;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AdminReportSeeder extends Seeder
{
    /**
     * Seed dummy admin reports (admin_reports table).
     * Run after migrations and with at least one admin user: php artisan db:seed --class=AdminReportSeeder
     */
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            $this->command->warn('No admin user found. Run AdminUserSeeder first. Skipping admin reports.');

            return;
        }

        $reports = [
            [
                'title' => 'Monthly Financial Summary',
                'type' => 'financial',
                'status' => 'generated',
                'scheduled_at' => null,
                'recurrence' => 'monthly',
                'generated_at' => Carbon::now()->subDays(5),
                'file_path' => 'reports/financial-' . date('Y-m') . '.pdf',
                'file_size' => 102400,
                'format' => 'pdf',
                'parameters' => ['period' => 'month'],
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Customer Performance Q1',
                'type' => 'customer',
                'status' => 'pending',
                'scheduled_at' => Carbon::now()->addDay(),
                'recurrence' => null,
                'generated_at' => null,
                'file_path' => null,
                'file_size' => null,
                'format' => 'pdf',
                'parameters' => ['quarter' => 1],
                'created_by' => $admin->id,
            ],
            [
                'title' => 'Operational Summary',
                'type' => 'operational',
                'status' => 'scheduled',
                'scheduled_at' => Carbon::now()->addDays(3),
                'recurrence' => 'daily',
                'generated_at' => null,
                'file_path' => null,
                'file_size' => null,
                'format' => 'pdf',
                'parameters' => [],
                'created_by' => $admin->id,
            ],
            [
                'title' => 'User Activity Report',
                'type' => 'user',
                'status' => 'generated',
                'scheduled_at' => null,
                'recurrence' => 'weekly',
                'generated_at' => Carbon::now()->subDay(),
                'file_path' => 'reports/user-activity.csv',
                'file_size' => 25600,
                'format' => 'csv',
                'parameters' => [],
                'created_by' => $admin->id,
            ],
        ];

        foreach ($reports as $data) {
            AdminReport::firstOrCreate(
                ['title' => $data['title']],
                $data
            );
        }

        $this->command->info('Created ' . count($reports) . ' admin report(s).');
    }
}
