<?php

/**
 * Check HR user roles
 * Usage: php check_hr_user.php [user_email]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$userEmail = $argv[1] ?? null;

if (!$userEmail) {
    echo "Usage: php check_hr_user.php [user_email]\n";
    echo "Example: php check_hr_user.php hr@test.com\n\n";
    
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

echo "=== Checking HR User ===\n\n";
echo "User: {$user->name} ({$user->email})\n";
echo "ID: {$user->id}\n";
echo "Role column: {$user->role}\n";
echo "Status: {$user->status}\n\n";

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

if ($user->role === 'hr' && !$user->hasRole('hr')) {
    echo "\n⚠️  PROBLEM: User has role column = 'hr' but no Spatie role assigned!\n";
    echo "\n=== Fix ===\n";
    echo "Run: php artisan users:fix-roles\n";
    echo "Or manually assign: \$user->assignRole('hr');\n";
}

