<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Emirate;
use App\Models\VendorType;

/**
 * Public options for the vendor registration wizard (active rows only).
 */
class VendorRegistrationOptionsController extends Controller
{
    public function __invoke()
    {
        return ApiResponse::success('Registration options retrieved successfully.', [
            'vendor_types' => VendorType::query()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (VendorType $row) => $row->toApiArray())
                ->values(),
            'emirates' => Emirate::query()
                ->active()
                ->orderBy('name')
                ->get()
                ->map(fn (Emirate $row) => $row->toApiArray())
                ->values(),
        ]);
    }
}
