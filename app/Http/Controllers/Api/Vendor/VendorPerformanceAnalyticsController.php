<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorPerformanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorPerformanceAnalyticsController extends Controller
{
    public function __construct(
        private readonly VendorPerformanceAnalyticsService $analytics
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $period = $this->analytics->normalizePeriod((string) $request->query('period', 'month'));

        return ApiResponse::success('Performance analytics retrieved.', [
            'analytics' => $this->analytics->build($vendor, $period),
        ]);
    }
}
