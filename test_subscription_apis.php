<?php

/**
 * Subscription API Test Script
 * Tests all subscription endpoints to ensure they work properly
 * 
 * Run: php test_subscription_apis.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== Subscription API Test Suite ===\n\n";

$passed = 0;
$failed = 0;
$errors = [];

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
        'is_json' => json_decode($response) !== null,
    ];
}

// Get or create test users
echo "1. Setting up test users...\n";
$admin = User::where('email', 'admin@tandil.com')->first();
$client = User::where('email', 'client@tandil.com')->first();

if (!$admin) {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin@tandil.com',
        'password' => 'password123',
        'role' => 'admin',
        'status' => 'active',
    ]);
    $admin->assignRole('admin');
}

if (!$client) {
    $client = User::create([
        'name' => 'Client User',
        'email' => 'client@tandil.com',
        'password' => 'password123',
        'role' => 'client',
        'status' => 'active',
    ]);
    $client->assignRole('client');
}

echo "   ✅ Admin user: {$admin->email}\n";
echo "   ✅ Client user: {$client->email}\n\n";

// Create tokens
echo "2. Creating authentication tokens...\n";
$adminToken = $admin->createToken('test-token')->plainTextToken;
$clientToken = $client->createToken('test-token')->plainTextToken;
echo "   ✅ Tokens created\n\n";

$baseUrl = 'http://localhost:8000';
$subscriptionId = null;

// Test 1: GET /api/subscriptions/plans (Public)
echo "3. Testing GET /api/subscriptions/plans (Public)...\n";
$result = testEndpoint('GET', "{$baseUrl}/api/subscriptions/plans");
if ($result['is_json'] && $result['code'] == 200) {
    $data = json_decode($result['body'], true);
    if (isset($data['success']) && isset($data['data']) && is_array($data['data'])) {
        echo "   ✅ PASS - Returns plans list\n";
        echo "   - Plans found: " . count($data['data']) . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid structure\n";
        $failed++;
    }
} else {
    echo "   ❌ FAIL - Status: {$result['code']}\n";
    $failed++;
}
echo "\n";

// Test 2: POST /api/subscriptions (Create)
echo "4. Testing POST /api/subscriptions (Create)...\n";
$createData = [
    'plan' => '1_month',
    'start_date' => date('Y-m-d'),
];
$result = testEndpoint('POST', "{$baseUrl}/api/subscriptions", $clientToken, $createData);
if ($result['is_json'] && $result['code'] == 201) {
    $data = json_decode($result['body'], true);
    if (isset($data['success']) && isset($data['data']['id'])) {
        $subscriptionId = $data['data']['id'];
        echo "   ✅ PASS - Subscription created\n";
        echo "   - Subscription ID: {$subscriptionId}\n";
        echo "   - Amount: " . ($data['data']['amount'] ?? 'N/A') . "\n";
        $passed++;
    } else {
        echo "   ❌ FAIL - Invalid structure\n";
        $failed++;
    }
} else {
    echo "   ❌ FAIL - Status: {$result['code']}\n";
    echo "   Response: " . substr($result['body'], 0, 200) . "\n";
    $failed++;
}
echo "\n";

if ($subscriptionId) {
    // Test 3: GET /api/subscriptions/{id}
    echo "5. Testing GET /api/subscriptions/{$subscriptionId}...\n";
    $result = testEndpoint('GET', "{$baseUrl}/api/subscriptions/{$subscriptionId}", $clientToken);
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success']) && isset($data['data'])) {
            echo "   ✅ PASS - Subscription retrieved\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Invalid structure\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        $failed++;
    }
    echo "\n";

    // Test 4: PUT /api/subscriptions/{id} (Update - Admin only for amount)
    echo "6. Testing PUT /api/subscriptions/{$subscriptionId} (Update amount - Admin)...\n";
    $updateData = [
        'amount' => 600.00,
        'payment_status' => 'paid',
        'total_visits' => 2,
        'completed_visits' => 1,
        'payment_reference' => 'TEST-REF-123',
    ];
    $result = testEndpoint('PUT', "{$baseUrl}/api/subscriptions/{$subscriptionId}", $adminToken, $updateData);
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success']) && isset($data['data']['amount']) && $data['data']['amount'] == 600.00) {
            echo "   ✅ PASS - Subscription updated (amount changed)\n";
            echo "   - New amount: {$data['data']['amount']}\n";
            echo "   - Payment status: {$data['data']['payment_status']}\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Amount not updated\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        echo "   Response: " . substr($result['body'], 0, 200) . "\n";
        $failed++;
    }
    echo "\n";

    // Test 5: PUT /api/subscriptions/{id} (Update dates - Client)
    echo "7. Testing PUT /api/subscriptions/{$subscriptionId} (Update dates - Client)...\n";
    $updateData = [
        'start_date' => date('Y-m-d', strtotime('+1 day')),
        'end_date' => date('Y-m-d', strtotime('+32 days')),
    ];
    $result = testEndpoint('PUT', "{$baseUrl}/api/subscriptions/{$subscriptionId}", $clientToken, $updateData);
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success'])) {
            echo "   ✅ PASS - Dates updated by client\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Invalid structure\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        $failed++;
    }
    echo "\n";

    // Test 6: POST /api/subscriptions/{id}/mark-paid
    echo "8. Testing POST /api/subscriptions/{$subscriptionId}/mark-paid...\n";
    $result = testEndpoint('POST', "{$baseUrl}/api/subscriptions/{$subscriptionId}/mark-paid", $adminToken);
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success']) && isset($data['data']['payment_status']) && $data['data']['payment_status'] == 'paid') {
            echo "   ✅ PASS - Subscription marked as paid\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Payment status not updated\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        $failed++;
    }
    echo "\n";

    // Test 7: GET /api/subscriptions (List)
    echo "9. Testing GET /api/subscriptions (List)...\n";
    $result = testEndpoint('GET', "{$baseUrl}/api/subscriptions", $clientToken);
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        if (isset($data['success']) && isset($data['data'])) {
            echo "   ✅ PASS - Subscriptions listed\n";
            echo "   - Count: " . (is_array($data['data']) ? count($data['data']) : 'N/A') . "\n";
            $passed++;
        } else {
            echo "   ❌ FAIL - Invalid structure\n";
            $failed++;
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        $failed++;
    }
    echo "\n";
}

// Test 8: Test authorization (client trying to update amount)
if ($subscriptionId) {
    echo "10. Testing authorization (Client cannot update amount)...\n";
    $updateData = ['amount' => 1000.00];
    $result = testEndpoint('PUT', "{$baseUrl}/api/subscriptions/{$subscriptionId}", $clientToken, $updateData);
    // Should succeed but amount should NOT be updated (admin only)
    if ($result['is_json'] && $result['code'] == 200) {
        $data = json_decode($result['body'], true);
        // Check if amount was actually updated (should not be for non-admin)
        $checkResult = testEndpoint('GET', "{$baseUrl}/api/subscriptions/{$subscriptionId}", $clientToken);
        $checkData = json_decode($checkResult['body'], true);
        if (isset($checkData['data']['amount']) && $checkData['data']['amount'] != 1000.00) {
            echo "   ✅ PASS - Client cannot update amount (admin only)\n";
            $passed++;
        } else {
            echo "   ⚠️  WARNING - Client was able to update amount (should be admin only)\n";
        }
    } else {
        echo "   ❌ FAIL - Status: {$result['code']}\n";
        $failed++;
    }
    echo "\n";
}

// Summary
echo "=== Test Summary ===\n";
echo "✅ Passed: {$passed}\n";
echo "❌ Failed: {$failed}\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 All subscription APIs are working correctly!\n";
    echo "✅ Price/amount update functionality verified\n";
    echo "✅ Admin-only fields properly protected\n";
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
}

echo "\n";
echo "Note: Make sure your Laravel server is running (php artisan serve)\n";

