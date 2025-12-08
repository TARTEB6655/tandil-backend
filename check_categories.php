<?php

/**
 * Check existing categories
 * Run: php check_categories.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;

echo "=== Existing Categories ===\n\n";

$categories = Category::orderBy('name')->get();

if ($categories->count() === 0) {
    echo "No categories found in database.\n";
    exit(0);
}

echo "Found {$categories->count()} category(ies):\n\n";

foreach ($categories as $category) {
    echo "ID: {$category->id}\n";
    echo "  Name: {$category->name}\n";
    echo "  Slug: {$category->slug}\n";
    echo "  Description: " . ($category->description ?? 'N/A') . "\n";
    echo "  Created: {$category->created_at}\n";
    echo "\n";
}

echo "=== How to Create a New Category ===\n";
echo "Use a unique slug that doesn't exist above.\n";
echo "Example: If 'new-category' exists, try 'new-category-2' or 'another-category'\n";

