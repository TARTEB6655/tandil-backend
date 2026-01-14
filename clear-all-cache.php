<?php

/**
 * Clear All Cache Script
 * This script clears all Laravel caches without requiring database connection
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

echo "=== Clearing All Laravel Caches ===\n\n";

try {
    // Clear config cache
    echo "1. Clearing config cache...\n";
    $app->make('Illuminate\Contracts\Console\Kernel')->call('config:clear');
    echo "   ✅ Config cache cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Config cache: " . $e->getMessage() . "\n\n";
}

try {
    // Clear route cache
    echo "2. Clearing route cache...\n";
    $app->make('Illuminate\Contracts\Console\Kernel')->call('route:clear');
    echo "   ✅ Route cache cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Route cache: " . $e->getMessage() . "\n\n";
}

try {
    // Clear view cache
    echo "3. Clearing view cache...\n";
    $app->make('Illuminate\Contracts\Console\Kernel')->call('view:clear');
    echo "   ✅ View cache cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  View cache: " . $e->getMessage() . "\n\n";
}

try {
    // Clear event cache
    echo "4. Clearing event cache...\n";
    $app->make('Illuminate\Contracts\Console\Kernel')->call('event:clear');
    echo "   ✅ Event cache cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Event cache: " . $e->getMessage() . "\n\n";
}

try {
    // Clear compiled classes
    echo "5. Clearing compiled classes...\n";
    $app->make('Illuminate\Contracts\Console\Kernel')->call('clear-compiled');
    echo "   ✅ Compiled classes cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Compiled classes: " . $e->getMessage() . "\n\n";
}

// Clear application cache (using file system, not database)
echo "6. Clearing application cache (file-based)...\n";
try {
    $cachePath = storage_path('framework/cache');
    if (is_dir($cachePath)) {
        $files = glob($cachePath . '/*');
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== '.gitignore') {
                @unlink($file);
            }
        }
        echo "   ✅ Application cache files cleared\n\n";
    }
} catch (\Exception $e) {
    echo "   ⚠️  Application cache: " . $e->getMessage() . "\n\n";
}

// Clear bootstrap cache
echo "7. Clearing bootstrap cache...\n";
try {
    $bootstrapCache = [
        bootstrap_path('cache/config.php'),
        bootstrap_path('cache/routes.php'),
        bootstrap_path('cache/services.php'),
        bootstrap_path('cache/packages.php'),
    ];
    
    foreach ($bootstrapCache as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
    echo "   ✅ Bootstrap cache cleared\n\n";
} catch (\Exception $e) {
    echo "   ⚠️  Bootstrap cache: " . $e->getMessage() . "\n\n";
}

echo "=== Cache Clearing Complete ===\n";
echo "\nNote: If you see database errors above, update your .env file with correct MySQL credentials.\n";
echo "Then run: php artisan optimize:clear\n\n";

