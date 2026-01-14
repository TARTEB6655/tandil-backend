<?php

/**
 * Verify Storage Setup Script
 * Run: php verify-storage-setup.php
 * This verifies and fixes storage directory setup
 */

echo "==========================================\n";
echo "  Storage Setup Verification & Fix\n";
echo "==========================================\n\n";

$errors = [];
$fixed = [];

// 1. Check storage/framework/views directory
echo "[1/5] Checking storage/framework/views directory...\n";
$viewsPath = __DIR__ . '/storage/framework/views';
if (!is_dir($viewsPath)) {
    @mkdir($viewsPath, 0775, true);
    @chmod($viewsPath, 0775);
    $fixed[] = "Created storage/framework/views directory";
    echo "  ✅ Created storage/framework/views\n\n";
} else {
    @chmod($viewsPath, 0775);
    echo "  ✅ storage/framework/views exists\n\n";
}

// 2. Check and set permissions
echo "[2/5] Setting correct permissions...\n";
$paths = [
    'storage' => 0775,
    'storage/framework' => 0775,
    'storage/framework/views' => 0775,
    'storage/framework/cache' => 0775,
    'storage/framework/sessions' => 0775,
    'storage/logs' => 0775,
    'bootstrap/cache' => 0775,
];

foreach ($paths as $path => $perm) {
    $fullPath = __DIR__ . '/' . $path;
    if (is_dir($fullPath)) {
        @chmod($fullPath, $perm);
        // Also set permissions recursively for storage
        if ($path === 'storage') {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @chmod($item->getPathname(), $perm);
                }
            }
        }
    }
}
echo "  ✅ Permissions set correctly\n\n";

// 3. Verify config/view.php
echo "[3/5] Verifying config/view.php...\n";
$configViewPath = __DIR__ . '/config/view.php';
if (!file_exists($configViewPath)) {
    $errors[] = "config/view.php is missing";
    echo "  ❌ config/view.php missing\n\n";
} else {
    $config = require $configViewPath;
    $compiledPath = $config['compiled'] ?? null;
    if (!$compiledPath) {
        $errors[] = "config/view.php missing compiled path";
        echo "  ❌ Compiled path not configured\n\n";
    } else {
        // Check if path resolves correctly
        $resolvedPath = realpath($viewsPath);
        if ($resolvedPath && strpos($compiledPath, 'storage/framework/views') !== false) {
            echo "  ✅ config/view.php correctly configured\n";
            echo "     Compiled path: " . $compiledPath . "\n\n";
        } else {
            echo "  ⚠️  Compiled path may need verification\n\n";
        }
    }
}

// 4. Clear all caches
echo "[4/5] Clearing all caches...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    
    // Remove config cache first
    $configCache = __DIR__ . '/bootstrap/cache/config.php';
    if (file_exists($configCache)) {
        @unlink($configCache);
    }
    
    // Clear caches
    $app->make('Illuminate\Contracts\Console\Kernel')->call('cache:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('view:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('config:clear');
    $app->make('Illuminate\Contracts\Console\Kernel')->call('route:clear');
    
    echo "  ✅ All caches cleared\n\n";
} catch (\Exception $e) {
    $errors[] = "Cache clearing error: " . $e->getMessage();
    echo "  ⚠️  Cache clearing: " . $e->getMessage() . "\n";
    // Manual fallback
    $cacheFiles = [
        'bootstrap/cache/config.php',
        'bootstrap/cache/routes.php',
    ];
    foreach ($cacheFiles as $file) {
        $path = __DIR__ . '/' . $file;
        if (file_exists($path)) {
            @unlink($path);
        }
    }
    // Clear view cache files manually
    if (is_dir($viewsPath)) {
        $files = glob($viewsPath . '/*.php');
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    echo "  ✅ Caches cleared manually\n\n";
}

// 5. Run optimize:clear
echo "[5/5] Running optimize:clear...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->call('optimize:clear');
    echo "  ✅ optimize:clear completed successfully\n\n";
} catch (\Exception $e) {
    $errors[] = "optimize:clear error: " . $e->getMessage();
    echo "  ⚠️  optimize:clear: " . $e->getMessage() . "\n\n";
}

// Summary
echo "==========================================\n";
echo "  Summary\n";
echo "==========================================\n";

if (!empty($fixed)) {
    echo "✅ Fixed:\n";
    foreach ($fixed as $item) {
        echo "   - $item\n";
    }
    echo "\n";
}

if (empty($errors)) {
    echo "✅ All checks passed! Storage setup is correct.\n\n";
} else {
    echo "⚠️  Issues found:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
    echo "\n";
}

echo "You can now run: php artisan optimize:clear\n";
echo "This should work without errors.\n\n";

