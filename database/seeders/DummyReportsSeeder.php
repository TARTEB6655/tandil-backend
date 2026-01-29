<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyReportsSeeder extends Seeder
{
    /**
     * Seed dummy visit reports so the admin dashboard "Pending Reports" and
     * Reports Management page (admin/reports) show sample data.
     */
    public function run(): void
    {
        $this->command->info('📄 Seeding dummy reports for admin dashboard...');

        // Visits that have subscription + technician (required for admin reports index display)
        $visitsWithoutReport = Visit::with(['subscription.client', 'technician'])
            ->has('subscription')
            ->has('technician')
            ->doesntHave('report')
            ->orderBy('id')
            ->limit(15)
            ->get();

        $created = 0;
        $statuses = ['pending', 'pending', 'approved', 'approved', 'rejected']; // mix so dashboard shows pending count

        foreach ($visitsWithoutReport as $index => $visit) {
            Report::create([
                'visit_id' => $visit->id,
                'supervisor_id' => null,
                'technician_notes' => 'Dummy technician notes for visit #' . $visit->id . '. Service completed as per checklist.',
                'supervisor_notes' => in_array($statuses[$index % 5], ['approved']) ? 'Report reviewed and approved.' : null,
                'notes' => 'Sample report for demo.',
                'recommendations' => [],
                'recommended_products' => [],
                'status' => $statuses[$index % 5],
                'approved_by' => $statuses[$index % 5] === 'approved' ? User::where('role', 'admin')->value('id') : null,
                'approved_at' => $statuses[$index % 5] === 'approved' ? Carbon::now()->subDays(rand(1, 14)) : null,
                'created_at' => Carbon::now()->subDays(rand(1, 30)),
            ]);
            $created++;
        }

        // If we still have no/few reports, create minimal visits + reports
        if ($created < 5) {
            $client = User::where('role', 'client')->first();
            $technician = User::where('role', 'technician')->first();
            $supervisor = User::where('role', 'supervisor')->first();
            $area = Area::first();

            if ($client && $technician && $supervisor && $area) {
                $subscription = Subscription::where('client_id', $client->id)->first();
                if (! $subscription) {
                    $subscription = Subscription::create([
                        'client_id' => $client->id,
                        'plan' => '1_month',
                        'amount' => 500,
                        'start_date' => Carbon::now()->subMonths(2),
                        'end_date' => Carbon::now()->addMonth(),
                        'payment_status' => 'paid',
                        'total_visits' => 1,
                        'completed_visits' => 0,
                    ]);
                }

                $needed = 8 - $created;
                for ($i = 0; $i < $needed; $i++) {
                    $visit = Visit::create([
                        'subscription_id' => $subscription->id,
                        'technician_id' => $technician->id,
                        'supervisor_id' => $supervisor->id,
                        'area_id' => $area->id,
                        'scheduled_date' => Carbon::now()->subDays(rand(5, 25)),
                        'status' => 'completed',
                        'completed_at' => Carbon::now()->subDays(rand(1, 20)),
                        'notes' => 'Demo visit for report.',
                        'created_at' => Carbon::now()->subDays(rand(1, 30)),
                    ]);

                    Report::create([
                        'visit_id' => $visit->id,
                        'supervisor_id' => null,
                        'technician_notes' => 'Dummy report notes for demo visit #' . $visit->id,
                        'supervisor_notes' => $i % 3 === 0 ? 'Approved for demo.' : null,
                        'notes' => 'Sample report.',
                        'recommendations' => [],
                        'recommended_products' => [],
                        'status' => ['pending', 'pending', 'approved'][$i % 3],
                        'approved_by' => $i % 3 === 2 ? User::where('role', 'admin')->value('id') : null,
                        'approved_at' => $i % 3 === 2 ? Carbon::now()->subDay() : null,
                        'created_at' => Carbon::now()->subDays(rand(1, 20)),
                    ]);
                    $created++;
                }
            }
        }

        $pendingCount = Report::where('status', 'pending')->count();
        $this->command->info('✅ Dummy reports done. Total reports: ' . Report::count() . ', Pending: ' . $pendingCount . '.');
    }
}
