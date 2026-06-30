<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\Vendor;
use App\Models\VendorProduct;
use App\Services\Vendor\VendorProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        private readonly VendorProductService $products
    ) {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $products = VendorProduct::with(['product.category', 'inventory', 'currentPrice'])
            ->where('vendor_id', $vendor->id)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->query('search');
                $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('vendor.products.index', compact('products', 'vendor'));
    }

    public function create(Request $request): View
    {
        $vendor = $this->vendor($request);
        $categories = Category::forVendorCatalog($vendor->id)->ordered()->get(['id', 'name']);
        $services = Service::forVendorCatalog($vendor->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('vendor.products.create', compact('categories', 'services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,draft,archived',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        try {
            $this->products->create($vendor, $data, $request->file('image'));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['category_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('vendor.products.index')->with('success', 'Product created successfully.');
    }

    public function edit(Request $request, int $product): View|RedirectResponse
    {
        $vendor = $this->vendor($request);
        $vendorProduct = $this->products->findForVendor($vendor, $product);
        if ($vendorProduct === null) {
            return redirect()->route('vendor.products.index')->with('error', 'Product not found.');
        }

        $categories = Category::forVendorCatalog($vendor->id)->ordered()->get(['id', 'name']);
        $services = Service::forVendorCatalog($vendor->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('vendor.products.edit', [
            'vendorProduct' => $vendorProduct->load(['product.category', 'product.services', 'inventory', 'currentPrice']),
            'categories' => $categories,
            'services' => $services,
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $vendorProduct = $this->products->findForVendor($vendor, $product);
        if ($vendorProduct === null) {
            return redirect()->route('vendor.products.index')->with('error', 'Product not found.');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'low_stock_threshold' => 'nullable|integer|min:0',
            'status' => 'nullable|in:active,draft,archived',
            'vendor_product_status' => 'nullable|in:active,inactive',
            'sku' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
        ]);

        try {
            $this->products->update($vendorProduct, $data, $request->file('image'), false, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['category_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('vendor.products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, int $product): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $vendorProduct = $this->products->findForVendor($vendor, $product);
        if ($vendorProduct === null) {
            return redirect()->route('vendor.products.index')->with('error', 'Product not found.');
        }

        $this->products->delete($vendorProduct);

        return redirect()->route('vendor.products.index')->with('success', 'Product deleted.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor') ?? $request->user()->vendor;
    }
}
