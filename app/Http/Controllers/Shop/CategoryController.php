<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Build stable product image payload (root image + image_url follow primary image).
     */
    private function productToCategoryApiData(Product $product): array
    {
        $imagesCollection = $product->relationLoaded('images') ? $product->images : collect([]);
        $primaryImage = $product->relationLoaded('primaryImage') ? $product->primaryImage : null;
        $mainImagePath = $primaryImage?->image_path;

        if (! $mainImagePath) {
            foreach (ProductImage::uniqueByPath($imagesCollection) as $img) {
                if ($img->is_primary && $img->image_path) {
                    $mainImagePath = $img->image_path;
                    break;
                }
            }
        }

        $rootImagePath = $mainImagePath ?? $product->image;
        $arr = $product->toArray();
        $arr['image'] = $rootImagePath;
        $arr['image_url'] = ProductImage::buildFullUrl($rootImagePath);

        return $arr;
    }

    /**
     * Same category data shape as admin API: id, name, slug, description, image, image_url (+ optional extras).
     */
    private function categoryToApiData(Category $category, array $extra = []): array
    {
        $isActive = isset($category->is_active) ? (bool) $category->is_active : true;
        return array_merge([
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image' => $category->image,
            'image_url' => $category->image_url,
            'is_active' => $isActive,
            'coming_soon' => ! $isActive,
            'sort_order' => (int) ($category->sort_order ?? 0),
            'created_at' => $category->created_at,
            'updated_at' => $category->updated_at,
        ], $extra);
    }

    /**
     * List all categories – same response shape as admin (easy to read).
     */
    public function index(Request $request)
    {
        $categories = Category::platformCatalog()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->withCount(['products' => function ($query) {
                $query->visibleInClientShop();
            }])
            ->with(['products' => function ($query) {
                $query->visibleInClientShop()
                    ->with(['images', 'primaryImage'])
                    ->orderBy('created_at', 'desc')
                    ->take(3);
            }])
            ->ordered()
            ->get();

        $data = $categories->map(fn (Category $c) => $this->categoryToApiData($c, [
            'products_count' => $c->products_count ?? 0,
            'products' => $c->relationLoaded('products')
                ? $c->products->map(fn (Product $p) => $this->productToCategoryApiData($p))->values()->all()
                : [],
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

        $category = Category::platformCatalog()
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('slug', $id);
            })
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->first();

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $productsQuery = $category->products()
            ->visibleInClientShop()
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
            'products_count' => $category->products()->visibleInClientShop()->count(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully.',
            'data' => [
                'category' => $categoryData,
                'products' => array_map(fn (Product $p) => $this->productToCategoryApiData($p), $products->items()),
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

