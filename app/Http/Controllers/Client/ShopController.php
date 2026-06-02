<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;

class ShopController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    public function index(Request $request)
    {
        $categoryId = $request->query('category_id');

        $query = Product::with(['category', 'primaryImage', 'optionGroups.options'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $products = $query->paginate(12)->withQueryString();

        $categories = Category::withCount(['products' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        $selectedCategory = $categoryId ? Category::find($categoryId) : null;

        return view('client.shop.index', compact('products', 'categories', 'selectedCategory'));
    }

    public function show($id)
    {
        $product = Product::with(['category', 'images', 'primaryImage', 'optionGroups.options'])
            ->where('status', 'active')
            ->findOrFail($id);

        return view('client.shop.show', compact('product'));
    }
}

