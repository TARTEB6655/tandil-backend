<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorInventory;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private readonly VendorInventoryService $inventory
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $items = VendorProduct::with(['product', 'inventory'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('filter'), function ($q) use ($request) {
                if ($request->query('filter') === 'low') {
                    $q->where(function ($query) {
                        $query->whereHas('inventory', fn ($iq) => $iq
                            ->whereColumn('quantity', '<=', 'low_stock_threshold')
                            ->where('quantity', '>', 0))
                            ->orWhere(function ($query) {
                                $query->whereDoesntHave('inventory')
                                    ->whereHas('product', fn ($pq) => $pq->where('stock', '>', 0)->where('stock', '<=', 5));
                            });
                    });
                }
                if ($request->query('filter') === 'out') {
                    $q->where(function ($query) {
                        $query->whereHas('inventory', fn ($iq) => $iq->where('quantity', '<=', 0))
                            ->orWhere(function ($query) {
                                $query->whereDoesntHave('inventory')
                                    ->whereHas('product', fn ($pq) => $pq->where('stock', '<=', 0));
                            });
                    });
                }
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.inventory.index', compact('items', 'vendor'));
    }

    public function update(Request $request, int $vendorProduct): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $vp = VendorProduct::where('vendor_id', $vendor->id)->where('id', $vendorProduct)->first();
        if ($vp === null) {
            return redirect()->route('vendor.inventory.index')->with('error', 'Product not found.');
        }

        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $this->inventory->adjust($vp, (int) $data['quantity'], $request->user(), 'manual', $data['notes'] ?? null);

        if (isset($data['low_stock_threshold']) && $vp->inventory) {
            $vp->inventory->update(['low_stock_threshold' => (int) $data['low_stock_threshold']]);
        }

        return redirect()->route('vendor.inventory.index')->with('success', 'Inventory updated.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor');
    }
}
