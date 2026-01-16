<?php

/**
 * Test Admin Dashboard Statistics API
 * Run: php test_admin_statistics_api.php
 */

$baseUrl = 'http://localhost:8000';
$token = 'YOUR_ADMIN_TOKEN_HERE'; // Replace with actual admin token

echo "🧪 Testing Admin Dashboard Statistics API\n";
echo str_repeat("=", 60) . "\n\n";

$url = $baseUrl . '/api/admin/dashboard/statistics';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "URL: {$url}\n";
echo "HTTP Code: {$httpCode}\n\n";

if ($httpCode === 200) {
    $data = json_decode($response, true);
    
    if ($data && isset($data['success']) && $data['success']) {
        echo "✅ API Response Structure:\n";
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        echo "\n\n";
        
        // Validate structure
        $required = ['customers', 'technicians', 'employees'];
        $allValid = true;
        
        foreach ($required as $key) {
            if (!isset($data['data'][$key])) {
                echo "❌ Missing: data.{$key}\n";
                $allValid = false;
                continue;
            }
            
            $item = $data['data'][$key];
            $requiredFields = ['total', 'daily', 'weekly', 'monthly', 'yearly', 'growth'];
            foreach ($requiredFields as $field) {
                if (!isset($item[$field])) {
                    echo "❌ Missing: data.{$key}.{$field}\n";
                    $allValid = false;
                }
            }
            
            if (isset($item['growth'])) {
                $growthFields = ['daily', 'weekly', 'monthly', 'yearly'];
                foreach ($growthFields as $gf) {
                    if (!isset($item['growth'][$gf])) {
                        echo "❌ Missing: data.{$key}.growth.{$gf}\n";
                        $allValid = false;
                    }
                }
            }
        }
        
        if ($allValid) {
            echo "✅ All required fields present!\n";
            echo "✅ Response format matches requirements!\n";
        }
    } else {
        echo "❌ API returned error\n";
        echo $response . "\n";
    }
} else {
    echo "❌ Request failed\n";
    echo "Response: {$response}\n";
}

echo "\n" . str_repeat("=", 60) . "\n";

