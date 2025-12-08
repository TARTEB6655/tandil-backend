<?php

/**
 * Create a test notification for a user
 * Usage: php create_test_notification.php [user_email] [title] [message]
 * Example: php create_test_notification.php client@test.com "Welcome" "Welcome to Tandil!"
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Notifications\AdminNotification;

$userEmail = $argv[1] ?? null;
$title = $argv[2] ?? 'Test Notification';
$message = $argv[3] ?? 'This is a test notification created for API testing.';

if (!$userEmail) {
    echo "Usage: php create_test_notification.php [user_email] [title] [message]\n";
    echo "Example: php create_test_notification.php client@test.com \"Welcome\" \"Welcome to Tandil!\"\n\n";
    
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
    echo "\nAvailable users:\n";
    $users = User::select('id', 'email', 'name', 'role')->get();
    foreach ($users as $u) {
        echo "  - {$u->email} (ID: {$u->id}, Role: {$u->role})\n";
    }
    exit(1);
}

echo "=== Creating Test Notification ===\n\n";
echo "User: {$user->name} ({$user->email})\n";
echo "Title: {$title}\n";
echo "Message: {$message}\n\n";

try {
    $user->notify(new AdminNotification($title, $message));
    echo "✅ Notification sent successfully!\n\n";
    
    echo "=== Test in Postman ===\n";
    echo "1. Login as: {$user->email}\n";
    echo "2. Get token from login response\n";
    echo "3. Use token in: GET /api/notifications\n";
    echo "4. You should see the notification in the response\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

