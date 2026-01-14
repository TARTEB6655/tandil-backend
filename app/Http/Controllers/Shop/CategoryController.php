<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * List all categories with product counts and featured products.
     */
    public function index(Request $request)
    {
        $categories = Category::withCount(['products' => function($query) {
            $query->where('status', 'active');
        }])
        ->with(['products' => function($query) {
            $query->where('status', 'active')
                  ->with('primaryImage')
                  ->orderBy('created_at', 'desc')
                  ->take(3); // Get 3 latest products per category
        }])
        ->orderBy('name')
        ->get();

        return response()->json([
            'status' => true,
            'message' => 'Categories retrieved successfully',
            'data' => $categories,
            'total' => $categories->count()
        ]);
    }

    /**
     * Show single category with products.
     */
    public function show(Request $request, $id)
    {
        $perPage = (int) $request->query('per_page', 12);
        $sortBy = $request->query('sort_by', 'name'); // name, price, created_at
        $sortDir = $request->query('sort_dir', 'asc'); // asc, desc
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');

        // Try to find by ID or slug
        $category = Category::where('id', $id)
            ->orWhere('slug', $id)
            ->first();

        if (!$category) {
            return response()->json([
                'status' => false,
                'message' => 'Category not found'
            ], 404);
        }

        // Build products query
        $productsQuery = $category->products()
            ->where('status', 'active')
            ->with(['category', 'images', 'primaryImage']);

        // Price filters
        if ($minPrice !== null) {
            $productsQuery->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $productsQuery->where('price', '<=', $maxPrice);
        }

        // Sorting
        if (in_array($sortBy, ['name', 'price', 'created_at'])) {
            $productsQuery->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $products = $productsQuery->paginate($perPage > 0 ? $perPage : 12);

        return response()->json([
            'status' => true,
            'message' => 'Category retrieved successfully',
            'data' => [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products_count' => $category->products()->where('status', 'active')->count(),
                ],
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ]
            ]
        ]);
    }
}

