<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeds dummy data for subscriptions and reports (visit reports + admin reports).
 * Run after default seed: php artisan db:seed --class=SubscriptionAndReportDummySeeder
 *
 * This runs:
 * - SampleDataSeeder: 50 products, 20 clients, 1 subscription per client, 10 visits per subscription, visit photos, 1 report per visit
 * - AdminReportSeeder: 5 dummy admin_reports (financial, subscription, customer, operational, user)
 *
 * For more data (areas, technicians, supervisors, orders, complaints), run ComprehensiveSeeder instead:
 * php artisan db:seed --class=ComprehensiveSeeder
 */
class SubscriptionAndReportDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SampleDataSeeder::class,
            AdminReportSeeder::class,
        ]);
    }
}
