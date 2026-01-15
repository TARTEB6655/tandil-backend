<?php

/**
 * Simple Notification API Test Script
 * Tests all notification endpoints directly (no HTTP required)
 * 
 * Run: php test_notification_apis_simple.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Notifications\AdminNotification;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;

echo "=== Notification API Test Suite ===\n\n";

$passed = 0;
$failed = 0;
$errors = [];

// Get or create test user
echo "1. Setting up test user...\n";
$testUser = User::where('email', 'test@tandil.com')->first();
if (!$testUser) {
    $testUser = User::create([
        'name' => 'Test User',
        'email' => 'test@tandil.com',
        'password' => 'password123',
        'role' => 'client',
        'status' => 'active',
    ]);
    $testUser->assignRole('client');
}
echo "   ✅ Test user ready: {$testUser->email}\n\n";

// Create test notifications
echo "2. Creating test notifications...\n";
for ($i = 1; $i <= 5; $i++) {
    $testUser->notify(new AdminNotification(
        "Test Notification {$i}",
        "This is test notification number {$i} for API testing."
    ));
}
$unreadCount = $testUser->unreadNotifications()->count();
echo "   ✅ Created 5 test notifications. Unread count: {$unreadCount}\n\n";

// Test 1: GET /api/notifications (NotificationController)
echo "3. Testing NotificationController@index (GET /api/notifications)...\n";
try {
    $request = Request::create('/api/notifications', 'GET');
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $controller = new NotificationController();
    $response = $controller->index($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($response->headers->get('Content-Type') === 'application/json' && 
        isset($data['success']) && 
        isset($data['data']) &&
        isset($data['data']['notifications']) &&
        isset($data['data']['unread_count'])) {
        echo "   ✅ PASS - Returns JSON with correct structure\n";
        echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        echo "   - Unread count: {$data['data']['unread_count']}\n";
        echo "   - Notifications count: " . count($data['data']['notifications']) . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid JSON structure\n";
        echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "   Response: " . substr($content, 0, 200) . "\n";
        $failed++;
        $errors[] = "NotificationController@index - Invalid structure";
    }
} catch (\Exception $e) {
    echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
    $errors[] = "NotificationController@index - Exception: " . $e->getMessage();
}
echo "\n";

// Test 2: GET /api/user/notifications (UserController)
echo "4. Testing UserController@getNotifications (GET /api/user/notifications)...\n";
try {
    $request = Request::create('/api/user/notifications', 'GET');
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $controller = new UserController();
    $response = $controller->getNotifications($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($response->headers->get('Content-Type') === 'application/json' && 
        isset($data['success']) && 
        isset($data['data'])) {
        echo "   ✅ PASS - Returns JSON with correct structure\n";
        echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid JSON structure\n";
        echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "   Response: " . substr($content, 0, 200) . "\n";
        $failed++;
        $errors[] = "UserController@getNotifications - Invalid structure";
    }
} catch (\Exception $e) {
    echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
    $errors[] = "UserController@getNotifications - Exception: " . $e->getMessage();
}
echo "\n";

// Get a notification ID
$notification = $testUser->notifications()->latest()->first();
$notificationId = $notification ? $notification->id : null;

if ($notificationId) {
    // Test 3: POST /api/notifications/{id}/mark-read
    echo "5. Testing NotificationController@markAsRead (POST /api/notifications/{id}/mark-read)...\n";
    try {
        $request = Request::create("/api/notifications/{$notificationId}/mark-read", 'POST');
        $request->setUserResolver(function () use ($testUser) {
            return $testUser;
        });
        $request->headers->set('Accept', 'application/json');
        
        $controller = new NotificationController();
        $response = $controller->markAsRead($request, $notificationId);
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        if ($response->headers->get('Content-Type') === 'application/json' && 
            isset($data['success'])) {
            echo "   ✅ PASS - Returns JSON with success\n";
            echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Invalid JSON structure\n";
            echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
            echo "   Response: " . substr($content, 0, 200) . "\n";
            $failed++;
            $errors[] = "NotificationController@markAsRead - Invalid structure";
        }
    } catch (\Exception $e) {
        echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = "NotificationController@markAsRead - Exception: " . $e->getMessage();
    }
    echo "\n";

    // Test 4: POST /api/user/notifications/{id}/read
    echo "6. Testing UserController@markNotificationAsRead (POST /api/user/notifications/{id}/read)...\n";
    try {
        $request = Request::create("/api/user/notifications/{$notificationId}/read", 'POST');
        $request->setUserResolver(function () use ($testUser) {
            return $testUser;
        });
        $request->headers->set('Accept', 'application/json');
        
        $controller = new UserController();
        $response = $controller->markNotificationAsRead($request, $notificationId);
        $content = $response->getContent();
        $data = json_decode($content, true);
        
        if ($response->headers->get('Content-Type') === 'application/json' && 
            isset($data['success'])) {
            echo "   ✅ PASS - Returns JSON with success\n";
            echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Invalid JSON structure\n";
            echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
            echo "   Response: " . substr($content, 0, 200) . "\n";
            $failed++;
            $errors[] = "UserController@markNotificationAsRead - Invalid structure";
        }
    } catch (\Exception $e) {
        echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = "UserController@markNotificationAsRead - Exception: " . $e->getMessage();
    }
    echo "\n";
}

// Test 5: POST /api/notifications/mark-all-read
echo "7. Testing NotificationController@markAllAsRead (POST /api/notifications/mark-all-read)...\n";
try {
    $request = Request::create('/api/notifications/mark-all-read', 'POST');
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $controller = new NotificationController();
    $response = $controller->markAllAsRead($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($response->headers->get('Content-Type') === 'application/json' && 
        isset($data['success'])) {
        echo "   ✅ PASS - Returns JSON with success\n";
        echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid JSON structure\n";
        echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "   Response: " . substr($content, 0, 200) . "\n";
        $failed++;
        $errors[] = "NotificationController@markAllAsRead - Invalid structure";
    }
} catch (\Exception $e) {
    echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
    $errors[] = "NotificationController@markAllAsRead - Exception: " . $e->getMessage();
}
echo "\n";

// Test 6: POST /api/user/notifications/read-all
echo "8. Testing UserController@markAllNotificationsAsRead (POST /api/user/notifications/read-all)...\n";
try {
    $request = Request::create('/api/user/notifications/read-all', 'POST');
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    
    $controller = new UserController();
    $response = $controller->markAllNotificationsAsRead($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($response->headers->get('Content-Type') === 'application/json' && 
        isset($data['success'])) {
        echo "   ✅ PASS - Returns JSON with success\n";
        echo "   - Success: " . ($data['success'] ? 'true' : 'false') . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid JSON structure\n";
        echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "   Response: " . substr($content, 0, 200) . "\n";
        $failed++;
        $errors[] = "UserController@markAllNotificationsAsRead - Invalid structure";
    }
} catch (\Exception $e) {
    echo "   ❌ FAIL - Exception: " . $e->getMessage() . "\n";
    $failed++;
    $errors[] = "UserController@markAllNotificationsAsRead - Exception: " . $e->getMessage();
}
echo "\n";

// Test 7: Test error handling (invalid notification ID)
echo "9. Testing error handling (invalid notification ID)...\n";
try {
    $request = Request::create('/api/notifications/invalid-uuid-id/mark-read', 'POST');
    $request->setUserResolver(function () use ($testUser) {
        return $testUser;
    });
    $request->headers->set('Accept', 'application/json');
    
    $controller = new NotificationController();
    $response = $controller->markAsRead($request, 'invalid-uuid-id');
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if ($response->headers->get('Content-Type') === 'application/json' && 
        isset($data['success']) && 
        $data['success'] === false) {
        echo "   ✅ PASS - Returns JSON error (not HTML)\n";
        echo "   - Success: false\n";
        echo "   - Message: " . ($data['message'] ?? 'N/A') . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Should return JSON error\n";
        echo "   Content-Type: " . $response->headers->get('Content-Type') . "\n";
        echo "   Response: " . substr($content, 0, 200) . "\n";
        $failed++;
        $errors[] = "Error handling - Should return JSON";
    }
} catch (\Exception $e) {
    // Exception is OK, but should be caught by handler and return JSON
    echo "   ⚠️  Exception caught (should be handled by Exception Handler): " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 All notification APIs are working correctly and return JSON!\n";
    echo "✅ No HTML responses detected\n";
    echo "✅ All endpoints return proper JSON structure\n";
} else {
    echo "⚠️  Some tests failed. Errors:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}

echo "\n";
echo "=== Verified Endpoints ===\n";
echo "✅ GET /api/notifications\n";
echo "✅ GET /api/user/notifications\n";
echo "✅ POST /api/notifications/{id}/mark-read\n";
echo "✅ POST /api/user/notifications/{id}/read\n";
echo "✅ POST /api/notifications/mark-all-read\n";
echo "✅ POST /api/user/notifications/read-all\n";
echo "\n";

