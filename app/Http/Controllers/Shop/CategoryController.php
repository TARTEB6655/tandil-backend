<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Same category data shape as admin API: id, name, slug, description, image, image_url (+ optional extras).
     */
    private function categoryToApiData(Category $category, array $extra = []): array
    {
        return array_merge([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'image_url' => $category->image_url,
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ], $extra);
    }

    /**
     * List all categories – same response shape as admin (easy to read).
     */
    public function index(Request $request)
    {
        $categories = Category::withCount(['products' => function ($query) {
            $query->where('status', 'active');
        }])
            ->with(['products' => function ($query) {
                $query->where('status', 'active')
                    ->with('primaryImage')
                    ->orderBy('created_at', 'desc')
                    ->take(3);
            }])
            ->orderBy('name')
            ->get();

        $data = $categories->map(fn (Category $c) => $this->categoryToApiData($c, [
            'products_count' => $c->products_count ?? 0,
            'products' => $c->relationLoaded('products') ? $c->products->toArray() : [],
        ]))->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Show single category with products – same category shape as admin (easy to read).
     */
    public function show(Request $request, $id)
    {
        $perPage = (int) $request->query('per_page', 12);
        $sortBy = $request->query('sort_by', 'name');
        $sortDir = $request->query('sort_dir', 'asc');
        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');

        $category = Category::where('id', $id)->orWhere('slug', $id)->first();

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $productsQuery = $category->products()
            ->where('status', 'active')
            ->with(['category', 'images', 'primaryImage']);

        if ($minPrice !== null) {
            $productsQuery->where('price', '>=', $minPrice);
        }
        if ($maxPrice !== null) {
            $productsQuery->where('price', '<=', $maxPrice);
        }
        if (in_array($sortBy, ['name', 'price', 'created_at'])) {
            $productsQuery->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $products = $productsQuery->paginate($perPage > 0 ? $perPage : 12);

        $categoryData = $this->categoryToApiData($category, [
            'products_count' => $category->products()->where('status', 'active')->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully.',
            'data' => [
                'category' => $categoryData,
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
            ],
        ]);
    }
}

