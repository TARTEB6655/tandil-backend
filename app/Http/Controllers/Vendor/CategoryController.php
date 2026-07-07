<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $categories = Category::query()
            ->platformCatalog()
            ->where('is_active', true)
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('vendor.categories.index', compact('categories', 'vendor'));
    }

    public function create(): never
    {
        $this->denyVendorCategoryManagement();
    }

    public function store(Request $request): never
    {
        $this->denyVendorCategoryManagement();
    }

    public function edit(Request $request, int $category): never
    {
        $this->denyVendorCategoryManagement();
    }

    public function update(Request $request, int $category): never
    {
        $this->denyVendorCategoryManagement();
    }

    public function destroy(Request $request, int $category): never
    {
        $this->denyVendorCategoryManagement();
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor') ?? $request->user()->vendor;
    }

    private function denyVendorCategoryManagement(): never
    {
        throw new HttpException(403, 'Vendors cannot manage categories. Use platform categories when adding products.');
    }
}
