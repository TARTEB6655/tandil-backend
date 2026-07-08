<?php

namespace App\Http\Controllers;

use App\Services\Vendor\VendorAnalyticsShareService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SharedVendorAnalyticsController extends Controller
{
    public function __construct(
        private readonly VendorAnalyticsShareService $shares
    ) {}

    /**
     * Public browser view — opens PDF inline (no login).
     */
    public function show(string $token): BinaryFileResponse
    {
        $share = $this->shares->findActiveShare($token);
        if ($share === null) {
            abort(404, 'This analytics link has expired or is invalid.');
        }

        $filename = 'vendor_analytics_'.$share->period.'.pdf';

        return response()->file(
            Storage::disk('public')->path($share->file_path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }

    /**
     * PDF file download.
     */
    public function download(string $token): BinaryFileResponse
    {
        $share = $this->shares->findActiveShare($token);
        if ($share === null) {
            abort(404, 'This analytics link has expired or is invalid.');
        }

        $filename = 'vendor_analytics_'.$share->period.'_'.now()->format('Y-m-d').'.pdf';

        return response()->download(
            Storage::disk('public')->path($share->file_path),
            $filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
