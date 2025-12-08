<?php

/**
 * Create sample tips for testing
 * Run: php create_sample_tips.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Tip;
use App\Models\User;

echo "=== Creating Sample Tips ===\n\n";

// Get admin user for created_by
$admin = User::where('role', 'admin')->first();
if (!$admin) {
    echo "❌ No admin user found. Please create an admin user first.\n";
    exit(1);
}

$sampleTips = [
    [
        'title' => 'Water Your Plants Regularly',
        'content' => 'Make sure to water your plants regularly, especially during hot weather. Check the soil moisture before watering to avoid overwatering.',
        'type' => 'general',
        'status' => 'published',
        'language' => 'en',
        'created_by' => $admin->id,
    ],
    [
        'title' => 'Weekly Fertilizer Application',
        'content' => 'Apply fertilizer once a week during the growing season. Use organic fertilizers for better results and healthier plants.',
        'type' => 'weekly',
        'status' => 'published',
        'language' => 'en',
        'created_by' => $admin->id,
    ],
    [
        'title' => 'Monthly Garden Maintenance',
        'content' => 'Perform monthly maintenance tasks: prune dead branches, check for pests, and refresh mulch around plants.',
        'type' => 'monthly',
        'status' => 'published',
        'language' => 'en',
        'created_by' => $admin->id,
    ],
    [
        'title' => 'Seasonal Planting Guide',
        'content' => 'Plan your seasonal planting: Spring for vegetables, Summer for flowers, Fall for bulbs, and Winter for indoor plants.',
        'type' => 'seasonal',
        'status' => 'published',
        'language' => 'en',
        'created_by' => $admin->id,
    ],
    [
        'title' => 'Proper Pruning Techniques',
        'content' => 'Learn proper pruning techniques to promote healthy growth. Always use clean, sharp tools and prune at the right time of year for each plant type.',
        'type' => 'general',
        'status' => 'published',
        'language' => 'en',
        'created_by' => $admin->id,
    ],
];

$created = 0;
foreach ($sampleTips as $tipData) {
    $tip = Tip::create($tipData);
    $created++;
    echo "✅ Created tip: {$tip->title} (ID: {$tip->id}, Status: {$tip->status})\n";
}

echo "\n=== Summary ===\n";
echo "Created {$created} tips\n";
echo "All tips have status = 'published', so they will appear in the API.\n\n";

echo "=== Test API ===\n";
echo "Now test: GET /api/tips\n";
echo "You should see {$created} tips in the response.\n";

