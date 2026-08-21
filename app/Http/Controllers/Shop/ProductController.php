<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Shop\CartController;
use App\Enums\VendorStatus;
use App\Services\CategoryShippingService;
use App\Services\Vendor\VendorComparisonService;
use App\Services\JobSchedulingService;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductImage;
use Carbon\Carbon;

class ProductController extends Controller
{
    public function __construct(
        private readonly VendorComparisonService $vendorComparison
    ) {}

    /**
     * Get featured products for home / featured section. Active only, optional limit.
     */
    public function featured(Request $request)
    {
        try {
            $limit = min(max((int) $request->query('limit', 10), 1), 50);

            $products = Product::with([
                'category',
                'images',
                'primaryImage',
                'optionGroups.options',
                'variants.options'
            ])
                ->visibleInClientShop()
                ->where('is_featured', true)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Featured products retrieved successfully',
                'data' => array_map(
                    fn (Product $p) => $this->productToPublicData($p),
                    $products->all()
                ),
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
            $minPrice = $request->query('min_price');
            $maxPrice = $request->query('max_price');
            $sortBy   = $request->query('sort_by', 'created_at');
            $sortDir  = $request->query('sort_dir', 'desc');
            $inStock  = $request->query('in_stock');

            $query = Product::with([
                'category',
                'images',
                'primaryImage',
                'optionGroups.options',
                'variants.options'
            ])
                ->visibleInClientShop();

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
            if ($sortBy === 'sort_order') {
                $query->ordered();
            } elseif (in_array($sortBy, ['name', 'price', 'created_at', 'stock'])) {
                $query->orderBy(
                    $sortBy,
                    $sortDir === 'asc' ? 'asc' : 'desc'
                );
            } else {
                $query->ordered();
            }

            $products = $query->paginate($perPage > 0 ? $perPage : 12);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => array_map(
                    fn (Product $p) => $this->productToPublicData($p),
                    $products->items()
                ),
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
     * Build product data for public API.
     */
    private function productToPublicData(Product $product): array
    {
        $imagesCollection = $product->relationLoaded('images')
            ? $product->images
            : collect([]);

        $primaryImage = $product->relationLoaded('primaryImage')
            ? $product->primaryImage
            : null;

        $mainImage = null;
        $galleryImages = [];

        if ($primaryImage && $primaryImage->image_path) {
            $mainImage = [
                'id' => $primaryImage->id,
                'image_path' => $primaryImage->image_path,
                'image_url' => ProductImage::buildFullUrl(
                    $primaryImage->image_path
                ),
            ];
        }

        $uniqueImages = ProductImage::uniqueByPath($imagesCollection);

        foreach ($uniqueImages as $img) {
            if ($img->is_primary) {
                if ($mainImage === null) {
                    $mainImage = [
                        'id' => $img->id,
                        'image_path' => $img->image_path,
                        'image_url' => ProductImage::buildFullUrl(
                            $img->image_path
                        ),
                    ];
                }
            } else {
                $galleryImages[] = [
                    'id' => $img->id,
                    'image_path' => $img->image_path,
                    'image_url' => ProductImage::buildFullUrl(
                        $img->image_path
                    ),
                    'sort_order' => (int) $img->sort_order,
                ];
            }
        }

        // Keep public API image fields aligned with resolved primary/main image.
        $rootImagePath = $mainImage['image_path'] ?? $product->image;

        $imagesList = [];

        if ($mainImage !== null) {
            $imagesList[] = $mainImage;
        }

        foreach ($galleryImages as $galleryImage) {
            $imagesList[] = $galleryImage;
        }

        $optionGroups = [];
        $variants = [];
        $productType = $product->product_type ?? 'simple';

        if ($product->relationLoaded('optionGroups')) {
            $optionGroups = $product->optionGroups
                ->sortBy('sort_order')
                ->values()
                ->map(
                    fn (\App\Models\ProductOptionGroup $group) =>
                        $group->toApiArray()
                )
                ->all();
        }

        if ($product->relationLoaded('variants')) {
            foreach ($product->variants as $variant) {
                $optIds = [];

                if ($variant->relationLoaded('options')) {
                    $optIds = $variant->options->pluck('id')->all();
                }

                $variants[] = [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price ?? $product->price,
                    'stock' => $variant->stock,
                    'is_default' => $variant->is_default,
                    'label' => $variant->label,
                    'option_ids' => $optIds,
                ];
            }
        }

        $estimatedShipping = $product->category_id
            ? (
                CategoryShippingService::shippingAmountForCategoryId(
                    (int) $product->category_id
                )
                ?? CartController::getEffectiveShippingAmount()
            )
            : CartController::getEffectiveShippingAmount();

        $categoryPayload = null;

        if (
            $product->relationLoaded('category')
            && $product->category
        ) {
            $categoryPayload = array_merge([
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
                'estimated_shipping' => round(
                    (float) $estimatedShipping,
                    2
                ),
            ], $product->category->shippingTaxConfigForApi());
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'product_type' => $productType,
            'price' => $product->price,
            'stock' => $product->stock,
            'status' => $product->status,
            'is_featured' => (bool) ($product->is_featured ?? false),
            'sort_order' => (int) $product->sort_order,
            'category_id' => $product->category_id,
            'weight_unit' => $product->weight_unit,
            'sku' => $product->sku,
            'handle' => $product->handle,
            'estimated_arrival' => $product->estimated_arrival,
            'job_duration' => $product->job_duration,

            'image' => $rootImagePath,
            'image_url' => ProductImage::buildFullUrl($rootImagePath),

            'main_image' => $mainImage,
            'gallery_images' => $galleryImages,
            'images' => $imagesList,

            'category' => $categoryPayload,

            'estimated_shipping' => round(
                (float) $estimatedShipping,
                2
            ),

            'shipping_cost' => $product->category?->shipping_cost !== null
                ? round(
                    (float) $product->category->shipping_cost,
                    2
                )
                : null,

            'tax_percentage' => $product->category !== null
                ? $product->category->effectiveTaxPercentage()
                : CartController::getEffectiveTaxPercent(),

            'option_groups' => $optionGroups,
            'variants' => $variants,

            'created_at' => $product->created_at,
            'updated_at' => $product->updated_at,
        ];
    }

    /**
     * Build booking/date/time availability for the client product page.
     *
     * Uses the existing JobSchedulingService so that working days,
     * working hours, blocked dates, blocked slots, capacity and
     * existing bookings all use the same rules as the rest of the system.
     */
    private function getBookingAvailability(?string $selectedDate = null, ?int $productId = null): array
    {
        /*
         * Dates/times come from global admin Job Scheduling (folder K).
         * Capacity (booked_count / remaining / available) is per product.
         */
        $daysToShow = 7;
        $today = Carbon::today();
        $dates = [];

        for ($i = 0; $i < $daysToShow; $i++) {
            $date = $today->copy()->addDays($i);
            $dateString = $date->format('Y-m-d');

            $slots = JobSchedulingService::availableSlots($dateString, $productId);
            $hasBookableSlot = collect($slots)->contains(
                fn ($slot) => ! empty($slot['available'])
            );

            $dates[] = [
                'date' => $dateString,
                'day' => $date->format('D'),
                'day_number' => (int) $date->format('d'),
                'month' => $date->format('M'),
                'available' => $hasBookableSlot,
            ];
        }

        if ($selectedDate) {
            $dateToUse = Carbon::parse($selectedDate)->format('Y-m-d');
        } else {
            $firstAvailable = collect($dates)
                ->first(
                    fn ($date) => $date['available'] === true
                );

            $dateToUse = $firstAvailable['date'] ?? ($dates[0]['date'] ?? null);
        }

        $slots = $dateToUse
            ? JobSchedulingService::availableSlots($dateToUse, $productId)
            : [];

        $formattedSlots = array_map(function ($slot) {
            return [
                'id' => $slot['id'],
                'time' => Carbon::parse(
                    $slot['start_time']
                )->format('h:i A'),
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'duration_minutes' => $slot['duration_minutes'],
                'booked_count' => $slot['booked_count'],
                'remaining' => $slot['remaining'],
                'max_bookings' => $slot['max_bookings'] ?? null,
                'blocked' => (bool) ($slot['blocked'] ?? false),
                'available' => (bool) $slot['available'],
            ];
        }, $slots);

        $settings = JobSchedulingService::settings();

        return [
            'enabled' => true,
            'product_id' => $productId,
            'slot_source' => 'global',
            'capacity_scope' => 'product',
            'date_selection_required' => true,
            'time_selection_required' => true,
            // Live from admin GET/PUT /api/admin/job-scheduling/working-hours
            'max_bookings_per_slot' => (int) $settings->max_bookings_per_slot,
            'max_bookings_per_day' => (int) $settings->max_bookings_per_day,
            'buffer_minutes' => (int) $settings->buffer_minutes,
            'dates' => $dates,
            'selected_date' => $dateToUse,
            'slots' => $formattedSlots,
        ];
    }

    /**
     * Show single product by ID or handle.
     *
     * Supports:
     *
     * GET /api/shop/products/{id}
     *
     * GET /api/shop/products/{id}?date=2026-08-13
     */
    public function show(Request $request, $id)
    {
        try {
            /*
             * Optional date parameter.
             *
             * Example:
             * ?date=2026-08-13
             */
            $request->validate([
                'date' => [
                    'nullable',
                    'date_format:Y-m-d',
                    'after_or_equal:today',
                ],
            ]);

            $product = Product::query()
                ->visibleInClientShop()
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                        ->orWhere('handle', $id);
                })
                ->with([
                    'category',
                    'images',
                    'primaryImage',
                    'optionGroups.options',
                    'variants.options'
                ])
                ->first();

            if (! $product) {
                return $this->shopProductNotFoundResponse($id);
            }

            /*
             * Existing product response.
             */
            $data = $this->productToPublicData($product);

            /*
             * NEW:
             * Add date/time availability.
             */
            $data['booking'] = $this->getBookingAvailability(
                $request->query('date'),
                (int) $product->id
            );

            /*
             * Existing vendor comparison.
             */
            $data['compare_vendors'] =
                $this->vendorComparison->availabilityForProduct($product);

            return response()->json([
                'success' => true,
                'message' => 'Product retrieved successfully',
                'data' => $data,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error(
                'ProductController::show ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to load product.',
            ], 500);
        }
    }

    /**
     * Compare vendors offering live products in the same category as this product.
     */
    public function compareVendors(Request $request, $id)
    {
        try {
            $product = Product::query()
                ->visibleInClientShop()
                ->where(function ($q) use ($id) {
                    $q->where('id', $id)
                        ->orWhere('handle', $id);
                })
                ->first();

            if (! $product) {
                return $this->shopProductNotFoundResponse($id);
            }

            $validated = $request->validate([
                'sort_by' => 'sometimes|in:price,rating,delivery',
            ]);

            $data = $this->vendorComparison->compareByProduct(
                (int) $product->id,
                $validated['sort_by'] ?? 'price'
            );

            return response()->json([
                'success' => true,
                'message' => 'Vendor comparison retrieved successfully',
                'data' => $data,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error(
                'ProductController::compareVendors ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' => 'Unable to load vendor comparison.',
            ], 500);
        }
    }

    /**
     * Get all categories.
     */
    public function getCategories()
    {
        try {
            $categories = \App\Models\Category::platformCatalog()
                ->where(function ($q) {
                    $q->where('is_active', true)
                        ->orWhereNull('is_active');
                })
                ->withCount([
                    'products' => function ($query) {
                        $query->visibleInClientShop();
                    }
                ])
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Categories retrieved successfully',
                'data' => $categories,
            ]);
        } catch (\Throwable $e) {
            \Log::error(
                'ProductController::getCategories ' . $e->getMessage()
            );

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

            $products = Product::where(
                'category_id',
                $category->id
            )
                ->visibleInClientShop()
                ->with([
                    'category',
                    'images',
                    'primaryImage',
                    'optionGroups.options',
                    'variants.options'
                ])
                ->ordered()
                ->paginate(12);

            return response()->json([
                'success' => true,
                'message' => 'Products retrieved successfully',
                'data' => [
                    'category' => $category,

                    'products' => array_map(
                        fn (Product $p) =>
                            $this->productToPublicData($p),
                        $products->items()
                    ),

                    'pagination' => [
                        'current_page' => $products->currentPage(),
                        'last_page' => $products->lastPage(),
                        'per_page' => $products->perPage(),
                        'total' => $products->total(),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error(
                'ProductController::getByCategory ' . $e->getMessage()
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to load products for this category.',
            ], 500);
        }
    }

    /**
     * @param int|string $id
     */
    private function shopProductNotFoundResponse($id)
    {
        $product = Product::query()
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('handle', $id);
            })
            ->with(['vendorProduct.vendor'])
            ->first();

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
                'hint' =>
                    'Wrong product_id. On server run: php artisan vendor:compare-demo-status',
            ], 404);
        }

        $reasons = [];

        if ($product->status !== 'active') {
            $reasons[] =
                'product status is ' . $product->status;
        }

        $vendorProduct = $product->vendorProduct;

        if ($vendorProduct) {
            if ($vendorProduct->status !== 'active') {
                $reasons[] =
                    'vendor listing status is ' .
                    $vendorProduct->status;
            }

            if ($vendorProduct->approval_status !== 'approved') {
                $reasons[] =
                    'vendor listing approval is ' .
                    $vendorProduct->approval_status;
            }

            if ($vendorProduct->disabled_by_admin) {
                $reasons[] =
                    'product disabled by admin';
            }

            if (
                $vendorProduct->vendor
                && $vendorProduct->vendor->status
                    !== VendorStatus::Approved->value
            ) {
                $reasons[] =
                    'vendor status is ' .
                    $vendorProduct->vendor->status;
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Product not available in shop',
            'product_id' => $product->id,
            'reasons' => $reasons !== []
                ? $reasons
                : [
                    'product does not meet marketplace visibility rules'
                ],
            'hint' =>
                'Approve vendor and set product active. Run: php artisan vendor:compare-demo-status',
        ], 404);
    }
}