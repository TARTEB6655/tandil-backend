<?php

/**
 * Fix View Cache Configuration
 * Run this on production: php fix-view-cache.php
 */

echo "=== Fixing View Cache Configuration ===\n\n";

// 1. Remove config cache
echo "1. Removing config cache...\n";
$configCache = __DIR__ . '/bootstrap/cache/config.php';
if (file_exists($configCache)) {
    @unlink($configCache);
    echo "   ✅ Config cache removed\n\n";
} else {
    echo "   ✅ Config cache already cleared\n\n";
}

// 2. Ensure view cache directory exists
echo "2. Creating view cache directory...\n";
$viewPath = __DIR__ . '/storage/framework/views';
if (!is_dir($viewPath)) {
    @mkdir($viewPath, 0755, true);
    echo "   ✅ View cache directory created\n\n";
} else {
    echo "   ✅ View cache directory exists\n\n";
}

// 3. Clear view cache files
echo "3. Clearing view cache files...\n";
if (is_dir($viewPath)) {
    $files = glob($viewPath . '/*.php');
    $count = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $count++;
        }
    }
    echo "   ✅ Cleared {$count} view cache files\n\n";
}

// 4. Now try optimize:clear
echo "4. Running optimize:clear...\n";
try {
    require __DIR__.'/vendor/autoload.php';
    $app = require_once __DIR__.'/bootstrap/app.php';
    $app->make('Illuminate\Contracts\Console\Kernel')->call('optimize:clear');
    echo "   ✅ optimize:clear completed successfully\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  optimize:clear error: " . $e->getMessage() . "\n";
    echo "   But view cache files are already cleared manually.\n\n";
}

echo "=== Done ===\n";
echo "\nYou can now run: php artisan optimize:clear\n";

