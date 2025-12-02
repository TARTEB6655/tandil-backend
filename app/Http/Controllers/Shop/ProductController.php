<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * List products with pagination.
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 12);
        $perPage = $perPage > 0 ? $perPage : 12;  // Avoid negative or zero perPage values

        $products = Product::paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $products
        ], 200);
    }

    /**
     * Show single product by id.
     */
    public function show($id)
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $product
        ], 200);
    }
}
