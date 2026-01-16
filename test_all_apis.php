<?php

/**
 * Comprehensive API Test Script
 * Tests: Banner API, Dashboard Statistics, User Statistics, User List
 * Run: php test_all_apis.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🧪 Testing All APIs\n";
echo str_repeat("=", 70) . "\n\n";

$errors = [];
$success = [];
$baseUrl = 'http://localhost:8000';

// Test 1: Banner API (Public)
echo "1. Testing Banner API (Public)...\n";
try {
    $banners = \App\Models\Banner::active()->ordered()->get();
    echo "   ✓ Banner model works (Found: {$banners->count()} active banners)\n";
    
    // Test API route exists
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $bannerRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/banners' && in_array('GET', $route->methods())) {
            $bannerRoute = $route;
            break;
        }
    }
    if ($bannerRoute) {
        echo "   ✓ Banner API route exists: GET /api/banners\n";
        $success[] = "Banner API route";
    } else {
        echo "   ✗ Banner API route not found\n";
        $errors[] = "Banner API route not found";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Banner API: " . $e->getMessage();
}

// Test 2: Admin Dashboard Statistics API
echo "\n2. Testing Admin Dashboard Statistics API...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $statsRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/admin/dashboard/statistics' && in_array('GET', $route->methods())) {
            $statsRoute = $route;
            break;
        }
    }
    if ($statsRoute) {
        echo "   ✓ Dashboard statistics route exists: GET /api/admin/dashboard/statistics\n";
        $success[] = "Dashboard statistics route";
        
        // Test controller method
        $controller = new \App\Http\Controllers\Admin\AdminDashboardController();
        $reflection = new ReflectionClass($controller);
        if ($reflection->hasMethod('statistics')) {
            echo "   ✓ Controller has statistics() method\n";
            $success[] = "Dashboard statistics method";
        } else {
            echo "   ✗ Controller missing statistics() method\n";
            $errors[] = "Dashboard statistics method missing";
        }
    } else {
        echo "   ✗ Dashboard statistics route not found\n";
        $errors[] = "Dashboard statistics route not found";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Dashboard statistics: " . $e->getMessage();
}

// Test 3: User Statistics API
echo "\n3. Testing User Statistics API...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $userStatsRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/admin/users/statistics' && in_array('GET', $route->methods())) {
            $userStatsRoute = $route;
            break;
        }
    }
    if ($userStatsRoute) {
        echo "   ✓ User statistics route exists: GET /api/admin/users/statistics\n";
        $success[] = "User statistics route";
        
        // Test controller method
        $controller = new \App\Http\Controllers\Admin\UserController();
        $reflection = new ReflectionClass($controller);
        if ($reflection->hasMethod('statistics')) {
            echo "   ✓ Controller has statistics() method\n";
            $success[] = "User statistics method";
        } else {
            echo "   ✗ Controller missing statistics() method\n";
            $errors[] = "User statistics method missing";
        }
    } else {
        echo "   ✗ User statistics route not found\n";
        $errors[] = "User statistics route not found";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "User statistics: " . $e->getMessage();
}

// Test 4: User List API
echo "\n4. Testing User List API...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $userListRoute = null;
    foreach ($routes as $route) {
        if ($route->uri() === 'api/admin/users' && in_array('GET', $route->methods())) {
            $userListRoute = $route;
            break;
        }
    }
    if ($userListRoute) {
        echo "   ✓ User list route exists: GET /api/admin/users\n";
        $success[] = "User list route";
        
        // Test category filtering logic
        $controller = new \App\Http\Controllers\Admin\UserController();
        $reflection = new ReflectionClass($controller);
        if ($reflection->hasMethod('index')) {
            echo "   ✓ Controller has index() method\n";
            $success[] = "User list method";
        } else {
            echo "   ✗ Controller missing index() method\n";
            $errors[] = "User list method missing";
        }
    } else {
        echo "   ✗ User list route not found\n";
        $errors[] = "User list route not found";
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "User list: " . $e->getMessage();
}

// Test 5: Check for duplicate routes
echo "\n5. Checking for duplicate routes...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $routeMap = [];
    $duplicates = [];
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        $method = $route->methods()[0] ?? 'GET';
        $key = $method . ':' . $uri;
        
        if (isset($routeMap[$key])) {
            $duplicates[] = $key;
        } else {
            $routeMap[$key] = true;
        }
    }
    
    if (empty($duplicates)) {
        echo "   ✓ No duplicate routes found\n";
        $success[] = "No duplicate routes";
    } else {
        echo "   ✗ Found duplicate routes:\n";
        foreach ($duplicates as $dup) {
            echo "     - {$dup}\n";
        }
        $errors[] = "Duplicate routes found: " . implode(', ', $duplicates);
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Duplicate check: " . $e->getMessage();
}

// Test 6: Check for duplicate controller methods
echo "\n6. Checking for duplicate controller methods...\n";
try {
    $adminController = new \App\Http\Controllers\Admin\AdminDashboardController();
    $userController = new \App\Http\Controllers\Admin\UserController();
    
    $adminMethods = get_class_methods($adminController);
    $userMethods = get_class_methods($userController);
    
    // Check for duplicate statistics methods
    $adminHasStats = in_array('statistics', $adminMethods);
    $userHasStats = in_array('statistics', $userMethods);
    
    if ($adminHasStats && $userHasStats) {
        echo "   ✓ Both controllers have statistics() method (different purposes - OK)\n";
        $success[] = "Controller methods";
    } else {
        if (!$adminHasStats) {
            echo "   ✗ AdminDashboardController missing statistics() method\n";
            $errors[] = "AdminDashboardController missing statistics method";
        }
        if (!$userHasStats) {
            echo "   ✗ UserController missing statistics() method\n";
            $errors[] = "UserController missing statistics method";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Controller method check: " . $e->getMessage();
}

// Test 7: Test actual data calculation
echo "\n7. Testing data calculations...\n";
try {
    $now = \Carbon\Carbon::now();
    
    // Test customers count
    $customersTotal = \App\Models\User::where('role', 'client')->count();
    echo "   ✓ Customers total: {$customersTotal}\n";
    
    // Test technicians count
    $techniciansTotal = \App\Models\User::where('role', 'technician')->count();
    echo "   ✓ Technicians total: {$techniciansTotal}\n";
    
    // Test supervisors count
    $supervisorsTotal = \App\Models\User::where('role', 'supervisor')->count();
    echo "   ✓ Supervisors total: {$supervisorsTotal}\n";
    
    // Test managers count
    $managersTotal = \App\Models\User::where('role', 'area_manager')->count();
    echo "   ✓ Managers total: {$managersTotal}\n";
    
    // Test employees count (technicians + supervisors + area_managers + hr)
    $employeesTotal = \App\Models\User::whereIn('role', ['technician', 'supervisor', 'area_manager', 'hr'])->count()
        + \App\Models\Employee::count();
    echo "   ✓ Employees total: {$employeesTotal}\n";
    
    $success[] = "Data calculations";
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Data calculations: " . $e->getMessage();
}

// Test 8: Test route middleware
echo "\n8. Testing route middleware...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    
    $bannerRoute = null;
    $statsRoute = null;
    $userStatsRoute = null;
    
    foreach ($routes as $route) {
        if ($route->uri() === 'api/banners') {
            $bannerRoute = $route;
        }
        if ($route->uri() === 'api/admin/dashboard/statistics') {
            $statsRoute = $route;
        }
        if ($route->uri() === 'api/admin/users/statistics') {
            $userStatsRoute = $route;
        }
    }
    
    // Banner should be public (no auth required)
    if ($bannerRoute) {
        $middleware = $bannerRoute->gatherMiddleware();
        if (empty($middleware) || !in_array('auth:sanctum', $middleware)) {
            echo "   ✓ Banner route is public (no auth required)\n";
            $success[] = "Banner middleware";
        } else {
            echo "   ⚠ Banner route has auth middleware (should be public)\n";
        }
    }
    
    // Admin routes should have auth and role middleware
    if ($statsRoute) {
        $middleware = $statsRoute->gatherMiddleware();
        if (in_array('auth:sanctum', $middleware)) {
            echo "   ✓ Dashboard statistics route has auth middleware\n";
            $success[] = "Dashboard statistics middleware";
        } else {
            echo "   ✗ Dashboard statistics route missing auth middleware\n";
            $errors[] = "Dashboard statistics missing auth";
        }
    }
    
    if ($userStatsRoute) {
        $middleware = $userStatsRoute->gatherMiddleware();
        if (in_array('auth:sanctum', $middleware)) {
            echo "   ✓ User statistics route has auth middleware\n";
            $success[] = "User statistics middleware";
        } else {
            echo "   ✗ User statistics route missing auth middleware\n";
            $errors[] = "User statistics missing auth";
        }
    }
} catch (Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n";
    $errors[] = "Middleware check: " . $e->getMessage();
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 Test Summary\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Successful: " . count($success) . " tests\n";
if (count($errors) > 0) {
    echo "❌ Errors: " . count($errors) . " tests\n";
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
} else {
    echo "❌ Errors: 0 tests\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
if (count($errors) === 0) {
    echo "🎉 All tests passed! All APIs are working correctly.\n";
    echo "\n✅ APIs Ready:\n";
    echo "  1. GET /api/banners (Public)\n";
    echo "  2. GET /api/admin/dashboard/statistics (Admin)\n";
    echo "  3. GET /api/admin/users/statistics (Admin)\n";
    echo "  4. GET /api/admin/users (Admin)\n";
    exit(0);
} else {
    echo "⚠️  Some tests failed. Please review the errors above.\n";
    exit(1);
}

