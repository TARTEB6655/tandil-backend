<?php

namespace App\Services\Vendor;

use App\Models\Vendor;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminVendorExportService
{
    public function __construct(
        private readonly AdminVendorListService $list,
        private readonly AdminVendorMetricsService $metrics
    ) {}

    /**
     * @param  list<Vendor>  $vendors
     */
    public function streamCsv(array $vendors): StreamedResponse
    {
        $ids = collect($vendors)->pluck('id')->all();
        $metricsMap = $this->metrics->mapForVendorIds($ids);

        $filename = 'vendors-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($vendors, $metricsMap) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'ID',
                'Business Name',
                'Owner Name',
                'Email',
                'Phone',
                'Store Name',
                'Status',
                'Verified',
                'Total Products',
                'Active Products',
                'Total Orders',
                'Revenue (AED)',
                'Commission (AED)',
                'Registration Date',
            ]);

            foreach ($vendors as $vendor) {
                $profile = $vendor->profile;
                $m = $metricsMap[$vendor->id] ?? $this->metrics->emptyMetricsPublic();
                $verified = $this->list->isVerified($vendor) ? 'Yes' : 'No';

                fputcsv($handle, [
                    $vendor->id,
                    $profile?->business_name,
                    $profile?->owner_name,
                    $profile?->email ?? $vendor->user?->email,
                    $profile?->phone ?? $vendor->user?->phone,
                    $profile?->business_name,
                    $vendor->statusEnum()->label(),
                    $verified,
                    $m['total_products'],
                    $m['active_products'],
                    $m['total_orders'],
                    $m['revenue'],
                    $m['commission_earned'],
                    $vendor->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
