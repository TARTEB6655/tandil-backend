<?php

/**
 * Comprehensive Notification API Test Script
 * Tests all notification endpoints to ensure they return JSON (no HTML)
 * 
 * Run: php test_notification_apis.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Notifications\AdminNotification;

echo "=== Notification API Test Suite ===\n\n";

// Helper function to check if response is JSON
function isJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// Helper function to test API endpoint
function testEndpoint($method, $url, $token = null, $data = null) {
    $ch = curl_init();
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => $response,
        'content_type' => $contentType,
        'is_json' => isJson($response),
    ];
}

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

// Create a test token
echo "2. Creating authentication token...\n";
$token = $testUser->createToken('test-token')->plainTextToken;
echo "   ✅ Token created\n\n";

// Create some test notifications
echo "3. Creating test notifications...\n";
for ($i = 1; $i <= 3; $i++) {
    $testUser->notify(new AdminNotification(
        "Test Notification {$i}",
        "This is test notification number {$i} for API testing."
    ));
}
$unreadCount = $testUser->unreadNotifications()->count();
echo "   ✅ Created 3 test notifications. Unread count: {$unreadCount}\n\n";

$baseUrl = 'http://localhost:8000';
$passed = 0;
$failed = 0;
$tests = [];

// Test 1: GET /api/notifications
echo "4. Testing GET /api/notifications...\n";
$result = testEndpoint('GET', "{$baseUrl}/api/notifications", $token);
$tests[] = [
    'name' => 'GET /api/notifications',
    'result' => $result,
];

if ($result['is_json'] && $result['code'] == 200) {
    $data = json_decode($result['body'], true);
    if (isset($data['success']) && isset($data['data'])) {
        echo "   ✅ PASS - Returns JSON with success and data\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - JSON structure incorrect\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
} else {
    echo "   ❌ FAIL - Status: {$result['code']}, Is JSON: " . ($result['is_json'] ? 'Yes' : 'No') . "\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

// Test 2: GET /api/user/notifications
echo "5. Testing GET /api/user/notifications...\n";
$result = testEndpoint('GET', "{$baseUrl}/api/user/notifications", $token);
$tests[] = [
    'name' => 'GET /api/user/notifications',
    'result' => $result,
];

if ($result['is_json'] && $result['code'] == 200) {
    $data = json_decode($result['body'], true);
    if (isset($data['success']) && isset($data['data'])) {
        echo "   ✅ PASS - Returns JSON with success and data\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - JSON structure incorrect\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
} else {
    echo "   ❌ FAIL - Status: {$result['code']}, Is JSON: " . ($result['is_json'] ? 'Yes' : 'No') . "\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

// Get a notification ID for testing
$notification = $testUser->notifications()->latest()->first();
$notificationId = $notification ? $notification->id : null;

if ($notificationId) {
    // Test 3: POST /api/notifications/{id}/mark-read
    echo "6. Testing POST /api/notifications/{$notificationId}/mark-read...\n";
    $result = testEndpoint('POST', "{$baseUrl}/api/notifications/{$notificationId}/mark-read", $token);
    $tests[] = [
        'name' => "POST /api/notifications/{$notificationId}/mark-read",
        'result' => $result,
    ];

    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success'])) {
            echo "   ✅ PASS - Returns JSON with success\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - JSON structure incorrect\n";
            echo "   Response: " . substr($result['body'], 0, 200) . "\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}, Is JSON: " . ($result['is_json'] ? 'Yes' : 'No') . "\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
    echo "\n";

    // Test 4: POST /api/user/notifications/{id}/read
    echo "7. Testing POST /api/user/notifications/{$notificationId}/read...\n";
    $result = testEndpoint('POST', "{$baseUrl}/api/user/notifications/{$notificationId}/read", $token);
    $tests[] = [
        'name' => "POST /api/user/notifications/{$notificationId}/read",
        'result' => $result,
    ];

    if ($result['is_json'] && ($result['code'] == 200 || $result['code'] == 404)) {
        $data = json_decode($result['body'], true);
        if (isset($data['success']) || isset($data['message'])) {
            echo "   ✅ PASS - Returns JSON\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - JSON structure incorrect\n";
            echo "   Response: " . substr($result['body'], 0, 200) . "\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}, Is JSON: " . ($result['is_json'] ? 'Yes' : 'No') . "\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
    echo "\n";
}

// Test 5: POST /api/user/notifications/read-all
echo "8. Testing POST /api/user/notifications/read-all...\n";
$result = testEndpoint('POST', "{$baseUrl}/api/user/notifications/read-all", $token);
$tests[] = [
    'name' => 'POST /api/user/notifications/read-all',
    'result' => $result,
];

if ($result['is_json'] && $result['code'] == 200) {
    $data = json_decode($result['body'], true);
    if (isset($data['success'])) {
        echo "   ✅ PASS - Returns JSON with success\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - JSON structure incorrect\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
} else {
    echo "   ❌ FAIL - Status: {$result['code']}, Is JSON: " . ($result['is_json'] ? 'Yes' : 'No') . "\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

// Test 6: Test without authentication (should return JSON error)
echo "9. Testing GET /api/notifications without auth (should return JSON error)...\n";
$result = testEndpoint('GET', "{$baseUrl}/api/notifications");
$tests[] = [
    'name' => 'GET /api/notifications (no auth)',
    'result' => $result,
];

if ($result['is_json']) {
    echo "   ✅ PASS - Returns JSON error (not HTML)\n";
    $passed++;
} else {
    echo "   ❌ FAIL - Returns HTML instead of JSON\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

// Test 7: Test with invalid notification ID
echo "10. Testing POST /api/notifications/invalid-id/mark-read (should return JSON error)...\n";
$result = testEndpoint('POST', "{$baseUrl}/api/notifications/invalid-uuid-id/mark-read", $token);
$tests[] = [
    'name' => 'POST /api/notifications/invalid-id/mark-read',
    'result' => $result,
];

if ($result['is_json']) {
    echo "   ✅ PASS - Returns JSON error (not HTML)\n";
    $passed++;
} else {
    echo "   ❌ FAIL - Returns HTML instead of JSON\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 All notification APIs are working correctly and return JSON!\n";
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
}

echo "\n";
echo "=== Detailed Results ===\n";
foreach ($tests as $test) {
    $status = $test['result']['is_json'] ? '✅ JSON' : '❌ NOT JSON';
    $code = $test['result']['code'];
    echo "{$test['name']}: {$status} (HTTP {$code})\n";
}

echo "\n";
echo "Note: Make sure your Laravel server is running (php artisan serve)\n";

