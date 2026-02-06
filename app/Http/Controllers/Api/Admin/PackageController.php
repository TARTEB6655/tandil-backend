<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PackageController extends Controller
{
    /**
     * List all packages with order count per package.
     */
    public function index(Request $request)
    {
        $packages = Package::orderBy('sort_order')->orderBy('id')
            ->withCount('orders')
            ->get()
            ->map(fn ($p) => $this->packageToArray($p));

        return ApiResponse::success('Packages retrieved successfully.', $packages);
    }

    /**
     * Create a package. Image optional.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:combined,fruit,vegetable',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $slug = Str::slug($request->input('name'));
        if (Package::where('slug', $slug)->exists()) {
            $slug = $slug . '-' . (Package::max('id') + 1);
        }

        $imagePath = null;
        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $imagePath = $request->file('image')->store('packages', 'public');
        }

        $package = Package::create([
            'name' => $request->input('name'),
            'slug' => $slug,
            'type' => $request->input('type'),
            'price' => $request->input('price'),
            'description' => $request->input('description'),
            'image' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) ($request->input('sort_order') ?? 0),
        ]);

        return ApiResponse::success('Package created successfully.', $this->packageToArray($package->loadCount('orders')), 201);
    }

    /**
     * Get one package with order count.
     */
    public function show($id)
    {
        $package = Package::withCount('orders')->findOrFail($id);
        return ApiResponse::success('Package retrieved successfully.', $this->packageToArray($package));
    }

    /**
     * Update package (price, image, etc.).
     */
    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:combined,fruit,vegetable',
            'price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string|max:2000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $data = array_filter([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
            'price' => $request->has('price') ? $request->input('price') : null,
            'description' => $request->input('description'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'sort_order' => $request->has('sort_order') ? (int) $request->input('sort_order') : null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            if ($package->image && Storage::disk('public')->exists($package->image)) {
                Storage::disk('public')->delete($package->image);
            }
            $data['image'] = $request->file('image')->store('packages', 'public');
        }

        $package->update($data);

        return ApiResponse::success('Package updated successfully.', $this->packageToArray($package->fresh()->loadCount('orders')));
    }

    /**
     * Delete a package.
     */
    public function destroy($id)
    {
        $package = Package::findOrFail($id);
        if ($package->image && Storage::disk('public')->exists($package->image)) {
            Storage::disk('public')->delete($package->image);
        }
        $package->delete();
        return ApiResponse::success('Package deleted successfully.');
    }

    private function packageToArray(Package $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'type' => $p->type,
            'price' => (float) $p->price,
            'image' => $p->image,
            'image_url' => $p->image_url,
            'description' => $p->description,
            'is_active' => $p->is_active,
            'sort_order' => $p->sort_order,
            'orders_count' => $p->orders_count ?? $p->orders()->count(),
            'created_at' => $p->created_at?->format('c'),
            'updated_at' => $p->updated_at?->format('c'),
        ];
    }
}
