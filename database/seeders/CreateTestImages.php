<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CreateTestImages extends Seeder
{
    /**
     * Create simple test images for all products without images.
     */
    public function run()
    {
        $this->command->info('🖼️ Creating test images for products...');

        $products = Product::whereNull('image')
            ->orWhere('image', '')
            ->orWhere('image', 'like', 'products/placeholder%')
            ->get();

        if ($products->isEmpty()) {
            $products = Product::all();
        }

        $colors = [
            ['r' => 74, 'g' => 222, 'b' => 128],  // Green
            ['r' => 59, 'g' => 130, 'b' => 246],  // Blue
            ['r' => 168, 'g' => 85, 'b' => 247],  // Purple
            ['r' => 236, 'g' => 72, 'b' => 153],  // Pink
            ['r' => 251, 'g' => 146, 'b' => 60],  // Orange
            ['r' => 16, 'g' => 185, 'b' => 129],  // Teal
            ['r' => 99, 'g' => 102, 'b' => 241],  // Indigo
        ];

        $created = 0;
        foreach ($products as $product) {
            $colorIndex = crc32($product->name) % count($colors);
            $color = $colors[$colorIndex];
            
            // Create SVG as image (works everywhere)
            $svg = $this->createSVGImage($product->name, $color);
            
            // Save as file
            if (!Storage::disk('public')->exists('products')) {
                Storage::disk('public')->makeDirectory('products');
            }

            $imagePath = 'products/test-' . Str::slug($product->name) . '-' . $product->id . '.svg';
            Storage::disk('public')->put($imagePath, $svg);
            
            // Create ProductImage record
            ProductImage::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'is_primary' => true
                ],
                [
                    'image_path' => $imagePath,
                    'sort_order' => 0,
                ]
            );

            // Update product main image
            $product->update(['image' => $imagePath]);
            $created++;
        }

        $this->command->info("✅ Created {$created} test images!");
    }

    private function createSVGImage($productName, $color)
    {
        $text = substr(preg_replace('/[^a-zA-Z0-9\s]/', '', $productName), 0, 20);
        $bgColor = sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
        
        return sprintf('<?xml version="1.0" encoding="UTF-8"?>
<svg width="800" height="800" xmlns="http://www.w3.org/2000/svg">
  <rect width="800" height="800" fill="%s"/>
  <text x="400" y="400" font-family="Arial, sans-serif" font-size="32" font-weight="bold" fill="white" text-anchor="middle" dominant-baseline="middle">%s</text>
</svg>', $bgColor, htmlspecialchars($text));
    }
}

