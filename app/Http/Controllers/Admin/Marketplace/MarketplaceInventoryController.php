<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use Illuminate\Http\Request;

class MarketplaceInventoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');

        $query = VendorInventory::with(['vendorProduct.product', 'vendorProduct.vendor.profile']);

        if ($filter === 'low') {
            $query->whereColumn('quantity', '<=', 'low_stock_threshold')->where('quantity', '>', 0);
        } elseif ($filter === 'out') {
            $query->where('quantity', '<=', 0);
        } elseif ($filter === 'inconsistent') {
            $ids = VendorProduct::with(['product', 'inventory'])
                ->get()
                ->filter(fn ($vp) => $vp->inventory && $vp->product && (int) $vp->product->stock !== (int) $vp->inventory->quantity)
                ->pluck('inventory.id');
            $query->whereIn('id', $ids->isEmpty() ? [-1] : $ids);
        }

        $items = $query->orderBy('quantity')->paginate(25)->withQueryString();

        $stats = [
            'low_stock' => VendorInventory::whereColumn('quantity', '<=', 'low_stock_threshold')->where('quantity', '>', 0)->count(),
            'out_of_stock' => VendorInventory::where('quantity', '<=', 0)->count(),
            'total_tracked' => VendorInventory::count(),
        ];

        return view('admin.marketplace.inventory.index', compact('items', 'stats', 'filter'));
    }
}
