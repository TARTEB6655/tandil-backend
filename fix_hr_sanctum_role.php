<?php

/**
 * Fix HR user role for sanctum guard
 * Usage: php fix_hr_sanctum_role.php [user_email]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

$userEmail = $argv[1] ?? null;

if (!$userEmail) {
    echo "Usage: php fix_hr_sanctum_role.php [user_email]\n";
    echo "Example: php fix_hr_sanctum_role.php hr1@example.com\n\n";
    
    // List HR users
    echo "HR users:\n";
    $hrUsers = User::where('role', 'hr')->get();
    foreach ($hrUsers as $user) {
        echo "  - {$user->email} (ID: {$user->id})\n";
    }
    exit(1);
}

$user = User::where('email', $userEmail)->first();

if (!$user) {
    echo "❌ User not found: {$userEmail}\n";
    exit(1);
}

echo "=== Fixing HR Role for Sanctum Guard ===\n\n";
echo "User: {$user->name} ({$user->email})\n";
echo "ID: {$user->id}\n\n";

// Ensure role exists for sanctum guard
$hrRoleSanctum = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
echo "✅ Ensured 'hr' role exists for sanctum guard\n\n";

// Check if user has role for sanctum guard
$hasSanctumRole = $user->roles()->where('guard_name', 'sanctum')->where('name', 'hr')->exists();

if (!$hasSanctumRole) {
    echo "Assigning 'hr' role for sanctum guard...\n";
    // Manually attach the role for sanctum guard
    $user->roles()->syncWithoutDetaching([$hrRoleSanctum->id]);
    echo "✅ Role assigned for sanctum guard\n\n";
} else {
    echo "✅ User already has 'hr' role for sanctum guard\n\n";
}

// Refresh and check
$user->refresh();
echo "Current Spatie Roles:\n";
$roles = $user->roles;
foreach ($roles as $role) {
    echo "  - {$role->name} (guard: {$role->guard_name})\n";
}

echo "\nRole Checks:\n";
echo "  hasRole('hr'): " . ($user->hasRole('hr') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasRole('hr', 'web'): " . ($user->hasRole('hr', 'web') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasRole('hr', 'sanctum'): " . ($user->hasRole('hr', 'sanctum') ? 'YES ✅' : 'NO ❌') . "\n";
echo "  hasAnyRole(['hr', 'admin']): " . ($user->hasAnyRole(['hr', 'admin']) ? 'YES ✅' : 'NO ❌') . "\n";

// Create new token
echo "\n=== Creating New Token ===\n";
$token = $user->createToken('api_token')->plainTextToken;
echo "✅ New token:\n";
echo "  {$token}\n\n";

echo "=== Test in Postman ===\n";
echo "GET /api/admin/hr/employees\n";
echo "Authorization: Bearer {$token}\n";
echo "Accept: application/json\n";

