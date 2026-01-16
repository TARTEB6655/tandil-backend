<?php

/**
 * Comprehensive Route Security Testing
 * Checks all routes for proper middleware and security
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Console\Kernel');
$kernel->bootstrap();

echo "🔒 Route Security Audit\n";
echo str_repeat("=", 80) . "\n\n";

$routes = \Illuminate\Support\Facades\Route::getRoutes();
$issues = [];
$secure = [];
$insecure = [];

// Check all routes
foreach ($routes as $route) {
    $uri = $route->uri();
    $methods = $route->methods();
    $middleware = $route->middleware();
    $name = $route->getName() ?: 'unnamed';
    
    // Skip public routes
    $publicRoutes = ['/', '/login', '/register', '/health', '/api/health', '/api/auth/login', '/api/auth/register'];
    if (in_array('/' . $uri, $publicRoutes) || strpos($uri, 'api/auth/login') !== false || strpos($uri, 'api/auth/register') !== false) {
        continue;
    }
    
    // Check admin routes
    if (strpos($uri, 'admin/') === 0 || strpos($uri, 'admin/') !== false) {
        $hasAuth = in_array('auth', $middleware) || in_array('web', $middleware);
        $hasRole = false;
        
        foreach ($middleware as $mw) {
            if (strpos($mw, 'role:admin') !== false || strpos($mw, 'role') !== false) {
                $hasRole = true;
                break;
            }
        }
        
        if (!$hasAuth && !$hasRole) {
            $insecure[] = [
                'uri' => $uri,
                'methods' => implode(', ', $methods),
                'name' => $name,
                'middleware' => implode(', ', $middleware),
                'issue' => 'Missing auth and role middleware'
            ];
        } elseif (!$hasAuth) {
            $insecure[] = [
                'uri' => $uri,
                'methods' => implode(', ', $methods),
                'name' => $name,
                'middleware' => implode(', ', $middleware),
                'issue' => 'Missing auth middleware'
            ];
        } elseif (!$hasRole) {
            $insecure[] = [
                'uri' => $uri,
                'methods' => implode(', ', $methods),
                'name' => $name,
                'middleware' => implode(', ', $middleware),
                'issue' => 'Missing role middleware'
            ];
        } else {
            $secure[] = $uri;
        }
    }
    
    // Check other protected routes
    if (strpos($uri, 'supervisor/') !== false || strpos($uri, 'technician/') !== false || 
        strpos($uri, 'client/') !== false || strpos($uri, 'hr/') !== false) {
        $hasAuth = in_array('auth', $middleware) || in_array('web', $middleware);
        if (!$hasAuth) {
            $insecure[] = [
                'uri' => $uri,
                'methods' => implode(', ', $methods),
                'name' => $name,
                'middleware' => implode(', ', $middleware),
                'issue' => 'Missing auth middleware'
            ];
        } else {
            $secure[] = $uri;
        }
    }
}

// Check API routes
foreach ($routes as $route) {
    $uri = $route->uri();
    if (strpos($uri, 'api/') === 0) {
        $middleware = $route->middleware();
        $isPublic = strpos($uri, 'api/auth/login') !== false || 
                   strpos($uri, 'api/auth/register') !== false ||
                   strpos($uri, 'api/health') !== false ||
                   strpos($uri, 'api/banners') !== false && strpos($uri, 'api/admin') === false;
        
        if (!$isPublic) {
            $hasAuth = in_array('auth:sanctum', $middleware) || in_array('sanctum', $middleware);
            if (!$hasAuth) {
                $insecure[] = [
                    'uri' => $uri,
                    'methods' => implode(', ', $route->methods()),
                    'name' => $route->getName() ?: 'unnamed',
                    'middleware' => implode(', ', $middleware),
                    'issue' => 'Missing auth:sanctum middleware'
                ];
            }
        }
    }
}

// Report results
echo "📊 Security Audit Results\n";
echo str_repeat("-", 80) . "\n";
echo "✅ Secure Routes: " . count($secure) . "\n";
echo "❌ Insecure Routes: " . count($insecure) . "\n\n";

if (!empty($insecure)) {
    echo "❌ INSECURE ROUTES FOUND:\n";
    echo str_repeat("-", 80) . "\n";
    foreach ($insecure as $route) {
        echo "   Route: {$route['uri']}\n";
        echo "   Methods: {$route['methods']}\n";
        echo "   Name: {$route['name']}\n";
        echo "   Current Middleware: {$route['middleware']}\n";
        echo "   Issue: {$route['issue']}\n";
        echo "\n";
    }
} else {
    echo "✅ All routes are properly secured!\n";
}

// Check controllers
echo "\n🔍 Controller Middleware Check\n";
echo str_repeat("-", 80) . "\n";

$adminControllers = [
    'AdminDashboardController' => \App\Http\Controllers\Admin\AdminDashboardController::class,
    'UserController' => \App\Http\Controllers\Admin\UserController::class,
    'BannerController' => \App\Http\Controllers\Admin\BannerController::class,
    'ProductController' => \App\Http\Controllers\Admin\ProductController::class,
    'RoleController' => \App\Http\Controllers\Admin\RoleController::class,
];

foreach ($adminControllers as $name => $class) {
    if (class_exists($class)) {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        
        if ($constructor) {
            $source = file_get_contents($constructor->getFileName());
            $lines = explode("\n", $source);
            $startLine = $constructor->getStartLine() - 1;
            $endLine = $constructor->getEndLine();
            $constructorCode = implode("\n", array_slice($lines, $startLine, $endLine - $startLine));
            
            if (strpos($constructorCode, 'middleware') !== false || strpos($constructorCode, 'role:admin') !== false) {
                echo "   ✅ {$name} has middleware in constructor\n";
            } else {
                echo "   ⚠️  {$name} may not have middleware in constructor (check routes)\n";
            }
        } else {
            echo "   ⚠️  {$name} has no constructor (relying on route middleware)\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "✅ Security Audit Complete!\n";

