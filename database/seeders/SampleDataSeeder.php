<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Report;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create sample products
        Product::factory()->count(50)->create();

        // Create sample clients
        $clients = User::factory()->count(20)->create();

        foreach ($clients as $client) {
            $subscription = Subscription::factory()->create([
                'client_id' => $client->id,
            ]);

            // create a few visits per subscription
            $visits = Visit::factory()->count(10)->create([
                'subscription_id' => $subscription->id,
            ]);

            foreach ($visits as $visit) {
                VisitPhoto::factory()->create(['visit_id' => $visit->id, 'type' => 'before']);
                VisitPhoto::factory()->create(['visit_id' => $visit->id, 'type' => 'after']);

                $report = Report::factory()->create([
                    'visit_id' => $visit->id,
                    'supervisor_id' => null,
                ]);
            }
        }
    }
}
