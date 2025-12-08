<?php

/**
 * Check if phone number exists
 * Usage: php check_user_phone.php [phone_number]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$phone = $argv[1] ?? '+971501234567';

echo "=== Checking Phone Number ===\n\n";
echo "Phone: {$phone}\n\n";

$user = User::where('phone', $phone)->first();

if ($user) {
    echo "❌ Phone number already exists!\n\n";
    echo "Existing User:\n";
    echo "  ID: {$user->id}\n";
    echo "  Name: {$user->name}\n";
    echo "  Email: {$user->email}\n";
    echo "  Phone: {$user->phone}\n";
    echo "  Role: {$user->role}\n";
    echo "  Created: {$user->created_at}\n\n";
    
    echo "=== Solution ===\n";
    echo "Use a different phone number, for example:\n";
    echo "  +971501234568\n";
    echo "  +971501234569\n";
    echo "  +971502345678\n";
} else {
    echo "✅ Phone number is available!\n";
    echo "You can use this phone number for registration.\n";
}

echo "\n=== All Users with Similar Phones ===\n";
$similarPhones = User::where('phone', 'LIKE', '+971501234%')->get();
if ($similarPhones->count() > 0) {
    foreach ($similarPhones as $u) {
        echo "  - {$u->phone} (User: {$u->name}, Email: {$u->email}, Role: {$u->role})\n";
    }
} else {
    echo "  No similar phone numbers found.\n";
}

