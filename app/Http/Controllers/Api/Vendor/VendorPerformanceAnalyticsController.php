<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Vendor\VendorPerformanceAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Request $request): StreamedResponse
    {
        $vendor = $request->attributes->get('vendor');
        $period = $this->analytics->normalizePeriod((string) $request->query('period', 'month'));
        $filename = $this->analytics->exportFilename($period);

        return response()->streamDownload(function () use ($vendor, $period) {
            $handle = fopen('php://output', 'w');
            foreach ($this->analytics->buildCsvRows($vendor, $period) as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
