<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorAnalyticsShareService;
use App\Services\Vendor\VendorPerformanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class VendorPerformanceAnalyticsController extends Controller
{
    public function __construct(
        private readonly VendorPerformanceAnalyticsService $analytics,
        private readonly VendorAnalyticsShareService $shares
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $period = $this->analytics->normalizePeriod((string) $request->query('period', 'month'));

        return ApiResponse::success('Performance analytics retrieved.', [
            'analytics' => $this->analytics->build($vendor, $period),
        ]);
    }

    public function export(Request $request): Response
    {
        $vendor = $request->attributes->get('vendor');
        $period = $this->analytics->normalizePeriod((string) $request->query('period', 'month'));
        $filename = $this->analytics->exportFilename($period);

        return response($this->analytics->buildPdfBinary($vendor, $period), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function share(Request $request): JsonResponse
    {
        $vendor = $request->attributes->get('vendor');
        $period = $this->analytics->normalizePeriod((string) $request->query('period', 'month'));
        $share = $this->shares->createShare($vendor, $period);

        return ApiResponse::success('Analytics share link created.', [
            'share' => $share,
        ]);
    }
}
