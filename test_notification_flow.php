<?php

/**
 * Complete test flow for notifications
 * This will create a notification, get it, and show you how to mark it as read
 * Run: php test_notification_flow.php [user_email]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Notifications\AdminNotification;

$userEmail = $argv[1] ?? 'admin@test.com';

echo "=== Complete Notification Test Flow ===\n\n";

// Step 1: Find or create user
$user = User::where('email', $userEmail)->first();

if (!$user) {
    echo "❌ User not found: {$userEmail}\n";
    exit(1);
}

echo "Step 1: User Found\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  ID: {$user->id}\n\n";

// Step 2: Create a test notification
echo "Step 2: Creating Test Notification\n";
$user->notify(new AdminNotification('Test Notification', 'This is a test notification for API testing.'));
echo "✅ Notification created!\n\n";

// Step 3: Get the notification
echo "Step 3: Getting Notifications\n";
$notifications = $user->notifications()->latest()->get();
$notification = $notifications->first();

if (!$notification) {
    echo "❌ No notification found (this shouldn't happen)\n";
    exit(1);
}

echo "✅ Notification found!\n";
echo "  ID: {$notification->id}\n";
echo "  Title: Test Notification\n";
echo "  Status: " . ($notification->read_at ? 'Read' : 'Unread') . "\n\n";

// Step 4: Show how to test in Postman
echo "=== Test in Postman ===\n\n";

echo "1. Login as {$user->email}:\n";
echo "   POST /api/auth/login\n";
echo "   {\n";
echo "     \"email\": \"{$user->email}\",\n";
echo "     \"password\": \"password\"\n";
echo "   }\n";
echo "   → Copy the 'token' from response\n\n";

echo "2. Get notifications:\n";
echo "   GET /api/notifications\n";
echo "   Authorization: Bearer {TOKEN_FROM_STEP_1}\n";
echo "   → You should see the notification with ID: {$notification->id}\n\n";

echo "3. Mark notification as read:\n";
echo "   POST /api/notifications/{$notification->id}/mark-read\n";
echo "   Authorization: Bearer {TOKEN_FROM_STEP_1}\n";
echo "   → Should return: {\"status\": true, \"message\": \"Notification marked as read.\"}\n\n";

echo "4. Verify it's read:\n";
echo "   GET /api/notifications\n";
echo "   Authorization: Bearer {TOKEN_FROM_STEP_1}\n";
echo "   → Check that 'read_at' is no longer null\n\n";

echo "=== Important Notes ===\n";
echo "✅ Make sure you use the SAME token for all requests\n";
echo "✅ The notification ID is: {$notification->id}\n";
echo "✅ This notification belongs to user ID: {$user->id} ({$user->email})\n";
echo "✅ You MUST use a token from this user to mark it as read\n";

