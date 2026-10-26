<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Public Services API. Services are a separate table; optional category_id for grouping.
 */
class ServiceController extends Controller
{
    /** List/card thumbnail width (matches shop product grids; served via /media/…?w=). */
    private const LIST_THUMB_WIDTH = 384;

    /**
     * Return product payload with canonical image path/url from primary image.
     *
     * @param  bool  $forList  Lighter JSON + thumb URLs for grids (default for list endpoints).
     */
    private function productToApiData(Product $product, bool $forList = true): array
    {
        $rootImagePath = $product->primaryImage?->image_path ?? $product->image;
        $thumbUrl = ProductImage::buildThumbUrl($rootImagePath, self::LIST_THUMB_WIDTH)
            ?? ProductImage::buildFullUrl($rootImagePath);
        $fullUrl = ProductImage::buildFullUrl($rootImagePath);

        $pricing = $forList
            ? \App\Support\ServiceAreaPricing::productApiFieldsForList($product)
            : \App\Support\ServiceAreaPricing::productApiFields($product);

        $payload = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->handle ?? Str::slug($product->name),
            'type' => $product->type ?? 'product',
            ...$pricing,
            'image' => $rootImagePath,
            'image_url' => $forList ? $thumbUrl : ($fullUrl ?? $thumbUrl),
            'status' => $product->status,
        ];

        if ($forList) {
            $payload['image_thumb_url'] = $thumbUrl;
            $payload['image_full_url'] = $fullUrl;
            $desc = $product->description;
            $payload['description'] = is_string($desc) && $desc !== ''
                ? Str::limit(strip_tags($desc), 200)
                : $desc;
        } else {
            $payload['description'] = $product->description;
            if ($fullUrl !== null) {
                $payload['image_full_url'] = $fullUrl;
            }
        }

        return $payload;
    }

    private function serviceToArray(Service $service, array $extra = []): array
    {
        return array_merge([
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'description' => $service->description,
            'image' => $service->image,
            'image_url' => $service->image_url,
            'icon' => $service->icon,
            'is_active' => (bool) $service->is_active,
            'coming_soon' => (bool) $service->coming_soon,
            'category_id' => $service->category_id,
            'created_at' => $service->created_at?->format('c'),
            'updated_at' => $service->updated_at?->format('c'),
        ], $extra);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Service>  $services
     * @return array<int, list<string>>
     */
    private function sampleProductNamesByServiceId($services): array
    {
        if ($services->isEmpty()) {
            return [];
        }

        $serviceIds = $services->pluck('id')->all();

        $rows = DB::table('product_service')
            ->join('products', 'products.id', '=', 'product_service.product_id')
            ->whereIn('product_service.service_id', $serviceIds)
            ->where('products.status', 'active')
            ->orderBy('products.name')
            ->select('product_service.service_id', 'products.name')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $sid = (int) $row->service_id;
            if (! isset($map[$sid])) {
                $map[$sid] = [];
            }
            if (count($map[$sid]) < 5) {
                $map[$sid][] = $row->name;
            }
        }

        return $map;
    }

    private function listCacheResponse(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    /**
     * List services. Optional: category_id, search, per_page. Returns all (inactive show "Coming Soon").
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 12), 1), 100);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Service::listedInCatalog()->withCount('products');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('description', 'LIKE', '%'.$search.'%');
            });
        }
        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $services = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
        $nameMap = $this->sampleProductNamesByServiceId($services->getCollection());

        $data = $services->getCollection()->map(fn (Service $s) => $this->serviceToArray($s, [
            'products_count' => $s->products_count ?? 0,
            'product_names' => $nameMap[$s->id] ?? [],
        ]))->values()->all();

        return $this->listCacheResponse(ApiResponse::success('Services retrieved successfully.', [
            'data' => $data,
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ],
        ]));
    }

    /**
     * All products of service(s) – for Services screen (cards with search & category filter).
     * GET /api/services/products?service_id=1&category_id=1&search=water&per_page=20
     * Optional view=detail for full pricing + description (heavier; use on product detail only).
     */
    public function allProductsOfService(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 100);
        $serviceId = $request->query('service_id');
        $categoryId = $request->query('category_id');
        $search = $request->query('search');
        $forList = strtolower((string) $request->query('view', '')) !== 'detail';

        $query = Product::query()
            ->visibleInClientShop()
            ->whereHas('services');

        if ($serviceId) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
        }
        if ($categoryId) {
            $query->whereHas('services', fn ($q) => $q->where('services.category_id', $categoryId));
        }
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', '%'.$search.'%')
                    ->orWhere('description', 'LIKE', '%'.$search.'%');
            });
        }

        $products = $query->with([
            'primaryImage',
            'category:id,name',
            'services:id,name,pricing_type,price,price_includes',
        ])
            ->orderBy('name')
            ->paginate($perPage);

        $data = $products->getCollection()->map(function (Product $product) use ($forList) {
            $serviceNames = $product->relationLoaded('services')
                ? $product->services->pluck('name')->values()->all()
                : [];
            $productData = $this->productToApiData($product, $forList);

            return array_merge($productData, [
                'category' => $product->relationLoaded('category') && $product->category
                    ? ['id' => $product->category->id, 'name' => $product->category->name]
                    : null,
                'service_names' => $serviceNames,
                'rating' => null,
            ]);
        })->values()->all();

        return $this->listCacheResponse(ApiResponse::success('Products of services retrieved successfully.', [
            'data' => $data,
            'total' => $products->total(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
            ],
        ]));
    }

    /**
     * Get products linked to a service (service ke hisab se products). Public, no auth.
     * GET /api/services/{id}/products
     */
    public function productsByService(Request $request, $id)
    {
        $forList = strtolower((string) $request->query('view', '')) !== 'detail';
        $service = Service::findOrFail($id);
        $products = $service->products()
            ->visibleInClientShop()
            ->with('primaryImage')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->productToApiData($product, $forList))
            ->values()
            ->all();

        return $this->listCacheResponse(ApiResponse::success('Products for service retrieved successfully.', [
            'service_id' => (int) $id,
            'service_name' => $service->name,
            'products' => $products,
        ]));
    }

    /**
     * Show single service (with products linked via pivot).
     */
    public function show($id)
    {
        $service = Service::withCount('products')
            ->findOrFail($id);
        $nameMap = $this->sampleProductNamesByServiceId(collect([$service]));

        return ApiResponse::success('Service retrieved successfully.', $this->serviceToArray($service, [
            'products_count' => $service->products_count ?? 0,
            'product_names' => $nameMap[$service->id] ?? [],
        ]));
    }

    /**
     * Get all services (no pagination). Same shape as list.
     */
    public function getCategories(Request $request)
    {
        $query = Service::listedInCatalog()->withCount('products');
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        $services = $query->orderBy('sort_order')->orderBy('name')->get();
        $nameMap = $this->sampleProductNamesByServiceId($services);

        $data = $services->map(fn (Service $s) => $this->serviceToArray($s, [
            'products_count' => $s->products_count ?? 0,
            'product_names' => $nameMap[$s->id] ?? [],
        ]))->values()->all();

        return $this->listCacheResponse(ApiResponse::success('Services retrieved successfully.', $data));
    }

    /**
     * Get one service by id with full products list (products linked to this service via pivot).
     */
    public function getByCategory(Request $request, $id)
    {
        $forList = strtolower((string) $request->query('view', '')) !== 'detail';

        $service = Service::with(['products' => function ($q) {
            $q->visibleInClientShop()->with('primaryImage')->orderBy('name');
        }])->findOrFail($id);

        $products = $service->products
            ->map(fn (Product $product) => $this->productToApiData($product, $forList))
            ->values()
            ->all();

        $serviceData = $this->serviceToArray($service, [
            'products_count' => $service->products->count(),
            'products' => $products,
        ]);

        return $this->listCacheResponse(ApiResponse::success('Service retrieved successfully.', $serviceData));
    }
}
