<?php

/**
 * Test category API response
 * Run: php test_category_api.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;

echo "=== Testing Category API Response ===\n\n";

$categories = Category::all();

if ($categories->count() === 0) {
    echo "No categories found.\n";
    exit(0);
}

echo "Found {$categories->count()} categories:\n\n";

foreach ($categories as $category) {
    echo "ID: {$category->id}\n";
    echo "  Name: {$category->name}\n";
    echo "  Slug (DB): {$category->slug}\n";
    echo "  Expected Slug: " . \Illuminate\Support\Str::slug($category->name) . "\n";
    
    // Check if slug matches expected
    $expectedSlug = \Illuminate\Support\Str::slug($category->name);
    if ($category->slug !== $expectedSlug) {
        echo "  ⚠️  WARNING: Slug doesn't match expected format!\n";
        echo "     DB has: '{$category->slug}'\n";
        echo "     Expected: '{$expectedSlug}'\n";
    } else {
        echo "  ✅ Slug is correct\n";
    }
    echo "\n";
}

echo "=== Test API Response Format ===\n";
$firstCategory = $categories->first();
echo "Category ID {$firstCategory->id} would return:\n";
echo json_encode([
    'status' => true,
    'message' => 'Category retrieved successfully.',
    'data' => [
        'id' => $firstCategory->id,
        'name' => $firstCategory->name,
        'slug' => $firstCategory->slug,
        'description' => $firstCategory->description,
    ]
], JSON_PRETTY_PRINT) . "\n";

