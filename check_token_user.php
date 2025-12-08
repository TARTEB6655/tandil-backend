<?php

/**
 * Check which user a token belongs to
 * Usage: php check_token_user.php [token]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Laravel\Sanctum\PersonalAccessToken;

$token = $argv[1] ?? null;

if (!$token) {
    echo "Usage: php check_token_user.php [token]\n";
    echo "Example: php check_token_user.php \"29|nn0G2vlJANV7OjCHWG5hW1pLUdGNX6GxHoszcd0Udc1ecbff\"\n";
    exit(1);
}

echo "=== Checking Token ===\n\n";
echo "Token: " . substr($token, 0, 20) . "...\n\n";

$dbToken = PersonalAccessToken::findToken($token);

if (!$dbToken) {
    echo "❌ Token not found in database\n";
    exit(1);
}

$user = $dbToken->tokenable;

if (!$user) {
    echo "❌ User not found for this token\n";
    exit(1);
}

echo "✅ Token belongs to:\n";
echo "  User ID: {$user->id}\n";
echo "  Name: {$user->name}\n";
echo "  Email: {$user->email}\n";
echo "  Role column: {$user->role}\n";
echo "  Status: {$user->status}\n\n";

echo "Spatie Roles:\n";
$roles = $user->roles;
if ($roles->count() > 0) {
    foreach ($roles as $role) {
        echo "  - {$role->name} (guard: {$role->guard_name})\n";
    }
} else {
    echo "  ❌ No Spatie roles assigned!\n";
}

echo "\nRole Checks:\n";
echo "  hasRole('hr'): " . ($user->hasRole('hr') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasRole('hr', 'web'): " . ($user->hasRole('hr', 'web') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasRole('hr', 'sanctum'): " . ($user->hasRole('hr', 'sanctum') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasAnyRole(['hr', 'admin']): " . ($user->hasAnyRole(['hr', 'admin']) ? 'YES ✅' : 'NO ❌') . "\n";

if ($user->role === 'hr' && !$user->hasRole('hr', 'sanctum')) {
    echo "\n⚠️  PROBLEM: User has role column = 'hr' but no Spatie role for sanctum guard!\n";
    echo "\n=== Fixing ===\n";
    
    // Ensure role exists
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
    
    // Assign role for sanctum guard
    $hrRoleSanctum = \Spatie\Permission\Models\Role::where('name', 'hr')->where('guard_name', 'sanctum')->first();
    $user->roles()->syncWithoutDetaching([$hrRoleSanctum->id]);
    
    echo "✅ Role assigned for sanctum guard\n\n";
    
    // Refresh and check again
    $user->refresh();
    echo "Updated Role Checks:\n";
    echo "  hasRole('hr', 'sanctum'): " . ($user->hasRole('hr', 'sanctum') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "  hasAnyRole(['hr', 'admin']): " . ($user->hasAnyRole(['hr', 'admin']) ? 'YES ✅' : 'NO ❌') . "\n";
}

echo "\n=== Test in Postman ===\n";
echo "GET /api/admin/hr/employees\n";
echo "Authorization: Bearer {$token}\n";
echo "Accept: application/json\n";

