<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorComparisonService;
use Illuminate\Http\Request;

class VendorComparisonController extends Controller
{
    public function __construct(
        private readonly VendorComparisonService $comparison
    ) {}

    public function show(Request $request, int $productId)
    {
        $comparison = $this->comparison->compareByProduct($productId);

        return view('client.shop.vendor-compare', compact('comparison', 'productId'));
    }
}
