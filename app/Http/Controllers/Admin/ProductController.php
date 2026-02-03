<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * List products (with search, category filter, pagination).
     */
    public function index(Request $request)
    {
        $search     = $request->query('search');
        $categoryId = $request->query('category_id');

        $query = Product::with(['category', 'images', 'primaryImage']);

        if ($search) {
            $query->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
        }

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        // Apply filter tabs
        $filter = $request->get('filter', 'all');
        if ($filter === 'active') {
            $query->where('stock', '>', 0);
        } elseif ($filter === 'draft') {
            // You can add a status field to products table later
            $query->where('stock', '<=', 0);
        } elseif ($filter === 'archived') {
            // You can add an archived field to products table later
            $query->where('stock', '<=', 0);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(15);
        $categories = \App\Models\Category::all();

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        }

        return view('admin.products.index', compact('products', 'categories'));
    }

    /**
     * Create a product.
     * Same endpoint for (1) JSON body: product fields + optional image_urls (array of URLs).
     * (2) Multipart/form-data: product fields as form fields + image files in images[] (or image for single)
     *     + optional image_urls (JSON string) or image_url[] (repeated) to merge with file uploads.
     * Auth: Authorization: Bearer {{admin_token}}.
     */
    public function store(Request $request)
    {
        // Normalize empty category_id so validation accepts it (optional category)
        if ($request->has('category_id') && $request->category_id === '') {
            $request->merge(['category_id' => null]);
        }

        // Normalize image_urls for multipart: Option 1 = JSON string; Option 2 = repeated image_url[]
        $imageUrls = null;
        if ($request->has('image_urls')) {
            $v = $request->input('image_urls');
            if (is_string($v)) {
                $decoded = json_decode($v, true);
                $imageUrls = is_array($decoded) ? array_values($decoded) : null;
            } elseif (is_array($v)) {
                $imageUrls = array_values($v);
            }
        }
        if ($imageUrls === null && $request->has('image_url')) {
            $imageUrls = array_values((array) $request->input('image_url'));
        }
        if ($imageUrls !== null) {
            $request->merge(['image_urls' => $imageUrls]);
        }

        $validated = $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string',
            'vendor'              => 'nullable|string|max:255',
            'type'                => 'nullable|string|max:255',
            'sku'                 => 'nullable|string|max:255|unique:products,sku',
            'barcode'             => 'nullable|string|max:255',
            'price'               => 'required|numeric|min:0',
            'compare_at_price'    => 'nullable|numeric|min:0',
            'cost_per_item'       => 'nullable|numeric|min:0',
            'stock'               => 'nullable|integer|min:0',
            'status'              => 'nullable|in:draft,active,archived',
            'track_quantity'      => 'nullable|boolean',
            'allow_backorder'     => 'nullable|boolean',
            'weight'              => 'nullable|string|max:50',
            'weight_unit'         => 'nullable|in:kg,g,lb,oz',
            'tags'                => 'nullable|string',
            'meta_title'          => 'nullable|string|max:255',
            'meta_description'    => 'nullable|string|max:500',
            'handle'              => 'nullable|string|max:255|unique:products,handle',
            'requires_shipping'   => 'nullable|boolean',
            'taxable'             => 'nullable|boolean',
            'category_id'         => 'nullable|integer',
            // File upload (primary): use multipart/form-data with "image" (single) or "images[]" (multiple)
            'images'              => 'nullable|array',
            'images.*'            => 'image|mimes:jpg,jpeg,png,webp|max:5120',
            'image'               => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            // Optional: pass URLs instead (e.g. JSON body)
            'image_urls'          => 'nullable|array',
            'image_urls.*'        => 'nullable|string|url',
        ], [
            'handle.unique' => 'The handle has already been taken. Please use a different handle or leave it blank to auto-generate.',
            'sku.unique'    => 'The SKU has already been taken. Please use a unique SKU.',
        ]);

        // Generate handle from name if not provided
        if (empty($validated['handle']) && ! empty($validated['name'])) {
            $validated['handle'] = Str::slug($validated['name']);
            // Ensure uniqueness
            $counter = 1;
            $originalHandle = $validated['handle'];
            while (Product::where('handle', $validated['handle'])->exists()) {
                $validated['handle'] = $originalHandle . '-' . $counter;
                $counter++;
            }
        }

        // Set defaults
        $validated['status'] = $validated['status'] ?? 'draft';
        $validated['track_quantity'] = $validated['track_quantity'] ?? true;
        $validated['allow_backorder'] = $validated['allow_backorder'] ?? false;
        $validated['requires_shipping'] = $validated['requires_shipping'] ?? true;
        $validated['taxable'] = $validated['taxable'] ?? true;
        $validated['weight_unit'] = $validated['weight_unit'] ?? 'kg';
        $validated['stock'] = $validated['stock'] ?? 0;

        // If category_id is set but category doesn't exist, leave product without category (assign later)
        if (isset($validated['category_id']) && $validated['category_id'] !== null) {
            if (! Category::find($validated['category_id'])) {
                $validated['category_id'] = null;
            }
        }

        // Only pass fillable attributes to create (exclude image_urls, images, etc.)
        $createData = array_intersect_key($validated, array_flip((new Product)->getFillable()));

        // Resolve category_id from request then validated (form-data sends string "2"; ensure it's used)
        $rawCategoryId = $request->input('category_id') ?? ($validated['category_id'] ?? null);
        if (is_array($rawCategoryId)) {
            $rawCategoryId = $rawCategoryId[0] ?? null;
        }
        if ($rawCategoryId !== null && $rawCategoryId !== '' && is_numeric($rawCategoryId)) {
            $cid = (int) $rawCategoryId;
            $createData['category_id'] = Category::find($cid) ? $cid : null;
        } else {
            $createData['category_id'] = null;
        }

        // SQLite keeps category_id NOT NULL; use first category (or create Uncategorized) when none selected
        if ($createData['category_id'] === null && \Illuminate\Support\Facades\Schema::getConnection()->getDriverName() === 'sqlite') {
            $firstCategory = Category::orderBy('id')->first();
            if ($firstCategory) {
                $createData['category_id'] = $firstCategory->id;
            } else {
                $uncategorized = Category::firstOrCreate(
                    ['slug' => 'uncategorized'],
                    ['name' => 'Uncategorized', 'description' => null]
                );
                $createData['category_id'] = $uncategorized->id;
            }
        }

        try {
            $product = Product::create($createData);
        } catch (\Illuminate\Database\QueryException $e) {
            $msg = strtolower($e->getMessage());
            if (! str_contains($msg, 'unique')) {
                throw $e;
            }
            $errors = [];
            // Try to detect which column from exception message (e.g. "products_handle_unique" or "products.handle")
            if (str_contains($msg, 'handle')) {
                $errors['handle'] = ['The handle has already been taken. Please use a different handle or leave it blank to auto-generate.'];
            }
            if (str_contains($msg, 'sku')) {
                $errors['sku'] = ['The SKU has already been taken. Please use a unique SKU.'];
            }
            // Fallback: check DB for which one exists
            if ($errors === []) {
                if (! empty($createData['handle']) && Product::where('handle', $createData['handle'])->exists()) {
                    $errors['handle'] = ['The handle has already been taken. Please use a different handle or leave it blank to auto-generate.'];
                }
                if (! empty($createData['sku']) && Product::where('sku', $createData['sku'])->exists()) {
                    $errors['sku'] = ['The SKU has already been taken. Please use a unique SKU.'];
                }
            }
            if ($errors === []) {
                $errors['handle'] = ['A product with this handle or SKU already exists. Please use a unique handle and a unique SKU.'];
                $errors['sku'] = ['A product with this handle or SKU already exists. Please use a unique handle and a unique SKU.'];
            }
            return response()->json([
                'success' => false,
                'message' => 'Validation failed. Handle or SKU may already exist.',
                'errors'  => $errors,
            ], 422);
        }

        // Handle image file uploads: multipart uses "images[]" (multiple) or "image" (single)
        $sortOrder = 0;
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = $image->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'sort_order' => $sortOrder,
                    'is_primary' => $sortOrder === 0,
                ]);
                $sortOrder++;
            }
        } elseif ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'sort_order' => $sortOrder,
                'is_primary' => true,
            ]);
            $sortOrder++;
        }

        // Merge image URLs (from JSON body or multipart image_urls / image_url[] with file uploads
        if ($request->has('image_urls') && is_array($request->image_urls)) {
            foreach ($request->image_urls as $imageUrl) {
                if (is_string($imageUrl) && $imageUrl !== '') {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imageUrl,
                        'sort_order' => $sortOrder,
                        'is_primary' => $sortOrder === 0,
                    ]);
                    $sortOrder++;
                }
            }
        }

        // Set product main image from first image (file or URL)
        $firstImage = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->first();
        if ($firstImage) {
            $product->update(['image' => $firstImage->image_path]);
        }

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product created successfully.',
                'data' => $product->load(['category', 'images', 'primaryImage'])
            ], 201);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product created successfully.');
    }

    /**
     * Show form for editing product.
     */
    public function edit($id)
    {
        $product = Product::with(['category', 'images'])->findOrFail($id);
        $categories = \App\Models\Category::all();
        return view('admin.products.edit', compact('product', 'categories'));
    }

    /**
     * Show a single product.
     */
    public function show(Request $request, $id)
    {
        $product = Product::with(['category', 'images', 'primaryImage'])->findOrFail($id);
        
        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product retrieved successfully.',
                'data' => $product
            ]);
        }
        
        return view('admin.products.show', compact('product'));
    }

    /**
     * Show form for creating a product.
     */
    public function create()
    {
        $categories = \App\Models\Category::all();
        return view('admin.products.create', compact('categories'));
    }

    /**
     * Update product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Normalize empty category_id so it can be cleared or set later
        if ($request->has('category_id') && $request->category_id === '') {
            $request->merge(['category_id' => null]);
        }

        $validated = $request->validate([
            'name'        => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'category_id' => 'nullable|integer',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'images'      => 'nullable|array',
            'images.*'    => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        // New image uploads: single "image" or multiple "images[]"
        if ($request->hasFile('images')) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
            $maxOrder = (int) ProductImage::where('product_id', $product->id)->max('sort_order');
            foreach ($request->file('images') as $index => $file) {
                $imagePath = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                    'sort_order' => $maxOrder + 1 + $index,
                    'is_primary' => $index === 0,
                ]);
            }
            $primary = ProductImage::where('product_id', $product->id)->where('is_primary', true)->first();
            if ($primary) {
                $validated['image'] = $primary->image_path;
            }
        } elseif ($request->hasFile('image')) {
            ProductImage::where('product_id', $product->id)->update(['is_primary' => false]);
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $validated['image'],
                'sort_order' => (int) ProductImage::where('product_id', $product->id)->max('sort_order') + 1,
                'is_primary' => true,
            ]);
        }

        // If category_id is set but category doesn't exist, leave product without category (assign later)
        if (array_key_exists('category_id', $validated) && $validated['category_id'] !== null) {
            if (! Category::find($validated['category_id'])) {
                $validated['category_id'] = null;
            }
        }

        // Build update payload: only fillable keys; ensure category_id from request (form/API can send string)
        $updateData = array_intersect_key($validated, array_flip((new Product)->getFillable()));
        if ($request->has('category_id')) {
            if ($request->filled('category_id') && is_numeric($request->category_id)) {
                $cid = (int) $request->category_id;
                $updateData['category_id'] = Category::find($cid) ? $cid : null;
            } else {
                $updateData['category_id'] = null;
            }
        }

        $product->update($updateData);

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product updated successfully.',
                'data' => $product->load(['category', 'images', 'primaryImage'])
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product updated successfully.');
    }

    /**
     * Delete a product
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        // Delete image
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        // Check if this is an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'message' => 'Product deleted successfully.'
            ]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        $query = Product::with('category');

        // Apply filters if any
        if ($request->has('search') && $request->search) {
            $query->where('name', 'LIKE', "%{$request->search}%")
                  ->orWhere('description', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('created_at', 'desc')->get();

        $filename = 'products_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($products) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers (matching import format)
            fputcsv($file, [
                'Name',
                'Description',
                'Category',
                'Price',
                'Stock',
                'Image Path'
            ]);

            // Add product data (category may be null when product has no category)
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->name,
                    $product->description ?? '',
                    $product->category?->name ?? '',
                    $product->price,
                    $product->stock ?? 0,
                    $product->image ?? '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show import form
     */
    public function showImport()
    {
        return view('admin.products.import');
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        $data = array_map('str_getcsv', file($path));
        
        // Remove header row
        $header = array_shift($data);
        
        $errors = [];
        $successCount = 0;
        $skipCount = 0;

        foreach ($data as $index => $row) {
            $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed

            // Skip empty rows
            if (empty(array_filter($row))) {
                $skipCount++;
                continue;
            }

            // Map CSV columns (Name, Description, Category, Price, Stock, Image Path)
            $name = isset($row[0]) && !empty(trim($row[0])) ? trim($row[0]) : null;
            $description = isset($row[1]) ? trim($row[1]) : '';
            $categoryName = isset($row[2]) && !empty(trim($row[2])) ? trim($row[2]) : null;
            $price = isset($row[3]) && !empty(trim($row[3])) ? trim($row[3]) : null;
            $stock = isset($row[4]) && !empty(trim($row[4])) ? intval(trim($row[4])) : 0;
            $imagePath = isset($row[5]) && !empty(trim($row[5])) ? trim($row[5]) : null;

            // Validate required fields
            if (empty($name)) {
                $errors[] = "Row {$rowNumber}: Name is required";
                continue;
            }

            if (empty($price) || !is_numeric($price)) {
                $errors[] = "Row {$rowNumber}: Valid price is required";
                continue;
            }

            // Find or create category
            $categoryId = null;
            if ($categoryName) {
                $category = Category::firstOrCreate(['name' => trim($categoryName)]);
                $categoryId = $category->id;
            }

            try {
                // Check if product already exists (by name)
                $existingProduct = Product::where('name', trim($name))->first();

                if ($existingProduct && !$request->has('update_existing')) {
                    $skipCount++;
                    continue;
                }

                $productData = [
                    'name' => trim($name),
                    'description' => trim($description),
                    'category_id' => $categoryId,
                    'price' => floatval($price),
                    'stock' => intval($stock),
                ];

                if ($existingProduct && $request->has('update_existing')) {
                    $existingProduct->update($productData);
                    $successCount++;
                } else {
                    Product::create($productData);
                    $successCount++;
                }
            } catch (\Exception $e) {
                $errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
        }

        $message = "Import completed. Success: {$successCount}, Skipped: {$skipCount}";
        if (!empty($errors)) {
            $message .= ", Errors: " . count($errors);
        }

        return redirect()->route('admin.products.index')
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        $count = Product::whereIn('id', $request->product_ids)->delete();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "{$count} product(s) deleted successfully.",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "{$count} product(s) deleted successfully.");
    }

    /**
     * Bulk update status
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'status' => 'required|in:active,draft,archived',
        ]);

        $count = Product::whereIn('id', $request->product_ids)
            ->update(['status' => $request->status]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "Status updated for {$count} product(s).",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "Status updated for {$count} product(s).");
    }

    /**
     * Bulk update stock
     */
    public function bulkUpdateStock(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'stock' => 'required|integer|min:0',
        ]);

        $count = Product::whereIn('id', $request->product_ids)
            ->update(['stock' => $request->stock]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => "Stock updated for {$count} product(s).",
                'count'   => $count,
            ]);
        }
        return redirect()->route('admin.products.index')
            ->with('success', "Stock updated for {$count} product(s).");
    }

    /**
     * Toggle product publish status.
     */
    public function toggleStatus(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $newStatus = $product->status === 'active' ? 'draft' : 'active';
        $product->update(['status' => $newStatus]);

        $message = $newStatus === 'active' ? 'Product published successfully.' : 'Product unpublished successfully.';

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'  => true,
                'message' => $message,
                'data'    => $product->fresh()->load(['category', 'images', 'primaryImage']),
            ]);
        }
        return redirect()->back()->with('success', $message);
    }
}
