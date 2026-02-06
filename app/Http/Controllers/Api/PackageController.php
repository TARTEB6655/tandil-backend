<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Package;

class PackageController extends Controller
{
    /**
     * List active packages for customer home page.
     * Combined, Fruit Basket, Vegetable Basket.
     */
    public function index()
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'type' => $p->type,
                'price' => (float) $p->price,
                'image' => $p->image,
                'image_url' => $p->image_url,
                'description' => $p->description,
            ]);

        return ApiResponse::success('Packages retrieved successfully.', $packages);
    }
}
