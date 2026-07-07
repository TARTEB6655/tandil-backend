<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:vendor', 'vendor.approved']);
    }

    public function index(Request $request): View
    {
        $vendor = $this->vendor($request);
        $services = Service::with('category')
            ->platformCatalog()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('vendor.services.index', compact('services', 'vendor'));
    }

    public function create(Request $request): never
    {
        $this->denyVendorServiceManagement();
    }

    public function store(Request $request): never
    {
        $this->denyVendorServiceManagement();
    }

    public function edit(Request $request, int $service): never
    {
        $this->denyVendorServiceManagement();
    }

    public function update(Request $request, int $service): never
    {
        $this->denyVendorServiceManagement();
    }

    public function destroy(Request $request, int $service): never
    {
        $this->denyVendorServiceManagement();
    }

    private function vendor(Request $request): Vendor
    {
        return $request->attributes->get('vendor') ?? $request->user()->vendor;
    }

    private function denyVendorServiceManagement(): never
    {
        throw new HttpException(403, 'Vendors cannot manage services. Use platform services when adding products.');
    }
}
