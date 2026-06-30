<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Vendor;
use App\Services\ImageCompressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $categories = Category::query()
            ->where('vendor_id', $vendor->id)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.categories.index', compact('categories', 'vendor'));
    }

    public function create(): View
    {
        return view('vendor.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|boolean',
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $slug = $this->uniqueSlug(Str::slug($validated['name']));
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('categories', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($imagePath);
        }

        Category::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['name'],
            'slug' => $slug,
            'description' => $validated['description'] ?? null,
            'image' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => Category::nextSortOrder(),
            'shipping_cost' => $validated['shipping_cost'] ?? 0,
            'tax_percentage' => $validated['tax_percentage'] ?? 0,
        ]);

        return redirect()->route('vendor.categories.index')->with('success', 'Category created.');
    }

    public function edit(Request $request, int $category): View|RedirectResponse
    {
        $vendor = $this->vendor($request);
        $model = Category::where('vendor_id', $vendor->id)->where('id', $category)->first();
        if ($model === null) {
            return redirect()->route('vendor.categories.index')->with('error', 'Category not found.');
        }

        return view('vendor.categories.edit', ['category' => $model]);
    }

    public function update(Request $request, int $category): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $model = Category::where('vendor_id', $vendor->id)->where('id', $category)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_active' => 'nullable|boolean',
            'shipping_cost' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $updates = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'shipping_cost' => $validated['shipping_cost'] ?? $model->shipping_cost,
            'tax_percentage' => $validated['tax_percentage'] ?? $model->tax_percentage,
        ];

        if ($request->hasFile('image')) {
            $updates['image'] = $request->file('image')->store('categories', 'public');
            ImageCompressionService::compressIfNeededFromPublicPath($updates['image']);
        }

        $model->update($updates);

        return redirect()->route('vendor.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Request $request, int $category): RedirectResponse
    {
        $vendor = $this->vendor($request);
        Category::where('vendor_id', $vendor->id)->where('id', $category)->firstOrFail()->delete();

        return redirect()->route('vendor.categories.index')->with('success', 'Category deleted.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor') ?? $request->user()->vendor;
    }

    private function uniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;
        while (Category::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
