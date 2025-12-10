<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * List products with pagination, search, sorting, and filtering.
     */
    public function index(Request $request)
    {
        $perPage  = (int) $request->query('per_page', 12);
        $search   = $request->query('search') ?? $request->query('q'); // Support both 'search' and 'q' parameters
        $category = $request->query('category_id');
        $sortBy   = $request->query('sort_by', 'created_at');  // name, price, created_at
        $sortDir  = $request->query('sort_dir', 'desc');        // asc, desc

        $query = Product::query();

        // Search
        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
        }

        // Filter by category
        if ($category) {
            $query->where('category_id', $category);
        }

        // Sorting
        if (in_array($sortBy, ['name', 'price', 'created_at'])) {
            $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $products = $query->paginate($perPage > 0 ? $perPage : 12);

        return ApiResponse::success('Products retrieved successfully.', $products);
    }

    /**
     * Show single product by ID.
     */
    public function show($id)
    {
        $product = Product::findOrFail($id);

        return ApiResponse::success('Product retrieved successfully.', $product);
    }

    /**
     * Get product categories
     */
    public function getCategories()
    {
        $categories = \App\Models\Category::all();
        return ApiResponse::success('Categories retrieved successfully.', $categories);
    }

    /**
     * Get products by category
     */
    public function getByCategory($categoryId)
    {
        $category = \App\Models\Category::with('products')->findOrFail($categoryId);
        return ApiResponse::success('Products retrieved successfully.', $category->products);
    }
}

