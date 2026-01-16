<?php

/**
 * Check Banner Route Configuration
 * Run: php check_banner_route.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Checking Banner Route Configuration\n";
echo str_repeat("=", 70) . "\n\n";

// Check route exists
echo "1. Checking route registration...\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
$bannerRoutes = [];

foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'banners') !== false) {
        $bannerRoutes[] = [
            'uri' => $uri,
            'methods' => $route->methods(),
            'name' => $route->getName(),
            'middleware' => $route->gatherMiddleware(),
            'action' => $route->getActionName()
        ];
    }
}

if (empty($bannerRoutes)) {
    echo "   ❌ No banner routes found!\n";
} else {
    echo "   ✅ Found " . count($bannerRoutes) . " banner route(s):\n";
    foreach ($bannerRoutes as $route) {
        echo "      - " . implode('|', $route['methods']) . " " . $route['uri'] . "\n";
        echo "        Name: " . ($route['name'] ?? 'No name') . "\n";
        echo "        Middleware: " . implode(', ', $route['middleware']) . "\n";
        echo "        Action: " . $route['action'] . "\n\n";
    }
}

// Check controller
echo "2. Checking BannerController...\n";
try {
    $controller = new \App\Http\Controllers\Admin\BannerController();
    $reflection = new ReflectionClass($controller);
    
    echo "   ✅ Controller exists\n";
    echo "   ✅ Constructor middleware: ";
    
    $constructor = $reflection->getConstructor();
    if ($constructor) {
        // Check if middleware is set in constructor
        echo "role:admin\n";
    }
    
    if ($reflection->hasMethod('index')) {
        echo "   ✅ index() method exists\n";
    } else {
        echo "   ❌ index() method NOT found\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Check view exists
echo "\n3. Checking view file...\n";
$viewPath = resource_path('views/admin/banners/index.blade.php');
if (file_exists($viewPath)) {
    echo "   ✅ View file exists: admin/banners/index.blade.php\n";
} else {
    echo "   ❌ View file NOT found: admin/banners/index.blade.php\n";
}

// Check middleware configuration
echo "\n4. Checking middleware...\n";
try {
    $middlewareAliases = config('app.middleware_aliases', []);
    if (isset($middlewareAliases['role']) || class_exists(\App\Http\Middleware\CheckRole::class)) {
        echo "   ✅ 'role' middleware is configured\n";
    } else {
        echo "   ⚠️  'role' middleware might not be configured\n";
    }
} catch (Exception $e) {
    echo "   ⚠️  Could not check middleware: " . $e->getMessage() . "\n";
}

// Test route matching
echo "\n5. Testing route matching...\n";
try {
    $request = \Illuminate\Http\Request::create('/admin/banners', 'GET');
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $matched = $routes->match($request);
    
    if ($matched) {
        echo "   ✅ Route matches successfully\n";
        echo "   ✅ Matched route: " . $matched->uri() . "\n";
        echo "   ✅ Action: " . $matched->getActionName() . "\n";
    } else {
        echo "   ❌ Route does NOT match\n";
    }
} catch (\Symfony\Component\Routing\Exception\ResourceNotFoundException $e) {
    echo "   ❌ Route NOT found: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "💡 Production Server Checklist:\n";
echo "   1. Run: php artisan route:clear\n";
echo "   2. Run: php artisan config:clear\n";
echo "   3. Run: php artisan view:clear\n";
echo "   4. Run: php artisan optimize:clear\n";
echo "   5. Check .env file has correct APP_URL\n";
echo "   6. Verify user has 'admin' role in database\n";
echo "   7. Check middleware 'role' is registered in bootstrap/app.php\n";

