<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Jobs\GenerateVisitsForSubscription;
use App\Jobs\SendVisitReminders;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Visit;
use App\Notifications\ReportFinalized;

class FullPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_purchase_to_final_report_flow()
    {
        Notification::fake();
        Storage::fake('public');

        // Create client
        $client = User::factory()->create();

        // Act as client and create subscription via controller
        $this->actingAs($client, 'sanctum');

        $payload = [
            'plan' => '1_month',
            'amount' => 100.00,
        ];

        $res = $this->postJson('/api/subscriptions', $payload);
        $res->assertStatus(201);

        $sub = Subscription::first();
        $this->assertNotNull($sub);

        // Run visit generation job synchronously for test
        GenerateVisitsForSubscription::dispatchSync($sub);

        $visits = $sub->visits()->get();
        $this->assertGreaterThan(0, $visits->count());

        // Assign a technician and simulate the technician flow
        $tech = User::factory()->create();
        $visit = $visits->first();
        $visit->technician_id = $tech->id;
        $visit->save();

        // Technician accepts, starts, completes, uploads photo
        $this->actingAs($tech, 'sanctum');
        $resp = $this->postJson("/api/tech/visits/{$visit->id}/accept");
        if ($resp->status() !== 200) {
            fwrite(STDERR, "ACCEPT_RESPONSE: " . $resp->getContent() . PHP_EOL);
        }
        $resp->assertStatus(200);

        $resp = $this->postJson("/api/tech/visits/{$visit->id}/start");
        if ($resp->status() !== 200) {
            fwrite(STDERR, "START_RESPONSE: " . $resp->getContent() . PHP_EOL);
        }
        $resp->assertStatus(200);

        $resp = $this->postJson("/api/tech/visits/{$visit->id}/complete", ['notes' => 'All good']);
        if ($resp->status() !== 200) {
            fwrite(STDERR, "COMPLETE_RESPONSE: " . $resp->getContent() . PHP_EOL);
        }
        $resp->assertStatus(200);

        // use create() instead of image() to avoid GD dependency in CI/dev
        $file = UploadedFile::fake()->create('after.jpg', 100);
        $this->postJson("/api/tech/visits/{$visit->id}/photos", [
            'photo' => $file,
            'type' => 'after',
        ])->assertStatus(201);

        // Supervisor reviews and finalizes report
        $supervisor = User::factory()->create();
        $this->actingAs($supervisor, 'sanctum');

        $this->postJson("/api/supervisor/visits/{$visit->id}/finalize", ['notes' => 'Approved'])->assertStatus(200);

        // Assert a notification was sent to client
        Notification::assertSentTo($client, ReportFinalized::class);
    }
}
