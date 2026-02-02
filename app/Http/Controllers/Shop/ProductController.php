<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * List products with pagination, search, sorting, and filtering.
     */
    public function index(Request $request)
    {
        try {
            $perPage  = (int) $request->query('per_page', 12);
            $search   = $request->query('search');
            $category = $request->query('category_id');
            $status   = $request->query('status', 'active'); // Filter by status
            $minPrice = $request->query('min_price');
            $maxPrice = $request->query('max_price');
            $sortBy   = $request->query('sort_by', 'created_at');  // name, price, created_at
            $sortDir  = $request->query('sort_dir', 'desc');        // asc, desc
            $inStock  = $request->query('in_stock'); // Filter by stock availability

            $query = Product::with(['category', 'images', 'primaryImage']);

            // Search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%")
                        ->orWhere('tags', 'LIKE', "%{$search}%");
                });
            }

            // Filter by category
            if ($category) {
                $query->where('category_id', $category);
            }

            // Filter by status
            if ($status) {
                $query->where('status', $status);
            }

            // Filter by price range
            if ($minPrice !== null) {
                $query->where('price', '>=', $minPrice);
            }
            if ($maxPrice !== null) {
                $query->where('price', '<=', $maxPrice);
            }

            // Filter by stock availability
            if ($inStock !== null) {
                if ($inStock == '1' || $inStock === 'true') {
                    $query->where('stock', '>', 0);
                } elseif ($inStock == '0' || $inStock === 'false') {
                    $query->where('stock', '<=', 0);
                }
            }

            // Sorting
            if (in_array($sortBy, ['name', 'price', 'created_at', 'stock'])) {
                $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
            }

            $products = $query->paginate($perPage > 0 ? $perPage : 12);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('ProductController::index ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load products.',
            ], 500);
        }
    }

    /**
     * Show single product by ID or handle.
     */
    public function show($id)
    {
        try {
            $product = Product::where('id', $id)
                ->orWhere('handle', $id)
                ->with(['category', 'images', 'primaryImage'])
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product,
            ]);
        } catch (\Throwable $e) {
            \Log::error('ProductController::show ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load product.',
            ], 500);
        }
    }

    /**
     * Get all categories.
     */
    public function getCategories()
    {
        try {
            $categories = \App\Models\Category::withCount(['products' => function ($query) {
                $query->where('status', 'active');
            }])
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $categories,
            ]);
        } catch (\Throwable $e) {
            \Log::error('ProductController::getCategories ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => [],
            ]);
        }
    }

    /**
     * Get products by category.
     */
    public function getByCategory($id)
    {
        try {
            $category = \App\Models\Category::find($id);

            if (! $category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found',
                ], 404);
            }

            $products = Product::where('category_id', $category->id)
                ->where('status', 'active')
                ->with(['category', 'images', 'primaryImage'])
                ->paginate(12);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'category' => $category,
                    'products' => $products->items(),
                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('ProductController::getByCategory ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load products for this category.',
            ], 500);
        }
    }
}

