<?php

namespace App\Services\Vendor;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVendorProductListService
{
    /**
     * @return array<string, int>
     */
    public function stats(Vendor $vendor): array
    {
        $base = VendorProduct::query()->where('vendor_id', $vendor->id);

        $outOfStock = (clone $base)
            ->marketplaceLive()
            ->whereHas('inventory', fn ($q) => $q->where('quantity', '<=', 0))
            ->count();

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->marketplaceLive()->count(),
            'disabled' => (clone $base)->where('disabled_by_admin', true)->count(),
            'draft' => (clone $base)->whereHas('product', fn ($q) => $q->where('status', 'draft'))->count(),
            'out_of_stock' => $outOfStock,
        ];
    }

    public function paginate(Vendor $vendor, Request $request): LengthAwarePaginator
    {
        $query = VendorProduct::query()
            ->with(['product.category', 'product.primaryImage', 'product.images', 'inventory', 'currentPrice', 'disabledByAdminUser'])
            ->where('vendor_id', $vendor->id);

        if ($request->filled('status')) {
            $status = $request->query('status');
            if ($status === 'disabled_by_admin') {
                $query->where('disabled_by_admin', true);
            } elseif ($status === 'out_of_stock') {
                $query->where('status', 'active')
                    ->whereHas('inventory', fn ($q) => $q->where('quantity', '<=', 0));
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('category_id')) {
            $query->whereHas('product', fn ($q) => $q->where('category_id', $request->integer('category_id')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $sort = $request->query('sort', 'newest');
        match ($sort) {
            'oldest' => $query->oldest('vendor_products.created_at'),
            'name' => $query->orderBy(
                Product::select('name')->whereColumn('products.id', 'vendor_products.product_id')
            ),
            'price_high' => $query->orderByDesc(
                Product::select('price')->whereColumn('products.id', 'vendor_products.product_id')
            ),
            'price_low' => $query->orderBy(
                Product::select('price')->whereColumn('products.id', 'vendor_products.product_id')
            ),
            'stock_low' => $query->orderBy(
                VendorInventory::select('quantity')->whereColumn('vendor_inventories.vendor_product_id', 'vendor_products.id')
            ),
            default => $query->latest('vendor_products.created_at'),
        };

        return $query->paginate($request->integer('per_page', 20))->withQueryString();
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, int>
     */
    public function salesCounts(int $vendorId, array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return OrderItem::query()
            ->select('product_id', DB::raw('SUM(quantity) as total_sales'))
            ->whereIn('product_id', $productIds)
            ->whereHas('order.vendorMappings', fn ($q) => $q->where('vendor_id', $vendorId))
            ->groupBy('product_id')
            ->pluck('total_sales', 'product_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    public function categoriesForVendor(Vendor $vendor)
    {
        $categoryIds = VendorProduct::query()
            ->where('vendor_products.vendor_id', $vendor->id)
            ->join('products', 'products.id', '=', 'vendor_products.product_id')
            ->whereNotNull('products.category_id')
            ->distinct()
            ->pluck('products.category_id');

        return Category::query()->whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name']);
    }
}
