<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorDashboardController extends Controller
{
    public function __construct(
        private readonly VendorDashboardService $dashboard
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');

        return ApiResponse::success('Dashboard statistics.', $this->dashboard->stats($vendor));
    }
}
