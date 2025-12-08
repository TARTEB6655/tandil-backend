<?php

/**
 * Get HR user token
 * Usage: php get_hr_token.php [user_email]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$userEmail = $argv[1] ?? null;

if (!$userEmail) {
    echo "Usage: php get_hr_token.php [user_email]\n";
    echo "Example: php get_hr_token.php hr1@example.com\n\n";
    
    // List HR users
    echo "HR users:\n";
    $hrUsers = User::where('role', 'hr')->get();
    foreach ($hrUsers as $user) {
        echo "  - {$user->email} (ID: {$user->id}, Name: {$user->name})\n";
    }
    exit(1);
}

$user = User::where('email', $userEmail)->first();

if (!$user) {
    echo "❌ User not found: {$userEmail}\n";
    exit(1);
}

if ($user->role !== 'hr') {
    echo "⚠️  Warning: User role is '{$user->role}', not 'hr'\n";
}

echo "=== HR User Token ===\n\n";
echo "User: {$user->name} ({$user->email})\n";
echo "ID: {$user->id}\n";
echo "Role column: {$user->role}\n";
echo "Status: {$user->status}\n\n";

// Check Spatie roles
echo "Spatie Roles:\n";
$roles = $user->roles;
if ($roles->count() > 0) {
    foreach ($roles as $role) {
        echo "  - {$role->name} (guard: {$role->guard_name})\n";
    }
} else {
    echo "  ❌ No Spatie roles assigned!\n";
    echo "\n  Fixing...\n";
    $user->assignRole('hr');
    echo "  ✅ Assigned 'hr' role\n";
}

echo "\nRole Checks:\n";
echo "  hasRole('hr'): " . ($user->hasRole('hr') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasAnyRole(['hr', 'admin']): " . ($user->hasAnyRole(['hr', 'admin']) ? 'YES ✅' : 'NO ❌') . "\n";

if (!$user->hasRole('hr')) {
    echo "\n⚠️  User doesn't have 'hr' role. Fixing...\n";
    $user->assignRole('hr');
    echo "✅ Role assigned. Checking again...\n";
    $user->refresh();
    echo "  hasRole('hr'): " . ($user->hasRole('hr') ? 'YES ✅' : 'NO ❌') . "\n";
}

// Create token
echo "\n=== Creating Token ===\n";
$token = $user->createToken('api_token')->plainTextToken;
echo "✅ Token created:\n";
echo "  {$token}\n\n";

echo "=== Test in Postman ===\n";
echo "GET /api/admin/hr/employees\n";
echo "Authorization: Bearer {$token}\n";
echo "Accept: application/json\n";

