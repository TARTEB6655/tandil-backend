<?php

namespace App\Http\Controllers\Admin\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\VendorProduct;
use App\Services\Vendor\AdminVendorProductService;
use App\Services\Vendor\VendorProductService;
use Illuminate\Http\Request;

class MarketplaceProductController extends Controller
{
    public function __construct(
        private readonly AdminVendorProductService $adminProducts,
        private readonly VendorProductService $vendorProducts
    ) {
        $this->middleware('role:admin');
    }

    public function index(Request $request)
    {
        $products = VendorProduct::with(['product.category', 'vendor.profile', 'inventory', 'currentPrice'])
            ->when($request->filled('approval_status'), fn ($q) => $q->where('approval_status', $request->query('approval_status')))
            ->when($request->filled('vendor_id'), fn ($q) => $q->where('vendor_id', $request->query('vendor_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = $request->query('search');
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$s}%"));
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketplace.products.index', compact('products'));
    }

    public function show(VendorProduct $vendorProduct)
    {
        $vendorProduct->load(['product.category', 'vendor.profile', 'inventory', 'currentPrice', 'prices', 'approvedByUser']);

        return view('admin.marketplace.products.show', compact('vendorProduct'));
    }

    public function edit(VendorProduct $vendorProduct)
    {
        $vendorProduct->load(['product', 'vendor.profile']);
        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('admin.marketplace.products.edit', compact('vendorProduct', 'categories'));
    }

    public function update(Request $request, VendorProduct $vendorProduct)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,draft,archived',
            'image' => 'nullable|image|max:5120',
        ]);

        $this->vendorProducts->update($vendorProduct, $data, $request->file('image'), true, $request->user()->id);

        return redirect()->route('admin.marketplace.products.show', $vendorProduct)->with('success', 'Product updated.');
    }

    public function approve(Request $request, VendorProduct $vendorProduct)
    {
        $this->adminProducts->approve($vendorProduct, $request->user(), $request->input('notes'));

        return back()->with('success', 'Product approved.');
    }

    public function reject(Request $request, VendorProduct $vendorProduct)
    {
        $request->validate(['reason' => 'required|string|max:1000']);
        $this->adminProducts->reject($vendorProduct, $request->user(), $request->input('reason'));

        return back()->with('success', 'Product rejected.');
    }

    public function destroy(VendorProduct $vendorProduct)
    {
        $this->adminProducts->removeListing($vendorProduct);

        return redirect()->route('admin.marketplace.products.index')->with('success', 'Listing removed.');
    }
}
