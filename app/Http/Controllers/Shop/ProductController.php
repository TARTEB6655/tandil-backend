<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController;
use App\Services\CategoryShippingService;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;

class ProductController extends Controller
{
    /**
     * Get featured products for home / featured section. Active only, optional limit.
     */
    public function featured(Request $request)
    {
        try {
            $limit = min(max((int) $request->query('limit', 10), 1), 50);

            $products = Product::with(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options'])
                ->where('status', 'active')
                ->where('is_featured', true)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Featured products retrieved successfully',
                'data' => array_map(fn (Product $p) => $this->productToPublicData($p), $products->all()),
            ]);
        } catch (\Throwable $e) {
            \Log::error('ProductController::featured ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to load featured products.',
            ], 500);
        }
    }

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

            $query = Product::with(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options']);

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
                'data' => array_map(fn (Product $p) => $this->productToPublicData($p), $products->items()),
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
     * Build product data for public API. Main image once (main_image + image_url); gallery separate (gallery_images).
     * For variable products, includes option_groups (with options) and variants.
     */
    private function productToPublicData(Product $product): array
    {
        $imagesCollection = $product->relationLoaded('images') ? $product->images : collect([]);
        $primaryImage = $product->relationLoaded('primaryImage') ? $product->primaryImage : null;
        $mainImage = null;
        $galleryImages = [];
        if ($primaryImage && $primaryImage->image_path) {
            $mainImage = [
                'id' => $primaryImage->id,
                'image_path' => $primaryImage->image_path,
                'image_url' => ProductImage::buildFullUrl($primaryImage->image_path),
            ];
        }
        $uniqueImages = ProductImage::uniqueByPath($imagesCollection);
        foreach ($uniqueImages as $img) {
            if ($img->is_primary) {
                if ($mainImage === null) {
                    $mainImage = [
                        'id' => $img->id,
                        'image_path' => $img->image_path,
                        'image_url' => ProductImage::buildFullUrl($img->image_path),
                    ];
                }
            } else {
                $galleryImages[] = [
                    'id' => $img->id,
                    'image_path' => $img->image_path,
                    'image_url' => ProductImage::buildFullUrl($img->image_path),
                    'sort_order' => (int) $img->sort_order,
                ];
            }
        }
        // Keep public API image fields aligned with resolved primary/main image.
        // Prevents stale `products.image` from leaking into client responses.
        $rootImagePath = $mainImage['image_path'] ?? $product->image;

        // Variable product extras
        $optionGroups = [];
        $variants     = [];
        $productType  = $product->product_type ?? 'simple';
        if ($productType === 'variable') {
            if ($product->relationLoaded('optionGroups')) {
                foreach ($product->optionGroups as $group) {
                    $opts = [];
                    if ($group->relationLoaded('options')) {
                        foreach ($group->options as $opt) {
                            $opts[] = [
                                'id'             => $opt->id,
                                'label'          => $opt->label,
                                'subtitle'       => $opt->subtitle,
                                'price_modifier' => $opt->price_modifier,
                                'image_path'     => $opt->image_path,
                                'image_url'      => $opt->image_url,
                                'sort_order'     => $opt->sort_order,
                            ];
                        }
                    }
                    $optionGroups[] = [
                        'id'          => $group->id,
                        'name'        => $group->name,
                        'subtitle'    => $group->subtitle,
                        'input_type'  => $group->input_type,
                        'is_required' => $group->is_required,
                        'sort_order'  => $group->sort_order,
                        'options'     => $opts,
                    ];
                }
            }
            if ($product->relationLoaded('variants')) {
                foreach ($product->variants as $variant) {
                    $optIds = [];
                    if ($variant->relationLoaded('options')) {
                        $optIds = $variant->options->pluck('id')->all();
                    }
                    $variants[] = [
                        'id'         => $variant->id,
                        'sku'        => $variant->sku,
                        'price'      => $variant->price ?? $product->price,
                        'stock'      => $variant->stock,
                        'is_default' => $variant->is_default,
                        'label'      => $variant->label,
                        'option_ids' => $optIds,
                    ];
                }
            }
        }

        $estimatedShipping = $product->category_id
            ? (CategoryShippingService::shippingAmountForCategoryId((int) $product->category_id)
                ?? CartController::getEffectiveShippingAmount())
            : CartController::getEffectiveShippingAmount();

        $categoryPayload = null;
        if ($product->relationLoaded('category') && $product->category) {
            $categoryPayload = [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
                'shipping_amount' => $product->category->shipping_amount !== null
                    ? round((float) $product->category->shipping_amount, 2)
                    : null,
            ];
        }

        return [
            'id'               => $product->id,
            'name'             => $product->name,
            'description'      => $product->description,
            'product_type'     => $productType,
            'price'            => $product->price,
            'stock'            => $product->stock,
            'status'           => $product->status,
            'is_featured'      => (bool) ($product->is_featured ?? false),
            'category_id'      => $product->category_id,
            'weight_unit'      => $product->weight_unit,
            'sku'              => $product->sku,
            'handle'           => $product->handle,
            'estimated_arrival'=> $product->estimated_arrival,
            'job_duration'     => $product->job_duration,
            'image'            => $rootImagePath,
            'image_url'        => ProductImage::buildFullUrl($rootImagePath),
            'main_image'       => $mainImage,
            'gallery_images'   => $galleryImages,
            'category'         => $categoryPayload,
            'estimated_shipping' => round((float) $estimatedShipping, 2),
            'option_groups'    => $optionGroups,
            'variants'         => $variants,
            'created_at'       => $product->created_at,
            'updated_at'       => $product->updated_at,
        ];
    }

    /**
     * Show single product by ID or handle.
     * Same response shape as admin: data.main_image + data.gallery_images (no duplication).
     */
    public function show($id)
    {
        try {
            $product = Product::where('id', $id)
                ->orWhere('handle', $id)
                ->with(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options'])
                ->first();

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $data = $this->productToPublicData($product);
            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $data,
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
                ->with(['category', 'images', 'primaryImage', 'optionGroups.options', 'variants.options'])
                ->paginate(12);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'category' => $category,
                    'products' => array_map(fn (Product $p) => $this->productToPublicData($p), $products->items()),
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

