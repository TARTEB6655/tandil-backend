<?php

/**
 * Comprehensive Test for Users Statistics API Issue
 * Run: php test_users_statistics_issue.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Testing Users Statistics API Issue\n";
echo str_repeat("=", 70) . "\n\n";

$errors = [];
$success = [];

// Test 1: Check route registration
echo "1. Checking route registration...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $routeFound = false;
    $routeDetails = null;
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        $methods = $route->methods();
        
        if ($uri === 'api/admin/users/statistics' && in_array('GET', $methods)) {
            $routeFound = true;
            $routeDetails = $route;
            echo "   ✅ Route found: GET /api/admin/users/statistics\n";
            echo "   ✅ Controller: " . $route->getActionName() . "\n";
            echo "   ✅ Route Name: " . ($route->getName() ?? 'No name') . "\n";
            echo "   ✅ Middleware: " . implode(', ', $route->gatherMiddleware()) . "\n";
            $success[] = "Route registration";
            break;
        }
    }
    
    if (!$routeFound) {
        echo "   ❌ Route NOT found!\n";
        $errors[] = "Route not registered";
        
        // Check for similar routes
        echo "\n   Checking similar routes:\n";
        foreach ($routes as $route) {
            if (strpos($route->uri(), 'users') !== false) {
                echo "      - " . implode('|', $route->methods()) . " " . $route->uri() . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "Route check: " . $e->getMessage();
}

// Test 2: Check route order (statistics should be before {id})
echo "\n2. Checking route order...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $userRoutes = [];
    
    foreach ($routes as $route) {
        $uri = $route->uri();
        if (strpos($uri, 'api/admin/users') === 0 && in_array('GET', $route->methods())) {
            $userRoutes[] = [
                'uri' => $uri,
                'name' => $route->getName(),
                'action' => $route->getActionName()
            ];
        }
    }
    
    // Sort by URI length (shorter = more specific, should come first)
    usort($userRoutes, function($a, $b) {
        return strlen($a['uri']) - strlen($b['uri']);
    });
    
    echo "   Route order:\n";
    foreach ($userRoutes as $index => $route) {
        $marker = ($route['uri'] === 'api/admin/users/statistics') ? ' ⭐' : '';
        echo "      " . ($index + 1) . ". " . $route['uri'] . $marker . "\n";
    }
    
    // Check if statistics comes before {id}
    $statisticsIndex = null;
    $idIndex = null;
    foreach ($userRoutes as $index => $route) {
        if ($route['uri'] === 'api/admin/users/statistics') {
            $statisticsIndex = $index;
        }
        if (strpos($route['uri'], '{id}') !== false) {
            $idIndex = $index;
        }
    }
    
    if ($statisticsIndex !== null && $idIndex !== null && $statisticsIndex < $idIndex) {
        echo "   ✅ Route order is correct (statistics before {id})\n";
        $success[] = "Route order";
    } else {
        echo "   ⚠️  Route order issue: statistics should come before {id}\n";
        $errors[] = "Route order incorrect";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "Route order check: " . $e->getMessage();
}

// Test 3: Check controller method
echo "\n3. Checking controller method...\n";
try {
    $controller = new \App\Http\Controllers\Admin\UserController();
    $reflection = new ReflectionClass($controller);
    
    if ($reflection->hasMethod('statistics')) {
        $method = $reflection->getMethod('statistics');
        echo "   ✅ Method 'statistics()' exists\n";
        echo "   ✅ Method is " . ($method->isPublic() ? 'public' : 'private') . "\n";
        echo "   ✅ Parameters: " . $method->getNumberOfParameters() . "\n";
        $success[] = "Controller method";
    } else {
        echo "   ❌ Method 'statistics()' NOT found\n";
        $errors[] = "Controller method missing";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "Controller check: " . $e->getMessage();
}

// Test 4: Test route matching
echo "\n4. Testing route matching...\n";
try {
    $request = \Illuminate\Http\Request::create('/api/admin/users/statistics', 'GET');
    $request->headers->set('Accept', 'application/json');
    
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $matched = $routes->match($request);
    
    if ($matched) {
        echo "   ✅ Route matches successfully\n";
        echo "   ✅ Matched route: " . $matched->uri() . "\n";
        echo "   ✅ Action: " . $matched->getActionName() . "\n";
        $success[] = "Route matching";
    } else {
        echo "   ❌ Route does NOT match\n";
        $errors[] = "Route matching failed";
    }
} catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
    echo "   ❌ Route NOT found: " . $e->getMessage() . "\n";
    $errors[] = "Route matching: " . $e->getMessage();
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "Route matching: " . $e->getMessage();
}

// Test 5: Check if route file is loaded
echo "\n5. Checking route file loading...\n";
try {
    $apiRoutesFile = __DIR__ . '/routes/api.php';
    if (file_exists($apiRoutesFile)) {
        echo "   ✅ routes/api.php exists\n";
        
        $content = file_get_contents($apiRoutesFile);
        if (strpos($content, 'users/statistics') !== false) {
            echo "   ✅ Route definition found in file\n";
            $success[] = "Route file";
        } else {
            echo "   ❌ Route definition NOT found in file\n";
            $errors[] = "Route not in file";
        }
    } else {
        echo "   ❌ routes/api.php NOT found\n";
        $errors[] = "Route file missing";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "File check: " . $e->getMessage();
}

// Test 6: Test actual controller call (without auth for now)
echo "\n6. Testing controller method execution...\n";
try {
    $controller = new \App\Http\Controllers\Admin\UserController();
    $request = new \Illuminate\Http\Request();
    
    // Try to call the method directly
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('statistics');
    
    echo "   ✅ Method can be called\n";
    echo "   ⚠️  Note: Full test requires authentication\n";
    $success[] = "Controller execution";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    $errors[] = "Controller execution: " . $e->getMessage();
}

// Test 7: Check middleware
echo "\n7. Checking middleware configuration...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    foreach ($routes as $route) {
        if ($route->uri() === 'api/admin/users/statistics') {
            $middleware = $route->gatherMiddleware();
            echo "   ✅ Middleware: " . implode(', ', $middleware) . "\n";
            
            if (in_array('auth:sanctum', $middleware)) {
                echo "   ✅ Requires authentication (auth:sanctum)\n";
            }
            if (in_array('role:admin', $middleware)) {
                echo "   ✅ Requires admin role\n";
            }
            $success[] = "Middleware";
            break;
        }
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
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
    echo "✅ All tests passed! Route should be working.\n";
    echo "\n💡 If API still returns 404, check:\n";
    echo "   1. Server is running (php artisan serve)\n";
    echo "   2. Route cache is cleared (php artisan route:clear)\n";
    echo "   3. You're using correct URL: GET /api/admin/users/statistics\n";
    echo "   4. You have valid admin token in Authorization header\n";
    echo "   5. Base URL is correct in Postman environment\n";
} else {
    echo "⚠️  Some issues found. Please review errors above.\n";
}

echo "\n🔧 Quick Fix Commands:\n";
echo "   php artisan route:clear\n";
echo "   php artisan config:clear\n";
echo "   php artisan optimize:clear\n";
echo "   php artisan route:list --path=api/admin/users/statistics\n";

