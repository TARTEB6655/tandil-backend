<?php

/**
 * Verify if a token is valid
 * Usage: php verify_token.php "YOUR_TOKEN_HERE"
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;

$token = $argv[1] ?? null;

if (!$token) {
    echo "Usage: php verify_token.php \"YOUR_TOKEN_HERE\"\n";
    exit(1);
}

echo "=== Verifying Token ===\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

// Find token in database
$dbToken = PersonalAccessToken::findToken($token);

if (!$dbToken) {
    echo "❌ Token NOT found in database\n";
    echo "\nPossible issues:\n";
    echo "1. Token was deleted\n";
    echo "2. Token format is incorrect (should be: ID|hash)\n";
    echo "3. Token belongs to a different user\n";
    exit(1);
}

echo "✅ Token found in database:\n";
echo "  Token ID: {$dbToken->id}\n";
echo "  Tokenable ID: {$dbToken->tokenable_id}\n";
echo "  Tokenable Type: {$dbToken->tokenable_type}\n";
echo "  Name: {$dbToken->name}\n";
echo "  Created: {$dbToken->created_at}\n";

$user = $dbToken->tokenable;
if ($user) {
    echo "\n✅ User found:\n";
    echo "  ID: {$user->id}\n";
    echo "  Email: {$user->email}\n";
    echo "  Role column: {$user->role}\n";
    echo "  Spatie roles: " . $user->roles->pluck('name')->implode(', ') . "\n";
    echo "  Status: {$user->status}\n";
    
    if ($user->status !== 'active') {
        echo "\n⚠️  WARNING: User status is '{$user->status}', not 'active'\n";
        echo "   This might cause authentication to fail!\n";
    }
} else {
    echo "\n❌ User NOT found for this token\n";
}

echo "\n=== Token Usage ===\n";
echo "In Postman, use:\n";
echo "  Authorization → Type: Bearer Token\n";
echo "  Token: {$token}\n";
echo "\nOr in Headers:\n";
echo "  Authorization: Bearer {$token}\n";
echo "  Accept: application/json\n";

