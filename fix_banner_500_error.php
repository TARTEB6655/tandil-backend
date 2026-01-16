<?php

/**
 * Fix Banner 500 Error - Diagnostic and Fix Script
 * Run this on production server to identify and fix the issue
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Diagnosing Banner 500 Error\n";
echo str_repeat("=", 70) . "\n\n";

$errors = [];

// 1. Check if Banner model exists
echo "1. Checking Banner Model...\n";
try {
    $bannerModel = new \App\Models\Banner();
    echo "   ✅ Banner model exists\n";
} catch (Exception $e) {
    $errors[] = "Banner model not found: " . $e->getMessage();
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 2. Check if ordered() scope exists
echo "\n2. Checking ordered() scope...\n";
try {
    $reflection = new ReflectionClass(\App\Models\Banner::class);
    $methods = $reflection->getMethods();
    $hasOrderedScope = false;
    foreach ($methods as $method) {
        if ($method->getName() === 'scopeOrdered') {
            $hasOrderedScope = true;
            break;
        }
    }
    if ($hasOrderedScope) {
        echo "   ✅ ordered() scope exists\n";
    } else {
        $errors[] = "ordered() scope not found in Banner model";
        echo "   ❌ ordered() scope NOT found\n";
    }
} catch (Exception $e) {
    $errors[] = "Error checking scope: " . $e->getMessage();
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 3. Check if banners table exists
echo "\n3. Checking banners table...\n";
try {
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('banners');
    if ($tableExists) {
        echo "   ✅ banners table exists\n";
        
        // Check table structure
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('banners');
        echo "   📋 Table columns: " . implode(', ', $columns) . "\n";
    } else {
        $errors[] = "banners table does not exist";
        echo "   ❌ banners table does NOT exist\n";
        echo "   💡 Run: php artisan migrate\n";
    }
} catch (Exception $e) {
    $errors[] = "Error checking table: " . $e->getMessage();
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// 4. Check if view exists
echo "\n4. Checking view file...\n";
$viewPath = resource_path('views/admin/banners/index.blade.php');
if (file_exists($viewPath)) {
    echo "   ✅ View file exists: {$viewPath}\n";
} else {
    $errors[] = "View file not found: {$viewPath}";
    echo "   ❌ View file NOT found: {$viewPath}\n";
}

// 5. Test Banner query
echo "\n5. Testing Banner query...\n";
try {
    $banners = \App\Models\Banner::ordered()->get();
    echo "   ✅ Query successful, found " . $banners->count() . " banners\n";
} catch (Exception $e) {
    $errors[] = "Query error: " . $e->getMessage();
    echo "   ❌ Query error: " . $e->getMessage() . "\n";
    echo "   📝 Full error: " . $e->getTraceAsString() . "\n";
}

// 6. Check route
echo "\n6. Checking route...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $bannerRoute = null;
    foreach ($routes as $route) {
        if ($route->getName() === 'admin.banners.index') {
            $bannerRoute = $route;
            break;
        }
    }
    if ($bannerRoute) {
        echo "   ✅ Route exists: " . $bannerRoute->uri() . "\n";
    } else {
        $errors[] = "Route admin.banners.index not found";
        echo "   ❌ Route NOT found\n";
    }
} catch (Exception $e) {
    $errors[] = "Error checking route: " . $e->getMessage();
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
if (empty($errors)) {
    echo "✅ All checks passed! The issue might be:\n";
    echo "   - Server configuration\n";
    echo "   - PHP error logs (check storage/logs/laravel.log)\n";
    echo "   - Permission issues\n";
    echo "   - Cache issues (run: php artisan optimize:clear)\n";
} else {
    echo "❌ Issues Found:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n💡 Fixes:\n";
    if (in_array("ordered() scope not found in Banner model", $errors)) {
        echo "   1. Add ordered() scope to Banner model\n";
    }
    if (in_array("banners table does not exist", $errors)) {
        echo "   2. Run: php artisan migrate\n";
    }
    if (in_array("View file not found", $errors)) {
        echo "   3. Create view file: resources/views/admin/banners/index.blade.php\n";
    }
}

echo "\n🔧 Quick Fix Commands:\n";
echo "   php artisan optimize:clear\n";
echo "   php artisan route:clear\n";
echo "   php artisan config:clear\n";
echo "   php artisan view:clear\n";
echo "   php artisan cache:clear\n";

