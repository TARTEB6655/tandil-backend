<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Category;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Build service/category API response shape (matches category API: image, image_url, is_active, coming_soon).
     */
    private function serviceToArray(Category $category, array $extra = []): array
    {
        $isActive = isset($category->is_active) ? (bool) $category->is_active : true;
        $imagePath = $category->image;
        $imageUrl = $category->image_url;

        return array_merge([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $imagePath,
            'image_url' => $imageUrl,
            'icon' => $category->icon,
            'is_active' => $isActive,
            'coming_soon' => ! $isActive,
            'created_at' => $category->created_at?->format('c'),
            'updated_at' => $category->updated_at?->format('c'),
        ], $extra);
    }

    /**
     * List services (categories). Public: only active. Optional search, category_id, per_page.
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Category::query()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            });

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%' . $search . '%')
                    ->orWhere('description', 'LIKE', '%' . $search . '%');
            });
        }

        if ($categoryId) {
            $query->where('id', $categoryId);
        }

        $categories = $query->withCount(['products' => function ($q) {
            $q->where('status', 'active');
        }])
            ->with(['products' => function ($q) {
                $q->where('status', 'active')->orderBy('name')->limit(5)->select('id', 'name', 'category_id');
            }])
            ->orderBy('name')
            ->paginate($perPage);

        $data = $categories->getCollection()->map(fn (Category $c) => $this->serviceToArray($c, [
            'products_count' => $c->products_count ?? 0,
            'product_names' => $c->relationLoaded('products') ? $c->products->pluck('name')->values()->all() : [],
        ]))->values()->all();

        return ApiResponse::success('Services retrieved successfully.', [
            'data' => $data,
            'pagination' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Show single service (category). Public: only if active.
     */
    public function show($id)
    {
        $category = Category::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })
            ->withCount(['products' => function ($q) {
                $q->where('status', 'active');
            }])
            ->with(['products' => function ($q) {
                $q->where('status', 'active')->orderBy('name')->limit(5)->select('id', 'name', 'category_id');
            }])
            ->findOrFail($id);

        return ApiResponse::success('Service retrieved successfully.', $this->serviceToArray($category, [
            'products_count' => $category->products_count ?? 0,
            'product_names' => $category->relationLoaded('products') ? $category->products->pluck('name')->values()->all() : [],
        ]));
    }

    /**
     * Get all service categories (active only). Same shape as list for consistency.
     */
    public function getCategories(Request $request)
    {
        $categories = Category::withCount(['products' => function ($q) {
            $q->where('status', 'active');
        }])
            ->with(['products' => function ($q) {
                $q->where('status', 'active')->orderBy('name')->limit(5)->select('id', 'name', 'category_id');
            }])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get();

        $data = $categories->map(fn (Category $c) => $this->serviceToArray($c, [
            'products_count' => $c->products_count ?? 0,
            'product_names' => $c->relationLoaded('products') ? $c->products->pluck('name')->values()->all() : [],
        ]))->values()->all();

        return ApiResponse::success('Categories retrieved successfully.', $data);
    }

    /**
     * Get services by category: category + products (active only). Products include image_url.
     */
    public function getByCategory($id)
    {
        $category = Category::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })
            ->with(['products' => function ($q) {
                $q->where('status', 'active')->orderBy('name');
            }])
            ->findOrFail($id);

        $products = $category->products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->handle ?? \Illuminate\Support\Str::slug($product->name),
                'description' => $product->description,
                'price' => (float) $product->price,
                'image' => $product->image,
                'image_url' => $product->image_url,
                'status' => $product->status,
            ];
        })->values()->all();

        $categoryData = $this->serviceToArray($category, [
            'products_count' => $category->products->count(),
            'products' => $products,
        ]);

        return ApiResponse::success('Services retrieved successfully.', $categoryData);
    }
}
