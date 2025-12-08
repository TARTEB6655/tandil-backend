<?php

/**
 * Test supervisor token authentication
 * Run: php test_supervisor_token.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

echo "=== Test Supervisor Token Authentication ===\n\n";

// Find supervisor
$supervisor = User::where('role', 'supervisor')->first();
if (!$supervisor) {
    echo "❌ No supervisor found\n";
    exit(1);
}

echo "Supervisor found:\n";
echo "  ID: {$supervisor->id}\n";
echo "  Email: {$supervisor->email}\n";
echo "  Role column: {$supervisor->role}\n";
echo "  Spatie roles: " . $supervisor->roles->pluck('name')->implode(', ') . "\n\n";

// Check existing tokens
$tokens = $supervisor->tokens;
echo "Existing tokens: " . $tokens->count() . "\n";
if ($tokens->count() > 0) {
    foreach ($tokens as $token) {
        echo "  - Token ID: {$token->id} | Name: {$token->name} | Created: {$token->created_at}\n";
    }
}

// Create a new token
echo "\n=== Creating New Token ===\n";
$newToken = $supervisor->createToken('api_token')->plainTextToken;
echo "✅ New token created:\n";
echo "  {$newToken}\n\n";

// Verify token
$tokenParts = explode('|', $newToken);
if (count($tokenParts) === 2) {
    $tokenId = $tokenParts[0];
    $tokenHash = $tokenParts[1];
    
    echo "Token breakdown:\n";
    echo "  Token ID: {$tokenId}\n";
    echo "  Token Hash: " . substr($tokenHash, 0, 20) . "...\n\n";
    
    // Try to find the token in database
    $dbToken = PersonalAccessToken::findToken($newToken);
    if ($dbToken) {
        echo "✅ Token found in database:\n";
        echo "  Tokenable ID: {$dbToken->tokenable_id}\n";
        echo "  Tokenable Type: {$dbToken->tokenable_type}\n";
        echo "  Name: {$dbToken->name}\n";
        
        $tokenUser = $dbToken->tokenable;
        if ($tokenUser) {
            echo "  User: {$tokenUser->email} (ID: {$tokenUser->id})\n";
            echo "  User Role: {$tokenUser->role}\n";
        }
    } else {
        echo "❌ Token NOT found in database\n";
    }
}

echo "\n=== Instructions ===\n";
echo "1. Copy the token above\n";
echo "2. Use it in Postman: Authorization → Bearer Token\n";
echo "3. Make sure the header is: Authorization: Bearer {$newToken}\n";
echo "4. Also add: Accept: application/json\n";

