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

    public function index()
    {
        $products = Product::with('category')
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $categories = Category::withCount('products')->get();

        return view('client.shop.index', compact('products', 'categories'));
    }
}

