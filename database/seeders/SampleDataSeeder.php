<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Models\Report;
use App\Models\Tip;
use App\Models\Complaint;
use App\Notifications\AdminNotification;

class SampleDataSeeder extends Seeder
{
    /**
     * Only seed products/categories if tables are empty (e.g. fresh install).
     * Prevents re-running db:seed from adding 50 products again after you delete them.
     */
    public function run(): void
    {
        // Only create categories if none exist (prevents duplicate categories on re-seed)
        if (Category::count() === 0) {
            Category::factory()->count(8)->create();
        }

        // Only create products if none exist (prevents products re-appearing after delete)
        if (Product::count() === 0) {
            Product::factory()->count(50)->create();
        }

        // Use only the fixed client (client1@test.com) for dummy subscriptions/visits
        $client = User::where('email', 'client1@test.com')->first();
        if (!$client) {
            $this->command->warn('Client client1@test.com not found. Run FixedUsersOnlySeeder first. Skipping subscription/visit data.');
            return;
        }

        // Only create subscriptions/visits if client has none (prevents duplicates on re-seed)
        if (Subscription::where('client_id', $client->id)->exists()) {
            return;
        }

        $clients = collect([$client]);
        $allVisits = [];

        foreach ($clients as $client) {
            $subscription = Subscription::factory()->create([
                'client_id' => $client->id,
            ]);

            // create a few visits per subscription
            $visits = Visit::factory()->count(10)->create([
                'subscription_id' => $subscription->id,
            ]);

            foreach ($visits as $visit) {
                $allVisits[] = $visit;
                VisitPhoto::factory()->create(['visit_id' => $visit->id, 'type' => 'before']);
                VisitPhoto::factory()->create(['visit_id' => $visit->id, 'type' => 'after']);

                Report::factory()->create([
                    'visit_id' => $visit->id,
                    'supervisor_id' => null,
                ]);
            }
        }

        // Complaints (for ~20% of visits)
        foreach ($allVisits as $visit) {
            if (random_int(0, 9) < 2) {
                Complaint::create([
                    'visit_id' => $visit->id,
                    'client_id' => $visit->subscription->client_id,
                    'notes' => 'Sample complaint about visit #' . $visit->id,
                    'status' => ['open', 'in_progress', 'resolved', 'escalated'][array_rand(['open', 'in_progress', 'resolved', 'escalated'])],
                ]);
            }
        }

        // Tips: reset and seed exactly 5 fixed tips
        Tip::query()->delete();

        $admin = User::where('role', 'admin')->first();
        $createdBy = $admin?->id;

        $tips = [
            [
                'title' => 'Weekly Safety Check',
                'content' => 'Review all active tickets and make sure critical issues are prioritized for this week.',
                'type' => 'weekly',
                'status' => 'published',
                'language' => 'en',
                'scheduled_at' => null,
                'created_by' => $createdBy,
            ],
            [
                'title' => 'Monthly Performance Review',
                'content' => 'Check technician performance reports and follow up on any overdue visits or complaints.',
                'type' => 'monthly',
                'status' => 'published',
                'language' => 'en',
                'scheduled_at' => null,
                'created_by' => $createdBy,
            ],
            [
                'title' => 'Client Communication Reminder',
                'content' => 'Always update clients after each visit with a short summary and next steps.',
                'type' => 'general',
                'status' => 'published',
                'language' => 'en',
                'scheduled_at' => null,
                'created_by' => $createdBy,
            ],
            [
                'title' => 'Supervisor Daily Checklist',
                'content' => 'Supervisors should verify route completion, visit photos, and reports before end of day.',
                'type' => 'weekly',
                'status' => 'published',
                'language' => 'en',
                'scheduled_at' => null,
                'created_by' => $createdBy,
            ],
            [
                'title' => 'HR Policy Reminder',
                'content' => 'Ensure leave requests and overtime approvals are processed within 48 hours.',
                'type' => 'general',
                'status' => 'published',
                'language' => 'en',
                'scheduled_at' => null,
                'created_by' => $createdBy,
            ],
        ];

        Tip::insert($tips);

        // Notifications (database) for some clients and admin
        $notificationExamples = [
            ['title' => 'Welcome to Tandil', 'message' => 'Your account is set up. Explore your dashboard.'],
            ['title' => 'Subscription reminder', 'message' => 'Your subscription will renew in 7 days.'],
            ['title' => 'Visit scheduled', 'message' => 'A technician visit has been scheduled for next week.'],
            ['title' => 'Report ready', 'message' => 'Your service report is now available to view.'],
        ];
        foreach ($clients as $client) {
            $n = $notificationExamples[array_rand($notificationExamples)];
            $client->notify(new AdminNotification($n['title'], $n['message']));
        }
        if ($admin) {
            $admin->notify(new AdminNotification('System', 'New complaints and reports are available for review.'));
        }
    }
}
