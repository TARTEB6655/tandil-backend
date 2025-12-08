<?php

/**
 * Test actual category API response format
 * Run: php test_category_response.php [category_id]
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Category;
use App\Helpers\ApiResponse;

$categoryId = $argv[1] ?? 1;

echo "=== Testing Category API Response ===\n\n";

$category = Category::find($categoryId);

if (!$category) {
    echo "❌ Category with ID {$categoryId} not found.\n";
    exit(1);
}

echo "Category ID: {$category->id}\n";
echo "Name: {$category->name}\n";
echo "Slug (from DB): '{$category->slug}'\n";
echo "Description: " . ($category->description ?? 'N/A') . "\n\n";

// Simulate what the API returns
$response = ApiResponse::success('Category retrieved successfully.', $category);
$responseData = json_decode($response->getContent(), true);

echo "=== API Response (what Postman would see) ===\n";
echo json_encode($responseData, JSON_PRETTY_PRINT) . "\n\n";

// Check the slug in the response
if (isset($responseData['data']['slug'])) {
    $apiSlug = $responseData['data']['slug'];
    $dbSlug = $category->slug;
    
    echo "=== Slug Comparison ===\n";
    echo "Database slug: '{$dbSlug}'\n";
    echo "API response slug: '{$apiSlug}'\n";
    
    if ($apiSlug === $dbSlug) {
        echo "✅ Slugs match!\n";
    } else {
        echo "❌ Slugs DON'T match!\n";
        echo "   Difference detected!\n";
    }
    
    // Check if slug format is correct
    $expectedSlug = \Illuminate\Support\Str::slug($category->name);
    echo "\nExpected slug from name: '{$expectedSlug}'\n";
    
    if ($apiSlug === $expectedSlug) {
        echo "✅ Slug format is correct!\n";
    } else {
        echo "⚠️  Slug format doesn't match expected!\n";
        echo "   This might be intentional if slug was manually set.\n";
    }
}

echo "\n=== All Categories ===\n";
$allCategories = Category::all();
foreach ($allCategories as $cat) {
    $expected = \Illuminate\Support\Str::slug($cat->name);
    $match = $cat->slug === $expected ? '✅' : '⚠️';
    echo "{$match} ID {$cat->id}: '{$cat->name}' → slug: '{$cat->slug}' (expected: '{$expected}')\n";
}

