<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Admin "Services" (Place Service Orders): service cards → click card → category + products.
 */
class ServicesController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    /**
     * Services page: show service cards (one per category). Click a card to see its category and products.
     */
    public function index()
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        return view('admin.services.index', ['categories' => $categories]);
    }

    /**
     * Show one service (category) with its products. Reached when clicking a service card.
     */
    public function showCategory($id)
    {
        $category = Category::withCount('products')
            ->with(['products' => fn ($q) => $q->orderBy('name')])
            ->findOrFail($id);

        return view('admin.services.show', ['category' => $category]);
    }
}
