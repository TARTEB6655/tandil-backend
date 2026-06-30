<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $services = Service::with('category')
            ->where('vendor_id', $vendor->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('vendor.services.index', compact('services', 'vendor'));
    }

    public function create(Request $request): View
    {
        $vendor = $this->vendor($request);
        $categories = Category::forVendorCatalog($vendor->id)->ordered()->get(['id', 'name']);

        return view('vendor.services.create', compact('categories'));
    }

    public function store(Request $request): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['category_id'])) {
            $allowed = Category::forVendorCatalog($vendor->id)->where('id', $validated['category_id'])->exists();
            if (! $allowed) {
                return back()->withErrors(['category_id' => 'Invalid category for your store.'])->withInput();
            }
        }

        $imagePath = $request->hasFile('image') ? $request->file('image')->store('services', 'public') : null;

        Service::create([
            'vendor_id' => $vendor->id,
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug(Str::slug($validated['name'])),
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'image' => $imagePath,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => 0,
        ]);

        return redirect()->route('vendor.services.index')->with('success', 'Service created.');
    }

    public function edit(Request $request, int $service): View|RedirectResponse
    {
        $vendor = $this->vendor($request);
        $model = Service::where('vendor_id', $vendor->id)->where('id', $service)->first();
        if ($model === null) {
            return redirect()->route('vendor.services.index')->with('error', 'Service not found.');
        }

        $categories = Category::forVendorCatalog($vendor->id)->ordered()->get(['id', 'name']);

        return view('vendor.services.edit', ['service' => $model, 'categories' => $categories]);
    }

    public function update(Request $request, int $service): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $model = Service::where('vendor_id', $vendor->id)->where('id', $service)->firstOrFail();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        if (! empty($validated['category_id'])) {
            $allowed = Category::forVendorCatalog($vendor->id)->where('id', $validated['category_id'])->exists();
            if (! $allowed) {
                return back()->withErrors(['category_id' => 'Invalid category for your store.'])->withInput();
            }
        }

        $updates = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image')) {
            if ($model->image) {
                Storage::disk('public')->delete($model->image);
            }
            $updates['image'] = $request->file('image')->store('services', 'public');
        }

        $model->update($updates);

        return redirect()->route('vendor.services.index')->with('success', 'Service updated.');
    }

    public function destroy(Request $request, int $service): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $model = Service::where('vendor_id', $vendor->id)->where('id', $service)->firstOrFail();
        if ($model->image) {
            Storage::disk('public')->delete($model->image);
        }
        $model->delete();

        return redirect()->route('vendor.services.index')->with('success', 'Service deleted.');
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor') ?? $request->user()->vendor;
    }

    private function uniqueSlug(string $slug): string
    {
        $original = $slug;
        $counter = 1;
        while (Service::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
