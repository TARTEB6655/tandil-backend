<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:client']);
    }

    /**
     * List service categories (Watering, Planting, Cleaning, Full Care, etc.) for "Place Service Orders".
     */
    public function index()
    {
        $categories = Category::withCount(['products' => function ($q) {
            $q->where('status', 'active');
        }])
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->orderBy('name')
            ->get();

        return view('client.services.index', compact('categories'));
    }

    /**
     * Show one service category with its products (services).
     */
    public function showCategory($id)
    {
        $category = Category::where(function ($q) {
            $q->where('is_active', true)->orWhereNull('is_active');
        })
            ->with(['products' => function ($q) {
                $q->where('status', 'active')->orderBy('name');
            }])
            ->findOrFail($id);

        return view('client.services.category', compact('category'));
    }
}
