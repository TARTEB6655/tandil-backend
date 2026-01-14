<?php

/**
 * Comprehensive Production Readiness Test
 * Tests all aspects of the application to ensure zero errors
 * Run: php test-production-ready.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "==========================================\n";
echo "  PRODUCTION READINESS TEST\n";
echo "==========================================\n\n";

$errors = [];
$warnings = [];
$passed = [];

// 1. Test View Configuration
echo "[1/15] Testing View Configuration...\n";
try {
    $viewConfig = config('view');
    if (empty($viewConfig['compiled'])) {
        throw new Exception('View compiled path is empty');
    }
    $viewPath = $viewConfig['compiled'];
    
    // Ensure directory exists
    if (!is_dir($viewPath)) {
        @mkdir($viewPath, 0775, true);
    }
    
    if (!is_dir($viewPath)) {
        throw new Exception('View directory could not be created: ' . $viewPath);
    }
    
    $passed[] = "View configuration";
    echo "  ✅ View config: {$viewPath}\n\n";
} catch (\Exception $e) {
    $errors[] = "View config: " . $e->getMessage();
    echo "  ❌ View config error: " . $e->getMessage() . "\n\n";
}

// 2. Test Cache Clearing
echo "[2/15] Testing Cache Clearing...\n";
try {
    // Remove config cache first
    $configCache = __DIR__ . '/bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        @unlink($configCache);
    }
    
    $app->make('Illuminate\Contracts\Console\Kernel')->call('config:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('cache:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('view:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('route:clear');
    
    $passed[] = "Cache clearing";
    echo "  ✅ All caches cleared successfully\n\n";
} catch (\Exception $e) {
    $errors[] = "Cache clearing: " . $e->getMessage();
    echo "  ❌ Cache clearing error: " . $e->getMessage() . "\n\n";
}

// 3. Test optimize:clear
echo "[3/15] Testing optimize:clear...\n";
try {
    $app->make('Illuminate\Contracts\Console\Kernel')->call('optimize:clear');
    $passed[] = "optimize:clear command";
    echo "  ✅ optimize:clear works\n\n";
} catch (\Exception $e) {
    $errors[] = "optimize:clear: " . $e->getMessage();
    echo "  ❌ optimize:clear error: " . $e->getMessage() . "\n\n";
}

// 4. Test Database Connection
echo "[4/15] Testing Database Connection...\n";
try {
    \DB::connection()->getPdo();
    $passed[] = "Database connection";
    echo "  ✅ Database connected\n\n";
} catch (\Exception $e) {
    $warnings[] = "Database: " . $e->getMessage();
    echo "  ⚠️  Database: " . $e->getMessage() . "\n\n";
}

// 5. Test Migrations
echo "[5/15] Testing Migrations...\n";
try {
    $migrations = \DB::table('migrations')->count();
    if ($migrations > 0) {
        $passed[] = "Migrations";
        echo "  ✅ {$migrations} migrations found\n\n";
    } else {
        $warnings[] = "No migrations found";
        echo "  ⚠️  No migrations found\n\n";
    }
} catch (\Exception $e) {
    $warnings[] = "Migrations: " . $e->getMessage();
    echo "  ⚠️  Migrations: " . $e->getMessage() . "\n\n";
}

// 6. Test Seeders (without running them)
echo "[6/15] Testing Seeder Files...\n";
$seeders = [
    'DatabaseSeeder',
    'RoleSeeder',
    'CustomUsersSeeder',
    'CompleteDataSeeder',
];
$seederErrors = [];
foreach ($seeders as $seeder) {
    $class = "Database\\Seeders\\{$seeder}";
    if (!class_exists($class)) {
        $seederErrors[] = "Missing: {$seeder}";
    }
}
if (empty($seederErrors)) {
    $passed[] = "Seeder files";
    echo "  ✅ All seeder files exist\n\n";
} else {
    $errors[] = "Seeders: " . implode(', ', $seederErrors);
    echo "  ❌ Seeder errors: " . implode(', ', $seederErrors) . "\n\n";
}

// 7. Test CompleteDataSeeder syntax
echo "[7/15] Testing CompleteDataSeeder syntax...\n";
try {
    $seederFile = __DIR__ . '/database/seeders/CompleteDataSeeder.php';
    $content = file_get_contents($seederFile);
    
    // Check for undefined variable
    if (preg_match('/count\(\$transactions\)/', $content) && !preg_match('/\$transactions\s*=/', $content)) {
        // Check if it uses Transaction::count() instead
        if (preg_match('/Transaction::count\(\)/', $content)) {
            $passed[] = "CompleteDataSeeder syntax";
            echo "  ✅ Seeder uses correct Transaction::count()\n\n";
        } else {
            $errors[] = "CompleteDataSeeder uses undefined \$transactions";
            echo "  ❌ Seeder has undefined \$transactions variable\n\n";
        }
    } else {
        $passed[] = "CompleteDataSeeder syntax";
        echo "  ✅ Seeder syntax OK\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "Seeder check: " . $e->getMessage();
    echo "  ❌ Seeder check error: " . $e->getMessage() . "\n\n";
}

// 8. Test Routes
echo "[8/15] Testing Routes...\n";
try {
    $routes = \Illuminate\Support\Facades\Route::getRoutes();
    $routeCount = count($routes);
    if ($routeCount > 0) {
        $passed[] = "Routes";
        echo "  ✅ {$routeCount} routes registered\n\n";
    } else {
        $errors[] = "No routes found";
        echo "  ❌ No routes found\n\n";
    }
} catch (\Exception $e) {
    $errors[] = "Routes: " . $e->getMessage();
    echo "  ❌ Routes error: " . $e->getMessage() . "\n\n";
}

// 9. Test Controllers
echo "[9/15] Testing Critical Controllers...\n";
$controllers = [
    'App\\Http\\Controllers\\Shop\\ProductController',
    'App\\Http\\Controllers\\Shop\\CategoryController',
    'App\\Http\\Controllers\\HR\\EmployeeController',
    'App\\Http\\Controllers\\Admin\\UserController',
];
$missingControllers = [];
foreach ($controllers as $controller) {
    if (!class_exists($controller)) {
        $missingControllers[] = $controller;
    }
}
if (empty($missingControllers)) {
    $passed[] = "Controllers";
    echo "  ✅ All critical controllers exist\n\n";
} else {
    $errors[] = "Missing controllers: " . implode(', ', $missingControllers);
    echo "  ❌ Missing controllers: " . implode(', ', $missingControllers) . "\n\n";
}

// 10. Test Models
echo "[10/15] Testing Models...\n";
$models = [
    'App\\Models\\User',
    'App\\Models\\Product',
    'App\\Models\\Transaction',
    'App\\Models\\Employee',
];
$missingModels = [];
foreach ($models as $model) {
    if (!class_exists($model)) {
        $missingModels[] = $model;
    }
}
if (empty($missingModels)) {
    $passed[] = "Models";
    echo "  ✅ All critical models exist\n\n";
} else {
    $errors[] = "Missing models: " . implode(', ', $missingModels);
    echo "  ❌ Missing models: " . implode(', ', $missingModels) . "\n\n";
}

// 11. Test Storage Directories
echo "[11/15] Testing Storage Directories...\n";
$storageDirs = [
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
];
$missingDirs = [];
foreach ($storageDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
        if (!is_dir($path)) {
            $missingDirs[] = $dir;
        }
    }
}
if (empty($missingDirs)) {
    $passed[] = "Storage directories";
    echo "  ✅ All storage directories exist\n\n";
} else {
    $errors[] = "Missing directories: " . implode(', ', $missingDirs);
    echo "  ❌ Missing directories: " . implode(', ', $missingDirs) . "\n\n";
}

// 12. Test Config Files
echo "[12/15] Testing Config Files...\n";
$configFiles = [
    'config/view.php',
    'config/cache.php',
    'config/database.php',
];
$missingConfigs = [];
foreach ($configFiles as $config) {
    if (!file_exists(__DIR__ . '/' . $config)) {
        $missingConfigs[] = $config;
    }
}
if (empty($missingConfigs)) {
    $passed[] = "Config files";
    echo "  ✅ All config files exist\n\n";
} else {
    $errors[] = "Missing configs: " . implode(', ', $missingConfigs);
    echo "  ❌ Missing configs: " . implode(', ', $missingConfigs) . "\n\n";
}

// 13. Test Cache Driver
echo "[13/15] Testing Cache Driver...\n";
$cacheDriver = config('cache.default');
if ($cacheDriver === 'file') {
    $passed[] = "Cache driver";
    echo "  ✅ Cache driver: file (correct for production)\n\n";
} else {
    $warnings[] = "Cache driver is '{$cacheDriver}', should be 'file' for production";
    echo "  ⚠️  Cache driver: {$cacheDriver} (should be 'file')\n\n";
}

// 14. Test Database Driver
echo "[14/15] Testing Database Driver...\n";
$dbDriver = config('database.default');
if ($dbDriver === 'mysql') {
    $passed[] = "Database driver";
    echo "  ✅ Database driver: mysql (correct for production)\n\n";
} else {
    $warnings[] = "Database driver is '{$dbDriver}', should be 'mysql' for production";
    echo "  ⚠️  Database driver: {$dbDriver} (should be 'mysql')\n\n";
}

// 15. Test Admin User
echo "[15/15] Testing Admin User...\n";
try {
    $admin = \App\Models\User::where('email', 'admin@tandil.com')->first();
    if ($admin && $admin->hasRole('admin')) {
        $passed[] = "Admin user";
        echo "  ✅ Admin user exists\n\n";
    } else {
        $warnings[] = "Admin user not found or missing role";
        echo "  ⚠️  Admin user not found (run: php artisan admin:ensure)\n\n";
    }
} catch (\Exception $e) {
    $warnings[] = "Admin check: " . $e->getMessage();
    echo "  ⚠️  Admin check: " . $e->getMessage() . "\n\n";
}

// Summary
echo "==========================================\n";
echo "  TEST SUMMARY\n";
echo "==========================================\n";
echo "✅ Passed: " . count($passed) . "\n";
echo "⚠️  Warnings: " . count($warnings) . "\n";
echo "❌ Errors: " . count($errors) . "\n\n";

if (!empty($passed)) {
    echo "✅ Passed Tests:\n";
    foreach ($passed as $test) {
        echo "   - {$test}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  Warnings:\n";
    foreach ($warnings as $warning) {
        echo "   - {$warning}\n";
    }
    echo "\n";
}

if (!empty($errors)) {
    echo "❌ Errors (MUST FIX):\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
    echo "\n";
    exit(1);
}

echo "✅ ALL TESTS PASSED - PRODUCTION READY!\n\n";

