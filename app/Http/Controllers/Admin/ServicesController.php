<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

/**
 * Admin "Services" – services are a separate table; optional category when creating.
 */
class ServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Services page: list all services (from services table). Click card → service detail with products.
     */
    public function index()
    {
        $services = Service::with('category')->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.services.index', ['services' => $services]);
    }

    /**
     * Show the form for creating a new service. Optional category selection.
     */
    public function create()
    {
        $categories = Category::orderBy('name')->get(['id', 'name']);
        return view('admin.services.create', compact('categories'));
    }

    /**
     * Store a newly created service (optional category_id).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:services,name',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['category_id'] = $request->input('category_id') ? (int) $request->input('category_id') : null;

        $counter = 1;
        $originalSlug = $validated['slug'];
        while (Service::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('services', 'public');
            \App\Services\ImageCompressionService::compressIfNeededFromPublicPath($validated['image']);
        }

        Service::create($validated);

        return redirect()->route('admin.services.index')
            ->with('success', 'Service created successfully.');
    }

    /**
     * Show one service with its linked products (via product_service pivot).
     */
    public function showCategory($id)
    {
        $service = Service::with('category')->withCount('products')
            ->with(['products' => fn ($q) => $q->orderBy('name')])
            ->findOrFail($id);

        return view('admin.services.show', ['service' => $service]);
    }
}
