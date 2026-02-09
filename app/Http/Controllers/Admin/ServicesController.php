<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Admin "Services" (Place Service Orders): same flow as the app — categories as filters, products below.
 */
class ServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Services page: category filter chips (All + each category), products list below.
     */
    public function index(Request $request)
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        $categoryId = $request->query('category');
        $products = Product::with('category')
            ->when($categoryId, function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.services.index', [
            'categories' => $categories,
            'products' => $products,
            'selectedCategoryId' => $categoryId ? (int) $categoryId : null,
        ]);
    }
}
