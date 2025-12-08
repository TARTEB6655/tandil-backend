<?php

/**
 * Get notification IDs for a user
 * Usage: php get_notification_id.php [user_email]
 * Example: php get_notification_id.php client@test.com
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$userEmail = $argv[1] ?? null;

if (!$userEmail) {
    echo "Usage: php get_notification_id.php [user_email]\n";
    echo "Example: php get_notification_id.php client@test.com\n\n";
    
    // List available users
    echo "Available users:\n";
    $users = User::select('id', 'email', 'name', 'role')->get();
    foreach ($users as $user) {
        echo "  - {$user->email} (ID: {$user->id}, Role: {$user->role}, Name: {$user->name})\n";
    }
    exit(1);
}

$user = User::where('email', $userEmail)->first();

if (!$user) {
    echo "❌ User not found: {$userEmail}\n";
    exit(1);
}

echo "=== Notifications for {$user->name} ({$user->email}) ===\n\n";

$notifications = $user->notifications()->latest()->get();

if ($notifications->count() === 0) {
    echo "❌ No notifications found for this user.\n\n";
    echo "=== Create a notification first ===\n";
    echo "Run: php create_test_notification.php {$userEmail} \"Test\" \"Test message\"\n";
    exit(1);
}

echo "Found {$notifications->count()} notification(s):\n\n";

foreach ($notifications as $notification) {
    $data = $notification->data;
    $title = $data['title'] ?? 'No title';
    $message = $data['message'] ?? 'No message';
    $readStatus = $notification->read_at ? '✅ Read' : '❌ Unread';
    
    echo "Notification ID: {$notification->id}\n";
    echo "  Title: {$title}\n";
    echo "  Message: {$message}\n";
    echo "  Status: {$readStatus}\n";
    echo "  Created: {$notification->created_at}\n";
    echo "\n";
    echo "  📋 Use this ID in Postman:\n";
    echo "  POST /api/notifications/{$notification->id}/mark-read\n";
    echo "\n";
    echo "  ---\n\n";
}

echo "=== How to Use in Postman ===\n";
echo "1. Copy one of the Notification IDs above (it's a UUID)\n";
echo "2. In Postman, use: POST /api/notifications/{NOTIFICATION_ID}/mark-read\n";
echo "3. Example: POST /api/notifications/{$notifications->first()->id}/mark-read\n";

