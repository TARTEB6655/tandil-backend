<?php

/**
 * Quick Test for Users Statistics API
 * Run: php test_users_statistics_api.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing GET /api/admin/users/statistics\n";
echo str_repeat("=", 60) . "\n\n";

// Check route exists
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$routeFound = false;

foreach ($routes as $route) {
    $uri = $route->uri();
    $methods = $route->methods();
    
    if ($uri === 'api/admin/users/statistics' && in_array('GET', $methods)) {
        $routeFound = true;
        echo "✅ Route found: GET /api/admin/users/statistics\n";
        echo "   Controller: " . $route->getActionName() . "\n";
        echo "   Middleware: " . implode(', ', $route->gatherMiddleware()) . "\n";
        break;
    }
}

if (!$routeFound) {
    echo "❌ Route NOT found!\n";
    echo "\nChecking similar routes:\n";
    foreach ($routes as $route) {
        if (strpos($route->uri(), 'users') !== false && strpos($route->uri(), 'statistics') !== false) {
            echo "   - " . implode('|', $route->methods()) . " " . $route->uri() . "\n";
        }
    }
    exit(1);
}

// Test controller method exists
try {
    $controller = new \App\Http\Controllers\Admin\UserController();
    $reflection = new ReflectionClass($controller);
    
    if ($reflection->hasMethod('statistics')) {
        echo "✅ Controller method 'statistics()' exists\n";
    } else {
        echo "❌ Controller method 'statistics()' NOT found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test data calculation
try {
    $allUsers = \App\Models\User::count();
    $workers = \App\Models\User::where('role', 'technician')->count();
    $supervisors = \App\Models\User::where('role', 'supervisor')->count();
    $managers = \App\Models\User::where('role', 'area_manager')->count();
    
    echo "\n✅ Data calculation works:\n";
    echo "   All Users: {$allUsers}\n";
    echo "   Workers: {$workers}\n";
    echo "   Supervisors: {$supervisors}\n";
    echo "   Managers: {$managers}\n";
} catch (Exception $e) {
    echo "❌ Error calculating data: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "✅ All tests passed! API should be working.\n";
echo "\nTo test with authentication:\n";
echo "GET {{base_url}}/api/admin/users/statistics\n";
echo "Headers: Authorization: Bearer {your_admin_token}\n";

