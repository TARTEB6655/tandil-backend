<?php

/**
 * Debug notification issue
 * Usage: php debug_notification.php [user_email] [notification_id]
 * Example: php debug_notification.php admin@test.com 5ad433c2-51e9-44ed-a949-b8842cd3bf07
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

$userEmail = $argv[1] ?? null;
$notificationId = $argv[2] ?? null;

if (!$userEmail || !$notificationId) {
    echo "Usage: php debug_notification.php [user_email] [notification_id]\n";
    echo "Example: php debug_notification.php admin@test.com 5ad433c2-51e9-44ed-a949-b8842cd3bf07\n\n";
    exit(1);
}

$user = User::where('email', $userEmail)->first();

if (!$user) {
    echo "❌ User not found: {$userEmail}\n";
    exit(1);
}

echo "=== Debug Notification ===\n\n";
echo "User: {$user->name} ({$user->email}, ID: {$user->id})\n";
echo "Notification ID: {$notificationId}\n\n";

// Check if notification exists at all
$notification = DatabaseNotification::find($notificationId);

if (!$notification) {
    echo "❌ Notification with ID '{$notificationId}' does NOT exist in database.\n\n";
    echo "=== All Notifications for This User ===\n";
    $userNotifications = $user->notifications()->latest()->get();
    if ($userNotifications->count() > 0) {
        foreach ($userNotifications as $notif) {
            echo "  ID: {$notif->id}\n";
            $data = $notif->data;
            echo "  Title: " . ($data['title'] ?? 'N/A') . "\n";
            echo "  Status: " . ($notif->read_at ? 'Read' : 'Unread') . "\n";
            echo "  ---\n";
        }
    } else {
        echo "  No notifications found for this user.\n";
    }
    exit(1);
}

echo "✅ Notification exists in database.\n";
echo "  Notification Type: {$notification->type}\n";
echo "  Notifiable Type: {$notification->notifiable_type}\n";
echo "  Notifiable ID: {$notification->notifiable_id}\n";
echo "  Created: {$notification->created_at}\n\n";

// Check if notification belongs to this user
if ($notification->notifiable_type !== 'App\\Models\\User') {
    echo "⚠️  WARNING: Notification notifiable_type is '{$notification->notifiable_type}', not 'App\\Models\\User'\n";
}

if ($notification->notifiable_id != $user->id) {
    echo "❌ PROBLEM FOUND!\n";
    echo "  Notification belongs to user ID: {$notification->notifiable_id}\n";
    echo "  But you're checking for user ID: {$user->id}\n";
    echo "  These don't match!\n\n";
    
    $actualUser = User::find($notification->notifiable_id);
    if ($actualUser) {
        echo "  The notification actually belongs to: {$actualUser->name} ({$actualUser->email})\n";
        echo "\n  === Solution ===\n";
        echo "  You need to use the token for: {$actualUser->email}\n";
        echo "  Or use this notification ID with that user's token.\n";
    } else {
        echo "  The user ID {$notification->notifiable_id} doesn't exist anymore.\n";
    }
} else {
    echo "✅ Notification belongs to this user.\n\n";
    
    // Try to find it via user relationship
    $userNotification = $user->notifications()->find($notificationId);
    if ($userNotification) {
        echo "✅ Notification found via user relationship.\n";
        echo "  This should work in the API!\n\n";
        echo "=== Test in Postman ===\n";
        echo "1. Login as: {$user->email}\n";
        echo "2. Get token\n";
        echo "3. Use: POST /api/notifications/{$notificationId}/mark-read\n";
    } else {
        echo "❌ Notification NOT found via user relationship.\n";
        echo "  This is strange - the notification exists and belongs to the user,\n";
        echo "  but the relationship query doesn't find it.\n";
    }
}

